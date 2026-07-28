<?php

declare(strict_types=1);

namespace MinifyX\Processor;

use MinifyX\Contract\AssetProcessorInterface;
use MinifyX\Contract\CssOptimizerInterface;
use MinifyX\Contract\JsOptimizerInterface;
use MinifyX\Contract\SourceCompilerInterface;
use MinifyX\Contract\SourceMapProviderInterface;
use MinifyX\Optimization\EsbuildModuleBundler;
use MinifyX\Optimization\FallbackJsOptimizer;
use MinifyX\Optimization\MatthiasCssOptimizer;

final class CssJsProcessor implements AssetProcessorInterface, SourceMapProviderInterface
{
    /** @var list<SourceCompilerInterface> */
    private array $compilers;

    private CssOptimizerInterface $cssOptimizer;

    private JsOptimizerInterface $jsOptimizer;
    private EsbuildModuleBundler $moduleBundler;
    private ?string $sourceMap = null;

    /**
     * @param list<SourceCompilerInterface> $compilers
     */
    public function __construct(
        array $compilers = [],
        ?CssOptimizerInterface $cssOptimizer = null,
        ?JsOptimizerInterface $jsOptimizer = null,
        ?EsbuildModuleBundler $moduleBundler = null
    ) {
        $this->compilers = $compilers;
        $this->cssOptimizer = $cssOptimizer ?? new MatthiasCssOptimizer();
        $this->jsOptimizer = $jsOptimizer ?? new FallbackJsOptimizer();
        $this->moduleBundler = $moduleBundler ?? new EsbuildModuleBundler();
    }

    public function process(array $absolutePaths, array $options = []): string
    {
        $type = isset($options['type']) ? (string) $options['type'] : $this->detectType($absolutePaths);
        $minify = !empty($options['minify']);
        $mangle = !empty($options['mangleJs']);
        $jsMangler = isset($options['jsMangler']) ? (string) $options['jsMangler'] : 'terser';
        $jsManglerPath = isset($options['jsManglerPath']) ? (string) $options['jsManglerPath'] : '';
        $outputPath = isset($options['outputPath']) ? (string) $options['outputPath'] : '';
        $sourceMaps = !empty($options['sourceMaps']);
        $bundleModules = $type === 'js' && !empty($options['bundleJsModules']) && !empty($options['jsModule']);
        $this->sourceMap = null;

        if ($bundleModules) {
            $result = $this->moduleBundler->bundle(
                $absolutePaths,
                $jsManglerPath,
                $minify || $mangle,
                $sourceMaps,
                $outputPath
            );
            $this->sourceMap = $this->moduleBundler->getSourceMap();

            return $result;
        }

        foreach ($this->compilers as $compiler) {
            if (method_exists($compiler, 'setSourceMaps')) {
                $compiler->setSourceMaps($sourceMaps && !$minify);
            }
        }

        /** @var array<string, string> $variables */
        $variables = [];
        if (isset($options['queryParams']) && is_array($options['queryParams'])) {
            foreach ($options['queryParams'] as $key => $value) {
                if (is_scalar($value)) {
                    $variables[(string) $key] = (string) $value;
                }
            }
        }

        $parts = [];
        $cssSourcePaths = [];
        $collectSourceMap = $sourceMaps && !$minify && count($absolutePaths) === 1;
        foreach ($absolutePaths as $path) {
            if (!is_file($path)) {
                throw new \RuntimeException(sprintf('Asset file not found: %s', $path));
            }
            $source = (string) file_get_contents($path);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === 'css') {
                $cssSourcePaths[] = $path;
            }
            $parts[] = $this->compileIfNeeded($path, $source, $ext, $variables);
            $compiler = $collectSourceMap && in_array($ext, ['scss', 'sass'], true)
                ? $this->findCompiler($ext)
                : null;
            if ($compiler instanceof SourceMapProviderInterface) {
                $this->sourceMap = $compiler->getSourceMap();
            }
        }

        $combined = implode("\n", $parts);
        if (!$minify && !$mangle) {
            return $combined;
        }

        if ($type === 'css') {
            return $this->cssOptimizer->optimize(
                $cssSourcePaths !== [] ? $cssSourcePaths : $absolutePaths,
                $combined,
                $outputPath
            );
        }

        if (method_exists($this->jsOptimizer, 'setSourceMaps')) {
            $this->jsOptimizer->setSourceMaps($sourceMaps);
        }
        $result = $this->jsOptimizer->optimize($combined, $minify, $mangle, $jsMangler, $jsManglerPath);
        if ($this->jsOptimizer instanceof SourceMapProviderInterface) {
            $this->sourceMap = $this->jsOptimizer->getSourceMap();
        }

        return $result;
    }

    public function getSourceMap(): ?string
    {
        return $this->sourceMap;
    }

    /**
     * @param array<string, string> $variables
     */
    private function compileIfNeeded(string $path, string $source, string $ext, array $variables): string
    {
        if (in_array($ext, ['css', 'js'], true)) {
            return $source;
        }
        if ($ext === 'coffee') {
            throw new UnsupportedSourceTypeException('coffee');
        }

        $compiler = $this->findCompiler($ext);
        if ($compiler !== null) {
            return $compiler->compile($path, $source, $variables);
        }

        return $source;
    }

    private function findCompiler(string $extension): ?SourceCompilerInterface
    {
        foreach ($this->compilers as $compiler) {
            if (in_array($extension, $compiler->supportedExtensions(), true)) {
                return $compiler;
            }
        }

        return null;
    }

    /**
     * @param list<string> $absolutePaths
     */
    private function detectType(array $absolutePaths): string
    {
        foreach ($absolutePaths as $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['css', 'scss', 'sass', 'less'], true)) {
                return 'css';
            }
            if (in_array($ext, ['js', 'coffee'], true)) {
                return 'js';
            }
        }

        return 'js';
    }
}
