<?php

declare(strict_types=1);

namespace MinifyX\Diagnostics;

final class HealthCheckCommand
{
    /**
     * @param array<string, string|false> $options
     */
    public function run(array $options): int
    {
        $basePath = isset($options['base-path']) ? (string) $options['base-path'] : '';
        $cachePath = isset($options['cache-path']) ? (string) $options['cache-path'] : '';
        $signingKeys = '';
        $terserPath = 'terser';
        $esbuildPath = 'esbuild';

        [$detectedBasePath, $modx] = $this->bootstrapModx($basePath);
        if ($basePath === '') {
            $basePath = $detectedBasePath;
        }

        if (is_object($modx) && method_exists($modx, 'getOption')) {
            $cacheFolder = (string) $modx->getOption(
                'minifyx_cacheFolder',
                null,
                '/assets/components/minifyx/cache/',
                true
            );
            $signingKeys = (string) $modx->getOption('minifyx_image_signing_keys', null, '', true);
            if (trim($signingKeys) === '') {
                $signingKeys = (string) $modx->getOption('minifyx_image_signing_key', null, '', true);
            }
            $mangler = (string) $modx->getOption('minifyx_jsMangler', null, 'terser', true);
            $manglerPath = (string) $modx->getOption('minifyx_jsManglerPath', null, '', true);
            if ($manglerPath !== '') {
                if ($mangler === 'esbuild') {
                    $esbuildPath = $manglerPath;
                } else {
                    $terserPath = $manglerPath;
                }
            }
            $configuredEsbuild = (string) $modx->getOption('minifyx_esbuildPath', null, '', true);
            if ($configuredEsbuild !== '') {
                $esbuildPath = $configuredEsbuild;
            }
            $cachePath = $cachePath !== ''
                ? $cachePath
                : rtrim($basePath, '/\\') . '/' . ltrim($cacheFolder, '/\\');
        }

        if ($cachePath === '') {
            if ($basePath === '') {
                fwrite(STDERR, "Use --base-path or --cache-path when MODX cannot be bootstrapped.\n");

                return 2;
            }
            $cachePath = rtrim($basePath, '/\\') . '/assets/components/minifyx/cache/';
        }

        $result = (new HealthCheck($cachePath, $terserPath, $esbuildPath, $signingKeys))->run();
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        return $result['healthy'] ? 0 : 1;
    }

    /**
     * @return array{0: string, 1: object|null}
     */
    private function bootstrapModx(string $basePath = ''): array
    {
        $componentRoot = dirname(__DIR__, 2);
        $candidates = $basePath !== ''
            ? [rtrim($basePath, '/\\') . '/index.php']
            : [
                dirname($componentRoot, 3) . '/index.php',
                dirname($componentRoot, 4) . '/index.php',
            ];

        foreach ($candidates as $index) {
            if (!is_file($index)) {
                continue;
            }
            if (!defined('MODX_API_MODE')) {
                define('MODX_API_MODE', true);
            }
            $modx = null;
            require_once $index;
            $scope = get_defined_vars();
            $instance = $scope['modx'] ?? null;
            $basePath = defined('MODX_BASE_PATH') ? (string) MODX_BASE_PATH : '';

            return [$basePath, is_object($instance) ? $instance : null];
        }

        return ['', null];
    }
}
