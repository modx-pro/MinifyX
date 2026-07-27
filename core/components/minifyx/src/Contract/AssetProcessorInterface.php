<?php

declare(strict_types=1);

namespace MinifyX\Contract;

interface AssetProcessorInterface
{
    /**
     * Combine and optionally minify a list of absolute file paths.
     *
     * @param list<string> $absolutePaths
     * @param array{minify?: bool, type?: string, queryParams?: array<string, string>} $options
     */
    public function process(array $absolutePaths, array $options = []): string;
}
