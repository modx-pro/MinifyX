<?php

declare(strict_types=1);

namespace MinifyX\Processor;

use MinifyX\Adapter\LegacyModxAdapter;
use MinifyX\Html\AssetTag;
use MinifyX\Html\AssetTagParser;
use MinifyX\Html\AssetTagRenderer;
use MinifyX\Html\BundlePlanner;
use MinifyX\Model\MinifyX;
use MinifyX\Support\SubresourceIntegrity;

/**
 * Orchestrates registered asset bundling for OnWebPagePrerender.
 */
final class RegisteredAssetPageProcessor
{
    private AssetTagParser $parser;
    private BundlePlanner $planner;
    private AssetTagRenderer $renderer;

    public function __construct(
        ?AssetTagParser $parser = null,
        ?BundlePlanner $planner = null,
        ?AssetTagRenderer $renderer = null
    ) {
        $this->parser = $parser ?? new AssetTagParser();
        $this->planner = $planner ?? new BundlePlanner();
        $this->renderer = $renderer ?? new AssetTagRenderer();
    }

    /**
     * @return array{head: list<string>, body: list<string>}
     */
    public function process(object $modx, MinifyX $minifyX): array
    {
        $adapter = new LegacyModxAdapter($modx);
        $current = [
            'head' => $modx->sjscripts ?? [],
            'body' => $modx->jscripts ?? [],
        ];
        $included = $excluded = $prepared = $raw = [
            'head' => ['css' => [], 'js' => [], 'html' => []],
            'body' => ['css' => [], 'js' => [], 'html' => []],
        ];
        $exclude = (string) $adapter->getOption('minifyx_exclude_registered', null, '');

        foreach ($current as $key => $value) {
            $position = 0;
            $tags = is_array($value)
                ? array_values(array_filter($value, 'is_string'))
                : [];
            foreach ($this->parser->parseTags($tags) as $item) {
                $item['_order'] = $position;
                $item['_orderEnd'] = $position;
                $position++;
                if ($item['kind'] === 'link' || ($item['kind'] === 'script' && isset($item['url']))) {
                    $url = (string) ($item['url'] ?? '');
                    $bucket = $this->parser->isCssUrl($url) ? 'css' : ($this->parser->isJsUrl($url) ? 'js' : null);
                    if ($bucket === null) {
                        $excluded[$key]['html'][] = $item;
                        continue;
                    }
                    if ($exclude !== '' && @preg_match($exclude, $url) === 1) {
                        $excluded[$key][$bucket][] = $item;
                    } else {
                        $included[$key][$bucket][] = $item;
                    }
                    continue;
                }

                if ($item['kind'] === 'raw-js') {
                    $raw[$key]['js'][] = $item;
                    continue;
                }
                if ($item['kind'] === 'raw-css') {
                    $raw[$key]['css'][] = $item;
                    continue;
                }

                $excluded[$key]['html'][] = $item;
            }
        }

        $this->processRawBlocks($adapter, $modx, $minifyX, $raw, $included);

        $renderConfig = [
            'preloadCss' => (bool) $adapter->getOption('minifyx_preloadCss', null, false, true),
            'preloadJs' => (bool) $adapter->getOption('minifyx_preloadJs', null, false, true),
            'cssPreloadTpl' => (string) $adapter->getOption('minifyx_cssPreloadTpl', null, '', true),
            'jsPreloadTpl' => (string) $adapter->getOption('minifyx_jsPreloadTpl', null, '', true),
            'bundleIntegrity' => (bool) $adapter->getOption('minifyx_bundleIntegrity', null, false, true),
            'debug' => (bool) $adapter->getOption('minifyx_debug', null, false, true),
        ];

        foreach ($included as $key => $value) {
            foreach (['css', 'js'] as $type) {
                foreach ($this->groupIncludedItems($value[$type] ?? [], $type) as $groupData) {
                    $group = $groupData['tags'];
                    $isModuleGroup = $type === 'js' && $group !== [] && $group[0]->isModuleScript();
                    $bundleModules = (bool) $adapter->getOption(
                        'minifyx_bundleJsModules',
                        null,
                        false,
                        true
                    );
                    if ($isModuleGroup && !$bundleModules) {
                        foreach ($group as $tag) {
                            $excluded[$key][$type][] = [
                                'raw' => $tag->getRaw(),
                                '_order' => $groupData['order'],
                            ];
                        }
                        continue;
                    }
                    $files = [];
                    foreach ($group as $tag) {
                        $files[] = $tag->getUrl();
                    }
                    $files = array_values(array_filter($files));
                    if ($files === []) {
                        continue;
                    }

                    $previousModuleConfig = [
                        'bundleJsModules' => $minifyX->config['bundleJsModules'] ?? false,
                        'jsModule' => $minifyX->config['jsModule'] ?? false,
                        'jsMangler' => $minifyX->config['jsMangler'] ?? 'terser',
                        'jsManglerPath' => $minifyX->config['jsManglerPath'] ?? '',
                    ];
                    if ($isModuleGroup) {
                        $minifyX->setConfig([
                            'bundleJsModules' => true,
                            'jsModule' => true,
                            'jsMangler' => 'esbuild',
                            'jsManglerPath' => $minifyX->config['esbuildPath'] ?? '',
                        ]);
                    }
                    try {
                        $result = $minifyX->processFiles($files, $type);
                    } finally {
                        if ($isModuleGroup) {
                            $minifyX->setConfig($previousModuleConfig);
                        }
                    }
                    if ($result === null || $result['filename'] === '') {
                        foreach ($group as $tag) {
                            $excluded[$key][$type][] = [
                                'raw' => $tag->getRaw(),
                                '_order' => $groupData['order'],
                            ];
                        }
                        continue;
                    }

                    $bundleAttributes = $this->planner->bundleAttributes($group);
                    if (!empty($renderConfig['bundleIntegrity']) && $result['content'] !== '') {
                        $bundleAttributes['integrity'] = SubresourceIntegrity::sha384((string) $result['content']);
                        $bundleAttributes['crossorigin'] = 'anonymous';
                    }
                    $bundleTag = new AssetTag(
                        $type === 'css' ? AssetTag::KIND_LINK : AssetTag::KIND_SCRIPT,
                        (string) $result['url'],
                        $bundleAttributes,
                        ''
                    );
                    $rendered = $this->renderer->renderBundle(
                        $type,
                        (string) $result['url'],
                        $bundleTag,
                        $renderConfig
                    );
                    $debug = '';
                    if ($renderConfig['debug']) {
                        $debug = sprintf(
                            '<!-- MinifyX type=%s sources=%d cache=%s bundle=%s -->',
                            $type,
                            count($files),
                            !empty($result['fromCache']) ? 'hit' : 'miss',
                            htmlspecialchars(basename((string) $result['filename']), ENT_QUOTES, 'UTF-8')
                        );
                    }
                    $prepared[$key][$type][] = [
                        'preload' => $rendered['preload'],
                        'tag' => $rendered['tag'],
                        'debug' => $debug,
                        '_order' => $groupData['order'],
                    ];
                }
            }
        }

        $final = ['head' => [], 'body' => []];
        foreach (['head', 'body'] as $section) {
            $ordered = [];
            $sequence = 0;
            foreach (['css', 'js'] as $type) {
                foreach ($excluded[$section][$type] ?? [] as $item) {
                    $ordered[] = [
                        'order' => (int) ($item['_order'] ?? PHP_INT_MAX),
                        'sequence' => $sequence++,
                        'markup' => (string) ($item['raw'] ?? ''),
                    ];
                }
                foreach ($prepared[$section][$type] ?? [] as $item) {
                    $markup = [];
                    if (!empty($item['preload'])) {
                        $markup[] = (string) $item['preload'];
                    }
                    if (!empty($item['debug'])) {
                        $markup[] = (string) $item['debug'];
                    }
                    $markup[] = (string) $item['tag'];
                    $ordered[] = [
                        'order' => (int) ($item['_order'] ?? PHP_INT_MAX),
                        'sequence' => $sequence++,
                        'markup' => implode("\n", $markup),
                    ];
                }
                foreach ($raw[$section][$type] ?? [] as $item) {
                    $ordered[] = [
                        'order' => (int) ($item['_order'] ?? PHP_INT_MAX),
                        'sequence' => $sequence++,
                        'markup' => (string) ($item['raw'] ?? ''),
                    ];
                }
            }
            foreach ($excluded[$section]['html'] ?? [] as $item) {
                $ordered[] = [
                    'order' => (int) ($item['_order'] ?? PHP_INT_MAX),
                    'sequence' => $sequence++,
                    'markup' => (string) ($item['raw'] ?? ''),
                ];
            }
            usort($ordered, static function (array $left, array $right): int {
                return [$left['order'], $left['sequence']] <=> [$right['order'], $right['sequence']];
            });
            $final[$section] = array_values(array_map(static function (array $item): string {
                return $item['markup'];
            }, $ordered));
        }

        return $final;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array{tags: list<AssetTag>, order: int}>
     */
    private function groupIncludedItems(array $items, string $type): array
    {
        usort($items, static function (array $left, array $right): int {
            return (int) ($left['_order'] ?? 0) <=> (int) ($right['_order'] ?? 0);
        });

        $groups = [];
        $current = [];
        $currentOrder = 0;
        $lastOrder = null;
        $lastKey = null;
        foreach ($items as $item) {
            if (!isset($item['kind']) || !is_string($item['kind'])) {
                continue;
            }
            $tagData = ['kind' => $item['kind']];
            if (isset($item['url']) && is_string($item['url'])) {
                $tagData['url'] = $item['url'];
            }
            if (isset($item['raw']) && is_string($item['raw'])) {
                $tagData['raw'] = $item['raw'];
            }
            if (isset($item['attributes']) && is_array($item['attributes'])) {
                $attributes = [];
                foreach ($item['attributes'] as $name => $value) {
                    if (is_string($name) && (is_string($value) || $value === true)) {
                        $attributes[$name] = $value;
                    }
                }
                $tagData['attributes'] = $attributes;
            }
            $tag = $this->parser->toAssetTag($tagData);
            if ($tag === null) {
                continue;
            }
            $order = (int) ($item['_order'] ?? 0);
            $orderEnd = (int) ($item['_orderEnd'] ?? $order);
            $key = $tag->getBundleKey($type);
            if ($current !== [] && ($order !== $lastOrder + 1 || $key !== $lastKey)) {
                $groups[] = ['tags' => $current, 'order' => $currentOrder];
                $current = [];
            }
            if ($current === []) {
                $currentOrder = $order;
            }
            $current[] = $tag;
            $lastOrder = $orderEnd;
            $lastKey = $key;
        }
        if ($current !== []) {
            $groups[] = ['tags' => $current, 'order' => $currentOrder];
        }

        return $groups;
    }

    /**
     * @param array<string, array<string, list<array<string, mixed>>>> $raw
     * @param array<string, array<string, list<array<string, mixed>>>> $included
     */
    private function processRawBlocks(
        LegacyModxAdapter $adapter,
        object $modx,
        MinifyX $minifyX,
        array &$raw,
        array &$included
    ): void {
        $resourceId = isset($modx->resource->id) ? (int) $modx->resource->id : 0;
        $cachePath = (string) ($minifyX->config['cacheFolderPath'] ?? '');
        $tmpDir = rtrim($cachePath, '/\\') . '/raw/resources/' . $resourceId . '/';

        foreach ($raw as $key => $value) {
            foreach ($value as $type => $rows) {
                $processRaw = ($type === 'css' && $adapter->getOption('minifyx_processRawCss', null, false, true))
                    || ($type === 'js' && $adapter->getOption('minifyx_processRawJs', null, false, true));
                if (!$processRaw || $rows === []) {
                    continue;
                }

                /**
                 * @var list<array{
                 *   nonce: string,
                 *   content: string,
                 *   attributes: array<string, string|true>,
                 *   order: int,
                 *   orderEnd: int
                 * }> $nonceGroups
                 */
                $nonceGroups = [];
                foreach ($rows as $row) {
                    $text = (string) ($row['raw'] ?? '');
                    $attributes = isset($row['attributes']) && is_array($row['attributes'])
                        ? $row['attributes']
                        : [];
                    $text = preg_replace('#^<(script|style)\b[^>]*>#i', '', $text) ?? $text;
                    $text = preg_replace('#</(script|style)>$#i', '', $text) ?? $text;
                    $nonce = isset($attributes['nonce']) ? (string) $attributes['nonce'] : '';
                    $order = (int) ($row['_order'] ?? 0);
                    $orderEnd = (int) ($row['_orderEnd'] ?? $order);
                    $lastIndex = array_key_last($nonceGroups);
                    if (
                        $lastIndex === null
                        || $nonceGroups[$lastIndex]['nonce'] !== $nonce
                        || $order !== $nonceGroups[$lastIndex]['orderEnd'] + 1
                    ) {
                        $nonceGroups[] = [
                            'nonce' => $nonce,
                            'content' => '',
                            'attributes' => $nonce === '' ? [] : ['nonce' => $nonce],
                            'order' => $order,
                            'orderEnd' => $orderEnd,
                        ];
                        $lastIndex = array_key_last($nonceGroups);
                    }
                    if ($lastIndex === null) {
                        continue;
                    }
                    $nonceGroups[$lastIndex]['content'] .= $text;
                    $nonceGroups[$lastIndex]['orderEnd'] = $orderEnd;
                }

                foreach ($nonceGroups as $nonceGroup) {
                    $tmp = $nonceGroup['content'];
                    if ($tmp === '') {
                        continue;
                    }

                    $file = sha1($tmp) . '.' . $type;
                    $absolute = $tmpDir . $file;
                    if (!is_file($absolute)) {
                        $minifyX->makeDir($tmpDir);
                        $tmpFile = $absolute . '.' . bin2hex(random_bytes(4)) . '.tmp';
                        file_put_contents($tmpFile, $tmp, LOCK_EX);
                        rename($tmpFile, $absolute);
                    }

                    $included[$key][$type][] = [
                        'raw' => '',
                        'kind' => $type === 'css' ? 'link' : 'script',
                        'url' => $absolute,
                        'attributes' => $nonceGroup['attributes'],
                        '_order' => $nonceGroup['order'],
                        '_orderEnd' => $nonceGroup['orderEnd'],
                    ];
                }
                $raw[$key][$type] = [];
            }
        }
    }
}
