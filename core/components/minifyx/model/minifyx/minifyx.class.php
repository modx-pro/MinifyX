<?php

namespace MinifyX\Model;

use InvalidArgumentException;
use MinifyX\Html\AssetTag;
use MinifyX\Html\AssetTagRenderer;
use MinifyX\Pipeline\AssetPipeline;
use MinifyX\ServiceFactory;
use MinifyX\Contract\HookHostInterface;
use MinifyX\Support\PathHelper;
use RuntimeException;
use Throwable;

/**
 * Compatibility facade for MODX getService() / snippet / minify() API.
 *
 * @property array $config
 */
class MinifyX implements HookHostInterface
{
    /** @var object */
    public $modx = null;

    /** @var array<string, array<int, string>> */
    public $groups = [];

    /** @var array<string, mixed> */
    public array $config = [];

    /** @var array{js?: list<string>, css?: list<string>} */
    protected $sources = [];

    protected string $content = '';
    protected string $filename = '';
    protected string $filetype = '';

    /** @var list<string> */
    protected array $cachedFiles = [];

    /** @var list<string> */
    protected array $parameters = ['jsGroups', 'cssGroups', 'jsSources', 'cssSources', 'hooks', 'preHooks'];

    private ?AssetPipeline $pipeline = null;

    public function __construct(object $modx, array $config = [])
    {
        $this->modx = $modx;
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }

        $groupsFile = (defined('MODX_CORE_PATH') ? MODX_CORE_PATH : '') . 'components/minifyx/config/groups.php';
        if (is_file($groupsFile)) {
            $loaded = include $groupsFile;
            if (is_array($loaded)) {
                $this->groups = $loaded;
            }
        }

        $this->pipeline = ServiceFactory::createFromLegacyModx($modx, $config, $this->groups);
        $this->config = $this->pipeline->getConfig()->all();
        $this->processParams();
        if ($this->prepareCacheFolder()) {
            $this->cachedFiles = $this->pipeline->getTrackedFiles();
        } else {
            $cacheDir = (string) ($this->config['cacheFolderPath'] ?? '');
            $this->logError('[MinifyX] Could not create cache dir "' . $cacheDir . '"');
        }
    }

    protected function processParams(): void
    {
        foreach ($this->parameters as $source) {
            $this->config[$source] = $this->explodeParam($this->config[$source] ?? '');
        }
        $this->syncPipelineConfig();
    }

    public function reset(array $config = []): void
    {
        $this->filename = '';
        $this->content = '';
        foreach ($this->parameters as $source) {
            $this->config[$source] = '';
        }
        $this->setConfig($config);
        $this->processParams();
        $this->config['jsExt'] = !empty($this->config['minifyJs']) || !empty($this->config['mangleJs'])
            ? '.min.js'
            : '.js';
        $this->config['cssExt'] = !empty($this->config['minifyCss']) ? '.min.css' : '.css';
        $this->syncPipelineConfig();
    }

    public function setConfig(array $config = []): void
    {
        $this->config = array_merge($this->config, $config);
        if (
            isset($config['minifyJs'])
            || isset($config['minifyCss'])
            || isset($config['jsExt'])
            || isset($config['cssExt'])
        ) {
            $this->config['jsExt'] = !empty($this->config['minifyJs']) ? '.min.js' : '.js';
            $this->config['cssExt'] = !empty($this->config['minifyCss']) ? '.min.css' : '.css';
        }
        $this->syncPipelineConfig();
    }

    public function getGroup($group)
    {
        return $this->groups[$group] ?? [];
    }

    public function prepareSources()
    {
        $this->syncPipelineConfig();
        $this->sources = $this->pipeline()->prepareSources($this);

        return $this->sources;
    }

    protected function explodeParam($param): array
    {
        if (is_array($param)) {
            return array_values($param);
        }

        return !empty($param) ? array_map('trim', explode(',', (string) $param)) : [];
    }

    protected function processHooks($hooks): void
    {
        $list = is_array($hooks) ? $hooks : $this->explodeParam($hooks);
        $this->config['hooks'] = $list;
        $this->syncPipelineConfig();
        // Hooks for content mutation are executed inside pipeline processAndSave / prepareSources.
    }

    protected function parseUrl($url)
    {
        $url = str_replace(['[[+', '{', '}'], ['[[++', '[[++', ']]'], $url);
        if (method_exists($this->modx, 'getParser')) {
            $parser = $this->modx->getParser();
            if (is_object($parser) && method_exists($parser, 'processElementTags')) {
                $parser->processElementTags('', $url, false, false, '[[', ']]', [], 1);
            }
        }

        return $url;
    }

    public function addJsGroup($group): void
    {
        if (!empty($group)) {
            if (!is_array($group)) {
                $group = $this->explodeParam($group);
            }
            $this->config['jsGroups'] = array_merge((array) $this->config['jsGroups'], $group);
            $this->syncPipelineConfig();
        }
    }

    public function getJsGroup($group = null)
    {
        return !empty($group) ? ($this->config['jsGroups'][$group] ?? null) : $this->config['jsGroups'];
    }

    public function setJsGroup($group): void
    {
        if (!is_array($group)) {
            $group = $this->explodeParam($group);
        }
        $this->config['jsGroups'] = $group;
        $this->syncPipelineConfig();
    }

    public function addCssGroup($group): void
    {
        if (!empty($group)) {
            if (!is_array($group)) {
                $group = $this->explodeParam($group);
            }
            $this->config['cssGroups'] = array_merge((array) $this->config['cssGroups'], $group);
            $this->syncPipelineConfig();
        }
    }

    public function getCssGroup($group = null)
    {
        return !empty($group) ? ($this->config['cssGroups'][$group] ?? null) : $this->config['cssGroups'];
    }

    public function setCssGroup($group): void
    {
        if (!is_array($group)) {
            $group = $this->explodeParam($group);
        }
        $this->config['cssGroups'] = $group;
        $this->syncPipelineConfig();
    }

    public function addJsSource($script): void
    {
        if (!empty($script)) {
            if (!is_array($script)) {
                $script = $this->explodeParam($script);
            }
            $this->config['jsSources'] = array_merge((array) $this->config['jsSources'], $script);
            $this->syncPipelineConfig();
        }
    }

    public function getJsSource($script = null)
    {
        return !empty($script) ? ($this->config['jsSources'][$script] ?? null) : $this->config['jsSources'];
    }

    public function setJsSource($script): void
    {
        if (!is_array($script)) {
            $script = $this->explodeParam($script);
        }
        $this->config['jsSources'] = $script;
        $this->syncPipelineConfig();
    }

    public function addCssSource($style): void
    {
        if (!empty($style)) {
            if (!is_array($style)) {
                $style = $this->explodeParam($style);
            }
            $this->config['cssSources'] = array_merge((array) $this->config['cssSources'], $style);
            $this->syncPipelineConfig();
        }
    }

    public function getCssSource($style = null)
    {
        return !empty($style) ? ($this->config['cssSources'][$style] ?? null) : $this->config['cssSources'];
    }

    public function setCssSource($style): void
    {
        if (!is_array($style)) {
            $style = $this->explodeParam($style);
        }
        $this->config['cssSources'] = $style;
        $this->syncPipelineConfig();
    }

    /**
     * Normalize files and return comma-separated web paths.
     * Query parameters are returned via reference array, not $_GET.
     *
     * @param array|string $files
     * @param array<string, string>|null $queryParams
     */
    public function prepareFiles($files, $type = '', ?array &$queryParams = null): string
    {
        $this->filetype = (string) $type;
        $normalized = $this->pipeline()->getNormalizer()->normalize($files);
        $queryParams = $normalized['queryParams'];

        return implode(',', $normalized['paths']);
    }

    /**
     * Process assets with the modern pure-PHP pipeline (Munee replacement).
     * Prefer processFiles() for cache + hooks. This method only compiles.
     *
     * @param string $files Comma-separated web paths
     * @param array<string, mixed> $options
     */
    public function Munee($files, $options = []): string
    {
        $paths = array_values(array_filter(array_map('trim', explode(',', (string) $files))));
        if ($paths === []) {
            return '';
        }

        $type = $this->filetype !== '' ? $this->filetype : $this->detectType($paths);
        $this->filetype = $type;
        $minify = isset($options['minify'])
            ? filter_var($options['minify'], FILTER_VALIDATE_BOOLEAN)
            : (bool) ($this->config['minify' . ucfirst($type)] ?? false);
        $queryParams = [];
        if (isset($options['queryParams']) && is_array($options['queryParams'])) {
            foreach ($options['queryParams'] as $key => $value) {
                if (is_scalar($value)) {
                    $queryParams[(string) $key] = (string) $value;
                }
            }
        }

        try {
            $absolute = $this->pipeline()->getNormalizer()->toAbsolutePaths($paths);
            if ($absolute === []) {
                throw new RuntimeException('No resolvable source files.');
            }

            return $this->pipeline()->getProcessor()->process($absolute, [
                'minify' => $minify,
                'type' => $type,
                'queryParams' => $queryParams,
            ]);
        } catch (Throwable $e) {
            $this->logError('[MinifyX] ' . $e->getMessage());

            return '';
        }
    }

    /**
     * Canonical compile + cache path used by snippet and plugin.
     *
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
     * }|null
     */
    public function processFiles($files, string $type): ?array
    {
        $this->syncPipelineConfig();
        $this->filetype = $type;
        $result = $this->pipeline()->processAndSave($files, $type, $this);
        $this->filename = $result['filename'];
        $this->content = $result['content'];
        if (!$result['success']) {
            if ($result['error'] !== '') {
                $this->logError('[MinifyX] ' . $result['error']);
            }

            return null;
        }
        if ($result['filename'] !== '' && !in_array($result['filename'], $this->cachedFiles, true)) {
            $this->cachedFiles[] = $result['filename'];
        }

        return $result;
    }

    public function prepareCacheFolder()
    {
        $folder = (string) ($this->config['cacheFolder'] ?? '');
        $base = defined('MODX_BASE_PATH') ? MODX_BASE_PATH : '';
        $path = trim(str_replace($base, '', trim($folder)), '/');
        if ($path === '') {
            return false;
        }
        $absolute = $base . $path . '/';
        $ok = $this->pipeline()->getCache()->makeDirectory($absolute);
        $this->config['cacheFolderPath'] = $absolute;
        $this->syncPipelineConfig();

        return $ok && is_dir($absolute);
    }

    public function getCachedFiles($prefix = '', $extension = '')
    {
        $dir = (string) ($this->config['cacheFolderPath'] ?? '');
        if ($dir === '' || !is_dir($dir)) {
            return [];
        }

        $hashLength = (int) ($this->config['hash_length'] ?? 10);
        $regexp = $prefix . '[a-z0-9]{' . $hashLength . '}.*';
        if ($extension !== '') {
            $regexp .= '?' . str_replace('.', '\.', $extension);
        }

        $cached = [];
        $files = scandir($dir);
        if ($files === false) {
            return [];
        }
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (preg_match('/' . $regexp . '/i', $file)) {
                $cached[] = $file;
            }
        }

        return $cached;
    }

    public function saveFile($data)
    {
        // Empty string after a processor failure must not be published.
        // Explicit empty content is allowed only when caller passes a successful compile.
        $type = $this->filetype ?: 'js';
        try {
            $filename = PathHelper::sanitizeFilename((string) ($this->config[$type . 'Filename'] ?? $type));
        } catch (InvalidArgumentException $e) {
            $this->logError('[MinifyX] ' . $e->getMessage());

            return false;
        }

        if (pathinfo($filename, PATHINFO_EXTENSION) === $type) {
            $this->filename = $filename;
        } else {
            $extension = (string) ($this->config[$type . 'Ext'] ?? ('.' . $type));
            $hash = substr(sha1((string) $data), 0, (int) ($this->config['hash_length'] ?? 10));
            $this->filename = $filename . '_' . $hash . $extension;
        }

        $this->setContent((string) $data);
        $hooks = (array) ($this->config['hooks'] ?? []);
        if ($hooks !== []) {
            $hooksPath = (string) ($this->config['hooksPath'] ?? '');
            foreach ($hooks as $hook) {
                if (preg_match('#\.php$#', (string) $hook) && is_file($hooksPath . basename((string) $hook))) {
                    $modx = $this->modx;
                    $MinifyX = $this;
                    include $hooksPath . basename((string) $hook);
                } elseif (method_exists($this->modx, 'runSnippet')) {
                    $this->modx->runSnippet((string) $hook, ['MinifyX' => $this]);
                }
            }
        }

        if ($this->filename === '') {
            return false;
        }

        try {
            $this->filename = PathHelper::sanitizeFilename($this->filename);
        } catch (InvalidArgumentException $e) {
            $this->logError('[MinifyX] ' . $e->getMessage());

            return false;
        }

        $force = (bool) ($this->config['forceUpdate'] ?? false);
        $written = $this->pipeline()->getCache()->write($this->filename, $this->getContent(), $force);
        if (!$written) {
            $cacheDir = (string) ($this->config['cacheFolderPath'] ?? '');
            $this->logError('[MinifyX] Could not save cache file ' . $cacheDir . $this->filename);

            return false;
        }
        if (!in_array($this->filename, $this->cachedFiles, true)) {
            $this->cachedFiles[] = $this->filename;
        }

        return is_file($this->getFilePath());
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $name): void
    {
        $this->filename = $name;
    }

    public function isJs(?string $file = null): bool
    {
        $file = $file ?? $this->filename;

        return $file !== '' ? pathinfo($file, PATHINFO_EXTENSION) === 'js' : false;
    }

    public function isCss(?string $file = null): bool
    {
        $file = $file ?? $this->filename;

        return $file !== '' ? pathinfo($file, PATHINFO_EXTENSION) === 'css' : false;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getFileUrl($file = null)
    {
        if ($file === null) {
            $file = $this->getFilename();
        }

        return rtrim((string) ($this->config['cacheFolder'] ?? ''), '/') . '/' . ltrim((string) $file, '/');
    }

    public function getFilePath($file = null)
    {
        if ($file === null) {
            $file = $this->getFilename();
        }

        return (string) ($this->config['cacheFolderPath'] ?? '') . $file;
    }

    public function makeDir($path = '')
    {
        if ($path === '') {
            return false;
        }

        return $this->pipeline()->getCache()->makeDirectory((string) $path);
    }

    public function removeDir($dir)
    {
        return $this->pipeline()->getCache()->removeDirectory((string) $dir);
    }

    public function getTmpDir()
    {
        $dir = str_replace('//', '/', (string) ($this->config['munee_cache'] ?? ''));
        if ($this->makeDir($dir)) {
            return $dir;
        }

        return false;
    }

    public function clearCache()
    {
        $this->syncPipelineConfig();
        $this->pipeline()->clearCache();
        $this->cachedFiles = [];

        return true;
    }

    public function getVersion()
    {
        $version = '';
        if (!empty($this->config['version'])) {
            $version = '?v=' . (($this->config['version'] === 'auto')
                ? substr(sha1($this->getContent()), 0, 6)
                : $this->config['version']);
        }

        return $version;
    }

    public function minify($value = true)
    {
        $this->config['minifyJs'] = $this->config['minifyCss'] = (bool) $value;
        $this->config['jsExt'] = $this->config['minifyJs'] ? '.min.js' : '.js';
        $this->config['cssExt'] = $this->config['minifyCss'] ? '.min.css' : '.css';
        $this->syncPipelineConfig();

        return $this;
    }

    public function run()
    {
        $sources = $this->prepareSources();
        $output = [];
        $renderer = new AssetTagRenderer();

        foreach ($sources as $type => $value) {
            if (empty($value)) {
                continue;
            }
            $register = (string) ($this->config['register' . ucfirst($type)] ?? 'default');
            $placeholder = !empty($this->config[$type . 'Placeholder'])
                ? (string) $this->config[$type . 'Placeholder']
                : '';

            $result = $this->pipeline()->processAndSave($value, $type, $this);
            $this->filename = $result['filename'];
            $this->content = $result['content'];
            $this->filetype = $type;

            if (!$result['success']) {
                if ($result['error'] !== '') {
                    $this->logError('[MinifyX] ' . $result['error']);
                }

                continue;
            }

            $contextKey = (string) ($this->modx->context->key ?? '');
            if ($result['filename'] === '' || $contextKey === 'mgr') {
                if ($contextKey === 'mgr' && $result['filename'] !== '') {
                    $output[] = $this->getFileUrl();
                }

                continue;
            }

            $versionSuffix = !empty($this->config['version']) ? $this->getVersion() : '';
            $link = $result['url'] . $versionSuffix;
            $assetTag = new AssetTag(
                $type === 'css' ? AssetTag::KIND_LINK : AssetTag::KIND_SCRIPT,
                $link
            );
            $bundle = $renderer->renderBundle($type, $link, $assetTag, $this->config);
            $tag = $bundle['tag'];
            $preload = $bundle['preload'];

            switch ($register) {
                case 'placeholder':
                    if ($placeholder !== '' && method_exists($this->modx, 'setPlaceholder')) {
                        $this->modx->setPlaceholder($placeholder, $tag);
                    }
                    if ($preload !== null && method_exists($this->modx, 'setPlaceholder')) {
                        $this->modx->setPlaceholder($placeholder . '.preload', $preload);
                    }
                    break;
                case 'print':
                    if ($preload !== null) {
                        $output[] = $preload;
                    }
                    $output[] = $tag;
                    break;
                case 'startup':
                    if ($preload !== null && method_exists($this->modx, 'regClientStartupScript')) {
                        $this->modx->regClientStartupScript($preload);
                    }
                    if ($type === 'js' && method_exists($this->modx, 'regClientStartupScript')) {
                        $this->modx->regClientStartupScript($tag);
                    }
                    break;
                default:
                    if ($preload !== null && method_exists($this->modx, 'regClientStartupScript')) {
                        $this->modx->regClientStartupScript($preload);
                    }
                    if ($type === 'css' && method_exists($this->modx, 'regClientCSS')) {
                        $this->modx->regClientCSS($tag);
                    } elseif (method_exists($this->modx, 'regClientScript')) {
                        $this->modx->regClientScript($tag);
                    }
            }
        }

        if (($this->modx->context->key ?? '') === 'mgr') {
            return $output[0] ?? $this->getFileUrl();
        }

        return implode("\n", $output);
    }

    public function cacheFolder($path)
    {
        if (!empty($path)) {
            $this->config['cacheFolder'] = $path;
            if (!$this->prepareCacheFolder()) {
                $this->logError("Can't create the specified cache folder!");
            }
        }

        return $this;
    }

    public function __call($key, $parameters)
    {
        if (isset($this->config[$key])) {
            $value = $parameters[0] ?? null;
            $this->config[$key] = in_array($key, $this->parameters, true)
                ? $this->explodeParam($value)
                : $value;
            if (in_array($key, ['minifyJs', 'minifyCss'], true)) {
                $this->config['jsExt'] = !empty($this->config['minifyJs']) ? '.min.js' : '.js';
                $this->config['cssExt'] = !empty($this->config['minifyCss']) ? '.min.css' : '.css';
            }
            $this->syncPipelineConfig();
        }

        return $this;
    }

    public function __toString()
    {
        return (string) $this->run();
    }

    public function getPipeline(): AssetPipeline
    {
        return $this->pipeline();
    }

    private function pipeline(): AssetPipeline
    {
        if ($this->pipeline === null) {
            throw new RuntimeException('MinifyX pipeline is not initialized.');
        }

        return $this->pipeline;
    }

    private function syncPipelineConfig(): void
    {
        if ($this->pipeline === null) {
            return;
        }
        $this->pipeline->getConfig()->merge($this->config);
        $this->config = $this->pipeline->getConfig()->all();
    }

    /**
     * @param list<string> $paths
     */
    private function detectType(array $paths): string
    {
        foreach ($paths as $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['css', 'scss', 'sass', 'less'], true)) {
                return 'css';
            }
        }

        return 'js';
    }

    private function logError(string $message): void
    {
        if (method_exists($this->modx, 'log')) {
            $level = defined('modX::LOG_LEVEL_ERROR') ? constant('modX::LOG_LEVEL_ERROR') : 3;
            $this->modx->log($level, $message);
        }
    }
}

if (!class_exists('MinifyX', false)) {
    class_alias(MinifyX::class, 'MinifyX');
}
