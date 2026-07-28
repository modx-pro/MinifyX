<?php

declare(strict_types=1);

namespace MinifyX\Image;

use MinifyX\Support\PathHelper;

final class PathGuard
{
    private string $root;

    public function __construct(string $root)
    {
        $real = realpath($root);
        $this->root = $real !== false ? rtrim($real, '/\\') : rtrim($root, '/\\');
    }

    public function resolve(string $relativeOrAbsolute): string
    {
        $candidate = $relativeOrAbsolute;
        if (!str_starts_with($candidate, '/') && !preg_match('#^[A-Za-z]:\\\\#', $candidate)) {
            $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $candidate);
            $candidate = $this->root . DIRECTORY_SEPARATOR . ltrim($normalized, '/\\');
        }

        $real = realpath($candidate);
        if ($real === false) {
            throw new \InvalidArgumentException('File not found.');
        }
        if (!PathHelper::isInsideRoot($real, $this->root)) {
            throw new \InvalidArgumentException('Path traversal denied.');
        }

        return $real;
    }

    public function getRoot(): string
    {
        return $this->root;
    }
}
