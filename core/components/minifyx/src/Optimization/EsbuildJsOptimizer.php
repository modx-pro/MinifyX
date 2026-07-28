<?php

declare(strict_types=1);

namespace MinifyX\Optimization;

use MinifyX\Contract\JsOptimizerInterface;

final class EsbuildJsOptimizer implements JsOptimizerInterface
{
    private ExternalProcessRunner $runner;

    public function __construct(?ExternalProcessRunner $runner = null)
    {
        $this->runner = $runner ?? new ExternalProcessRunner();
    }

    public function optimize(string $combined, bool $minify, bool $mangle, string $jsMangler, string $jsManglerPath): string
    {
        $binary = $this->resolveBinary($jsMangler, $jsManglerPath);
        if ($binary === null) {
            throw new JsManglerUnavailableException('esbuild binary is not available.');
        }

        $command = [$binary, '--loader=js', '--legal-comments=none'];
        if ($minify || $mangle) {
            $command[] = '--minify';
        }

        return $this->runner->run($command, $combined);
    }

    public function getBackendId(): string
    {
        return 'esbuild';
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
