<?php

declare(strict_types=1);

namespace MinifyX\Processor;

use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;
use MinifyX\Contract\AssetProcessorInterface;
use MinifyX\Contract\SourceCompilerInterface;

final class CssJsProcessor implements AssetProcessorInterface
{
    /** @var list<SourceCompilerInterface> */
    private array $compilers;

    /**
     * @param list<SourceCompilerInterface> $compilers
     */
    public function __construct(array $compilers = [])
    {
        $this->compilers = $compilers;
    }

    public function process(array $absolutePaths, array $options = []): string
    {
        $type = $options['type'] ?? $this->detectType($absolutePaths);
        $minify = (bool) ($options['minify'] ?? false);
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

        foreach ($absolutePaths as $path) {
            if (!is_file($path)) {
                throw new \RuntimeException(sprintf('Asset file not found: %s', $path));
            }
            $source = (string) file_get_contents($path);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $parts[] = $this->compileIfNeeded($path, $source, $ext, $variables);
        }

        $combined = implode("\n", $parts);
        if (!$minify) {
            return $combined;
        }

        if ($type === 'css') {
            $minifier = new CSS();
            $minifier->add($combined);

            return $minifier->minify();
        }

        $minifier = new JS();
        $minifier->add($combined);

        return $minifier->minify();
    }

    /**
     * @param array<string, string> $variables
     */
    private function compileIfNeeded(string $path, string $source, string $ext, array $variables): string
    {
        if (in_array($ext, ['css', 'js'], true)) {
            return $source;
        }

        foreach ($this->compilers as $compiler) {
            if (in_array($ext, $compiler->supportedExtensions(), true)) {
                return $compiler->compile($path, $source, $variables);
            }
        }

        return $source;
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
