<?php

declare(strict_types=1);

namespace MinifyX\Processor;

use MinifyX\Adapter\LegacyModxAdapter;
use MinifyX\Html\AssetTag;
use MinifyX\Html\AssetTagParser;
use MinifyX\Html\AssetTagRenderer;
use MinifyX\Html\BundlePlanner;
use MinifyX\Model\MinifyX;

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
            foreach ($this->parser->parseTags((array) $value) as $item) {
                if ($item['kind'] === 'link' || ($item['kind'] === 'script' && isset($item['url']))) {
                    $url = (string) ($item['url'] ?? '');
                    $bucket = $this->parser->isCssUrl($url) ? 'css' : ($this->parser->isJsUrl($url) ? 'js' : null);
                    if ($bucket === null) {
                        $excluded[$key]['html'][] = $item['raw'];
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
                    $raw[$key]['js'][] = $item['raw'];
                    continue;
                }
                if ($item['kind'] === 'raw-css') {
                    $raw[$key]['css'][] = $item['raw'];
                    continue;
                }

                $excluded[$key]['html'][] = $item['raw'];
            }
        }

        $this->processRawBlocks($adapter, $modx, $minifyX, $raw, $included);

        $renderConfig = [
            'preloadCss' => (bool) $adapter->getOption('minifyx_preloadCss', null, false, true),
            'preloadJs' => (bool) $adapter->getOption('minifyx_preloadJs', null, false, true),
            'cssPreloadTpl' => (string) $adapter->getOption('minifyx_cssPreloadTpl', null, '', true),
            'jsPreloadTpl' => (string) $adapter->getOption('minifyx_jsPreloadTpl', null, '', true),
        ];

        foreach ($included as $key => $value) {
            foreach (['css', 'js'] as $type) {
                $assetTags = [];
                foreach ($value[$type] as $item) {
                    $tag = $this->parser->toAssetTag($item);
                    if ($tag !== null) {
                        $assetTags[] = $tag;
                    }
                }

                foreach ($this->planner->groupCompatible($assetTags, $type) as $group) {
                    $files = [];
                    foreach ($group as $tag) {
                        $files[] = $tag->getUrl();
                    }
                    $files = array_values(array_filter($files));
                    if ($files === []) {
                        continue;
                    }

                    $result = $minifyX->processFiles($files, $type);
                    if ($result === null || $result['filename'] === '') {
                        continue;
                    }

                    $representative = $group[0];
                    $bundleTag = new AssetTag(
                        $type === 'css' ? AssetTag::KIND_LINK : AssetTag::KIND_SCRIPT,
                        (string) $result['url'],
                        $this->planner->bundleAttributes($group),
                        ''
                    );
                    $rendered = $this->renderer->renderBundle(
                        $type,
                        (string) $result['url'],
                        $bundleTag,
                        $renderConfig
                    );
                    $prepared[$key][$type][] = [
                        'preload' => $rendered['preload'],
                        'tag' => $rendered['tag'],
                        'representative' => $representative,
                    ];
                }
            }
        }

        $final = ['head' => [], 'body' => []];
        foreach (['head', 'body'] as $section) {
            foreach (['css', 'js'] as $type) {
                foreach ($excluded[$section][$type] as $item) {
                    $final[$section][] = is_array($item) ? (string) ($item['raw'] ?? '') : (string) $item;
                }
                foreach ($prepared[$section][$type] as $item) {
                    if (!empty($item['preload'])) {
                        $final[$section][] = (string) $item['preload'];
                    }
                    $final[$section][] = (string) $item['tag'];
                }
                foreach ($raw[$section][$type] as $item) {
                    $final[$section][] = $item;
                }
            }
            foreach ($excluded[$section]['html'] as $html) {
                $final[$section][] = $html;
            }
        }

        return $final;
    }

    /**
     * @param array<string, array<string, list<string>>> $raw
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
        $tmpDir = $minifyX->getTmpDir() . 'resources/' . $resourceId . '/';

        foreach ($raw as $key => $value) {
            foreach ($value as $type => $rows) {
                $processRaw = ($type === 'css' && $adapter->getOption('minifyx_processRawCss', null, false, true))
                    || ($type === 'js' && $adapter->getOption('minifyx_processRawJs', null, false, true));
                if (!$processRaw || $rows === []) {
                    continue;
                }

                $tmp = '';
                foreach ($rows as $text) {
                    $text = preg_replace('#^<(script|style)\b[^>]*>#i', '', $text) ?? $text;
                    $text = preg_replace('#</(script|style)>$#i', '', $text) ?? $text;
                    $tmp .= $text;
                }
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
                    'attributes' => [],
                ];
                $raw[$key][$type] = [];
            }
        }
    }
}
