<?php

declare(strict_types=1);

namespace MinifyX\Contract;

interface CssOptimizerInterface
{
    /**
     * @param list<string> $absolutePaths
     */
    public function optimize(array $absolutePaths, string $combined, string $outputPath): string;
}
