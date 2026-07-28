<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Cache\AtomicFilesystemCache;
use MinifyX\Image\ImageController;
use MinifyX\Image\ImageProcessorFactory;
use MinifyX\Image\PathGuard;
use MinifyX\Support\PathHelper;
use PHPUnit\Framework\TestCase;

final class SecurityFixesTest extends TestCase
{
    public function testPathHelperRejectsSiblingPrefix(): void
    {
        $root = sys_get_temp_dir() . '/minifyx-root-' . uniqid('', true);
        $sibling = $root . '-backup';
        mkdir($root, 0755, true);
        mkdir($sibling, 0755, true);
        file_put_contents($sibling . '/secret.jpg', 'x');

        self::assertFalse(PathHelper::isInsideRoot($sibling . '/secret.jpg', $root));

        $guard = new PathGuard($root);
        $this->expectException(\InvalidArgumentException::class);
        try {
            $guard->resolve($sibling . '/secret.jpg');
        } finally {
            @unlink($sibling . '/secret.jpg');
            @rmdir($sibling);
            @rmdir($root);
        }
    }

    public function testCacheWriteRejectsTraversalFilename(): void
    {
        $dir = sys_get_temp_dir() . '/minifyx-csec-' . uniqid('', true) . '/';
        $cache = new AtomicFilesystemCache($dir);
        $cache->prepare();
        self::assertFalse($cache->write('../../pwned.js', 'evil', true));
        self::assertFileDoesNotExist(dirname($dir) . '/pwned.js');
        $cache->removeDirectory($dir);
    }

    public function testImageSignatureEnforcedAndResizeClamped(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD required');
        }
        $root = sys_get_temp_dir() . '/minifyx-imgsec-' . uniqid('', true) . '/';
        $cacheDir = $root . 'cache/';
        mkdir($cacheDir, 0755, true);
        $img = imagecreatetruecolor(20, 10);
        imagejpeg($img, $root . 'photo.jpg', 90);
        imagedestroy($img);

        $controller = new ImageController(
            new PathGuard($root),
            ImageProcessorFactory::create('GD', 1_000_000, 5_000_000, 4096),
            new AtomicFilesystemCache($cacheDir),
            'secret-key',
            4096
        );

        $denied = $controller->handle([
            'files' => 'photo.jpg',
            'resize' => 'w[10]',
        ]);
        self::assertSame(403, $denied['status']);

        $oversized = $controller->handle([
            'files' => 'photo.jpg',
            'resize' => 'w[50000]',
            'sig' => $controller->sign('photo.jpg', 'w[50000]'),
        ]);
        self::assertSame(422, $oversized['status']);

        $okResize = 'w[10]';
        $ok = $controller->handle([
            'files' => 'photo.jpg',
            'resize' => $okResize,
            'sig' => $controller->sign('photo.jpg', $okResize),
        ]);
        self::assertSame(200, $ok['status']);

        foreach (glob($cacheDir . '*') ?: [] as $file) {
            @unlink($file);
        }
        @unlink($root . 'photo.jpg');
        @rmdir($cacheDir);
        @rmdir($root);
    }
}
