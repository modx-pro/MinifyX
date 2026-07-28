<?php

declare(strict_types=1);

namespace MinifyX\Optimization;

use MinifyX\Contract\JsOptimizerInterface;
use MinifyX\Contract\SourceMapProviderInterface;

final class TerserJsOptimizer implements JsOptimizerInterface, SourceMapProviderInterface
{
    private ExternalProcessRunner $runner;
    private bool $sourceMaps = false;
    private ?string $sourceMap = null;

    public function __construct(?ExternalProcessRunner $runner = null)
    {
        $this->runner = $runner ?? new ExternalProcessRunner();
    }

    public function optimize(
        string $combined,
        bool $minify,
        bool $mangle,
        string $jsMangler,
        string $jsManglerPath
    ): string {
        $binary = $this->resolveBinary($jsMangler, $jsManglerPath, 'terser');
        if ($binary === null) {
            throw new JsManglerUnavailableException('Terser binary is not available.');
        }

        $command = [$binary];
        if ($minify || $mangle) {
            $command[] = '--compress';
        }
        if ($mangle) {
            $command[] = '--mangle';
        }
        $command[] = '--comments';
        $command[] = '/^!/';
        if ($this->sourceMaps) {
            $command[] = '--source-map';
            $command[] = 'url=inline';
        }

        $result = InlineSourceMap::extract($this->runner->run($command, $combined));
        $this->sourceMap = $result['map'];

        return $result['code'];
    }

    public function getBackendId(): string
    {
        return 'terser';
    }

    public function setSourceMaps(bool $sourceMaps): void
    {
        $this->sourceMaps = $sourceMaps;
        $this->sourceMap = null;
    }

    public function getSourceMap(): ?string
    {
        return $this->sourceMap;
    }

    private function resolveBinary(string $jsMangler, string $jsManglerPath, string $defaultName): ?string
    {
        if ($jsMangler !== '' && $jsMangler !== 'terser' && $jsMangler !== 'esbuild') {
            return null;
        }
        if ($jsManglerPath !== '' && $this->runner->isExecutableAvailable($jsManglerPath)) {
            return $jsManglerPath;
        }
        if ($this->runner->isExecutableAvailable($defaultName)) {
            return $defaultName;
        }

        return null;
    }
}
