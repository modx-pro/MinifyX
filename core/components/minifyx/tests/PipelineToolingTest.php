<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Application\BuildSignature;
use MinifyX\Adapter\ArrayModxAdapter;
use MinifyX\Cache\AtomicFilesystemCache;
use MinifyX\Config;
use MinifyX\Contract\AssetProcessorInterface;
use MinifyX\Contract\SourceMapProviderInterface;
use MinifyX\Hook\HookRunner;
use MinifyX\Model\MinifyX;
use MinifyX\Optimization\EsbuildModuleBundler;
use MinifyX\Optimization\ExternalProcessRunner;
use MinifyX\Optimization\TerserJsOptimizer;
use MinifyX\Pipeline\ImportDependencyResolver;
use MinifyX\Pipeline\AssetPipeline;
use MinifyX\Pipeline\FileNormalizer;
use MinifyX\Processor\RegisteredAssetPageProcessor;
use MinifyX\Tooling\BoundedCommandRunner;
use MinifyX\Tooling\WarmCacheCommand;

final class PipelineToolingTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = rtrim(MODX_BASE_PATH, '/') . '/pipeline-tooling/';
        @mkdir($this->dir . 'styles/nested', 0755, true);
        @mkdir($this->dir . 'scripts', 0755, true);
        @mkdir($this->dir . 'cache', 0755, true);
        require_once dirname(__DIR__) . '/model/minifyx/minifyx.class.php';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->dir);
    }

    public function testImportResolverFindsTransitivePartialsAndStopsCycles(): void
    {
        $entry = $this->dir . 'styles/main.scss';
        $partial = $this->dir . 'styles/_colors.scss';
        $nested = $this->dir . 'styles/nested/_tokens.scss';
        file_put_contents($entry, '@use "colors";');
        file_put_contents($partial, '@forward "nested/tokens";');
        file_put_contents($nested, '@use "../colors";');

        $dependencies = (new ImportDependencyResolver(MODX_BASE_PATH))->resolve([$entry]);

        self::assertSame([realpath($partial), realpath($nested)], $dependencies);
    }

    public function testDependencyMtimeChangesBuildSignature(): void
    {
        $entry = $this->dir . 'styles/main.less';
        $dependency = $this->dir . 'styles/variables.less';
        file_put_contents($entry, '@import "variables";');
        file_put_contents($dependency, '@color: red;');
        $paths = array_merge([$entry], (new ImportDependencyResolver(MODX_BASE_PATH))->resolve([$entry]));
        $first = (new BuildSignature($paths))->hash();
        touch($dependency, time() + 2);
        clearstatcache(true, $dependency);

        self::assertNotSame($first, (new BuildSignature($paths))->hash());
    }

    public function testEsbuildModuleBundlerUsesEntryFilesInsteadOfConcatenating(): void
    {
        $first = $this->dir . 'scripts/first.js';
        $second = $this->dir . 'scripts/second.js';
        file_put_contents($first, 'export const first = 1;');
        file_put_contents($second, 'import { first } from "./first.js"; console.log(first);');
        $runner = new class extends ExternalProcessRunner {
            /** @var list<string> */
            public array $command = [];

            public function isExecutableAvailable(string $binary): bool
            {
                return true;
            }

            public function run(array $command, string $input = ''): string
            {
                $this->command = $command;
                foreach ($command as $argument) {
                    if (str_starts_with($argument, '--outfile=')) {
                        file_put_contents(substr($argument, 10), 'const first=1;console.log(first);');
                    }
                }

                return '';
            }
        };

        $content = (new EsbuildModuleBundler($runner))->bundle(
            [$first, $second],
            'esbuild',
            true,
            false,
            $this->dir . 'cache/modules.js'
        );

        self::assertSame('const first=1;console.log(first);', $content);
        self::assertStringContainsString('--bundle', implode(' ', $runner->command));
    }

    public function testModuleTagIsPreservedWhenEsbuildIsUnavailable(): void
    {
        file_put_contents($this->dir . 'scripts/module.js', 'export const value = 1;');
        $modx = new FakeModx('web');
        $modx->options['minifyx_bundleJsModules'] = true;
        $moduleTag = '<script type="module" src="/pipeline-tooling/scripts/module.js"></script>';
        $modx->sjscripts = [$moduleTag];
        $mx = new MinifyX($modx, [
            'cacheFolder' => '/pipeline-tooling/cache/',
            'cacheFolderPath' => $this->dir . 'cache/',
            'jsManglerPath' => '/missing/esbuild',
        ]);

        $result = (new RegisteredAssetPageProcessor())->process($modx, $mx);

        self::assertSame([$moduleTag], $result['head']);
        self::assertFalse((bool) $mx->config['bundleJsModules']);
        self::assertFalse((bool) $mx->config['jsModule']);
        self::assertSame('terser', $mx->config['jsMangler']);
    }

    public function testEsbuildModuleBundlerExposesExternalSourceMap(): void
    {
        $entry = $this->dir . 'scripts/module.js';
        file_put_contents($entry, 'export const value = 1;');
        $runner = new class extends ExternalProcessRunner {
            public function isExecutableAvailable(string $binary): bool
            {
                return true;
            }

            public function run(array $command, string $input = ''): string
            {
                foreach ($command as $argument) {
                    if (!str_starts_with($argument, '--outfile=')) {
                        continue;
                    }
                    $output = substr($argument, 10);
                    file_put_contents($output, "const value=1;\n//# sourceMappingURL=temp.js.map\n");
                    file_put_contents($output . '.map', '{"version":3,"sources":["module.js"],"mappings":""}');
                }

                return '';
            }
        };
        $bundler = new EsbuildModuleBundler($runner);

        $content = $bundler->bundle([$entry], 'esbuild', false, true, $this->dir . 'cache/module.js');

        self::assertStringNotContainsString('sourceMappingURL', $content);
        self::assertSame('{"version":3,"sources":["module.js"],"mappings":""}', $bundler->getSourceMap());
    }

    public function testTerserInlineMapIsExtractedForAtomicPublication(): void
    {
        $map = '{"version":3,"sources":["stdin.js"],"mappings":""}';
        $runner = new class($map) extends ExternalProcessRunner {
            private string $map;

            public function __construct(string $map)
            {
                $this->map = $map;
            }

            public function isExecutableAvailable(string $binary): bool
            {
                return true;
            }

            public function run(array $command, string $input = ''): string
            {
                return 'const value=1;' . "\n//# sourceMappingURL=data:application/json;base64,"
                    . base64_encode($this->map);
            }
        };
        $optimizer = new TerserJsOptimizer($runner);
        $optimizer->setSourceMaps(true);

        $content = $optimizer->optimize('const value = 1;', true, true, 'terser', '');

        self::assertSame('const value=1;', $content);
        self::assertSame($map, $optimizer->getSourceMap());
    }

    public function testPipelinePublishesMapAndReferenceTogether(): void
    {
        $source = $this->dir . 'scripts/mapped.js';
        file_put_contents($source, 'const mapped = true;');
        $adapter = new ArrayModxAdapter([], 'web', MODX_BASE_PATH, MODX_CORE_PATH, MODX_ASSETS_PATH);
        $config = new Config([
            'cacheFolder' => '/pipeline-tooling/cache/',
            'cacheFolderPath' => $this->dir . 'cache/',
            'jsFilename' => 'mapped',
            'minifyJs' => false,
            'mangleJs' => false,
            'sourceMaps' => true,
            'bundleJsModules' => false,
            'forceUpdate' => false,
            'hash_length' => 10,
            'hooks' => [],
            'preHooks' => [],
        ]);
        $processor = new class implements AssetProcessorInterface, SourceMapProviderInterface {
            public function process(array $absolutePaths, array $options = []): string
            {
                return 'const mapped=true;';
            }

            public function getSourceMap(): string
            {
                return '{"version":3,"sources":["mapped.js"],"mappings":""}';
            }
        };
        $pipeline = new AssetPipeline(
            $adapter,
            $config,
            new FileNormalizer(MODX_BASE_PATH, 'http://example.test/'),
            $processor,
            new AtomicFilesystemCache($this->dir . 'cache/'),
            new HookRunner($adapter, $this->dir)
        );

        $result = (new WarmCacheCommand($pipeline))->warm(['mapped' => ['/pipeline-tooling/scripts/mapped.js']]);
        $filename = (string) $result['groups'][0]['filename'];

        self::assertFileExists($this->dir . 'cache/' . $filename . '.map');
        self::assertStringContainsString(
            'sourceMappingURL=' . $filename . '.map',
            (string) file_get_contents($this->dir . 'cache/' . $filename)
        );
    }

    public function testWarmCacheSecondRunHasNoCompilerCalls(): void
    {
        file_put_contents($this->dir . 'scripts/app.js', 'window.app = true;');
        $modx = new FakeModx('web');
        $mx = new MinifyX($modx, [
            'cacheFolder' => '/pipeline-tooling/cache/',
            'cacheFolderPath' => $this->dir . 'cache/',
            'forceUpdate' => false,
        ]);
        $groups = ['app' => ['/pipeline-tooling/scripts/app.js']];

        $first = (new WarmCacheCommand($mx->getPipeline()))->warm($groups);
        $second = (new WarmCacheCommand($mx->getPipeline()))->warm($groups);

        self::assertSame(1, $first['compilerCalls']);
        self::assertSame(0, $second['compilerCalls']);
    }

    public function testParallelRunnerFallbackKeepsStableOrdering(): void
    {
        $runner = new BoundedCommandRunner(
            static function (): bool {
                return false;
            },
            static function (array $command): array {
                return ['exitCode' => 0, 'stdout' => $command[0], 'stderr' => ''];
            }
        );

        $result = $runner->run([['second'], ['first']], 4);

        self::assertFalse($runner->isSupported());
        self::assertSame(['second', 'first'], array_column($result, 'stdout'));
    }

    public function testParallelRunnerDrainsLargeProcessOutput(): void
    {
        $runner = new BoundedCommandRunner();
        if (!$runner->isSupported()) {
            self::markTestSkipped('proc_open is unavailable.');
        }

        $script = 'fwrite(STDOUT, str_repeat("o", 200000));'
            . 'fwrite(STDERR, str_repeat("e", 200000));';
        $result = $runner->run([[PHP_BINARY, '-r', $script]], 2);

        self::assertSame(0, $result[0]['exitCode']);
        self::assertSame(200000, strlen($result[0]['stdout']));
        self::assertSame(200000, strlen($result[0]['stderr']));
    }

    public function testParallelRunnerTerminatesTimedOutProcess(): void
    {
        $runner = new BoundedCommandRunner(null, null, 1);
        if (!$runner->isSupported()) {
            self::markTestSkipped('proc_open is unavailable.');
        }

        $result = $runner->run([[PHP_BINARY, '-r', 'sleep(5);']], 2);

        self::assertSame(124, $result[0]['exitCode']);
        self::assertStringContainsString('timed out', $result[0]['stderr']);
    }

    public function testModuleConfigIsRestoredWhenProcessingThrows(): void
    {
        $modx = new FakeModx('web');
        $modx->options['minifyx_bundleJsModules'] = true;
        $modx->sjscripts = ['<script type="module" src="/pipeline-tooling/scripts/module.js"></script>'];
        $minifyX = new class extends MinifyX {
            public string $activePath = '';

            public function __construct()
            {
                $this->config = [
                    'bundleJsModules' => false,
                    'jsModule' => false,
                    'jsMangler' => 'terser',
                    'jsManglerPath' => '/opt/terser',
                    'esbuildPath' => '/opt/esbuild',
                ];
            }

            public function processFiles($files, string $type): ?array
            {
                $this->activePath = (string) $this->config['jsManglerPath'];
                throw new \RuntimeException('Compilation failed.');
            }
        };

        try {
            (new RegisteredAssetPageProcessor())->process($modx, $minifyX);
            self::fail('Expected processing to throw.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Compilation failed.', $exception->getMessage());
        }

        self::assertFalse($minifyX->config['bundleJsModules']);
        self::assertFalse($minifyX->config['jsModule']);
        self::assertSame('terser', $minifyX->config['jsMangler']);
        self::assertSame('/opt/terser', $minifyX->config['jsManglerPath']);
        self::assertSame('/opt/esbuild', $minifyX->activePath);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path . '/');
            } else {
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
}
