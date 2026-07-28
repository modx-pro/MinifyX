<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Support\UrlHelper;

final class UrlHelperTest extends TestCase
{
    public function testSchemeAwareSiteUrlUpgradesHttpWhenHttpsDetected(): void
    {
        $url = UrlHelper::schemeAwareSiteUrl('http://example.test/sub/', true);
        self::assertSame('https://example.test/sub/', $url);
    }

    public function testAbsolutizeResolvesProtocolRelativeConnector(): void
    {
        $url = UrlHelper::absolutize(
            '//cdn.example.test/munee.php',
            'http://example.test/',
            true
        );
        self::assertSame('https://cdn.example.test/munee.php', $url);
    }

    public function testRootRelativeConnectorDoesNotInheritSiteSubdirectory(): void
    {
        $url = UrlHelper::absolutize(
            '/assets/components/minifyx/munee.php',
            'https://example.test/subdirectory/',
            true
        );

        self::assertSame('https://example.test/assets/components/minifyx/munee.php', $url);
    }
}
