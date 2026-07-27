<?php

declare(strict_types=1);

namespace MinifyX\Support;

final class PathHelper
{
    public static function isInsideRoot(string $path, string $root): bool
    {
        $real = realpath($path);
        $rootReal = realpath($root);
        if ($real === false || $rootReal === false) {
            return false;
        }

        $rootReal = rtrim($rootReal, '/\\');

        return $real === $rootReal || str_starts_with($real, $rootReal . DIRECTORY_SEPARATOR);
    }

    public static function sanitizeFilename(string $filename): string
    {
        $normalized = str_replace(['\\', "\0"], ['/', ''], $filename);
        if ($normalized !== basename($normalized) || str_contains($normalized, '..')) {
            throw new \InvalidArgumentException('Invalid cache filename.');
        }
        $base = basename($normalized);
        if ($base === '' || $base === '.' || $base === '..') {
            throw new \InvalidArgumentException('Invalid cache filename.');
        }
        if (preg_match('/[\/\\\\]/', $base) === 1) {
            throw new \InvalidArgumentException('Invalid cache filename.');
        }

        return $base;
    }
}
