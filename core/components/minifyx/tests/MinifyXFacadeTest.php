<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Model\MinifyX;

final class MinifyXFacadeTest extends TestCase
{
    private string $base;
    private string $cache;

    protected function setUp(): void
    {
        $this->base = rtrim(MODX_BASE_PATH, '/') . '/';
        $this->cache = $this->base . 'assets/components/minifyx/cache/';
        @mkdir($this->base . 'assets/js', 0755, true);
        @mkdir($this->base . 'assets/css', 0755, true);
        @mkdir($this->cache, 0755, true);
        file_put_contents($this->base . 'assets/js/one.js', "var one = 1;\n");
        file_put_contents($this->base . 'assets/js/two.js', "var two = 2;\n");
        file_put_contents($this->base . 'assets/css/one.css', "body { margin: 0; }\n");
        require_once dirname(__DIR__) . '/model/minifyx/minifyx.class.php';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cache . '*') ?: [] as $file) {
            @unlink($file);
        }
    }

    public function testMinifySyncsExtensionsAndPrintReturnsBothTypes(): void
    {
        $modx = new FakeModx('web');
        $mx = new MinifyX($modx, [
            'cacheFolder' => '/assets/components/minifyx/cache/',
            'jsSources' => 'assets/js/one.js,assets/js/two.js',
            'cssSources' => 'assets/css/one.css',
            'registerJs' => 'print',
            'registerCss' => 'print',
            'forceUpdate' => true,
        ]);
        $mx->minify(true);
        self::assertSame('.min.js', $mx->config['jsExt']);
        self::assertSame('.min.css', $mx->config['cssExt']);

        $out = $mx->run();
        self::assertStringContainsString('<link', $out);
        self::assertStringContainsString('<script', $out);
    }

    public function testWarmCacheSkipsCompiler(): void
    {
        $modx = new FakeModx('web');
        $config = [
            'cacheFolder' => '/assets/components/minifyx/cache/',
            'jsSources' => 'assets/js/one.js',
            'registerJs' => 'default',
            'forceUpdate' => true,
        ];
        $mx = new MinifyX($modx, $config);
        $mx->run();
        $firstCalls = $mx->getPipeline()->getCompilerCalls();
        self::assertGreaterThan(0, $firstCalls);

        $mx2 = new MinifyX($modx, array_merge($config, ['forceUpdate' => false]));
        $mx2->run();
        self::assertSame(0, $mx2->getPipeline()->getCompilerCalls());
    }

    public function testEmptySaveFileIsNotTreatedAsFailure(): void
    {
        $modx = new FakeModx('web');
        $mx = new MinifyX($modx, [
            'cacheFolder' => '/assets/components/minifyx/cache/',
            'jsFilename' => 'empty',
            'forceUpdate' => true,
        ]);
        $mx->prepareCacheFolder();
        // Force filetype via prepareFiles
        $mx->prepareFiles(['assets/js/one.js'], 'js');
        self::assertTrue($mx->saveFile(''));
        self::assertFileExists($mx->getFilePath());
        self::assertSame('', file_get_contents($mx->getFilePath()));
    }

    public function testPrepareFilesDoesNotMutateGet(): void
    {
        $_GET = ['original' => 'yes'];
        $modx = new FakeModx('web');
        $mx = new MinifyX($modx, ['cacheFolder' => '/assets/components/minifyx/cache/']);
        $params = [];
        $mx->prepareFiles('assets/js/one.js?x=1', 'js', $params);
        self::assertSame(['x' => '1'], $params);
        self::assertSame(['original' => 'yes'], $_GET);
    }
}
