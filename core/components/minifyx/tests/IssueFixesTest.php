<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Html\AssetTag;
use MinifyX\Html\AssetTagRenderer;
use MinifyX\Html\BundlePlanner;
use MinifyX\Optimization\ExternalProcessRunner;
use MinifyX\Optimization\FallbackJsOptimizer;
use MinifyX\Optimization\MatthiasCssOptimizer;
use MinifyX\Optimization\TerserJsOptimizer;
use MinifyX\Processor\CssJsProcessor;

final class IssueFixesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/minifyx-issues-' . uniqid('', true) . '/';
        mkdir($this->dir, 0755, true);
        mkdir($this->dir . 'css', 0755, true);
        mkdir($this->dir . 'img', 0755, true);
        mkdir($this->dir . 'cache', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    public function testEmptyCssUrlsArePreservedAfterMinify(): void
    {
        $cssPath = $this->dir . 'css/empty.css';
        $cssContent = ".icon { background: url(''); }\n"
            . ".icon2 { background: url(\"\"); }\n"
            . ".icon3 { background: url(); }\n";
        file_put_contents($cssPath, $cssContent);

        $outputPath = $this->dir . 'cache/bundle.min.css';
        $processor = new CssJsProcessor();
        $result = $processor->process([$cssPath], [
            'minify' => true,
            'type' => 'css',
            'outputPath' => $outputPath,
        ]);

        self::assertStringContainsString("url('')", $result);
        self::assertStringContainsString('url("")', $result);
        self::assertStringContainsString('url()', $result);
        self::assertStringNotContainsString('MODX_BASE_PATH', $result);
        self::assertStringNotContainsString($this->dir, $result);
    }

    public function testRelativeCssUrlsAreRebasedForCacheOutput(): void
    {
        file_put_contents($this->dir . 'img/logo.png', str_repeat('x', 10000));
        $cssPath = $this->dir . 'css/site.css';
        file_put_contents($cssPath, ".logo { background: url('../img/logo.png'); }\n");

        $outputPath = $this->dir . 'cache/styles_abc123.min.css';
        $optimizer = new MatthiasCssOptimizer();
        $result = $optimizer->optimize([$cssPath], (string) file_get_contents($cssPath), $outputPath);

        self::assertStringContainsString('../img/logo.png', $result);
        self::assertStringNotContainsString('css/../img', $result);
    }

    public function testDataAndExternalUrlsAreUntouched(): void
    {
        $cssPath = $this->dir . 'css/external.css';
        $css = ".a{background:url(data:image/png;base64,abc)} "
            . ".b{background:url(https://cdn.example/app.css)} "
            . ".c{background:url(#hash)}\n";
        file_put_contents($cssPath, $css);

        $processor = new CssJsProcessor();
        $result = $processor->process([$cssPath], [
            'minify' => true,
            'type' => 'css',
            'outputPath' => $this->dir . 'cache/out.css',
        ]);

        self::assertStringContainsString('data:image/png;base64,abc', $result);
        self::assertStringContainsString('https://cdn.example/app.css', $result);
        self::assertStringContainsString('#hash', $result);
    }

    public function testJsManglerFallsBackWhenBinaryMissing(): void
    {
        $logged = false;
        $optimizer = new FallbackJsOptimizer(static function (string $message) use (&$logged): void {
            $logged = str_contains($message, 'falling back');
        });

        $source = "function helloWorld() { return 'hello world'; }\n";
        $result = $optimizer->optimize($source, true, true, 'terser', '/nonexistent/terser');

        self::assertTrue($logged);
        self::assertNotSame('', trim($result));
    }

    public function testJsManglerUsesFakeProcessRunner(): void
    {
        $runner = new class extends ExternalProcessRunner {
            public function isExecutableAvailable(string $binary): bool
            {
                return true;
            }

            /**
             * @param list<string> $command
             */
            public function run(array $command, string $input = ''): string
            {
                return 'function h(){return"hello world";}';
            }
        };

        $optimizer = new TerserJsOptimizer($runner);
        $result = $optimizer->optimize("function helloWorld(){return 'hello world';}", true, true, 'terser', '');

        self::assertSame('function h(){return"hello world";}', $result);
    }

    public function testJsManglerRuntimeErrorFailsBuild(): void
    {
        $runner = new class extends ExternalProcessRunner {
            public function isExecutableAvailable(string $binary): bool
            {
                return true;
            }

            /**
             * @param list<string> $command
             */
            public function run(array $command, string $input = ''): string
            {
                throw new \RuntimeException('syntax error');
            }
        };

        $optimizer = new TerserJsOptimizer($runner);
        $this->expectException(\RuntimeException::class);
        $optimizer->optimize('function broken(', true, true, 'terser', '');
    }

    public function testBuildSignatureIncludesManglerBackend(): void
    {
        $signatureA = new \MinifyX\Application\BuildSignature(['/tmp/a.js'], ['jsBackend' => 'terser']);
        $signatureB = new \MinifyX\Application\BuildSignature(['/tmp/a.js'], ['jsBackend' => 'php-minify']);

        self::assertNotSame($signatureA->hash(), $signatureB->hash());
    }

    public function testSemanticBundlePlannerGroupsByModuleAndDefer(): void
    {
        $planner = new BundlePlanner();
        $tags = [
            new AssetTag(AssetTag::KIND_SCRIPT, '/a.js', ['defer' => true]),
            new AssetTag(AssetTag::KIND_SCRIPT, '/b.js', ['defer' => true]),
            new AssetTag(AssetTag::KIND_SCRIPT, '/c.js', ['type' => 'module']),
        ];

        $groups = $planner->groupCompatible($tags, 'js');
        self::assertCount(2, $groups);
        self::assertCount(2, $groups[0]);
        self::assertCount(1, $groups[1]);
    }

    public function testPreloadHintsAreCompanionTags(): void
    {
        $renderer = new AssetTagRenderer();
        $tag = new AssetTag(AssetTag::KIND_SCRIPT, '/bundle.js', ['type' => 'module', 'crossorigin' => 'anonymous']);

        $bundle = $renderer->renderBundle('js', '/bundle.js', $tag, [
            'preloadJs' => true,
            'jsTpl' => '<script src="[[+file]]" type="module"></script>',
        ]);

        self::assertStringContainsString('rel="modulepreload"', (string) $bundle['preload']);
        self::assertStringContainsString('crossorigin="anonymous"', (string) $bundle['preload']);
        self::assertStringContainsString('<script src="/bundle.js"', $bundle['tag']);
        self::assertStringNotContainsString('modulepreload', $bundle['tag']);
    }

    public function testBundleIntegrityIsOptIn(): void
    {
        $renderer = new AssetTagRenderer();
        $tag = new AssetTag(
            AssetTag::KIND_SCRIPT,
            '/bundle.js',
            [
                'integrity' => 'sha384-test',
                'crossorigin' => 'anonymous',
                'defer' => true,
            ]
        );

        $without = $renderer->renderBundle('js', '/bundle.js', $tag, []);
        self::assertStringNotContainsString('integrity=', $without['tag']);

        $with = $renderer->renderBundle('js', '/bundle.js', $tag, ['bundleIntegrity' => true]);
        self::assertStringContainsString('integrity="sha384-test"', $with['tag']);
        self::assertStringContainsString('crossorigin="anonymous"', $with['tag']);
        self::assertStringContainsString('defer', $with['tag']);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '*') ?: [] as $file) {
            if (is_dir($file)) {
                $this->removeDir($file . '/');
            } else {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
}
