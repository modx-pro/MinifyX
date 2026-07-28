<?php

declare(strict_types=1);

namespace MinifyX\Optimization;

use MinifyX\Contract\JsOptimizerInterface;

final class FallbackJsOptimizer implements JsOptimizerInterface
{
    private PhpJsOptimizer $phpOptimizer;
    private TerserJsOptimizer $terserOptimizer;
    private EsbuildJsOptimizer $esbuildOptimizer;
    /** @var callable|null */
    private $logger;

    /**
     * @param callable|null $logger function(string $message): void
     */
    public function __construct(?callable $logger = null)
    {
        $this->logger = $logger;
        $this->phpOptimizer = new PhpJsOptimizer();
        $this->terserOptimizer = new TerserJsOptimizer();
        $this->esbuildOptimizer = new EsbuildJsOptimizer();
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
            return $optimizer->optimize($combined, $minify, $mangle, $jsMangler, $jsManglerPath);
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
}
