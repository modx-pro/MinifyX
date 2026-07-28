<?php

declare(strict_types=1);

namespace MinifyX\Html;

final class AssetTag
{
    public const KIND_LINK = 'link';
    public const KIND_SCRIPT = 'script';

    private string $kind;
    private string $url;

    /** @var array<string, string|true> */
    private array $attributes;

    private string $raw;

    /**
     * @param array<string, string|true> $attributes
     */
    public function __construct(string $kind, string $url, array $attributes = [], string $raw = '')
    {
        $this->kind = $kind;
        $this->url = $url;
        $this->attributes = $attributes;
        $this->raw = $raw;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /** @return array<string, string|true> */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getRaw(): string
    {
        return $this->raw;
    }

    public function isModuleScript(): bool
    {
        return isset($this->attributes['type']) && strtolower((string) $this->attributes['type']) === 'module';
    }

    public function getMedia(): string
    {
        return isset($this->attributes['media']) ? (string) $this->attributes['media'] : '';
    }

    public function getBundleKey(string $assetType): string
    {
        if ($assetType === 'css') {
            return 'css|' . $this->getMedia();
        }

        $parts = ['js'];
        if ($this->isModuleScript()) {
            $parts[] = 'module';
        }
        foreach (['defer', 'async', 'nomodule', 'crossorigin', 'referrerpolicy'] as $name) {
            if (isset($this->attributes[$name])) {
                $parts[] = $name . ':' . (string) $this->attributes[$name];
            }
        }

        return implode('|', $parts);
    }
}
