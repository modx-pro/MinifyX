<?php

declare(strict_types=1);

namespace MinifyX\Diagnostics;

use MinifyX\Optimization\ExternalProcessRunner;
use MinifyX\Support\SigningKeys;

final class HealthCheck
{
    private string $cachePath;
    private string $terserPath;
    private string $esbuildPath;

    /** @var list<string> */
    private array $signingKeys;

    private ExternalProcessRunner $runner;

    /**
     * @param string|list<string> $signingKeys
     */
    public function __construct(
        string $cachePath,
        string $terserPath = 'terser',
        string $esbuildPath = 'esbuild',
        $signingKeys = '',
        ?ExternalProcessRunner $runner = null
    ) {
        $cachePath = rtrim($cachePath, '/\\');
        $this->cachePath = $cachePath === '' ? '' : $cachePath . DIRECTORY_SEPARATOR;
        $this->terserPath = $terserPath;
        $this->esbuildPath = $esbuildPath;
        $this->signingKeys = SigningKeys::normalize($signingKeys);
        $this->runner = $runner ?? new ExternalProcessRunner();
    }

    /**
     * @return array{
     *   healthy: bool,
     *   checks: array<string, array{available: bool, required: bool, message: string}>
     * }
     */
    public function run(): array
    {
        $cacheWritable = $this->checkCacheWritable();
        $checks = [
            'cacheWritable' => $this->check($cacheWritable, true, $cacheWritable ? 'Writable.' : 'Not writable.'),
            'gd' => $this->check(extension_loaded('gd'), false, 'Optional image driver.'),
            'imagick' => $this->check(
                extension_loaded('imagick') && class_exists('Imagick'),
                false,
                'Optional image driver.'
            ),
            'terser' => $this->check(
                $this->runner->isExecutableAvailable($this->terserPath),
                false,
                'Optional JS mangler.'
            ),
            'esbuild' => $this->check(
                $this->runner->isExecutableAvailable($this->esbuildPath),
                false,
                'Optional JS mangler.'
            ),
            'signingConfigured' => $this->check($this->signingKeys !== [], false, 'Recommended for image URLs.'),
        ];

        return ['healthy' => $cacheWritable, 'checks' => $checks];
    }

    /**
     * @return array{available: bool, required: bool, message: string}
     */
    private function check(bool $available, bool $required, string $message): array
    {
        return ['available' => $available, 'required' => $required, 'message' => $message];
    }

    private function checkCacheWritable(): bool
    {
        if ($this->cachePath === '') {
            return false;
        }
        if (!is_dir($this->cachePath) && !@mkdir($this->cachePath, 0755, true)) {
            return false;
        }
        if (!is_writable($this->cachePath)) {
            return false;
        }

        $probe = $this->cachePath . '.minifyx-health-' . bin2hex(random_bytes(4));
        $written = @file_put_contents($probe, 'ok', LOCK_EX);
        if ($written === false) {
            return false;
        }
        @unlink($probe);

        return true;
    }
}
