<?php

declare(strict_types=1);

namespace MinifyX\Contract;

interface ModxAdapterInterface
{
    /**
     * @param mixed $options
     * @param mixed $default
     * @return mixed
     */
    public function getOption(string $key, $options = null, $default = null, bool $skipEmpty = false);

    public function getContextKey(): string;

    public function log(int $level, string $message): void;

    public function processElementTags(string $content): string;

    public function setPlaceholder(string $key, string $value): void;

    public function regClientCSS(string $tag): void;

    public function regClientScript(string $tag): void;

    public function regClientStartupScript(string $tag): void;

    /**
     * @param array<string, mixed> $properties
     * @return mixed
     */
    public function runSnippet(string $name, array $properties = []);

    public function getResourceId(): ?int;

    public function getSiteUrl(): string;

    public function getBasePath(): string;

    public function getCorePath(): string;

    public function getAssetsPath(): string;
}
