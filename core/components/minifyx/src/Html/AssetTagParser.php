<?php

declare(strict_types=1);

namespace MinifyX\Html;

use MinifyX\Processor\RegisteredAssetsProcessor;

final class AssetTagParser
{
    private RegisteredAssetsProcessor $processor;

    public function __construct(?RegisteredAssetsProcessor $processor = null)
    {
        $this->processor = $processor ?? new RegisteredAssetsProcessor();
    }

    /**
     * @param list<string> $tags
     * @return list<array{
     *   raw: string,
     *   kind: string,
     *   url?: string,
     *   attributes?: array<string, string|true>,
     *   assetTag?: AssetTag|null
     * }>
     */
    public function parseTags(array $tags): array
    {
        $parsed = [];
        foreach ($this->processor->parseTags($tags) as $item) {
            $item['assetTag'] = $this->toAssetTag($item);
            $parsed[] = $item;
        }

        return $parsed;
    }

    /**
     * @param array{kind: string, url?: string, attributes?: array<string, string|true>, raw?: string} $item
     */
    public function toAssetTag(array $item): ?AssetTag
    {
        if ($item['kind'] === 'link') {
            return new AssetTag(
                AssetTag::KIND_LINK,
                (string) ($item['url'] ?? ''),
                (array) ($item['attributes'] ?? []),
                (string) ($item['raw'] ?? '')
            );
        }

        if ($item['kind'] === 'script' && isset($item['url']) && $item['url'] !== '') {
            return new AssetTag(
                AssetTag::KIND_SCRIPT,
                (string) $item['url'],
                (array) ($item['attributes'] ?? []),
                (string) ($item['raw'] ?? '')
            );
        }

        return null;
    }

    public function isCssUrl(string $url): bool
    {
        return $this->processor->isCssUrl($url);
    }

    public function isJsUrl(string $url): bool
    {
        return $this->processor->isJsUrl($url);
    }
}
