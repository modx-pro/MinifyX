<?php

declare(strict_types=1);

namespace MinifyX\Optimization;

use MinifyX\Contract\JsOptimizerInterface;
use MinifyX\Contract\SourceMapProviderInterface;

final class FallbackJsOptimizer implements JsOptimizerInterface, SourceMapProviderInterface
{
    private PhpJsOptimizer $phpOptimizer;
    private TerserJsOptimizer $terserOptimizer;
    private EsbuildJsOptimizer $esbuildOptimizer;
    private ?string $sourceMap = null;
    /** @var callable|null */
    private $logger;

    /**
     * @param callable|null $logger function(string $message): void
     */
    public function __construct(?callable $logger = null, ?ExternalProcessRunner $runner = null)
    {
        $this->logger = $logger;
        $this->phpOptimizer = new PhpJsOptimizer();
        $this->terserOptimizer = new TerserJsOptimizer($runner);
        $this->esbuildOptimizer = new EsbuildJsOptimizer($runner);
    }

    public function optimize(
        string $combined,
        bool $minify,
        bool $mangle,
        string $jsMangler,
        string $jsManglerPath
    ): string {
        if (!$minify && !$mangle) {
            return $combined;
        }

        if (!$mangle) {
            return $this->phpOptimizer->optimize($combined, $minify, false, $jsMangler, $jsManglerPath);
        }

        $optimizer = $jsMangler === 'esbuild' ? $this->esbuildOptimizer : $this->terserOptimizer;

        try {
            $result = $optimizer->optimize($combined, $minify, $mangle, $jsMangler, $jsManglerPath);
            $this->sourceMap = $optimizer->getSourceMap();

            return $result;
        } catch (JsManglerUnavailableException $e) {
            if ($this->logger !== null) {
                ($this->logger)('[MinifyX] JS mangler unavailable, falling back to PHP minifier.');
            }

            return $this->phpOptimizer->optimize($combined, $minify, false, $jsMangler, $jsManglerPath);
        }
    }

    public function getBackendId(): string
    {
        return 'fallback';
    }

    public function setSourceMaps(bool $sourceMaps): void
    {
        $this->sourceMap = null;
        $this->terserOptimizer->setSourceMaps($sourceMaps);
        $this->esbuildOptimizer->setSourceMaps($sourceMaps);
    }

    public function getSourceMap(): ?string
    {
        return $this->sourceMap;
    }
}
