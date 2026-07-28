<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Config;
use MinifyX\Integration\Modx3ServiceResolver;
use MinifyX\Model\MinifyX;
use MinifyX\Support\SettingUpgradeResolver;
use MODX\Revolution\modX as Modx3;

final class Migration3Test extends TestCase
{
    public function testConnectorAliasRequiresPrimaryConnectorOnly(): void
    {
        $alias = (string) file_get_contents(dirname(__DIR__, 4) . '/assets/components/minifyx/munee.php');
        $primary = (string) file_get_contents(dirname(__DIR__, 4) . '/assets/components/minifyx/minifyx.php');

        self::assertStringContainsString("require __DIR__ . '/minifyx.php';", $alias);
        self::assertStringNotContainsString('ImageController', $alias);
        self::assertStringContainsString("'minifyx_cache'", $primary);
        self::assertStringContainsString("'munee_cache'", $primary);
        self::assertStringContainsString("'minifyx_imageProcessor'", $primary);
        self::assertStringContainsString("'munee_imageProcessor'", $primary);
    }

    public function testUpgradeResolverCopiesOnlyUnsetValues(): void
    {
        $resolved = SettingUpgradeResolver::resolve([
            'munee_cache' => '/legacy/',
            'munee_imageProcessor' => 'Imagick',
            'minifyx_imageProcessor' => 'GD',
            'minifyx_cache' => null,
        ]);

        self::assertNull($resolved['minifyx_cache']);
        self::assertSame('GD', $resolved['minifyx_imageProcessor']);
        self::assertTrue(SettingUpgradeResolver::shouldMigrate(false, null, 'GD'));
        self::assertTrue(SettingUpgradeResolver::shouldMigrate(true, 'GD', 'GD'));
        self::assertFalse(SettingUpgradeResolver::shouldMigrate(true, 'Imagick', 'GD'));
    }

    public function testConfigNormalizesLegacySettingsOnCreateAndMerge(): void
    {
        $config = new Config(['munee_cache' => '/legacy/']);
        self::assertSame('/legacy/', $config->get('minifyx_cache'));

        $config->merge(['munee_imageProcessor' => 'Imagick']);
        self::assertSame('Imagick', $config->get('minifyx_imageProcessor'));
    }

    public function testSettingReadPrefersNewValueAndFallsBackToLegacy(): void
    {
        $modx = new FakeModx();
        $modx->options['munee_cache'] = '/legacy/';
        self::assertSame(
            '/legacy/',
            SettingUpgradeResolver::read($modx, 'minifyx_cache', 'munee_cache', '/default/')
        );

        $modx->options['minifyx_cache'] = '/new/';
        self::assertSame(
            '/new/',
            SettingUpgradeResolver::read($modx, 'minifyx_cache', 'munee_cache', '/default/')
        );
    }

    public function testNativeModx3ServiceResolutionPrecedesFallback(): void
    {
        require_once dirname(__DIR__) . '/model/minifyx/minifyx.class.php';
        $service = new MinifyX(new FakeModx(), ['cacheFolder' => '/assets/components/minifyx/cache/']);
        $modx = new Modx3();
        $modx->services = new class ($service) {
            public function __construct(private MinifyX $service)
            {
            }

            public function has(string $id): bool
            {
                return $id === 'minifyx';
            }

            public function get(string $id): MinifyX
            {
                if ($id !== 'minifyx') {
                    throw new \RuntimeException('Unknown service.');
                }

                return $this->service;
            }
        };

        self::assertSame(
            $service,
            Modx3ServiceResolver::resolveForModx3($modx, ['jsFilename' => 'native'])
        );
        self::assertSame('native', $service->config['jsFilename']);
    }

    public function testGetServiceCompatibilityFallbackRemainsAvailable(): void
    {
        $modx = new FakeModx();

        self::assertInstanceOf(MinifyX::class, Modx3ServiceResolver::resolve($modx));
    }
}
