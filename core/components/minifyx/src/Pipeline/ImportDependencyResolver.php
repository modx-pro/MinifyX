<?php

declare(strict_types=1);

namespace MinifyX\Pipeline;

final class ImportDependencyResolver
{
    private string $webroot;

    /** @var array<string, true> */
    private array $visited = [];

    /** @var list<string> */
    private array $resolved = [];

    public function __construct(string $webroot)
    {
        $real = realpath($webroot);
        if ($real === false || !is_dir($real)) {
            throw new \InvalidArgumentException('Webroot must be an existing directory.');
        }
        $this->webroot = rtrim($real, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Returns imported files only; source files remain compiler entry points.
     *
     * @param list<string> $sourcePaths
     * @return list<string>
     */
    public function resolve(array $sourcePaths): array
    {
        $this->visited = [];
        $this->resolved = [];
        foreach ($sourcePaths as $sourcePath) {
            $real = realpath($sourcePath);
            if ($real === false || !$this->isInsideWebroot($real)) {
                continue;
            }
            $this->walk($real, true);
        }

        return $this->resolved;
    }

    private function walk(string $path, bool $root = false): void
    {
        if (isset($this->visited[$path])) {
            return;
        }
        $this->visited[$path] = true;
        if (!$root) {
            $this->resolved[] = $path;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['scss', 'sass', 'less'], true)) {
            return;
        }
        $source = file_get_contents($path);
        if (!is_string($source)) {
            return;
        }

        foreach ($this->extractImports($source, $extension) as $import) {
            $resolved = $this->resolveImport($path, $import, $extension);
            if ($resolved !== null) {
                $this->walk($resolved);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function extractImports(string $source, string $extension): array
    {
        $directives = $extension === 'less' ? 'import' : 'import|use|forward';
        preg_match_all('/@(?:' . $directives . ')\s+([^;]+);/i', $source, $statements);

        $imports = [];
        foreach ($statements[1] as $statement) {
            preg_match_all('/[\'"]([^\'"]+)[\'"]/', (string) $statement, $matches);
            foreach ($matches[1] as $import) {
                $import = trim((string) $import);
                if (
                    $import === ''
                    || preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|~)#i', $import)
                    || str_contains($import, '#{')
                ) {
                    continue;
                }
                $imports[] = $import;
            }
        }

        return $imports;
    }

    private function resolveImport(string $sourcePath, string $import, string $extension): ?string
    {
        $importPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $import);
        $base = dirname($sourcePath) . DIRECTORY_SEPARATOR . ltrim($importPath, DIRECTORY_SEPARATOR);
        $providedExtension = pathinfo($base, PATHINFO_EXTENSION) !== '';
        $extensions = $providedExtension ? [''] : ($extension === 'less' ? ['.less'] : ['.scss', '.sass']);
        $candidates = [];

        foreach ($extensions as $suffix) {
            $candidate = $base . $suffix;
            $candidates[] = $candidate;
            $candidates[] = dirname($candidate) . DIRECTORY_SEPARATOR . '_' . basename($candidate);
            if (!$providedExtension) {
                $candidates[] = $base . DIRECTORY_SEPARATOR . 'index' . $suffix;
                $candidates[] = $base . DIRECTORY_SEPARATOR . '_index' . $suffix;
            }
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            $real = realpath($candidate);
            if ($real !== false && is_file($real) && $this->isInsideWebroot($real)) {
                return $real;
            }
        }

        return null;
    }

    private function isInsideWebroot(string $path): bool
    {
        $normalized = rtrim($path, '/\\');
        $root = rtrim($this->webroot, '/\\');

        return $normalized === $root || str_starts_with($normalized, $this->webroot);
    }
}
