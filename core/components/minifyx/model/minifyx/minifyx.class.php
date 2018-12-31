<?php

class MinifyX {
    /** @var modX $modx */
    public $modx = null;
    /** @var array */
    public $groups = array();
    /** @var array */
    protected $sources;
    /** @var string Content of the current processed file */
    protected $_content;
    /** @var string Name of the current processed file */
    protected $_filename;
    /** @var string Type of the processed files */
    protected $_filetype;
    /** @var array List of all cached files */
    protected $cachedFiles = array();
    /** @var array  */
    protected $parameters = array('jsGroups', 'cssGroups', 'jsSources', 'cssSources', 'hooks', 'preHooks');

    function __construct(modX $modx,array $config = array()) {
        $this->modx = $modx;

        $corePath = $this->modx->getOption('minifyx_core_path', $config, $this->modx->getOption('core_path')) . 'components/minifyx/';
        $assetsPath = $this->modx->getOption('minifyx_assets_path', $config, $this->modx->getOption('assets_path')) . 'components/minifyx/';
        $this->config = array_merge(array(
            'corePath' => $corePath,
            'modelPath' => $corePath.'model/',
            'basePath' => MODX_BASE_PATH,

            'cacheFolder' => $this->modx->getOption('minifyx_cacheFolder', null, $assetsPath . 'cache/', true),

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

            'forceUpdate' => $modx->context->key == 'mgr' ? true : false,
            'forceDelete' => $this->modx->getOption('minifyx_forceDelete', null, false),
            'munee_cache' => MODX_CORE_PATH . 'cache/default/munee/',
            'hash_length' => 10,
            'hooksPath' => MODX_CORE_PATH . 'components/minifyx/hooks/',
            'hooks' => '',
            'preHooks' => '',
            'jsTpl' => '<script src="[[+file]]"></script>',
            'cssTpl' => '<link rel="stylesheet" href="[[+file]]" type="text/css">',
            'version' => '',
        ),$config);
        $this->config['jsExt'] = $this->config['minifyJs'] ? '.min.js' : '.js';
        $this->config['cssExt'] = $this->config['minifyCss'] ? '.min.css' : '.css';
        if (empty($this->config['cacheFolder'])) {
            $this->config['cacheFolder'] = $assetsPath;
        }
        $this->processParams();
        if (file_exists(MODX_CORE_PATH . 'components/minifyx/config/groups.php')) {
            $this->groups = include MODX_CORE_PATH . 'components/minifyx/config/groups.php';
        }
        if ($this->prepareCacheFolder()) {
            $this->cachedFiles = $this->getCachedFiles();
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[MinifyX] Could not create cache dir "'.$this->config['cacheFolderPath'].'"');
        }
    }

    protected function processParams()
    {
        foreach ($this->parameters as $source) {
            $this->config[$source] = $this->explodeParam($this->config[$source]);
        }

    }

    public function reset(array $config = array())
    {
        $this->_filename = $this->_content = '';
        // vv For plugin vv
        foreach ($this->parameters as $source) {
            $this->config[$source] = '';
        }
        // ^^
        $this->setConfig($config);
        $this->processParams();
    }
    /**
     * Set new config.
     * @param array $config
     */
    public function setConfig(array $config = array())
    {
        $this->config = array_merge($this->config, $config);
    }
    /**
     * Get all file groups or the specified one.
     * @param $group
     * @return array
     */
    public function getGroup($group)
    {
        return isset($this->groups[$group]) ? $this->groups[$group] : array();
    }

    /**
     * Prepare an array of css and js files.
     * @return array
     */
    public function prepareSources()
    {
        $js = $css = array();
        $this->processHooks($this->config['preHooks']);
        foreach ($this->config['jsGroups'] as $group) {
            if (isset($this->groups[$group])) $js = array_merge($js, $this->groups[$group]);
        }
        foreach ($this->config['cssGroups'] as $group) {
            if (isset($this->groups[$group])) $css = array_merge($css, $this->groups[$group]);
        }
        $js = array_unique(array_merge($js, $this->config['jsSources']));
        $css = array_unique(array_merge($css, $this->config['cssSources']));
        $js = array_map(array($this,'parseUrl'), $js);
        $css = array_map(array($this,'parseUrl'), $css);

        return $this->sources = compact('js','css');
    }

    /**
     * Convert a snippet parameter to array.
     * @param $param
     * @return array
     */
    protected function explodeParam($param)
    {
        return !empty($param) ? array_map('trim', explode(',', $param)) : array();
    }

    /**
     * Process specified hooks.
     * @param $hooks
     */
    protected function processHooks($hooks)
    {
        foreach ($hooks as $hook) {
            $modx = $this->modx;
            if (preg_match('#\.php$#', $hook)) {
                $MinifyX = $this;
                if (file_exists($this->config['hooksPath'].$hook)) include $this->config['hooksPath'] . $hook;
            } else {
                $modx->runSnippet($hook, array('MinifyX' => $this));
            }
        }
    }
    /**
     * @param $url
     * @return mixed
     */
    protected function parseUrl($url)
    {
        $url = str_replace(array('[[+','{','}'), array('[[++','[[++',']]'), $url);
        $this->modx->getParser()->processElementTags('', $url, false, false, '[[', ']]', array(), 1);
        return $url;
    }

    /**
     * Add a group or groups of javascript files.
     * @param $group
     */
    public function addJsGroup($group)
    {
        if (!empty($group)) {
            if (!is_array($group)) $group = $this->explodeParam($group);
            $this->config['jsGroups'] = array_merge($this->config['jsGroups'], $group);
        }
    }

    /**
     * Get the stored groups of javascript files.
     * @param null $group
     * @return mixed
     */
    public function getJsGroup($group = null)
    {
        return !empty($group) ? $this->config['jsGroups'][$group] : $this->config['jsGroups'];
    }

    /**
     * Replace the stored js files groups with new ones.
     * @param $group
     */
    public function setJsGroup($group)
    {
        if (!is_array($group)) $group = $this->explodeParam($group);
        $this->config['jsGroups'] = $group;
    }

    public function addCssGroup($group)
    {
        if (!empty($group)) {
            if (!is_array($group)) $group = $this->explodeParam($group);
            $this->config['cssGroups'] = array_merge($this->config['cssGroups'], $group);
        }
    }

    public function getCssGroup($group = null)
    {
        return !empty($group) ? $this->config['cssGroups'][$group] : $this->config['cssGroups'];
    }

    public function setCssGroup($group)
    {
        if (!is_array($group)) $group = $this->explodeParam($group);
        $this->config['cssGroups'] = $group;
    }

    public function addJsSource($script)
    {
        if (!empty($script)) {
            if (!is_array($script)) $script = $this->explodeParam($script);
            $this->config['jsSources'] = array_merge($this->config['jsSources'], $script);
        }
    }

    public function getJsSource($script)
    {
        return !empty($script) ? $this->config['jsSources'][$script] : $this->config['jsSources'];
    }

    public function setJsSource($script)
    {
        if (!is_array($script)) $script = $this->explodeParam($script);
        $this->config['jsSources'] = $script;
    }

    public function addCssSource($style)
    {
        if (!empty($style)) {
            if (!is_array($style)) $style = $this->explodeParam($style);
            $this->config['cssSources'] = array_merge($this->config['cssSources'], $style);
        }
    }

    public function getCssSource($style)
    {
        return !empty($style) ? $this->config['cssSources'][$style] : $this->config['cssSources'];
    }

    public function setCssSource($style)
    {
        if (!is_array($style)) $style = $this->explodeParam($style);
        $this->config['cssSources'] = $style;
    }

    /**
     * Prepare string or array of files for Munee.
     *
     * @param array|string $files
     * @param string $type Type of files
     * @return string
     */
    public function prepareFiles($files, $type = '') {
        if (is_string($files)) {
            $files = array_map('trim', explode(',', $files));
        }
        if (!is_array($files)) {return '';}
        $site_url = $this->modx->getOption('site_url');
        $this->_filetype = $type;
        $output = array();
        foreach ($files as $file) {
            if (!empty($file) && $file[0] !== '-') {
                $file = str_replace(MODX_BASE_PATH, '', $file);
                $file = str_replace($site_url, '', $file);

                if (!preg_match('#https?:\/\/#', $file) && $file[0] != '/') {
                    $file = '/' . $file;
                }

                if ($tmp = parse_url($file)) {
                    // Adding file
                    $output[] = $tmp['path'];

                    // Parse file properties if set
                    if (!empty($tmp['query'])) {
                        $tmp2 = explode('&', $tmp['query']);
                        foreach ($tmp2 as $v) {
                            if ($tmp3 = explode('=', $v)) {
                                $_GET[$tmp3[0]] = @$tmp3[1];
                            }
                        }
                    }
                }
            }
        }

        return implode(',', $output);
    }

    /**
     * Process files with Munee library
     * http://mun.ee
     *
     * @param string $files
     * @param array $options Array with options for Munee class
     *
     * @return string
     */
    public function Munee($files, $options = array()) {
        if (!defined('WEBROOT')) {
            define('WEBROOT', MODX_BASE_PATH);
        }
        if (!defined('MUNEE_CACHE')) {
            define('MUNEE_CACHE', $this->getTmpDir());
        }

        require_once $this->config['corePath'] . 'vendor/autoload.php';

        try {
            $Request = new \Munee\Request($options);
            $Request->setFiles($files);
            foreach ($options as $k => $v) {
                $Request->setRawParam($k, $v);
            }
            $Request->init();

            /** @var \Munee\Asset\Type $AssetType */
            $AssetType = \Munee\Asset\Registry::getClass($Request);
            $AssetType->init();

            if (!empty($options['setHeaders'])) {
                if (isset($options['headerController']) && $options['headerController'] instanceof \Munee\Asset\HeaderSetter) {
                    $headerController = $options['headerController'];
                } else {
                    $headerController = new \Munee\Asset\HeaderSetter;
                }
                /** @var \Munee\Response $Response */
                $Response = new \Munee\Response($AssetType);
                $Response->setHeaderController($headerController);
                $Response->setHeaders(isset($options['maxAge']) ? $options['maxAge'] : 0);
            }

            return $AssetType->getContent();
        }
        catch (\Munee\ErrorException $e) {
            $error = $e->getMessage();
            if ($prev = $e->getPrevious()) {
                $error .= ': '. $e->getPrevious()->getMessage();
            }
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[MinifyX] ' . $error);
            return '';
        }
    }

    /**
     * Checks and creates cache dir for storing prepared scripts and styles
     *
     * @return bool|string
     */
    public function prepareCacheFolder() {
        $path = trim(str_replace(MODX_BASE_PATH, '', trim($this->config['cacheFolder'])), '/');

        if (!file_exists(MODX_BASE_PATH . $path)) {
            $this->makeDir($path);
        }

        if (substr($path, -1) !== '/') {
            $path .= '/';
        }

        $this->config['cacheFolderPath'] = MODX_BASE_PATH . $path;
        return file_exists($this->config['cacheFolderPath']);
    }

    /**
     * Get the latest cached files for current options
     *
     * @param string $prefix
     * @param string $extension
     *
     * @return array
     */
    public function getCachedFiles($prefix = '', $extension = '') {
        $cached = array();

        $regexp = $prefix . '[a-z0-9]{'.$this->config['hash_length'].'}.*';
        if (!empty($extension)) {
            $regexp .= '?' . str_replace('.', '\.', $extension);
        }

        $files = scandir($this->config['cacheFolderPath']);
        foreach ($files as $file) {
            if (preg_match("/$regexp/i", $file, $matches)) {
                $cached[] = $file;
            }
        }

        return $cached;
    }

    /**
     * Save data in cache file
     *
     * @param $data
     *
     * @return bool|string
     */
    public function saveFile($data) {
        $filename = $this->config[$this->_filetype . 'Filename'];
        if (pathinfo($filename, PATHINFO_EXTENSION) == $this->_filetype) {
            $this->_filename = $filename;
            if (file_exists($this->getFilePath())) $this->cachedFiles[] = $this->_filename;
        } else {
            $extension = $this->config[$this->_filetype.'Ext'];
            $hash = substr(sha1($data), 0, $this->config['hash_length']);
            $this->_filename = $filename . '_' . $hash . $extension;
        }
        $this->setContent($data);
        $this->processHooks($this->config['hooks']);
        if (empty($this->_filename)) return false;
        $tmp = array_flip($this->cachedFiles);
        if (!isset($tmp[$this->_filename]) || $this->config['forceUpdate']) {
            if (!file_put_contents($this->getFilePath(), $this->getContent())) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[MinifyX] Could not save cache file '. $this->config['cacheFolderPath'] . $this->_filename);
                return false;
            }
            if (!isset($tmp[$this->_filename])) $this->cachedFiles[] = $this->_filename;
        }

        return file_exists($this->getFilePath());
    }

    /**
     * Get filename of the processed file.
     * @return string
     */
    public function getFilename()
    {
        return $this->_filename;
    }

    /**
     * Set a filename for the processed file.
     * @param string $name
     * @return string
     */
    public function setFilename($name)
    {
        return $this->_filename = $name;
    }

    /**
     * Check if the process file is javascript file.
     * @param string $file
     * @return bool
     */
    public function isJs($file = null)
    {
        $file = $file ?: $this->_filename;
        return isset($file) ? pathinfo($file, PATHINFO_EXTENSION) == 'js' : false;
    }

    /**
     * Check if the process file is javascript file.
     * @param string $file
     * @return bool
     */
    public function isCss($file = null)
    {
        $file = $file ?: $this->_filename;
        return isset($file) ? pathinfo($file, PATHINFO_EXTENSION) == 'css' : false;
    }

    /**
     * Get content of the processed file.
     * @return string
     */
    public function getContent()
    {
        return $this->_content;
    }

    /**
     * Set new content.
     * @param string $content
     * @return string
     */
    public function setContent($content)
    {
        return $this->_content = $content;
    }

    /**
     * Get url of the cache file.
     * @param string $file
     * @return string
     */
    public function getFileUrl($file = null)
    {
        if (is_null($file)) $file = $this->getFilename();
        return $this->config['cacheFolder'] . $file;
    }

    /**
     * Get path of the cache file.
     * @param string $file
     * @return string
     */
    public function getFilePath($file = null)
    {
        if (is_null($file)) $file = $this->getFilename();
        return $this->config['cacheFolderPath'] . $file;
    }
    /**
     * Recursive create of directories by specified path
     *
     * @param $path
     *
     * @return bool
     */
    public function makeDir($path = '') {
        if (empty($path)) {return false;}
        elseif (file_exists($path)) {return true;}

        $base = strpos($path, MODX_CORE_PATH) !== false
            ? MODX_CORE_PATH
            : MODX_BASE_PATH;
        $tmp = explode('/', str_replace($base, '', $path));
        $path = $base;
        foreach ($tmp as $v) {
            if (!empty($v)) {
                $path .= $v . '/';
                if (!file_exists($path)) {
                    mkdir($path);
                }
            }
        }
        return file_exists($path);
    }

    /**
     * Recursive remove of a directory
     *
     * @param $dir
     *
     * @return bool
     */
    public function removeDir($dir) {
        $dir = rtrim($dir, '/');
        if (is_dir($dir)) {
            $list = scandir($dir);
            foreach ($list as $file) {
                if ($file[0] == '.') {continue;}
                elseif (is_dir($dir . '/' . $file)) {
                    $this->removeDir($dir . '/' . $file);
                }
                else {
                    @unlink($dir . '/' . $file);
                }
            }
        }
        @rmdir($dir);

        return !file_exists($dir);
    }

    /**
     * Prepares and returns path to temporary directory for storing Munee cache
     *
     * @return bool
     */
    public function getTmpDir() {
        $dir = str_replace('//', '/', $this->config['munee_cache']);

        if ($this->makeDir($dir)) {
            return $dir;
        }
        else {
            return false;
        }
    }
    
    /**
     * Removes cache files
     *
     * @return bool
     */
    public function clearCache() {
        if ($this->prepareCacheFolder()) {
            if ($this->config['forceDelete']) {
                foreach (new DirectoryIterator($this->config['cacheFolderPath']) as $file) {
                    if ($file->isFile()) unlink($file->getPathname());
                }
            } else {
                foreach ($this->cachedFiles as $file) {
                    unlink($this->config['cacheFolderPath'] . $file);
                }
            }
            $this->cachedFiles = array();
        }
        if ($dir = $this->getTmpDir()) {
            return $this->removeDir($dir);
        }

        return false;
    }

    public function getVersion()
    {
        $version = '';
        if(!empty($this->config['version'])) {
            $version = '?v=' . (($this->config['version'] == 'auto') ? substr(sha1($this->getContent()), 0, 6) : $this->config['version']);
        }
        return $version;
    }

    public function minify($value = true)
    {
        $this->config['minifyJs'] = $this->config['minifyCss'] = (bool) $value;
        return $this;
    }

    public function run()
    {
        $sources = $this->prepareSources();

        foreach ($sources as $type => $value) {
            if (empty($value)) {continue;}
            $register = $this->config['register'.ucfirst($type)];
            $placeholder = !empty($this->config[$type.'Placeholder'])
                ? $this->config[$type.'Placeholder']
                : '';

            $files = $this->prepareFiles($value, $type);
            $properties = array(
                'minify' => $this->config['minify'.ucfirst($type)]
                    ? 'true'
                    : 'false',
            );
            $result = $this->Munee($files, $properties);
            // Register file on frontend
            if ($this->saveFile($result) && $this->modx->context->key != 'mgr') {
                $link = $this->getFileUrl() . (!empty($this->config['version']) ? $this->getVersion() : '');
                $tag = str_replace('[[+file]]', $link, $type == 'css' ? $this->config['cssTpl'] : $this->config['jsTpl']);
                switch ($register) {
                    case 'placeholder':
                        if ($register == 'placeholder' && $placeholder) {
                            $this->modx->setPlaceholder($placeholder, $tag);
                        }
                        break;
                    case 'print':
                        return $tag;
                    case 'default':
                        if ($type == 'css') {
                            $this->modx->regClientCSS($tag);
                        }
                        else {
                            if ($register == 'startup') {
                                $this->modx->regClientStartupScript($tag);
                            }
                            else {
                                $this->modx->regClientScript($tag);
                            }
                        }
                        break;
                    default:
                }
            }
        }
        return $this->modx->context->key == 'mgr' ? $this->getFileUrl() : '';
    }

    public function cacheFolder($path)
    {
        if (!empty($path)) {
            $this->config['cacheFolder'] = $path;
            if (!$this->prepareCacheFolder()) {
                $this->modx->log(1, "Can't create the specified cache folder!");
            };
        }
        return $this;
    }

    public function __call($key, $parameters)
    {
        if (isset($this->config[$key])) {
            $this->config[$key] = (in_array($key, $this->parameters))
                ? $this->explodeParam($parameters[0])
                : $parameters[0];
        }
        return $this;
    }

    public function __toString()
    {
        return $this->run();
    }
}