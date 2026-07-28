<?php

declare(strict_types=1);

namespace MinifyX\Cache;

use MinifyX\Contract\CacheStoreInterface;
use MinifyX\Support\PathHelper;

final class AtomicFilesystemCache implements CacheStoreInterface
{
    private string $directory;
    private int $hashLength;

    public function __construct(string $directory, int $hashLength = 10)
    {
        $this->directory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
        $this->hashLength = $hashLength;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function prepare(): bool
    {
        return $this->makeDirectory($this->directory);
    }

    public function exists(string $filename): bool
    {
        return is_file($this->path($filename));
    }

    public function path(string $filename): string
    {
        $safe = PathHelper::sanitizeFilename($filename);
        $target = $this->directory . $safe;
        $dirReal = realpath($this->directory) ?: rtrim($this->directory, '/\\');
        $targetDir = realpath(dirname($target)) ?: dirname($target);
        if ($targetDir !== $dirReal && !str_starts_with($targetDir, rtrim($dirReal, '/\\') . DIRECTORY_SEPARATOR)) {
            throw new \InvalidArgumentException('Cache path escapes cache directory.');
        }

        return $target;
    }

    public function write(string $filename, string $content, bool $force = false): bool
    {
        if (!$this->prepare()) {
            return false;
        }

        try {
            $target = $this->path($filename);
        } catch (\InvalidArgumentException) {
            return false;
        }

        if (!$force && is_file($target)) {
            return true;
        }

        $tmp = $target . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $bytes = file_put_contents($tmp, $content, LOCK_EX);
        if ($bytes === false) {
            @unlink($tmp);

            return false;
        }

        if (!@rename($tmp, $target)) {
            @unlink($tmp);

            return false;
        }

        return is_file($target);
    }

    /**
     * @param list<string> $sourcePaths
     * @param array<string, mixed> $options
     */
    public function fingerprint(array $sourcePaths, array $options = []): string
    {
        $parts = [$options];
        foreach ($sourcePaths as $path) {
            $realpath = realpath($path) ?: $path;
            $mtime = is_file($realpath) ? (string) filemtime($realpath) : '0';
            $size = is_file($realpath) ? (string) filesize($realpath) : '0';
            $parts[] = $realpath . '|' . $mtime . '|' . $size;
        }

        return substr(hash('sha1', serialize($parts)), 0, $this->hashLength);
    }

    public function lookupByFingerprint(string $basename, string $fingerprint, string $extension): ?string
    {
        $filename = PathHelper::sanitizeFilename($basename) . '_' . $fingerprint . $extension;
        if ($this->exists($filename)) {
            return $filename;
        }

        return null;
    }

    /**
     * @param list<string> $trackedFiles
     */
    public function clear(bool $forceDelete = false, array $trackedFiles = []): void
    {
        if (!$this->prepare()) {
            return;
        }

        if ($forceDelete) {
            foreach (new \DirectoryIterator($this->directory) as $file) {
                if ($file->isFile()) {
                    @unlink($file->getPathname());
                }
            }

            return;
        }

        $pattern = '/^[a-z0-9._-]+_[a-z0-9]{' . $this->hashLength
            . '}\.(?:min\.)?(?:js|css)(?:\.map)?$/i';
        $toDelete = [];
        foreach ($trackedFiles as $file) {
            $safe = PathHelper::sanitizeFilename((string) $file);
            if (preg_match($pattern, $safe)) {
                $toDelete[$safe] = true;
            }
        }
        foreach (scandir($this->directory) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (preg_match($pattern, $file)) {
                $toDelete[$file] = true;
            }
        }
        foreach (array_keys($toDelete) as $file) {
            $path = $this->directory . $file;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function removeDirectory(string $dir): bool
    {
        $dir = rtrim($dir, '/\\');
        if (!is_dir($dir)) {
            return true;
        }

        $items = scandir($dir);
        if ($items === false) {
            return false;
        }

        foreach ($items as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);

        return !file_exists($dir);
    }

    public function makeDirectory(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if (is_dir($path)) {
            return true;
        }

        return mkdir($path, 0755, true) || is_dir($path);
    }
}
