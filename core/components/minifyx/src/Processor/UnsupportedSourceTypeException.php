<?php

declare(strict_types=1);

namespace MinifyX\Processor;

final class UnsupportedSourceTypeException extends \RuntimeException
{
    public function __construct(private readonly string $sourceType)
    {
        parent::__construct(sprintf(
            'Unsupported source type ".%s". Precompile it before passing it to MinifyX.',
            $sourceType
        ));
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }
}
