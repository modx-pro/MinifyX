<?php

declare(strict_types=1);

namespace MinifyX\Application;

final class BuildSignature
{
    /** @var list<string> */
    private array $sourcePaths;

    /** @var array<string, mixed> */
    private array $options;

    /** @var list<string> */
    private array $hooks;

    /**
     * @param list<string> $sourcePaths
     * @param array<string, mixed> $options
     * @param list<string> $hooks
     */
    public function __construct(array $sourcePaths, array $options = [], array $hooks = [])
    {
        $this->sourcePaths = $sourcePaths;
        $this->options = $options;
        $this->hooks = $hooks;
    }

    public function hash(int $length = 10): string
    {
        $parts = [$this->options, $this->hooks];
        foreach ($this->sourcePaths as $path) {
            $realpath = realpath($path) ?: $path;
            $mtime = is_file($realpath) ? (string) filemtime($realpath) : '0';
            $size = is_file($realpath) ? (string) filesize($realpath) : '0';
            $parts[] = $realpath . '|' . $mtime . '|' . $size;
        }

        return substr(hash('sha1', serialize($parts)), 0, $length);
    }
}
