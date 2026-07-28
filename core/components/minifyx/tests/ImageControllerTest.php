<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Cache\AtomicFilesystemCache;
use MinifyX\Image\ImageController;
use MinifyX\Image\ImageProcessorFactory;
use MinifyX\Image\PathGuard;

final class ImageControllerTest extends TestCase
{
    private string $root;
    private string $cacheDir;

    protected function setUp(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD extension required');
        }
        $this->root = sys_get_temp_dir() . '/minifyx-img-' . uniqid('', true) . '/';
        $this->cacheDir = $this->root . 'cache/';
        mkdir($this->cacheDir, 0755, true);
        $img = imagecreatetruecolor(20, 10);
        imagejpeg($img, $this->root . 'photo.jpg', 90);
        if (PHP_VERSION_ID < 80500) {
            imagedestroy($img);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cacheDir . '*') ?: [] as $file) {
            @unlink($file);
        }
        @unlink($this->root . 'photo.jpg');
        @rmdir($this->cacheDir);
        @rmdir($this->root);
    }

    public function testResizeAndRejectTraversal(): void
    {
        $controller = new ImageController(
            new PathGuard($this->root),
            ImageProcessorFactory::create('GD', 1_000_000, 5_000_000),
            new AtomicFilesystemCache($this->cacheDir)
        );

        $ok = $controller->handle([
            'files' => 'photo.jpg',
            'resize' => 'w[10]h[5]',
        ]);
        self::assertSame(200, $ok['status']);
        self::assertNotSame('', $ok['body']);

        $denied = $controller->handle([
            'files' => '../photo.jpg',
            'resize' => 'w[10]',
        ]);
        self::assertSame(403, $denied['status']);
    }

    public function testOversizedPixelLimitRejectedBeforeDecodePathUsesMetadata(): void
    {
        $controller = new ImageController(
            new PathGuard($this->root),
            ImageProcessorFactory::create('GD', 50, 5_000_000),
            new AtomicFilesystemCache($this->cacheDir)
        );
        $result = $controller->handle([
            'files' => 'photo.jpg',
            'resize' => 'w[5]',
        ]);
        self::assertSame(422, $result['status']);
        self::assertStringContainsString('pixel', strtolower($result['body']));
    }
}
