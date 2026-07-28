<?php

declare(strict_types=1);

namespace MinifyX\Contract;

interface CacheStoreInterface
{
    public function getDirectory(): string;

    public function prepare(): bool;

    public function exists(string $filename): bool;

    public function path(string $filename): string;

    /**
     * Atomically write content to a cache file.
     */
    public function write(string $filename, string $content, bool $force = false): bool;

    /**
     * @param list<string> $sourcePaths
     * @param array<string, mixed> $options
     */
    public function fingerprint(array $sourcePaths, array $options = []): string;

    public function lookupByFingerprint(string $basename, string $fingerprint, string $extension): ?string;

    /**
     * @param list<string> $trackedFiles
     */
    public function clear(bool $forceDelete = false, array $trackedFiles = []): void;

    public function removeDirectory(string $dir): bool;

    public function makeDirectory(string $path): bool;
}
