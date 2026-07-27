<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Processor\HtmlMinifier;
use MinifyX\Processor\RegisteredAssetsProcessor;
use PHPUnit\Framework\TestCase;

final class HtmlAndRegisteredAssetsTest extends TestCase
{
    public function testHtmlMinifierPreservesPreAndScript(): void
    {
        $html = "<html>\n<body>\n<pre>  keep  spaces  </pre>\n<script>var a = 1;\nvar b = 2;</script>\n<p>Hello   World</p>\n</body></html>";
        $min = (new HtmlMinifier())->minify($html);
        self::assertStringContainsString('<pre>  keep  spaces  </pre>', $min);
        self::assertStringContainsString("var a = 1;\nvar b = 2;", $min);
        self::assertStringNotContainsString('<p>Hello   World</p>', $min);
        self::assertStringContainsString('<p>Hello World</p>', $min);
    }

    public function testRegisteredAssetsPreserveAttributesAndDetectCssQueryString(): void
    {
        $parser = new RegisteredAssetsProcessor();
        $tags = [
            '<link rel="stylesheet" href="/app.css?v=1" media="print" />',
            '<script src="/app.js" defer type="module" integrity="sha256-abc"></script>',
            '<script>alert(1)</script>',
        ];
        $parsed = $parser->parseTags($tags);
        self::assertTrue($parser->isCssUrl($parsed[0]['url']));
        self::assertTrue($parser->isJsUrl($parsed[1]['url']));
        self::assertSame('raw-js', $parsed[2]['kind']);

        $rebuilt = $parser->buildTag('script', '/bundle.js', $parsed[1]['attributes']);
        self::assertStringContainsString('defer', $rebuilt);
        self::assertStringContainsString('type="module"', $rebuilt);
        self::assertStringContainsString('integrity="sha256-abc"', $rebuilt);
        self::assertStringContainsString('src="/bundle.js"', $rebuilt);

        $cssTag = $parser->buildTag('link', '/bundle.css?v=1', $parsed[0]['attributes']);
        self::assertStringContainsString('media="print"', $cssTag);
        self::assertStringContainsString('href="/bundle.css?v=1"', $cssTag);
    }
}
