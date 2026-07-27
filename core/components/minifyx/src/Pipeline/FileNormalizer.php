<?php

declare(strict_types=1);

namespace MinifyX\Pipeline;

use MinifyX\Support\PathHelper;

/**
 * Normalizes asset URLs/paths without mutating globals.
 */
final class FileNormalizer
{
    private string $basePath;
    private string $siteUrl;

    public function __construct(string $basePath, string $siteUrl = '')
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/') . '/';
        $this->siteUrl = rtrim($siteUrl, '/');
    }

    /**
     * @param array<int, string>|string $files
     * @return array{paths: list<string>, queryParams: array<string, string>}
     */
    public function normalize(array|string $files): array
    {
        if (is_string($files)) {
            $files = array_map('trim', explode(',', $files));
        }

        $paths = [];
        $queryParams = [];

        foreach ($files as $file) {
            if ($file === '' || $file[0] === '-') {
                continue;
            }

            // Absolute FS paths (raw temp assets) stay as-is after normalize to web-relative when under base.
            $file = str_replace('\\', '/', (string) $file);
            if (is_file($file) && PathHelper::isInsideRoot($file, $this->basePath)) {
                $real = realpath($file) ?: $file;
                $baseReal = realpath($this->basePath) ?: rtrim($this->basePath, '/');
                $relative = '/' . ltrim(str_replace('\\', '/', substr($real, strlen(rtrim($baseReal, '/\\')))), '/');
                $paths[] = $relative;
                continue;
            }

            $file = str_replace($this->basePath, '', $file);
            if ($this->siteUrl !== '') {
                $file = str_replace($this->siteUrl, '', $file);
            }

            if (!preg_match('#https?://#i', $file) && ($file[0] ?? '') !== '/') {
                $file = '/' . $file;
            }

            $parsed = parse_url($file);
            if ($parsed === false || empty($parsed['path'])) {
                continue;
            }

            $paths[] = $parsed['path'];
            if (!empty($parsed['query'])) {
                parse_str($parsed['query'], $params);
                foreach ($params as $key => $value) {
                    $queryParams[(string) $key] = is_scalar($value) ? (string) $value : '';
                }
            }
        }

        return [
            'paths' => $paths,
            'queryParams' => $queryParams,
        ];
    }

    /**
     * Resolve web paths to absolute filesystem paths inside the webroot.
     *
     * @param list<string> $webPaths
     * @return list<string>
     */
    public function toAbsolutePaths(array $webPaths): array
    {
        $absolute = [];
        $baseReal = realpath($this->basePath) ?: rtrim($this->basePath, '/\\');

        foreach ($webPaths as $webPath) {
            if (is_file($webPath) && PathHelper::isInsideRoot($webPath, $baseReal)) {
                $absolute[] = realpath($webPath) ?: $webPath;
                continue;
            }

            $candidate = $this->basePath . ltrim($webPath, '/');
            $real = realpath($candidate);
            if ($real === false) {
                $parent = dirname($candidate);
                if (!PathHelper::isInsideRoot($parent, $baseReal) && realpath($parent) !== false) {
                    continue;
                }
                // Missing file: still constrain candidate under base by string prefix with separator.
                $normalizedBase = rtrim(str_replace('\\', '/', $baseReal), '/') . '/';
                $normalizedCandidate = str_replace('\\', '/', $candidate);
                if (!str_starts_with($normalizedCandidate, $normalizedBase)) {
                    continue;
                }
                $absolute[] = $candidate;
                continue;
            }
            if (!PathHelper::isInsideRoot($real, $baseReal)) {
                continue;
            }
            $absolute[] = $real;
        }

        return $absolute;
    }

    public function assertInsideRoot(string $absolutePath, string $root): string
    {
        if (!PathHelper::isInsideRoot($absolutePath, $root)) {
            throw new \InvalidArgumentException('Path is outside the allowed root.');
        }

        $real = realpath($absolutePath);
        if ($real === false) {
            throw new \InvalidArgumentException('Path does not exist.');
        }

        return $real;
    }
}
