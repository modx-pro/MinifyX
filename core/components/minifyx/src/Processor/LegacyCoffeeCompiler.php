<?php

declare(strict_types=1);

namespace MinifyX\Processor;

use MinifyX\Contract\SourceCompilerInterface;
use MinifyX\Legacy\CoffeeScript\Compiler as CoffeeCompiler;

/**
 * Isolated legacy CoffeeScript adapter. Marked for removal in the next major.
 */
final class LegacyCoffeeCompiler implements SourceCompilerInterface
{
    private bool $warned = false;

    /** @var callable|null */
    private $logger;

    public function __construct(?callable $logger = null)
    {
        $this->logger = $logger;
    }

    public function supportedExtensions(): array
    {
        return ['coffee'];
    }

    public function compile(string $absolutePath, string $source, array $variables = []): string
    {
        if (!$this->warned && $this->logger !== null) {
            ($this->logger)(
                'CoffeeScript support is deprecated and will be removed in MinifyX 3.0. Precompile .coffee files.'
            );
            $this->warned = true;
        }

        return CoffeeCompiler::compile($source, ['filename' => $absolutePath]);
    }
}
