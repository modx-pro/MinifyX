<?php

use MinifyX\Integration\Modx3ServiceResolver;
use MinifyX\Model\MinifyX;
use MinifyX\Processor\HtmlMinifier;
use MinifyX\Processor\ImageRewriter;
use MinifyX\Processor\RegisteredAssetOutputInjector;
use MinifyX\Processor\RegisteredAssetPageProcessor;
use MinifyX\Support\UrlHelper;

$autoload = MODX_CORE_PATH . 'components/minifyx/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

switch ($modx->event->name) {
    case 'OnMODXInit':
        $file = $modx->getOption('minifyx_core_path', null, MODX_CORE_PATH)
            . 'components/minifyx/functions/function.php';
        if (file_exists($file)) {
            include_once $file;
        }
        break;

    case 'OnSiteRefresh':
        $MinifyX = Modx3ServiceResolver::resolve($modx);
        if ($MinifyX instanceof MinifyX && $MinifyX->clearCache()) {
            $modx->log($modx::LOG_LEVEL_INFO, $modx->lexicon('refresh_default') . ': MinifyX');
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
        $MinifyX = null;
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
                'bundleJsModules' => $modx->getOption('minifyx_bundleJsModules', null, false, true),
                'sourceMaps' => $modx->getOption('minifyx_sourceMaps', null, false, true),
                'jsMangler' => $modx->getOption('minifyx_jsMangler', null, 'terser', true),
                'jsManglerPath' => $modx->getOption('minifyx_jsManglerPath', null, '', true),
                'esbuildPath' => $modx->getOption('minifyx_esbuildPath', null, '', true),
                'jsManglerMaxInputBytes' => $modx->getOption(
                    'minifyx_jsManglerMaxInputBytes',
                    null,
                    5000000,
                    true
                ),
                'jsFilename' => $modx->getOption('minifyx_jsFilename', null, 'all', true),
                'cssFilename' => $modx->getOption('minifyx_cssFilename', null, 'all', true),
            ];

            $MinifyX = Modx3ServiceResolver::resolve($modx, $scriptProperties);

            if (!$MinifyX instanceof MinifyX) {
                $modx->log($modx::LOG_LEVEL_ERROR, '[MinifyX] Service could not be loaded.');
                break;
            }

            if (!$MinifyX->prepareCacheFolder()) {
                $cacheDir = (string) ($MinifyX->config['cacheFolderPath'] ?? '');
                $modx->log($modx::LOG_LEVEL_ERROR, '[MinifyX] Could not create cache dir "' . $cacheDir . '"');
                break;
            }

            $final = (new RegisteredAssetPageProcessor())->process($modx, $MinifyX);

            $startup = method_exists($modx, 'getRegisteredClientStartupScripts')
                ? $modx->getRegisteredClientStartupScripts()
                : '';
            $scripts = method_exists($modx, 'getRegisteredClientScripts')
                ? $modx->getRegisteredClientScripts()
                : '';

            $modx->resource->_output = (new RegisteredAssetOutputInjector())->inject(
                (string) $modx->resource->_output,
                $startup,
                $scripts,
                $final['head'],
                $final['body']
            );
        }

        if ($processImages) {
            if (!$MinifyX instanceof MinifyX) {
                $MinifyX = Modx3ServiceResolver::resolve($modx);
            }
            if (!$MinifyX instanceof MinifyX) {
                break;
            }

            $isHttps = UrlHelper::detectHttpsFromServer();
            $siteUrl = UrlHelper::schemeAwareSiteUrl(
                (string) $modx->getOption('site_url'),
                $isHttps
            );
            $connector = UrlHelper::absolutize(
                (string) $modx->getOption(
                    'minifyx_connector',
                    null,
                    '/assets/components/minifyx/minifyx.php',
                    true
                ),
                $siteUrl,
                $isHttps
            );
            $exclude = (string) $modx->getOption('minifyx_exclude_images', null, '#(thumb|/\d+x\d+/)#i');
            $default = (string) $modx->getOption('minifyx_images_filters', null, '', true);
            $signingKeys = (string) $modx->getOption('minifyx_image_signing_keys', null, '', true);
            if (trim($signingKeys) === '') {
                $signingKeys = (string) $modx->getOption('minifyx_image_signing_key', null, '', true);
            }
            $rewriter = new ImageRewriter(
                $connector,
                $siteUrl,
                $default,
                $exclude,
                $signingKeys
            );
            $modx->resource->_output = $rewriter->rewrite((string) $modx->resource->_output);
        }

        if ($minifyHtml) {
            $minifier = new HtmlMinifier();
            $modx->resource->_output = $minifier->minify((string) $modx->resource->_output);
        }

        $modx->log(
            $modx::LOG_LEVEL_INFO,
            '[MinifyX] Total time for page "' . $modx->resource->id . '" = ' . (microtime(true) - $time)
        );
        break;
}
