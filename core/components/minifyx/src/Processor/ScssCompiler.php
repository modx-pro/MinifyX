<?php

declare(strict_types=1);

namespace MinifyX\Processor;

use League\Uri\Uri;
use MinifyX\Contract\SourceCompilerInterface;
use MinifyX\Contract\SourceMapProviderInterface;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
use ScssPhp\ScssPhp\Syntax;
use ScssPhp\ScssPhp\ValueConverter;

final class ScssCompiler implements SourceCompilerInterface, SourceMapProviderInterface
{
    private bool $sourceMaps = false;
    private ?string $sourceMap = null;

    /** @var list<string> */
    private array $includedFiles = [];

    public function supportedExtensions(): array
    {
        return ['scss', 'sass'];
    }

    public function compile(string $absolutePath, string $source, array $variables = []): string
    {
        $compiler = new Compiler();
        $compiler->setOutputStyle(OutputStyle::EXPANDED);
        $compiler->setImportPaths([dirname($absolutePath)]);
        if ($this->sourceMaps) {
            $compiler->setSourceMap(Compiler::SOURCE_MAP_FILE);
            $compiler->setSourceMapOptions([
                'sourceMapBasepath' => dirname($absolutePath),
                'outputSourceFiles' => true,
            ]);
        }
        if ($variables !== []) {
            $converted = [];
            foreach ($variables as $key => $value) {
                $converted[$key] = ValueConverter::parseValue($value);
            }
            $compiler->addVariables($converted);
        }
        $result = $compiler->compileString(
            $source,
            Uri::new($absolutePath),
            syntax: Syntax::forPath($absolutePath)
        );
        $this->sourceMap = $result->getSourceMap();
        $this->includedFiles = array_values(array_map(
            self::normalizeIncludedFile(...),
            $result->getIncludedFiles()
        ));

        return $result->getCss();
    }

    public function setSourceMaps(bool $enabled): void
    {
        $this->sourceMaps = $enabled;
        $this->sourceMap = null;
        $this->includedFiles = [];
    }

    public function getSourceMap(): ?string
    {
        return $this->sourceMap;
    }

    /** @return list<string> */
    public function getIncludedFiles(): array
    {
        return $this->includedFiles;
    }

    private static function normalizeIncludedFile(string $file): string
    {
        $path = $file;
        if (str_starts_with($file, 'file://')) {
            $path = urldecode((string) parse_url($file, PHP_URL_PATH));
        }

        return realpath($path) ?: $path;
    }
}
