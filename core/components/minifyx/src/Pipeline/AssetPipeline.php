<?php

declare(strict_types=1);

namespace MinifyX\Pipeline;

use MinifyX\Application\BuildResult;
use MinifyX\Application\BuildRequest;
use MinifyX\Application\BuildSignature;
use MinifyX\Config;
use MinifyX\Contract\AssetProcessorInterface;
use MinifyX\Contract\CacheStoreInterface;
use MinifyX\Contract\HookHostInterface;
use MinifyX\Contract\ModxAdapterInterface;
use MinifyX\Contract\SourceMapProviderInterface;
use MinifyX\Hook\HookRunner;
use MinifyX\Processor\UnsupportedSourceTypeException;
use MinifyX\Support\PathHelper;

final class AssetPipeline
{
    private ModxAdapterInterface $modx;
    private Config $config;
    private FileNormalizer $normalizer;
    private AssetProcessorInterface $processor;
    private CacheStoreInterface $cache;
    private HookRunner $hooks;
    private ImportDependencyResolver $dependencyResolver;

    /** @var array<string, array<int, string>> */
    private array $groups;

    private string $content = '';
    private string $filename = '';
    private string $filetype = '';

    /** @var list<string> */
    private array $trackedFiles = [];

    private int $compilerCalls = 0;
    private int $outputWrites = 0;

    /**
     * @param array<string, array<int, string>> $groups
     */
    public function __construct(
        ModxAdapterInterface $modx,
        Config $config,
        FileNormalizer $normalizer,
        AssetProcessorInterface $processor,
        CacheStoreInterface $cache,
        HookRunner $hooks,
        array $groups = [],
        ?ImportDependencyResolver $dependencyResolver = null
    ) {
        $this->modx = $modx;
        $this->config = $config;
        $this->normalizer = $normalizer;
        $this->processor = $processor;
        $this->cache = $cache;
        $this->hooks = $hooks;
        $this->groups = $groups;
        $this->dependencyResolver = $dependencyResolver ?? new ImportDependencyResolver($modx->getBasePath());
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getCache(): CacheStoreInterface
    {
        return $this->cache;
    }

    public function getNormalizer(): FileNormalizer
    {
        return $this->normalizer;
    }

    public function getProcessor(): AssetProcessorInterface
    {
        return $this->processor;
    }

    public function getCompilerCalls(): int
    {
        return $this->compilerCalls;
    }

    public function getOutputWrites(): int
    {
        return $this->outputWrites;
    }

    /**
     * @return array{js: list<string>, css: list<string>}
     */
    public function prepareSources(HookHostInterface $host): array
    {
        $this->hooks->run($this->listParam('preHooks'), $host);

        $js = [];
        $css = [];
        foreach ($this->listParam('jsGroups') as $group) {
            if (isset($this->groups[$group])) {
                $js = array_merge($js, $this->groups[$group]);
            }
        }
        foreach ($this->listParam('cssGroups') as $group) {
            if (isset($this->groups[$group])) {
                $css = array_merge($css, $this->groups[$group]);
            }
        }

        $js = array_values(array_unique(array_merge($js, $this->listParam('jsSources'))));
        $css = array_values(array_unique(array_merge($css, $this->listParam('cssSources'))));
        foreach ($js as $index => $url) {
            $js[$index] = $this->parseUrl($url);
        }
        foreach ($css as $index => $url) {
            $css[$index] = $this->parseUrl($url);
        }

        return compact('js', 'css');
    }

    /**
     * @param list<string>|string $files
     * @return array<string, mixed>
     */
    public function processAndSave($files, string $type, HookHostInterface $host, bool $forceMinify = false)
    {
        $this->filetype = $type;
        $normalized = $this->normalizer->normalize($files);
        if ($normalized['paths'] === []) {
            return BuildResult::failure('No source files.', 'no_sources', '', 'source_resolution')->toArray();
        }

        $absolute = $this->normalizer->toAbsolutePaths($normalized['paths']);
        if ($absolute === []) {
            return BuildResult::failure(
                'No resolvable source files inside webroot.',
                'source_resolution',
                $this->safeSourceFile($normalized['paths'][0] ?? ''),
                'source_resolution'
            )->toArray();
        }
        foreach ($absolute as $index => $source) {
            if (!is_file($source)) {
                return BuildResult::failure(
                    'Source file could not be resolved.',
                    'source_resolution',
                    $this->safeSourceFile($normalized['paths'][$index] ?? ''),
                    'source_resolution'
                )->toArray();
            }
        }

        $minify = $forceMinify || (bool) $this->config->get('minify' . ucfirst($type), false);
        $mangleJs = $type === 'js' && !empty($this->config->get('mangleJs', false));
        $defaultFilename = $type === 'css' ? 'styles' : 'scripts';
        try {
            $basename = PathHelper::sanitizeFilename(
                (string) $this->config->get($type . 'Filename', $defaultFilename)
            );
        } catch (\InvalidArgumentException $e) {
            return BuildResult::failure('Invalid bundle filename.', 'invalid_filename', '', 'configuration')->toArray();
        }
        $extension = (string) $this->config->get($type . 'Ext', $type === 'css' ? '.css' : '.js');
        $jsMangler = (string) $this->config->get('jsMangler', 'terser');
        $jsManglerPath = (string) $this->config->get('jsManglerPath', '');
        $sourceMaps = (bool) $this->config->get('sourceMaps', false);
        $bundleJsModules = (bool) $this->config->get('bundleJsModules', false);
        $jsModule = $type === 'js' && (bool) $this->config->get('jsModule', false);
        $hooks = $this->listParam('hooks');
        $dependencies = $type === 'css' ? $this->dependencyResolver->resolve($absolute) : [];
        $signaturePaths = array_values(array_unique(array_merge($absolute, $dependencies)));

        /** @var array<string, string> $queryParams */
        $queryParams = [];
        foreach ($normalized['queryParams'] as $key => $value) {
            if (is_scalar($value)) {
                $queryParams[(string) $key] = (string) $value;
            }
        }

        $signature = new BuildSignature(
            $signaturePaths,
            [
                'minify' => $minify || $mangleJs,
                'mangleJs' => $mangleJs,
                'type' => $type,
                'queryParams' => $queryParams,
                'jsMangler' => $jsMangler,
                'jsManglerPath' => $jsManglerPath,
                'jsBackend' => $mangleJs ? $jsMangler : 'php-minify',
                'sourceMaps' => $sourceMaps,
                'bundleJsModules' => $bundleJsModules,
                'jsModule' => $jsModule,
                'moduleBackend' => $jsModule && $bundleJsModules ? 'esbuild-bundle' : 'none',
            ],
            $hooks
        );
        $fingerprint = $signature->hash((int) $this->config->get('hash_length', 10));
        $cachedName = $this->cache->lookupByFingerprint($basename, $fingerprint, $extension);
        $forceUpdate = (bool) $this->config->get('forceUpdate', false);

        if ($cachedName !== null && !$forceUpdate) {
            $this->filename = $cachedName;
            $this->content = (string) file_get_contents($this->cache->path($cachedName));
            $this->trackedFiles[] = $cachedName;
            $host->setFilename($this->filename);
            $host->setContent($this->content);

            return $this->successResult($this->content, $this->filename, true, false)->toArray();
        }

        $outputPath = $this->cache->path($basename . '_' . $fingerprint . $extension);
        $request = new BuildRequest(
            $absolute,
            $type,
            $minify,
            $mangleJs,
            $jsMangler,
            $jsManglerPath,
            $outputPath,
            $basename,
            $extension,
            $queryParams
        );

        try {
            $this->compilerCalls++;
            $processorOptions = $request->toProcessorOptions($hooks);
            $processorOptions['sourceMaps'] = $sourceMaps;
            $processorOptions['bundleJsModules'] = $bundleJsModules;
            $processorOptions['jsModule'] = $jsModule;
            $content = $this->processor->process($request->getAbsolutePaths(), $processorOptions);
        } catch (UnsupportedSourceTypeException $e) {
            return BuildResult::failure(
                $e->getMessage(),
                'unsupported_source_type',
                $this->safeSourceFile($normalized['paths'][0] ?? ''),
                'compile'
            )->toArray();
        } catch (\Throwable $e) {
            return BuildResult::failure(
                'Asset compilation failed.',
                'compile_failed',
                $this->safeSourceFile($normalized['paths'][0] ?? ''),
                'compile'
            )->toArray();
        }

        $this->content = $content;
        $sourceMap = $sourceMaps && $this->processor instanceof SourceMapProviderInterface
            ? $this->processor->getSourceMap()
            : null;
        $this->filename = $basename . '_' . $fingerprint . $extension;
        $host->setFilename($this->filename);
        $host->setContent($this->content);

        $hookList = $hooks;
        if ($hookList !== []) {
            $this->hooks->run($hookList, $host);
            $this->content = $host->getContent();
            $this->filename = $host->getFilename();
            if ($this->filename === '') {
                return $this->successResult($this->content, '', false, false)->toArray();
            }
            $sourceMap = null;
            $hookFingerprint = substr(hash('sha1', $fingerprint . '|' . $this->content), 0, 10);
            if (!str_contains($this->filename, '_')) {
                $this->filename = $basename . '_' . $hookFingerprint . $extension;
                $host->setFilename($this->filename);
            }
        }

        try {
            $safeName = PathHelper::sanitizeFilename($this->filename);
        } catch (\InvalidArgumentException $e) {
            return BuildResult::failure('Invalid bundle filename.', 'invalid_filename', '', 'write')->toArray();
        }
        $this->filename = $safeName;
        $host->setFilename($this->filename);

        if (is_string($sourceMap) && $sourceMap !== '') {
            $mapName = $this->filename . '.map';
            if (!$this->cache->write($mapName, $sourceMap, $forceUpdate)) {
                return BuildResult::failure('Could not write source map.', 'map_write_failed', '', 'write')->toArray();
            }
            $mapReference = $type === 'css'
                ? '/*# sourceMappingURL=' . basename($mapName) . ' */'
                : '//# sourceMappingURL=' . basename($mapName);
            $this->content = rtrim($this->content) . "\n" . $mapReference . "\n";
            $host->setContent($this->content);
        }
        $written = $this->cache->write($this->filename, $this->content, $forceUpdate);
        if (!$written) {
            return BuildResult::failure('Could not write cache file.', 'cache_write_failed', '', 'write')->toArray();
        }
        $this->outputWrites++;
        $this->trackedFiles[] = $this->filename;

        return $this->successResult($this->content, $this->filename, false, true)->toArray();
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getFiletype(): string
    {
        return $this->filetype;
    }

    /**
     * @return list<string>
     */
    public function getTrackedFiles(): array
    {
        return array_values(array_unique($this->trackedFiles));
    }

    public function clearCache(): bool
    {
        $this->cache->clear((bool) $this->config->get('forceDelete', false), $this->trackedFiles);
        $tmp = (string) $this->config->get('minifyx_cache', '');
        if ($tmp !== '') {
            return $this->cache->removeDirectory($tmp);
        }

        return true;
    }

    private function parseUrl(string $url): string
    {
        $url = str_replace(['[[+', '{', '}'], ['[[++', '[[++', ']]'], $url);

        return $this->modx->processElementTags($url);
    }

    /**
     * @return list<string>
     */
    private function listParam(string $key): array
    {
        $value = $this->config->get($key, []);
        if (is_string($value)) {
            return $value === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $value))));
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map('strval', $value));
    }

    private function successResult(string $content, string $filename, bool $fromCache, bool $written): BuildResult
    {
        $url = $filename !== ''
            ? rtrim((string) $this->config->get('cacheFolder'), '/') . '/' . $filename
            : '';
        $path = $filename !== '' ? $this->cache->path($filename) : '';

        return new BuildResult($content, $filename, $url, $path, $fromCache, $written, true, '');
    }

    private function safeSourceFile(string $source): string
    {
        $path = parse_url($source, PHP_URL_PATH);
        $path = is_string($path) ? $path : $source;

        return basename(str_replace('\\', '/', $path));
    }
}
