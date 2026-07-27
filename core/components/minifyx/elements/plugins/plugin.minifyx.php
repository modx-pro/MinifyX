<?php

use MinifyX\Processor\HtmlMinifier;
use MinifyX\Processor\ImageRewriter;
use MinifyX\Processor\RegisteredAssetsProcessor;

switch ($modx->event->name) {
    case 'OnMODXInit':
        $file = $modx->getOption('minifyx_core_path', null, MODX_CORE_PATH) . 'components/minifyx/functions/function.php';
        if (file_exists($file)) {
            include_once $file;
        }
        break;

    case 'OnSiteRefresh':
        /** @var MinifyX $MinifyX */
        if ($MinifyX = $modx->getService('minifyx', 'MinifyX', MODX_CORE_PATH . 'components/minifyx/model/minifyx/')) {
            if ($MinifyX->clearCache()) {
                $modx->log(modX::LOG_LEVEL_INFO, $modx->lexicon('refresh_default') . ': MinifyX');
            }
        }
        break;

    case 'OnWebPagePrerender':
        $processRegistered = (bool) $modx->getOption('minifyx_process_registered', null, false, true);
        $processImages = (bool) $modx->getOption('minifyx_process_images', null, false, true);
        $minifyHtml = (bool) $modx->getOption('minifyx_minifyHtml', null, false);

        if (!$processRegistered && !$processImages && !$minifyHtml) {
            break;
        }

        $time = microtime(true);
        $autoload = MODX_CORE_PATH . 'components/minifyx/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }

        if ($processRegistered) {
            $current = [
                'head' => $modx->sjscripts ?? [],
                'body' => $modx->jscripts ?? [],
            ];
            $included = $excluded = $prepared = $raw = [
                'head' => ['css' => [], 'js' => [], 'html' => []],
                'body' => ['css' => [], 'js' => [], 'html' => []],
            ];
            $exclude = (string) $modx->getOption('minifyx_exclude_registered', null, '');
            $parser = new RegisteredAssetsProcessor();

            foreach ($current as $key => $value) {
                foreach ($parser->parseTags((array) $value) as $item) {
                    if ($item['kind'] === 'link' || ($item['kind'] === 'script' && isset($item['url']))) {
                        $url = (string) ($item['url'] ?? '');
                        $isCss = $parser->isCssUrl($url);
                        $isJs = $parser->isJsUrl($url);
                        $bucket = $isCss ? 'css' : ($isJs ? 'js' : null);
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

            $scriptProperties = [
                'cacheFolder' => $modx->getOption('minifyx_cacheFolder', null, '/assets/components/minifyx/cache/', true),
                'forceUpdate' => $modx->getOption('minifyx_forceUpdate', null, false, true),
                'minifyJs' => $modx->getOption('minifyx_minifyJs', null, false, true),
                'minifyCss' => $modx->getOption('minifyx_minifyCss', null, false, true),
                'jsFilename' => $modx->getOption('minifyx_jsFilename', null, 'all', true),
                'cssFilename' => $modx->getOption('minifyx_cssFilename', null, 'all', true),
            ];

            /** @var MinifyX $MinifyX */
            if (isset($modx->minifyx) && $modx->minifyx instanceof MinifyX) {
                $MinifyX = $modx->minifyx;
                $MinifyX->reset($scriptProperties);
            } else {
                $MinifyX = $modx->getService('minifyx', 'MinifyX', MODX_CORE_PATH . 'components/minifyx/model/minifyx/', $scriptProperties);
            }

            if (!$MinifyX->prepareCacheFolder()) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[MinifyX] Could not create cache dir "' . ($MinifyX->config['cacheFolderPath'] ?? '') . '"');
                break;
            }

            $tmpDir = $MinifyX->getTmpDir() . 'resources/' . $modx->resource->id . '/';
            foreach ($raw as $key => $value) {
                foreach ($value as $type => $rows) {
                    $processRaw = ($type === 'css' && $modx->getOption('minifyx_processRawCss', null, false, true))
                        || ($type === 'js' && $modx->getOption('minifyx_processRawJs', null, false, true));
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
                        $MinifyX->makeDir($tmpDir);
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

            foreach ($included as $key => $value) {
                foreach ($value as $type => $items) {
                    if ($items === []) {
                        continue;
                    }
                    $files = [];
                    foreach ($items as $item) {
                        $files[] = is_array($item) ? (string) ($item['url'] ?? '') : (string) $item;
                    }
                    $files = array_values(array_filter($files));
                    if ($files === []) {
                        continue;
                    }

                    $result = $MinifyX->processFiles($files, $type);
                    if ($result !== null && $result['filename'] !== '') {
                        $prepared[$key][$type][] = [
                            'url' => $result['url'],
                            'attributes' => $parser->bundleAttributes($items),
                            'kind' => $type === 'css' ? 'link' : 'script',
                        ];
                    }
                }
            }

            $final = ['head' => [], 'body' => []];
            foreach (['head', 'body'] as $section) {
                foreach (['css', 'js'] as $type) {
                    foreach ($excluded[$section][$type] as $item) {
                        if (is_array($item)) {
                            $final[$section][] = $item['raw'];
                        } else {
                            $final[$section][] = (string) $item;
                        }
                    }
                    foreach ($prepared[$section][$type] as $item) {
                        $final[$section][] = $parser->buildTag(
                            (string) $item['kind'],
                            (string) $item['url'],
                            (array) ($item['attributes'] ?? [])
                        );
                    }
                    foreach ($raw[$section][$type] as $item) {
                        $final[$section][] = $item;
                    }
                }
                foreach ($excluded[$section]['html'] as $html) {
                    $final[$section][] = $html;
                }
            }

            $startup = method_exists($modx, 'getRegisteredClientStartupScripts')
                ? $modx->getRegisteredClientStartupScripts()
                : '';
            $scripts = method_exists($modx, 'getRegisteredClientScripts')
                ? $modx->getRegisteredClientScripts()
                : '';

            $modx->resource->_output = str_replace(
                [$startup . "\n</head>", $scripts . "\n</body>"],
                [implode("\n", $final['head']) . "\n</head>", implode("\n", $final['body']) . "\n</body>"],
                $modx->resource->_output
            );
        }

        if ($processImages) {
            if (!$modx->getService('minifyx', 'MinifyX', MODX_CORE_PATH . 'components/minifyx/model/minifyx/')) {
                break;
            }

            $connector = (string) $modx->getOption('minifyx_connector', null, '/assets/components/minifyx/munee.php', true);
            $exclude = (string) $modx->getOption('minifyx_exclude_images', null, '#(thumb|/\d+x\d+/)#i');
            $default = (string) $modx->getOption('minifyx_images_filters', null, '', true);
            $signingKey = (string) $modx->getOption('minifyx_image_signing_key', null, '', true);
            $rewriter = new ImageRewriter(
                $connector,
                (string) $modx->getOption('site_url'),
                $default,
                $exclude,
                $signingKey
            );
            $modx->resource->_output = $rewriter->rewrite((string) $modx->resource->_output);
        }

        if ($minifyHtml) {
            $minifier = new HtmlMinifier();
            $modx->resource->_output = $minifier->minify((string) $modx->resource->_output);
        }

        $modx->log(
            modX::LOG_LEVEL_INFO,
            '[MinifyX] Total time for page "' . $modx->resource->id . '" = ' . (microtime(true) - $time)
        );
        break;
}
