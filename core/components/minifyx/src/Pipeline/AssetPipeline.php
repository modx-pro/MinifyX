<?php

declare(strict_types=1);

namespace MinifyX\Pipeline;

use MinifyX\Config;
use MinifyX\Contract\AssetProcessorInterface;
use MinifyX\Contract\CacheStoreInterface;
use MinifyX\Contract\HookHostInterface;
use MinifyX\Contract\ModxAdapterInterface;
use MinifyX\Hook\HookRunner;
use MinifyX\Support\PathHelper;

final class AssetPipeline
{
    private ModxAdapterInterface $modx;
    private Config $config;
    private FileNormalizer $normalizer;
    private AssetProcessorInterface $processor;
    private CacheStoreInterface $cache;
    private HookRunner $hooks;

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
        array $groups = []
    ) {
        $this->modx = $modx;
        $this->config = $config;
        $this->normalizer = $normalizer;
        $this->processor = $processor;
        $this->cache = $cache;
        $this->hooks = $hooks;
        $this->groups = $groups;
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
        $js = array_map(fn (string $url): string => $this->parseUrl($url), $js);
        $css = array_map(fn (string $url): string => $this->parseUrl($url), $css);

        return compact('js', 'css');
    }

    /**
     * @param list<string>|string $files
     * @return array{
     *   content: string,
     *   filename: string,
     *   url: string,
     *   path: string,
     *   fromCache: bool,
     *   written: bool,
     *   success: bool,
     *   error: string
     * }
     */
    public function processAndSave(array|string $files, string $type, HookHostInterface $host, bool $forceMinify = false): array
    {
        $this->filetype = $type;
        $normalized = $this->normalizer->normalize($files);
        if ($normalized['paths'] === []) {
            return $this->failure('No source files.');
        }

        $absolute = $this->normalizer->toAbsolutePaths($normalized['paths']);
        if ($absolute === []) {
            return $this->failure('No resolvable source files inside webroot.');
        }

        $minify = $forceMinify || (bool) $this->config->get('minify' . ucfirst($type), false);
        $basename = PathHelper::sanitizeFilename((string) $this->config->get($type . 'Filename', $type === 'css' ? 'styles' : 'scripts'));
        $extension = (string) $this->config->get($type . 'Ext', $type === 'css' ? '.css' : '.js');

        $options = [
            'minify' => $minify,
            'type' => $type,
            'queryParams' => $normalized['queryParams'],
            'hooks' => $this->listParam('hooks'),
        ];

        $fingerprint = $this->cache->fingerprint($absolute, $options);
        $cachedName = $this->cache->lookupByFingerprint($basename, $fingerprint, $extension);
        $forceUpdate = (bool) $this->config->get('forceUpdate', false);

        if ($cachedName !== null && !$forceUpdate) {
            $this->filename = $cachedName;
            $this->content = (string) file_get_contents($this->cache->path($cachedName));
            $this->trackedFiles[] = $cachedName;
            $host->setFilename($this->filename);
            $host->setContent($this->content);

            return [
                'content' => $this->content,
                'filename' => $this->filename,
                'url' => rtrim((string) $this->config->get('cacheFolder'), '/') . '/' . $this->filename,
                'path' => $this->cache->path($this->filename),
                'fromCache' => true,
                'written' => false,
                'success' => true,
                'error' => '',
            ];
        }

        try {
            $this->compilerCalls++;
            $content = $this->processor->process($absolute, [
                'minify' => $minify,
                'type' => $type,
                'queryParams' => $normalized['queryParams'],
            ]);
        } catch (\Throwable $e) {
            return $this->failure($e->getMessage());
        }

        $this->content = $content;
        $this->filename = $basename . '_' . $fingerprint . $extension;
        $host->setFilename($this->filename);
        $host->setContent($this->content);

        $hookList = $this->listParam('hooks');
        if ($hookList !== []) {
            $this->hooks->run($hookList, $host);
            $this->content = $host->getContent();
            $this->filename = $host->getFilename();
            if ($this->filename === '') {
                // Hook disabled file registration (e.g. cssToPage).
                return [
                    'content' => $this->content,
                    'filename' => '',
                    'url' => '',
                    'path' => '',
                    'fromCache' => false,
                    'written' => false,
                    'success' => true,
                    'error' => '',
                ];
            }
            $hookFingerprint = substr(hash('sha1', $fingerprint . '|' . $this->content), 0, 10);
            if (!str_contains($this->filename, '_')) {
                $this->filename = $basename . '_' . $hookFingerprint . $extension;
                $host->setFilename($this->filename);
            }
        }

        try {
            $safeName = PathHelper::sanitizeFilename($this->filename);
        } catch (\InvalidArgumentException $e) {
            return $this->failure($e->getMessage());
        }
        $this->filename = $safeName;
        $host->setFilename($this->filename);

        $written = $this->cache->write($this->filename, $this->content, $forceUpdate);
        if (!$written) {
            return $this->failure('Could not write cache file.');
        }
        $this->outputWrites++;
        $this->trackedFiles[] = $this->filename;

        return [
            'content' => $this->content,
            'filename' => $this->filename,
            'url' => rtrim((string) $this->config->get('cacheFolder'), '/') . '/' . $this->filename,
            'path' => $this->cache->path($this->filename),
            'fromCache' => false,
            'written' => true,
            'success' => true,
            'error' => '',
        ];
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
        $tmp = (string) $this->config->get('munee_cache', '');
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

    /**
     * @return array{
     *   content: string,
     *   filename: string,
     *   url: string,
     *   path: string,
     *   fromCache: bool,
     *   written: bool,
     *   success: bool,
     *   error: string
     * }
     */
    private function failure(string $error): array
    {
        $this->content = '';
        $this->filename = '';

        return [
            'content' => '',
            'filename' => '',
            'url' => '',
            'path' => '',
            'fromCache' => false,
            'written' => false,
            'success' => false,
            'error' => $error,
        ];
    }
}
