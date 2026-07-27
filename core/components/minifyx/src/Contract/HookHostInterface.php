<?php

declare(strict_types=1);

namespace MinifyX\Contract;

/**
 * Host for file hooks that mutate compiled asset content.
 */
interface HookHostInterface
{
    public function getContent(): string;

    public function setContent(string $content): void;

    public function getFilename(): string;

    public function setFilename(string $filename): void;

    public function isCss(?string $file = null): bool;

    public function isJs(?string $file = null): bool;
}
