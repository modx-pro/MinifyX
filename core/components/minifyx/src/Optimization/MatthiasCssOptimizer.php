<?php

declare(strict_types=1);

namespace MinifyX\Optimization;

use MatthiasMullie\Minify\CSS;
use MinifyX\Contract\CssOptimizerInterface;

final class MatthiasCssOptimizer implements CssOptimizerInterface
{
    /** @var array<string, string> */
    private array $sentinels = [];

    /**
     * @param list<string> $absolutePaths
     */
    public function optimize(array $absolutePaths, string $combined, string $outputPath): string
    {
        if ($absolutePaths !== [] && $outputPath !== '' && $this->allCssFiles($absolutePaths)) {
            $minifier = new CSS();
            $tempFiles = [];
            try {
                foreach ($absolutePaths as $path) {
                    $protectedPath = $this->writeProtectedCopy($path);
                    $tempFiles[] = $protectedPath;
                    $minifier->add($protectedPath);
                }
                $minified = $minifier->minify($outputPath);
            } finally {
                foreach ($tempFiles as $tempFile) {
                    @unlink($tempFile);
                }
            }

            return $this->restoreEmptyUrls($minified);
        }

        $protected = $this->protectEmptyUrls($combined);
        $minifier = new CSS();
        $minifier->add($protected);

        return $this->restoreEmptyUrls($minifier->minify());
    }

    /**
     * @param list<string> $absolutePaths
     */
    private function allCssFiles(array $absolutePaths): bool
    {
        foreach ($absolutePaths as $path) {
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'css') {
                return false;
            }
        }

        return true;
    }

    private function writeProtectedCopy(string $path): string
    {
        $content = (string) file_get_contents($path);
        $protected = $this->protectEmptyUrls($content);
        $temp = dirname($path) . '/.minifyx-' . basename($path) . '.' . bin2hex(random_bytes(4)) . '.css';
        file_put_contents($temp, $protected, LOCK_EX);

        return $temp;
    }

    private function protectEmptyUrls(string $css): string
    {
        $this->sentinels = [];
        $index = 0;

        return (string) preg_replace_callback(
            '/url\(\s*([\'"]?)\s*\1\s*\)/',
            function (array $matches) use (&$index): string {
                $quote = $matches[1] !== '' ? $matches[1] : '';
                $sentinel = 'url(data:minifyx-empty-' . $index . ')';
                $this->sentinels[$sentinel] = 'url(' . $quote . $quote . ')';
                ++$index;

                return $sentinel;
            },
            $css
        );
    }

    private function restoreEmptyUrls(string $css): string
    {
        if ($this->sentinels === []) {
            return $css;
        }

        return str_replace(array_keys($this->sentinels), array_values($this->sentinels), $css);
    }
}
