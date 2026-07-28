<?php

declare(strict_types=1);

namespace MinifyX\Adapter;

use MinifyX\Contract\ModxAdapterInterface;

/**
 * In-memory MODX adapter for unit and characterization tests.
 */
final class ArrayModxAdapter implements ModxAdapterInterface
{
    /** @var array<string, mixed> */
    private array $options;

    /** @var list<array{level: int, message: string}> */
    public array $logs = [];

    /** @var array<string, string> */
    public array $placeholders = [];

    /** @var list<string> */
    public array $clientCss = [];

    /** @var list<string> */
    public array $clientScripts = [];

    /** @var list<string> */
    public array $startupScripts = [];

    private string $contextKey;
    private string $basePath;
    private string $corePath;
    private string $assetsPath;
    private ?int $resourceId;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        array $options = [],
        string $contextKey = 'web',
        string $basePath = '',
        string $corePath = '',
        string $assetsPath = '',
        ?int $resourceId = 1
    ) {
        $this->options = $options;
        $this->contextKey = $contextKey;
        $this->basePath = rtrim($basePath, '/') . '/';
        $this->corePath = rtrim($corePath, '/') . '/';
        $this->assetsPath = rtrim($assetsPath, '/') . '/';
        $this->resourceId = $resourceId;
    }

    public function getOption(string $key, $options = null, $default = null, bool $skipEmpty = false)
    {
        if (is_array($options) && array_key_exists($key, $options)) {
            $value = $options[$key];
        } else {
            $value = $this->options[$key] ?? $default;
        }

        if ($skipEmpty && ($value === '' || $value === null)) {
            return $default;
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    public function setOption(string $key, $value): void
    {
        $this->options[$key] = $value;
    }

    public function getContextKey(): string
    {
        return $this->contextKey;
    }

    public function setContextKey(string $key): void
    {
        $this->contextKey = $key;
    }

    public function log(int $level, string $message): void
    {
        $this->logs[] = ['level' => $level, 'message' => $message];
    }

    public function processElementTags(string $content): string
    {
        return preg_replace_callback('/\[\[\+\+([^\]]+)\]\]/', function (array $matches): string {
            $key = trim($matches[1]);

            return (string) ($this->options[$key] ?? '');
        }, $content) ?? $content;
    }

    public function setPlaceholder(string $key, string $value): void
    {
        $this->placeholders[$key] = $value;
    }

    public function regClientCSS(string $tag): void
    {
        $this->clientCss[] = $tag;
    }

    public function regClientScript(string $tag): void
    {
        $this->clientScripts[] = $tag;
    }

    public function regClientStartupScript(string $tag): void
    {
        $this->startupScripts[] = $tag;
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function runSnippet(string $name, array $properties = [])
    {
        return null;
    }

    public function getResourceId(): ?int
    {
        return $this->resourceId;
    }

    public function getSiteUrl(): string
    {
        return (string) $this->getOption('site_url', null, 'http://example.test/');
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getCorePath(): string
    {
        return $this->corePath;
    }

    public function getAssetsPath(): string
    {
        return $this->assetsPath;
    }
}
