<?php

declare(strict_types=1);

namespace MinifyX\Contract;

interface ImageProcessorInterface
{
    /**
     * @param array{width?: int|null, height?: int|null, filters?: string} $options
     */
    public function transform(string $absolutePath, array $options = []): string;

    public function contentType(string $absolutePath): string;
}
