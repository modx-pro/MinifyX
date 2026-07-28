<?php

declare(strict_types=1);

namespace MinifyX\Processor;

/**
 * Single-pass registered asset tag parser that preserves unknown attributes and order.
 */
final class RegisteredAssetsProcessor
{
    /**
     * @param list<string> $tags
     * @return list<array{
     *   raw: string,
     *   kind: 'link'|'script'|'style'|'html'|'raw-js'|'raw-css',
     *   url?: string,
     *   attributes?: array<string, string|true>
     * }>
     */
    public function parseTags(array $tags): array
    {
        $parsed = [];
        foreach ($tags as $tag) {
            $tag = (string) $tag;
            if (preg_match('#<link\b([^>]*)>#i', $tag, $m) && stripos($tag, 'stylesheet') !== false) {
                $attrs = $this->parseAttributes($m[1]);
                $href = isset($attrs['href']) && is_string($attrs['href']) ? $attrs['href'] : '';
                $parsed[] = [
                    'raw' => $tag,
                    'kind' => 'link',
                    'url' => $href,
                    'attributes' => $attrs,
                ];
                continue;
            }

            if (preg_match('#<script\b([^>]*)>(.*?)</script>#is', $tag, $m)) {
                $attrs = $this->parseAttributes($m[1]);
                if (isset($attrs['src']) && is_string($attrs['src']) && $attrs['src'] !== '') {
                    $parsed[] = [
                        'raw' => $tag,
                        'kind' => 'script',
                        'url' => $attrs['src'],
                        'attributes' => $attrs,
                    ];
                } else {
                    $parsed[] = [
                        'raw' => $tag,
                        'kind' => 'raw-js',
                        'attributes' => $attrs,
                    ];
                }
                continue;
            }

            if (preg_match('#<style\b([^>]*)>.*?</style>#is', $tag)) {
                $parsed[] = [
                    'raw' => $tag,
                    'kind' => 'raw-css',
                ];
                continue;
            }

            $parsed[] = [
                'raw' => $tag,
                'kind' => 'html',
            ];
        }

        return $parsed;
    }

    /**
     * Rebuild a tag, replacing URL while keeping all other attributes.
     *
     * @param array<string, string|true> $attributes
     */
    public function buildTag(string $kind, string $url, array $attributes = []): string
    {
        if ($kind === 'link') {
            $attributes['rel'] = $attributes['rel'] ?? 'stylesheet';
            $attributes['href'] = $url;
            if (!isset($attributes['type'])) {
                $attributes['type'] = 'text/css';
            }

            return '<link' . $this->stringifyAttributes($attributes) . '>';
        }

        $attributes['src'] = $url;

        return '<script' . $this->stringifyAttributes($attributes) . '></script>';
    }

    public function isCssUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path)) {
            $path = $url;
        }

        return (bool) preg_match('/\.css$/i', $path);
    }

    public function isJsUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path)) {
            $path = $url;
        }

        return (bool) preg_match('/\.js$/i', $path);
    }

    /**
     * Safe attributes for a combined bundle: first file only, drop SRI and URL attrs.
     *
     * @param list<array{attributes?: array<string, string|true>}> $items
     * @return array<string, string|true>
     */
    public function bundleAttributes(array $items): array
    {
        $first = $items[0]['attributes'] ?? [];
        if (!is_array($first)) {
            return [];
        }

        unset($first['href'], $first['src'], $first['integrity'], $first['crossorigin']);

        return $first;
    }

    /**
     * @return array<string, string|true>
     */
    private function parseAttributes(string $attributeString): array
    {
        $attrs = [];
        $pattern = '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/';
        if (preg_match_all($pattern, $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = strtolower($match[1]);
                if (array_key_exists(2, $match)) {
                    $attrs[$name] = $match[2];
                } elseif (array_key_exists(3, $match)) {
                    $attrs[$name] = $match[3];
                } elseif (array_key_exists(4, $match)) {
                    $attrs[$name] = $match[4];
                } else {
                    $attrs[$name] = true;
                }
            }
        }

        return $attrs;
    }

    /**
     * @param array<string, string|true> $attributes
     */
    private function stringifyAttributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $name => $value) {
            if ($value === true) {
                $parts[] = $name;
            } else {
                $parts[] = $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
        }

        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }
}
