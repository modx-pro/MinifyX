<?php

declare(strict_types=1);

namespace MinifyX;

use MinifyX\Adapter\LegacyModxAdapter;
use MinifyX\Cache\AtomicFilesystemCache;
use MinifyX\Contract\ModxAdapterInterface;
use MinifyX\Hook\HookRunner;
use MinifyX\Pipeline\AssetPipeline;
use MinifyX\Pipeline\FileNormalizer;
use MinifyX\Processor\CssJsProcessor;
use MinifyX\Processor\LegacyCoffeeCompiler;
use MinifyX\Processor\LessCompiler;
use MinifyX\Processor\ScssCompiler;

/**
 * Factory that wires the modern pure-PHP pipeline.
 */
final class ServiceFactory
{
    /**
     * @param array<string, array<int, string>> $groups
     */
    public static function create(ModxAdapterInterface $modx, Config $config, array $groups = []): AssetPipeline
    {
        $cacheDir = (string) $config->get('cacheFolderPath', $config->get('cacheFolder'));
        if (!str_starts_with((string) $cacheDir, '/') && !preg_match('#^[A-Za-z]:#', (string) $cacheDir)) {
            $cacheDir = $modx->getBasePath() . ltrim((string) $cacheDir, '/');
        }

        $cache = new AtomicFilesystemCache(
            (string) $cacheDir,
            (int) $config->get('hash_length', 10)
        );
        $cache->prepare();
        $config->set('cacheFolderPath', $cache->getDirectory());

        $logger = static function (string $message) use ($modx): void {
            $modx->log(2, '[MinifyX] ' . $message);
        };

        $processor = new CssJsProcessor([
            new ScssCompiler(),
            new LessCompiler(),
            new LegacyCoffeeCompiler($logger),
        ]);

        $normalizer = new FileNormalizer($modx->getBasePath(), $modx->getSiteUrl());
        $hooks = new HookRunner($modx, (string) $config->get('hooksPath'));

        return new AssetPipeline($modx, $config, $normalizer, $processor, $cache, $hooks, $groups);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, array<int, string>> $groups
     */
    public static function createFromLegacyModx(object $modx, array $config = [], array $groups = []): AssetPipeline
    {
        $adapter = new LegacyModxAdapter($modx);
        $corePath = (string) $adapter->getOption('minifyx_core_path', $config, $adapter->getCorePath()) . 'components/minifyx/';
        $assetsPath = (string) $adapter->getOption('minifyx_assets_path', $config, $adapter->getAssetsPath()) . 'components/minifyx/';
        $cacheFolder = (string) $adapter->getOption('minifyx_cacheFolder', null, '/assets/components/minifyx/cache/', true);

        $defaults = [
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'basePath' => $adapter->getBasePath(),
            'cacheFolder' => $cacheFolder,
            'jsGroups' => '',
            'cssGroups' => '',
            'jsSources' => '',
            'cssSources' => '',
            'cssFilename' => 'styles',
            'jsFilename' => 'scripts',
            'minifyJs' => false,
            'minifyCss' => false,
            'registerCss' => 'default',
            'registerJs' => 'default',
            'jsPlaceholder' => 'MinifyX.javascript',
            'cssPlaceholder' => 'MinifyX.css',
            'forceUpdate' => $adapter->getContextKey() === 'mgr',
            'forceDelete' => (bool) $adapter->getOption('minifyx_forceDelete', null, false),
            'munee_cache' => $adapter->getCorePath() . 'cache/default/munee/',
            'hash_length' => 10,
            'hooksPath' => $adapter->getCorePath() . 'components/minifyx/hooks/',
            'hooks' => '',
            'preHooks' => '',
            'jsTpl' => '<script src="[[+file]]"></script>',
            'cssTpl' => '<link rel="stylesheet" href="[[+file]]" type="text/css">',
            'version' => '',
            'cacheFolderPath' => $adapter->getBasePath() . ltrim($cacheFolder, '/'),
        ];

        $cfg = Config::fromDefaults($defaults, $config);

        return self::create($adapter, $cfg, $groups);
    }
}
