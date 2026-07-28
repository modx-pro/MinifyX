<?php

declare(strict_types=1);

namespace MinifyX\Tests;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @return never
     */
    public static function markTestSkipped(string $message = ''): void
    {
        parent::markTestSkipped($message);
    }

    /**
     * @param int|string $key
     * @param array<mixed>|\ArrayAccess<mixed, mixed> $array
     */
    public static function assertArrayHasKey($key, $array, string $message = ''): void
    {
        parent::assertArrayHasKey($key, $array, $message);
    }

    /**
     * @param int|string $key
     * @param array<mixed>|\ArrayAccess<mixed, mixed> $array
     */
    public static function assertArrayNotHasKey($key, $array, string $message = ''): void
    {
        parent::assertArrayNotHasKey($key, $array, $message);
    }

    /**
     * @param \Countable|iterable<mixed> $haystack
     */
    public static function assertCount(int $expectedCount, $haystack, string $message = ''): void
    {
        parent::assertCount($expectedCount, $haystack, $message);
    }

    /** @param mixed $condition */
    public static function assertFalse($condition, string $message = ''): void
    {
        parent::assertFalse($condition, $message);
    }

    public static function assertFileDoesNotExist(string $filename, string $message = ''): void
    {
        parent::assertFileDoesNotExist($filename, $message);
    }

    public static function assertFileExists(string $filename, string $message = ''): void
    {
        parent::assertFileExists($filename, $message);
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    public static function assertGreaterThan($expected, $actual, string $message = ''): void
    {
        parent::assertGreaterThan($expected, $actual, $message);
    }

    /**
     * @param mixed $actual
     */
    public static function assertNull($actual, string $message = ''): void
    {
        parent::assertNull($actual, $message);
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    public static function assertNotSame($expected, $actual, string $message = ''): void
    {
        parent::assertNotSame($expected, $actual, $message);
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    public static function assertSame($expected, $actual, string $message = ''): void
    {
        parent::assertSame($expected, $actual, $message);
    }

    public static function assertStringContainsString(
        string $needle,
        string $haystack,
        string $message = ''
    ): void {
        parent::assertStringContainsString($needle, $haystack, $message);
    }

    public static function assertStringNotContainsString(
        string $needle,
        string $haystack,
        string $message = ''
    ): void {
        parent::assertStringNotContainsString($needle, $haystack, $message);
    }

    /** @param mixed $condition */
    public static function assertTrue($condition, string $message = ''): void
    {
        parent::assertTrue($condition, $message);
    }
}
