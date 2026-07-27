<?php

declare(strict_types=1);

namespace MinifyX\Processor;

use MinifyX\Contract\SourceCompilerInterface;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
use ScssPhp\ScssPhp\ValueConverter;

final class ScssCompiler implements SourceCompilerInterface
{
    public function supportedExtensions(): array
    {
        return ['scss', 'sass'];
    }

    public function compile(string $absolutePath, string $source, array $variables = []): string
    {
        $compiler = new Compiler();
        $compiler->setOutputStyle(OutputStyle::EXPANDED);
        $compiler->setImportPaths([dirname($absolutePath)]);
        if ($variables !== []) {
            $converted = [];
            foreach ($variables as $key => $value) {
                $converted[$key] = ValueConverter::parseValue($value);
            }
            $compiler->addVariables($converted);
        }
        $result = $compiler->compileString($source, $absolutePath);

        return $result->getCss();
    }
}
