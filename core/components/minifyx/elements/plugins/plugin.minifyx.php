<?php

use MinifyX\Model\MinifyX;
use MinifyX\Processor\HtmlMinifier;
use MinifyX\Processor\ImageRewriter;
use MinifyX\Processor\RegisteredAssetPageProcessor;

$minifyxModelPath = MODX_CORE_PATH . 'components/minifyx/model/minifyx/';

switch ($modx->event->name) {
    case 'OnMODXInit':
        $file = $modx->getOption('minifyx_core_path', null, MODX_CORE_PATH)
            . 'components/minifyx/functions/function.php';
        if (file_exists($file)) {
            include_once $file;
        }
        break;

    case 'OnSiteRefresh':
        /** @var MinifyX $MinifyX */
        if ($MinifyX = $modx->getService('minifyx', MinifyX::class, $minifyxModelPath)) {
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
            $scriptProperties = [
                'cacheFolder' => $modx->getOption(
                    'minifyx_cacheFolder',
                    null,
                    '/assets/components/minifyx/cache/',
                    true
                ),
                'forceUpdate' => $modx->getOption('minifyx_forceUpdate', null, false, true),
                'minifyJs' => $modx->getOption('minifyx_minifyJs', null, false, true),
                'minifyCss' => $modx->getOption('minifyx_minifyCss', null, false, true),
                'mangleJs' => $modx->getOption('minifyx_mangleJs', null, false, true),
                'jsMangler' => $modx->getOption('minifyx_jsMangler', null, 'terser', true),
                'jsManglerPath' => $modx->getOption('minifyx_jsManglerPath', null, '', true),
                'jsFilename' => $modx->getOption('minifyx_jsFilename', null, 'all', true),
                'cssFilename' => $modx->getOption('minifyx_cssFilename', null, 'all', true),
            ];

            /** @var MinifyX $MinifyX */
            if (isset($modx->minifyx) && $modx->minifyx instanceof MinifyX) {
                $MinifyX = $modx->minifyx;
                $MinifyX->reset($scriptProperties);
            } else {
                $MinifyX = $modx->getService(
                    'minifyx',
                    MinifyX::class,
                    $minifyxModelPath,
                    $scriptProperties
                );
            }

            if (!$MinifyX instanceof MinifyX) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[MinifyX] Service could not be loaded.');
                break;
            }

            if (!$MinifyX->prepareCacheFolder()) {
                $cacheDir = (string) ($MinifyX->config['cacheFolderPath'] ?? '');
                $modx->log(modX::LOG_LEVEL_ERROR, '[MinifyX] Could not create cache dir "' . $cacheDir . '"');
                break;
            }

            $final = (new RegisteredAssetPageProcessor())->process($modx, $MinifyX);

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
            if (!$modx->getService('minifyx', MinifyX::class, $minifyxModelPath)) {
                break;
            }

            $connector = (string) $modx->getOption(
                'minifyx_connector',
                null,
                '/assets/components/minifyx/munee.php',
                true
            );
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
