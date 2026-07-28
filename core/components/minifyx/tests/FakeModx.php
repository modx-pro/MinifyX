<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Model\MinifyX;

final class FakeModx
{
    public object $context;
    public object $resource;
    /** @var array<string, mixed> */
    public array $options = [];
    /** @var list<array{0:int,1:string}> */
    public array $logs = [];
    /** @var array<string, string> */
    public array $placeholders = [];
    /** @var list<string> */
    public array $css = [];
    /** @var list<string> */
    public array $scripts = [];
    /** @var list<string> */
    public array $startup = [];

    public function __construct(string $contextKey = 'web')
    {
        $this->context = (object) ['key' => $contextKey];
        $this->resource = (object) ['id' => 1, '_output' => ''];
        $this->options = [
            'site_url' => 'http://example.test/',
            'core_path' => MODX_CORE_PATH,
            'assets_path' => MODX_ASSETS_PATH,
            'base_path' => MODX_BASE_PATH,
        ];
    }

    /**
     * @param mixed $options
     * @param mixed $default
     * @return mixed
     */
    public function getOption(string $key, $options = null, $default = null, bool $skipEmpty = false)
    {
        if (is_array($options) && array_key_exists($key, $options)) {
            return $options[$key];
        }

        return $this->options[$key] ?? $default;
    }

    public function log(int $level, string $message): void
    {
        $this->logs[] = [$level, $message];
    }

    public function getParser(): object
    {
        return new class {
            /**
             * @param mixed $a
             * @param mixed $content
             * @param mixed $b
             * @param mixed $c
             * @param mixed $d
             * @param mixed $e
             * @param mixed $f
             * @param mixed $g
             */
            public function processElementTags(
                $a,
                &$content,
                $b = false,
                $c = false,
                $d = '[[',
                $e = ']]',
                $f = [],
                $g = 1
            ): void {
                // no-op for tests
            }
        };
    }

    public function setPlaceholder(string $key, string $value): void
    {
        $this->placeholders[$key] = $value;
    }

    public function regClientCSS(string $tag): void
    {
        $this->css[] = $tag;
    }

    public function regClientScript(string $tag): void
    {
        $this->scripts[] = $tag;
    }

    public function regClientStartupScript(string $tag): void
    {
        $this->startup[] = $tag;
    }

    /**
     * @param array<string, mixed> $properties
     * @return mixed
     */
    public function runSnippet(string $name, array $properties = [])
    {
        return null;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function getService(string $name, string $class, string $path, array $config = []): object
    {
        require_once dirname(__DIR__) . '/model/minifyx/minifyx.class.php';

        return new MinifyX($this, $config);
    }
}
