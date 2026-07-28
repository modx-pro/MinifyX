<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Model\MinifyX;

use MinifyX\Processor\RegisteredAssetsProcessor;

final class PipelineFixesTest extends TestCase
{
    private string $base;
    private string $cache;

    protected function setUp(): void
    {
        $this->base = rtrim(MODX_BASE_PATH, '/') . '/';
        $this->cache = $this->base . 'assets/components/minifyx/cache/';
        @mkdir($this->base . 'assets/css', 0755, true);
        @mkdir($this->cache, 0755, true);
        file_put_contents($this->base . 'assets/css/vars.scss', '$c: red; body { color: $c; }');
        file_put_contents($this->base . 'assets/js/one.js', "var one = 1;\n");
        file_put_contents($this->base . 'assets/js/legacy.coffee', 'square = (x) -> x * x');
        require_once dirname(__DIR__) . '/model/minifyx/minifyx.class.php';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cache . '*') ?: [] as $file) {
            @unlink($file);
        }
        @unlink($this->base . 'assets/css/vars.scss');
        @unlink($this->base . 'assets/js/legacy.coffee');
    }

    public function testCompileFailureDoesNotPublish(): void
    {
        $modx = new FakeModx('web');
        $mx = new MinifyX($modx, [
            'cacheFolder' => '/assets/components/minifyx/cache/',
            'forceUpdate' => true,
        ]);
        $result = $mx->processFiles(['assets/missing-file.js'], 'js');
        self::assertNull($result);
    }

    public function testPipelineClassifiesMissingAndEmptySources(): void
    {
        $modx = new FakeModx('web');
        $mx = new MinifyX($modx, ['cacheFolder' => '/assets/components/minifyx/cache/']);

        $empty = $mx->getPipeline()->processAndSave([], 'js', $mx);
        self::assertSame('no_sources', $empty['errorCode']);
        self::assertSame('', $empty['sourceFile']);

        $missing = $mx->getPipeline()->processAndSave(['assets/missing-secret.js'], 'js', $mx);
        self::assertSame('source_resolution', $missing['errorCode']);
        self::assertSame('missing-secret.js', $missing['sourceFile']);
        self::assertStringNotContainsString(MODX_BASE_PATH, $missing['error']);

        $mx->setConfig(['jsFilename' => '../invalid']);
        $invalid = $mx->getPipeline()->processAndSave(['assets/js/one.js'], 'js', $mx);
        self::assertSame('invalid_filename', $invalid['errorCode']);
        self::assertSame('configuration', $invalid['phase']);
    }

    public function testPipelineReturnsStructuredUnsupportedCoffeeError(): void
    {
        $modx = new FakeModx('web');
        $mx = new MinifyX($modx, [
            'cacheFolder' => '/assets/components/minifyx/cache/',
            'forceUpdate' => true,
        ]);

        $result = $mx->getPipeline()->processAndSave(['assets/js/legacy.coffee'], 'js', $mx);

        self::assertFalse($result['success']);
        self::assertSame('unsupported_source_type', $result['errorCode']);
        self::assertStringContainsString('Precompile', $result['error']);
        self::assertSame('legacy.coffee', $result['sourceFile']);
    }

    public function testQueryParamsReachScssCompiler(): void
    {
        file_put_contents($this->base . 'assets/css/param.scss', 'body { color: $accent; }');
        $modx = new FakeModx('web');
        $mx = new MinifyX($modx, [
            'cacheFolder' => '/assets/components/minifyx/cache/',
            'forceUpdate' => true,
        ]);
        $params = [];
        $paths = $mx->prepareFiles('assets/css/param.scss?accent=%23f00', 'css', $params);
        self::assertSame(['accent' => '#f00'], $params);
        $css = $mx->Munee($paths, ['minify' => false, 'queryParams' => $params]);
        self::assertStringContainsString('#f00', $css);
        @unlink($this->base . 'assets/css/param.scss');
    }

    public function testResetSyncsExtensions(): void
    {
        $modx = new FakeModx('web');
        $mx = new MinifyX($modx, ['cacheFolder' => '/assets/components/minifyx/cache/']);
        $mx->minify(true);
        self::assertSame('.min.js', $mx->config['jsExt']);
        $mx->reset(['minifyJs' => false, 'minifyCss' => false, 'cacheFolder' => '/assets/components/minifyx/cache/']);
        self::assertSame('.js', $mx->config['jsExt']);
        self::assertSame('.css', $mx->config['cssExt']);
    }

    public function testHookMutatesPipelineContent(): void
    {
        $hooksDir = MODX_CORE_PATH . 'components/minifyx/hooks/';
        @mkdir($hooksDir, 0755, true);
        $hook = $hooksDir . 'testHook.php';
        file_put_contents($hook, '<?php $MinifyX->setContent($MinifyX->getContent() . "/*hooked*/");');

        $modx = new FakeModx('web');
        $mx = new MinifyX($modx, [
            'cacheFolder' => '/assets/components/minifyx/cache/',
            'jsSources' => 'assets/js/one.js',
            'hooks' => 'testHook.php',
            'hooksPath' => $hooksDir,
            'forceUpdate' => true,
            'registerJs' => 'print',
        ]);
        $out = $mx->run();
        self::assertNotSame('', $out);
        $content = $mx->getContent();
        self::assertStringContainsString('/*hooked*/', $content);
        @unlink($hook);
    }

    public function testBundleAttributesDropIntegrity(): void
    {
        $parser = new RegisteredAssetsProcessor();
        $attrs = $parser->bundleAttributes([
            ['attributes' => ['defer' => true, 'integrity' => 'sha256-a', 'src' => '/a.js']],
            ['attributes' => ['async' => true, 'integrity' => 'sha256-b']],
        ]);
        self::assertArrayHasKey('defer', $attrs);
        self::assertArrayNotHasKey('integrity', $attrs);
        self::assertArrayNotHasKey('src', $attrs);
        self::assertArrayNotHasKey('async', $attrs);
    }
}
