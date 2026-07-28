<?php

declare(strict_types=1);

namespace MinifyX\Contract;

interface SourceMapProviderInterface
{
    public function getSourceMap(): ?string;
}
