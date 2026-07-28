<?php

declare(strict_types=1);

namespace MinifyX\Html;

final class AssetTagRenderer
{
    private PreloadHintPolicy $preloadPolicy;

    public function __construct(?PreloadHintPolicy $preloadPolicy = null)
    {
        $this->preloadPolicy = $preloadPolicy ?? new PreloadHintPolicy();
    }

    /**
     * @param array<string, string|true> $attributes
     */
    public function renderExecutable(string $kind, string $url, array $attributes = []): string
    {
        if ($kind === AssetTag::KIND_LINK || $kind === 'css') {
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

    public function renderPreload(
        string $assetType,
        string $url,
        AssetTag $tag,
        bool $enabled,
        string $cssPreloadTpl = '',
        string $jsPreloadTpl = ''
    ): ?string {
        if ($assetType === 'css') {
            if (!$this->preloadPolicy->shouldPreloadCss($enabled)) {
                return null;
            }
            if ($cssPreloadTpl !== '') {
                return str_replace('[[+file]]', $url, $cssPreloadTpl);
            }

            return '<link rel="preload" href="' . htmlspecialchars($url, ENT_QUOTES) . '" as="style">';
        }

        if ($this->preloadPolicy->shouldModulePreload($enabled, $tag)) {
            $attrs = $this->crossOriginAttribute($tag);

            return '<link rel="modulepreload" href="' . htmlspecialchars($url, ENT_QUOTES) . '"' . $attrs . '>';
        }

        if (!$this->preloadPolicy->shouldPreloadJs($enabled, $tag)) {
            return null;
        }

        if ($jsPreloadTpl !== '') {
            return str_replace('[[+file]]', $url, $jsPreloadTpl);
        }

        $href = htmlspecialchars($url, ENT_QUOTES);
        $crossOrigin = $this->crossOriginAttribute($tag);

        return '<link rel="preload" href="' . $href . '" as="script"' . $crossOrigin . '>';
    }

    /**
     * @param array<string, mixed> $config
     * @return array{preload: ?string, tag: string}
     */
    public function renderBundle(string $assetType, string $url, AssetTag $tag, array $config): array
    {
        $attrs = $tag->getAttributes();
        unset($attrs['href'], $attrs['src']);
        if (empty($config['bundleIntegrity'])) {
            unset($attrs['integrity'], $attrs['crossorigin']);
        }

        $kind = $assetType === 'css' ? AssetTag::KIND_LINK : AssetTag::KIND_SCRIPT;
        $preloadEnabled = $assetType === 'css'
            ? !empty($config['preloadCss'])
            : !empty($config['preloadJs']);

        $executableTag = $this->renderExecutable($kind, $url, $attrs);
        if ($assetType === 'css' && !empty($config['cssTpl'])) {
            $executableTag = str_replace('[[+file]]', $url, (string) $config['cssTpl']);
        } elseif ($assetType === 'js' && !empty($config['jsTpl'])) {
            $executableTag = str_replace('[[+file]]', $url, (string) $config['jsTpl']);
        }

        return [
            'preload' => $this->renderPreload(
                $assetType,
                $url,
                $tag,
                $preloadEnabled,
                (string) ($config['cssPreloadTpl'] ?? ''),
                (string) ($config['jsPreloadTpl'] ?? '')
            ),
            'tag' => $executableTag,
        ];
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

    private function crossOriginAttribute(AssetTag $tag): string
    {
        if (!isset($tag->getAttributes()['crossorigin'])) {
            return '';
        }

        $value = $tag->getAttributes()['crossorigin'];

        return $value === true
            ? ' crossorigin'
            : ' crossorigin="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
    }
}
