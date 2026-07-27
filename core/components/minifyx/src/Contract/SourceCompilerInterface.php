<?php

declare(strict_types=1);

namespace MinifyX\Contract;

interface SourceCompilerInterface
{
    /**
     * @return list<string>
     */
    public function supportedExtensions(): array;

    /**
     * @param array<string, string> $variables
     */
    public function compile(string $absolutePath, string $source, array $variables = []): string;
}
