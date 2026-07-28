<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Pipeline\FileNormalizer;

final class FileNormalizerTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/minifyx-norm-' . uniqid('', true) . '/';
        mkdir($this->base . 'assets', 0755, true);
        file_put_contents($this->base . 'assets/a.js', 'var a=1;');
        file_put_contents($this->base . 'assets/b.css', 'body{}');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->base . 'assets/*') ?: []);
        @rmdir($this->base . 'assets');
        @rmdir($this->base);
    }

    public function testNormalizeSkipsCommentedFilesAndCollectsQueryParamsWithoutTouchingGlobals(): void
    {
        $_GET = ['keep' => '1'];
        $normalizer = new FileNormalizer($this->base, 'http://example.test');
        $result = $normalizer->normalize([
            '-assets/skip.js',
            'assets/a.js?foo=bar',
            'http://example.test/assets/b.css',
        ]);

        self::assertSame(['/assets/a.js', '/assets/b.css'], $result['paths']);
        self::assertSame(['foo' => 'bar'], $result['queryParams']);
        self::assertSame(['keep' => '1'], $_GET);
    }

    public function testPathTraversalIsRejectedOnAbsoluteResolution(): void
    {
        $outside = sys_get_temp_dir() . '/minifyx-outside-' . uniqid('', true) . '.js';
        file_put_contents($outside, 'evil');
        $normalizer = new FileNormalizer($this->base);
        $absolute = $normalizer->toAbsolutePaths(['/../' . basename($outside)]);
        // Candidate may be constructed, but assertInsideRoot must fail for outside files.
        $this->expectException(\InvalidArgumentException::class);
        $normalizer->assertInsideRoot($outside, $this->base);
        @unlink($outside);
    }
}
