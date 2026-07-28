<?php

declare(strict_types=1);

namespace MinifyX\Optimization;

use MinifyX\Contract\JsOptimizerInterface;

final class TerserJsOptimizer implements JsOptimizerInterface
{
    private ExternalProcessRunner $runner;

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

        return $this->runner->run($command, $combined);
    }

    public function getBackendId(): string
    {
        return 'terser';
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
