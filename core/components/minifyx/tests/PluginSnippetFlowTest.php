<?php

declare(strict_types=1);

namespace MinifyX\Tests;

final class PluginSnippetFlowTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = rtrim(MODX_BASE_PATH, '/') . '/';
        @mkdir($this->base . 'assets/css', 0755, true);
        @mkdir($this->base . 'assets/js', 0755, true);
        file_put_contents($this->base . 'assets/css/app.css', 'body { color: red; }');
        file_put_contents($this->base . 'assets/js/app.js', 'window.app = true;');
        file_put_contents($this->base . 'assets/js/one.js', 'window.one = true;');
        file_put_contents($this->base . 'assets/js/two.js', 'window.two = true;');
        file_put_contents($this->base . 'assets/js/excluded.js', 'window.excluded = true;');
    }

    public function testPluginProcessesRegisteredAssetsAndInjectsBundles(): void
    {
        $modx = new FakeModx();
        $modx->event->name = 'OnWebPagePrerender';
        $modx->options = array_merge($modx->options, [
            'minifyx_process_registered' => true,
            'minifyx_bundleIntegrity' => true,
            'minifyx_cacheFolder' => '/assets/components/minifyx/cache/',
        ]);
        $modx->sjscripts = ['<link rel="stylesheet" href="/assets/css/app.css">'];
        $modx->jscripts = ['<script src="/assets/js/app.js" defer></script>'];
        $modx->resource->_output = '<html><head >'
            . $modx->getRegisteredClientStartupScripts()
            . '</head ><body>'
            . $modx->getRegisteredClientScripts()
            . '</body ></html>';

        $this->includePlugin($modx);

        $output = (string) $modx->resource->_output;
        self::assertStringContainsString('integrity="sha384-', $output);
        self::assertStringContainsString('crossorigin="anonymous"', $output);
        self::assertStringContainsString('defer', $output);
        self::assertStringNotContainsString('/assets/css/app.css', $output);
        self::assertStringNotContainsString('/assets/js/app.js', $output);
        self::assertStringNotContainsString('<!-- MinifyX ', $output);
    }

    public function testSnippetRunsThroughLegacyEntryPoint(): void
    {
        $modx = new FakeModx();
        $scriptProperties = [
            'jsSources' => 'assets/js/app.js',
            'registerJs' => 'print',
            'cacheFolder' => '/assets/components/minifyx/cache/',
        ];

        $result = $this->includeSnippet($modx, $scriptProperties);

        self::assertStringContainsString('<script', (string) $result);
        self::assertStringContainsString('/assets/components/minifyx/cache/', (string) $result);
    }

    public function testDebugCommentIsSafeAndDefaultOff(): void
    {
        $modx = new FakeModx();
        $modx->event->name = 'OnWebPagePrerender';
        $modx->options['minifyx_process_registered'] = true;
        $modx->options['minifyx_debug'] = true;
        $modx->sjscripts = ['<script src="/assets/js/app.js"></script>'];
        $modx->resource->_output = '<html><head>'
            . $modx->getRegisteredClientStartupScripts()
            . '</head><body></body></html>';

        $this->includePlugin($modx);

        $output = (string) $modx->resource->_output;
        self::assertStringContainsString('<!-- MinifyX type=js sources=1 cache=', $output);
        self::assertStringNotContainsString(MODX_BASE_PATH, $output);
    }

    public function testRawBundlesPreserveAndSeparateCspNonces(): void
    {
        $modx = new FakeModx();
        $modx->event->name = 'OnWebPagePrerender';
        $modx->options['minifyx_process_registered'] = true;
        $modx->options['minifyx_processRawJs'] = true;
        $modx->sjscripts = [
            '<script nonce="one">window.one = true;</script>',
            '<script nonce="two">window.two = true;</script>',
            '<script nonce="one">window.oneAgain = true;</script>',
        ];
        $modx->resource->_output = '<html><head>'
            . $modx->getRegisteredClientStartupScripts()
            . '</head><body></body></html>';

        $this->includePlugin($modx);

        $output = (string) $modx->resource->_output;
        self::assertStringContainsString('nonce="one"', $output);
        self::assertStringContainsString('nonce="two"', $output);
        self::assertSame(3, substr_count($output, '<script'));
        self::assertMatchesRegularExpression(
            '/nonce="one".*nonce="two".*nonce="one"/s',
            $output
        );
    }

    public function testExcludedAssetRemainsBetweenAdjacentBundles(): void
    {
        $modx = new FakeModx();
        $modx->event->name = 'OnWebPagePrerender';
        $modx->options['minifyx_process_registered'] = true;
        $modx->options['minifyx_exclude_registered'] = '#excluded#';
        $modx->sjscripts = [
            '<script src="/assets/js/one.js"></script>',
            '<script src="/assets/js/excluded.js"></script>',
            '<script src="/assets/js/two.js"></script>',
        ];
        $modx->resource->_output = '<html><head>'
            . $modx->getRegisteredClientStartupScripts()
            . '</head><body></body></html>';

        $this->includePlugin($modx);

        preg_match_all('/<script[^>]+src="([^"]+)"/', (string) $modx->resource->_output, $matches);
        self::assertCount(3, $matches[1]);
        self::assertSame('/assets/js/excluded.js', $matches[1][1]);
    }

    public function testRawBlocksDoNotCrossExternalAssetBarrier(): void
    {
        $modx = new FakeModx();
        $modx->event->name = 'OnWebPagePrerender';
        $modx->options['minifyx_process_registered'] = true;
        $modx->options['minifyx_processRawJs'] = true;
        $modx->sjscripts = [
            '<script nonce="same">window.before = true;</script>',
            '<script src="/assets/js/app.js"></script>',
            '<script nonce="same">window.after = true;</script>',
        ];
        $modx->resource->_output = '<html><head>'
            . $modx->getRegisteredClientStartupScripts()
            . '</head><body></body></html>';

        $this->includePlugin($modx);

        $output = (string) $modx->resource->_output;
        self::assertSame(3, substr_count($output, '<script'));
        self::assertMatchesRegularExpression(
            '/nonce="same".*<script(?![^>]*nonce)[^>]*>.*nonce="same"/s',
            $output
        );
    }

    private function includePlugin(FakeModx $modx): void
    {
        include dirname(__DIR__) . '/elements/plugins/plugin.minifyx.php';
    }

    /**
     * @param array<string, mixed> $scriptProperties
     * @return mixed
     */
    private function includeSnippet(FakeModx $modx, array $scriptProperties)
    {
        return include dirname(__DIR__) . '/elements/snippets/snippet.minifyx.php';
    }
}
