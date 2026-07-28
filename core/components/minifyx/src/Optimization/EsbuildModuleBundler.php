<?php

declare(strict_types=1);

namespace MinifyX\Optimization;

use MinifyX\Contract\SourceMapProviderInterface;

final class EsbuildModuleBundler implements SourceMapProviderInterface
{
    private ExternalProcessRunner $runner;
    private ?string $sourceMap = null;

    public function __construct(?ExternalProcessRunner $runner = null)
    {
        $this->runner = $runner ?? new ExternalProcessRunner();
    }

    /**
     * @param list<string> $entryPaths
     */
    public function bundle(
        array $entryPaths,
        string $binaryPath,
        bool $minify,
        bool $sourceMaps,
        string $outputPath
    ): string {
        $this->sourceMap = null;
        $binary = $binaryPath !== '' ? $binaryPath : 'esbuild';
        if (!$this->runner->isExecutableAvailable($binary)) {
            throw new JsManglerUnavailableException('esbuild binary is not available.');
        }

        $directory = dirname($outputPath);
        $token = bin2hex(random_bytes(8));
        $entry = $directory . DIRECTORY_SEPARATOR . '.minifyx-entry-' . $token . '.mjs';
        $output = $directory . DIRECTORY_SEPARATOR . '.minifyx-output-' . $token . '.js';
        $imports = [];
        foreach ($entryPaths as $path) {
            $imports[] = 'import ' . json_encode($path, JSON_UNESCAPED_SLASHES) . ';';
        }
        if (file_put_contents($entry, implode("\n", $imports), LOCK_EX) === false) {
            throw new \RuntimeException('Could not create temporary esbuild entry.');
        }

        $command = [$binary, $entry, '--bundle', '--format=esm', '--legal-comments=none', '--outfile=' . $output];
        if ($minify) {
            $command[] = '--minify';
        }
        if ($sourceMaps) {
            $command[] = '--sourcemap=external';
        }

        try {
            $this->runner->run($command);
            $content = file_get_contents($output);
            if (!is_string($content)) {
                throw new \RuntimeException('esbuild did not produce an output file.');
            }
            $mapPath = $output . '.map';
            $map = $sourceMaps && is_file($mapPath) ? file_get_contents($mapPath) : false;
            $this->sourceMap = is_string($map) ? $map : null;
            $content = preg_replace('~\s*//# sourceMappingURL=.*$~m', '', $content) ?? $content;

            return rtrim($content);
        } finally {
            @unlink($entry);
            @unlink($output);
            @unlink($output . '.map');
        }
    }

    public function getSourceMap(): ?string
    {
        return $this->sourceMap;
    }
}
