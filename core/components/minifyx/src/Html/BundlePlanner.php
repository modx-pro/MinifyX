<?php

declare(strict_types=1);

namespace MinifyX\Html;

final class BundlePlanner
{
    /**
     * @param list<AssetTag> $tags
     * @return list<list<AssetTag>>
     */
    public function groupCompatible(array $tags, string $assetType): array
    {
        $groups = [];
        $order = [];
        foreach ($tags as $tag) {
            $key = $tag->getBundleKey($assetType);
            if (!isset($groups[$key])) {
                $groups[$key] = [];
                $order[] = $key;
            }
            $groups[$key][] = $tag;
        }

        $result = [];
        foreach ($order as $key) {
            $result[] = $groups[$key];
        }

        return $result;
    }

    /**
     * @param list<AssetTag> $tags
     * @return array<string, string|true>
     */
    public function bundleAttributes(array $tags): array
    {
        if ($tags === []) {
            return [];
        }

        $first = $tags[0]->getAttributes();
        unset($first['href'], $first['src'], $first['integrity'], $first['crossorigin']);

        return $first;
    }
}
