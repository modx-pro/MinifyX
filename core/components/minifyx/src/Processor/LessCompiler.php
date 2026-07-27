<?php

declare(strict_types=1);

namespace MinifyX\Processor;

use Less_Parser;
use MinifyX\Contract\SourceCompilerInterface;

final class LessCompiler implements SourceCompilerInterface
{
    public function supportedExtensions(): array
    {
        return ['less'];
    }

    public function compile(string $absolutePath, string $source, array $variables = []): string
    {
        $parser = new Less_Parser([
            'compress' => false,
        ]);
        $importDir = dirname($absolutePath);
        $parser->SetImportDirs([
            $importDir => static function (string $path) use ($importDir): ?array {
                $file = $importDir . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
                if (!is_file($file)) {
                    return null;
                }

                return [$file, file_get_contents($file)];
            },
        ]);
        if ($variables !== []) {
            $parser->ModifyVars($variables);
        }
        $parser->parseFile($absolutePath);

        return $parser->getCss();
    }
}
