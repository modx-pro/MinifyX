<?php

declare(strict_types=1);

namespace MinifyX\Html;

final class PreloadHintPolicy
{
    public function shouldPreloadCss(bool $enabled): bool
    {
        return $enabled;
    }

    public function shouldPreloadJs(bool $enabled, AssetTag $tag): bool
    {
        return $enabled && !$tag->isModuleScript();
    }

    public function shouldModulePreload(bool $enabled, AssetTag $tag): bool
    {
        return $enabled && $tag->isModuleScript();
    }
}
