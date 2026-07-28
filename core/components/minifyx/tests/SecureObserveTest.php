<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Application\BuildResult;
use MinifyX\Cache\AtomicFilesystemCache;
use MinifyX\Contract\ImageProcessorInterface;
use MinifyX\Diagnostics\HealthCheck;
use MinifyX\Diagnostics\HealthCheckCommand;
use MinifyX\Image\ImageController;
use MinifyX\Image\ImageRateLimiter;
use MinifyX\Image\PathGuard;
use MinifyX\Optimization\ExternalProcessRunner;
use MinifyX\Processor\ImageRewriter;

final class SecureObserveTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/minifyx-secure-observe-' . uniqid('', true) . '/';
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '*') ?: [] as $file) {
            if (is_dir($file)) {
                foreach (glob($file . '/*') ?: [] as $child) {
                    @unlink($child);
                }
                @rmdir($file);
            } else {
                @unlink($file);
            }
        }
        @rmdir($this->dir);
    }

    public function testBuildResultExposesStructuredSafeError(): void
    {
        $result = BuildResult::failure(
            'Asset compilation failed.',
            'compile_failed',
            'app.js',
            'compile'
        )->toArray();

        self::assertSame('Asset compilation failed.', $result['error']);
        self::assertSame('compile_failed', $result['errorCode']);
        self::assertSame('app.js', $result['sourceFile']);
        self::assertSame('compile', $result['phase']);
        self::assertStringNotContainsString('/', $result['sourceFile']);
    }

    public function testExternalRunnerRejectsOversizedInputBeforeProcessStart(): void
    {
        $runner = new ExternalProcessRunner(30, 5000, 1024);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('input exceeds');
        $runner->run(['/binary/that/must/not/run'], str_repeat('x', 1025));
    }

    public function testRateLimiterUsesInjectableClockAndFixedWindow(): void
    {
        $now = 100;
        $limiter = new ImageRateLimiter($this->dir . 'limits/', 2, 10, static function () use (&$now): int {
            return $now;
        });

        self::assertTrue($limiter->isAllowed('client'));
        self::assertTrue($limiter->isAllowed('client'));
        self::assertFalse($limiter->isAllowed('client'));
        $now = 110;
        self::assertTrue($limiter->isAllowed('client'));
    }

    public function testHealthCheckReportsRequiredAndOptionalChecks(): void
    {
        $runner = new class extends ExternalProcessRunner {
            public function isExecutableAvailable(string $binary): bool
            {
                return $binary === 'terser-test';
            }
        };
        $health = new HealthCheck($this->dir . 'cache/', 'terser-test', 'esbuild-test', ['current'], $runner);
        $result = $health->run();

        self::assertTrue($result['healthy']);
        self::assertTrue($result['checks']['cacheWritable']['available']);
        self::assertTrue($result['checks']['terser']['available']);
        self::assertFalse($result['checks']['esbuild']['available']);
        self::assertTrue($result['checks']['signingConfigured']['available']);
    }

    public function testHealthCommandBootstrapsModxFromBasePath(): void
    {
        file_put_contents(
            $this->dir . 'index.php',
            '<?php $modx = new class {'
            . ' public function getOption($key, $options = null, $default = null, $skipEmpty = false) {'
            . ' $values = ["minifyx_image_signing_keys" => "current",'
            . ' "minifyx_cacheFolder" => "/cache/"];'
            . ' return $values[$key] ?? $default;'
            . ' }};'
        );

        ob_start();
        $exitCode = (new HealthCheckCommand())->run(['base-path' => $this->dir]);
        $output = (string) ob_get_clean();
        $result = json_decode($output, true);

        self::assertSame(0, $exitCode);
        self::assertIsArray($result);
        self::assertTrue($result['checks']['signingConfigured']['available']);
    }

    public function testHealthCheckRejectsEmptyCachePath(): void
    {
        $result = (new HealthCheck(''))->run();

        self::assertFalse($result['healthy']);
        self::assertFalse($result['checks']['cacheWritable']['available']);
    }

    public function testSigningKeyRotationSignsWithFirstAndVerifiesAll(): void
    {
        file_put_contents($this->dir . 'image.bin', 'image');
        $processor = new class implements ImageProcessorInterface {
            public function transform(string $absolutePath, array $options = []): string
            {
                return (string) file_get_contents($absolutePath);
            }

            public function contentType(string $absolutePath): string
            {
                return 'application/octet-stream';
            }
        };
        $controller = new ImageController(
            new PathGuard($this->dir),
            $processor,
            new AtomicFilesystemCache($this->dir . 'cache/'),
            ['current', 'previous']
        );
        $oldSignature = hash_hmac('sha256', 'image.bin|', 'previous');
        $result = $controller->handle(['files' => 'image.bin', 'sig' => $oldSignature]);
        self::assertSame(200, $result['status']);

        $rewriter = new ImageRewriter('/image.php', 'https://example.test', '', '', ['current', 'previous']);
        $html = $rewriter->rewrite('<img src="/image.bin" width="10">');
        $currentSignature = hash_hmac('sha256', 'image.bin|w[10]', 'current');
        self::assertStringContainsString(rawurlencode($currentSignature), $html);
    }
}
