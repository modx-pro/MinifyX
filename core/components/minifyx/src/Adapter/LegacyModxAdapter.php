<?php

declare(strict_types=1);

namespace MinifyX\Adapter;

use MinifyX\Contract\ModxAdapterInterface;

/**
 * Thin adapter around a legacy modX instance for MODX 2.8 and 3.x.
 */
final class LegacyModxAdapter implements ModxAdapterInterface
{
    /** @var object */
    private object $modx;

    public function __construct(object $modx)
    {
        $this->modx = $modx;
    }

    public function getRaw(): object
    {
        return $this->modx;
    }

    public function getOption(string $key, $options = null, $default = null, bool $skipEmpty = false)
    {
        if (method_exists($this->modx, 'getOption')) {
            return $this->modx->getOption($key, $options, $default, $skipEmpty);
        }

        return $default;
    }

    public function getContextKey(): string
    {
        if (isset($this->modx->context) && is_object($this->modx->context) && isset($this->modx->context->key)) {
            return (string) $this->modx->context->key;
        }

        return 'web';
    }

    public function log(int $level, string $message): void
    {
        if (method_exists($this->modx, 'log')) {
            $this->modx->log($level, $message);
        }
    }

    public function processElementTags(string $content): string
    {
        if (!method_exists($this->modx, 'getParser')) {
            return $content;
        }

        $parser = $this->modx->getParser();
        if (!is_object($parser) || !method_exists($parser, 'processElementTags')) {
            return $content;
        }

        $parser->processElementTags('', $content, false, false, '[[', ']]', [], 1);

        return $content;
    }

    public function setPlaceholder(string $key, string $value): void
    {
        if (method_exists($this->modx, 'setPlaceholder')) {
            $this->modx->setPlaceholder($key, $value);
        }
    }

    public function regClientCSS(string $tag): void
    {
        if (method_exists($this->modx, 'regClientCSS')) {
            $this->modx->regClientCSS($tag);
        }
    }

    public function regClientScript(string $tag): void
    {
        if (method_exists($this->modx, 'regClientScript')) {
            $this->modx->regClientScript($tag);
        }
    }

    public function regClientStartupScript(string $tag): void
    {
        if (method_exists($this->modx, 'regClientStartupScript')) {
            $this->modx->regClientStartupScript($tag);
        }
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function runSnippet(string $name, array $properties = [])
    {
        if (method_exists($this->modx, 'runSnippet')) {
            return $this->modx->runSnippet($name, $properties);
        }

        return null;
    }

    public function getResourceId(): ?int
    {
        if (isset($this->modx->resource) && is_object($this->modx->resource) && isset($this->modx->resource->id)) {
            return (int) $this->modx->resource->id;
        }

        return null;
    }

    public function getSiteUrl(): string
    {
        return (string) $this->getOption('site_url', null, '');
    }

    public function getBasePath(): string
    {
        return defined('MODX_BASE_PATH') ? (string) MODX_BASE_PATH : (string) $this->getOption('base_path', null, '');
    }

    public function getCorePath(): string
    {
        return defined('MODX_CORE_PATH') ? (string) MODX_CORE_PATH : (string) $this->getOption('core_path', null, '');
    }

    public function getAssetsPath(): string
    {
        if (defined('MODX_ASSETS_PATH')) {
            return (string) MODX_ASSETS_PATH;
        }

        return (string) $this->getOption('assets_path', null, '');
    }
}
