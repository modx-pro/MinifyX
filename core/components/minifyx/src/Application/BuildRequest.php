<?php

declare(strict_types=1);

namespace MinifyX\Application;

final class BuildRequest
{
    /** @var list<string> */
    private array $absolutePaths;

    /** @var array<string, string> */
    private array $queryParams;

    private string $type;
    private bool $minify;
    private bool $mangleJs;
    private string $jsMangler;
    private string $jsManglerPath;
    private string $outputPath;
    private string $basename;
    private string $extension;

    /**
     * @param list<string> $absolutePaths
     * @param array<string, string> $queryParams
     */
    public function __construct(
        array $absolutePaths,
        string $type,
        bool $minify,
        bool $mangleJs,
        string $jsMangler,
        string $jsManglerPath,
        string $outputPath,
        string $basename,
        string $extension,
        array $queryParams = []
    ) {
        $this->absolutePaths = $absolutePaths;
        $this->type = $type;
        $this->minify = $minify;
        $this->mangleJs = $mangleJs;
        $this->jsMangler = $jsMangler;
        $this->jsManglerPath = $jsManglerPath;
        $this->outputPath = $outputPath;
        $this->basename = $basename;
        $this->extension = $extension;
        $this->queryParams = $queryParams;
    }

    /** @return list<string> */
    public function getAbsolutePaths(): array
    {
        return $this->absolutePaths;
    }

    /** @return array<string, string> */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isMinify(): bool
    {
        return $this->minify;
    }

    public function isMangleJs(): bool
    {
        return $this->mangleJs;
    }

    public function getJsMangler(): string
    {
        return $this->jsMangler;
    }

    public function getJsManglerPath(): string
    {
        return $this->jsManglerPath;
    }

    public function getOutputPath(): string
    {
        return $this->outputPath;
    }

    public function getBasename(): string
    {
        return $this->basename;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * @param list<string> $hooks
     * @return array<string, mixed>
     */
    public function toProcessorOptions(array $hooks = []): array
    {
        return [
            'minify' => $this->minify || $this->mangleJs,
            'mangleJs' => $this->mangleJs,
            'type' => $this->type,
            'queryParams' => $this->queryParams,
            'jsMangler' => $this->jsMangler,
            'jsManglerPath' => $this->jsManglerPath,
            'outputPath' => $this->outputPath,
            'hooks' => $hooks,
            'jsBackend' => $this->mangleJs ? $this->jsMangler : 'php-minify',
        ];
    }

    /**
     * @param list<string> $hooks
     * @return array<string, mixed>
     */
    public function toSignatureOptions(array $hooks = []): array
    {
        $options = $this->toProcessorOptions($hooks);
        unset($options['outputPath'], $options['hooks']);

        return $options;
    }
}
