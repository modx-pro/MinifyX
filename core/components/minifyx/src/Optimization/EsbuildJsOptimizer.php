<?php

declare(strict_types=1);

namespace MinifyX\Optimization;

use MinifyX\Contract\JsOptimizerInterface;
use MinifyX\Contract\SourceMapProviderInterface;

final class EsbuildJsOptimizer implements JsOptimizerInterface, SourceMapProviderInterface
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
        $binary = $this->resolveBinary($jsMangler, $jsManglerPath);
        if ($binary === null) {
            throw new JsManglerUnavailableException('esbuild binary is not available.');
        }

        $command = [$binary, '--loader=js', '--legal-comments=none'];
        if ($minify || $mangle) {
            $command[] = '--minify';
        }
        if ($this->sourceMaps) {
            $command[] = '--sourcemap=inline';
        }

        $result = InlineSourceMap::extract($this->runner->run($command, $combined));
        $this->sourceMap = $result['map'];

        return $result['code'];
    }

    public function getBackendId(): string
    {
        return 'esbuild';
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

    private function resolveBinary(string $jsMangler, string $jsManglerPath): ?string
    {
        if ($jsMangler !== '' && $jsMangler !== 'esbuild') {
            return null;
        }
        if ($jsManglerPath !== '' && $this->runner->isExecutableAvailable($jsManglerPath)) {
            return $jsManglerPath;
        }
        if ($this->runner->isExecutableAvailable('esbuild')) {
            return 'esbuild';
        }

        return null;
    }
}
