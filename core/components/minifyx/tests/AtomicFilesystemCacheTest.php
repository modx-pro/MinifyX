<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Cache\AtomicFilesystemCache;
use PHPUnit\Framework\TestCase;

final class AtomicFilesystemCacheTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/minifyx-cache-' . uniqid('', true) . '/';
    }

    protected function tearDown(): void
    {
        $cache = new AtomicFilesystemCache($this->dir);
        $cache->removeDirectory($this->dir);
    }

    public function testAtomicWriteAndFingerprintLookup(): void
    {
        $cache = new AtomicFilesystemCache($this->dir);
        self::assertTrue($cache->prepare());

        $source = $this->dir . 'src.js';
        file_put_contents($source, 'var x=1;');
        $fp = $cache->fingerprint([$source], ['minify' => true]);
        $name = 'scripts_' . $fp . '.min.js';
        self::assertTrue($cache->write($name, 'var x=1;', true));
        self::assertSame($name, $cache->lookupByFingerprint('scripts', $fp, '.min.js'));
        self::assertTrue($cache->exists($name));
    }

    public function testClearOnlyRemovesOwnArtifacts(): void
    {
        $cache = new AtomicFilesystemCache($this->dir);
        $cache->prepare();
        file_put_contents($this->dir . 'keep.txt', 'no');
        file_put_contents($this->dir . 'styles_abcdefghij.css', 'body{}');
        $cache->clear(false, ['styles_abcdefghij.css']);
        self::assertFileExists($this->dir . 'keep.txt');
        self::assertFileDoesNotExist($this->dir . 'styles_abcdefghij.css');
    }
}
