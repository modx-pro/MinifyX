<?php

declare(strict_types=1);

namespace MinifyX\Contract;

interface JsOptimizerInterface
{
    public function optimize(
        string $combined,
        bool $minify,
        bool $mangle,
        string $jsMangler,
        string $jsManglerPath
    ): string;

    public function getBackendId(): string;
}
