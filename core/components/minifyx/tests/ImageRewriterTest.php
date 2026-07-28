<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Processor\ImageRewriter;

final class ImageRewriterTest extends TestCase
{
    public function testSinglePassRewritePreservesOtherAttributes(): void
    {
        $html = '<div><img src="/assets/a.jpg" width="100" height="50" alt="A" class="x">'
            . '<img src="http://cdn.test/b.jpg" width="10"></div>';
        $rewriter = new ImageRewriter('/assets/components/minifyx/munee.php', 'http://example.test/', 's[true]');
        $out = $rewriter->rewrite($html);
        self::assertStringContainsString('munee.php?files=', $out);
        self::assertStringContainsString('alt="A"', $out);
        self::assertStringContainsString('class="x"', $out);
        self::assertStringContainsString('http://cdn.test/b.jpg', $out);
    }
}
