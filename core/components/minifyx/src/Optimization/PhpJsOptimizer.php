<?php

declare(strict_types=1);

namespace MinifyX\Optimization;

use MatthiasMullie\Minify\JS;
use MinifyX\Contract\JsOptimizerInterface;

final class PhpJsOptimizer implements JsOptimizerInterface
{
    public function optimize(string $combined, bool $minify, bool $mangle, string $jsMangler, string $jsManglerPath): string
    {
        if (!$minify && !$mangle) {
            return $combined;
        }

        $minifier = new JS();
        $minifier->add($combined);

        return $minifier->minify();
    }

    public function getBackendId(): string
    {
        return 'php-minify';
    }
}
