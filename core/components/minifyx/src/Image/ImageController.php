<?php

declare(strict_types=1);

namespace MinifyX\Image;

use MinifyX\Cache\AtomicFilesystemCache;
use MinifyX\Contract\ImageProcessorInterface;

final class ImageController
{
    public const MAX_DIMENSION = 4096;

    private PathGuard $guard;
    private ImageProcessorInterface $processor;
    private AtomicFilesystemCache $cache;
    private string $signingKey;
    private int $maxDimension;

    public function __construct(
        PathGuard $guard,
        ImageProcessorInterface $processor,
        AtomicFilesystemCache $cache,
        string $signingKey = '',
        int $maxDimension = self::MAX_DIMENSION
    ) {
        $this->guard = $guard;
        $this->processor = $processor;
        $this->cache = $cache;
        $this->signingKey = $signingKey;
        $this->maxDimension = max(1, $maxDimension);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{body: string, contentType: string, etag: string, status: int}
     */
    public function handle(array $query): array
    {
        $filesRaw = $query['files'] ?? '';
        $files = is_string($filesRaw) ? $filesRaw : '';
        if ($files === '') {
            return $this->error(400, 'Missing files parameter.');
        }

        $resizeRaw = $query['resize'] ?? '';
        $resize = is_string($resizeRaw) ? $resizeRaw : '';
        if (str_contains($files, '?')) {
            [$files, $extra] = explode('?', $files, 2);
            parse_str($extra, $extraParams);
            if (isset($extraParams['resize']) && is_scalar($extraParams['resize'])) {
                $resize = (string) $extraParams['resize'];
            }
        }

        if ($this->signingKey !== '' && !$this->isValidSignature($query, $files, $resize)) {
            return $this->error(403, 'Invalid or missing image signature.');
        }

        try {
            $absolute = $this->guard->resolve($files);
        } catch (\InvalidArgumentException $e) {
            return $this->error(403, $e->getMessage());
        }

        try {
            $options = $this->parseResize($resize);
        } catch (\InvalidArgumentException $e) {
            return $this->error(422, $e->getMessage());
        }

        $fingerprint = substr(hash('sha1', $absolute . '|' . serialize($options) . '|' . (string) filemtime($absolute)), 0, 16);
        $ext = pathinfo($absolute, PATHINFO_EXTENSION) ?: 'img';
        $cacheName = 'img_' . $fingerprint . '.' . $ext;

        try {
            $cachePath = $this->cache->path($cacheName);
        } catch (\InvalidArgumentException $e) {
            return $this->error(500, $e->getMessage());
        }

        $etag = '"' . $fingerprint . '"';
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if (is_file($cachePath) && is_string($ifNoneMatch) && trim($ifNoneMatch) === $etag) {
            return [
                'body' => '',
                'contentType' => $this->processor->contentType($absolute),
                'etag' => $etag,
                'status' => 304,
            ];
        }

        if (is_file($cachePath)) {
            return [
                'body' => (string) file_get_contents($cachePath),
                'contentType' => $this->processor->contentType($absolute),
                'etag' => $etag,
                'status' => 200,
            ];
        }

        try {
            $body = $this->processor->transform($absolute, $options);
        } catch (\Throwable $e) {
            return $this->error(422, $e->getMessage());
        }

        $this->cache->write($cacheName, $body, true);

        return [
            'body' => $body,
            'contentType' => $this->processor->contentType($absolute),
            'etag' => $etag,
            'status' => 200,
        ];
    }

    public function sign(string $file, string $resize = ''): string
    {
        return hash_hmac('sha256', $file . '|' . $resize, $this->signingKey);
    }

    /**
     * @return array{width?: int|null, height?: int|null, filters?: string}
     */
    private function parseResize(string $resize): array
    {
        $width = null;
        $height = null;
        if (preg_match('/w\[(\d+)\]/', $resize, $m)) {
            $width = (int) $m[1];
        }
        if (preg_match('/h\[(\d+)\]/', $resize, $m)) {
            $height = (int) $m[1];
        }
        if ($width !== null && $width > $this->maxDimension) {
            throw new \InvalidArgumentException('Requested width exceeds maximum dimension.');
        }
        if ($height !== null && $height > $this->maxDimension) {
            throw new \InvalidArgumentException('Requested height exceeds maximum dimension.');
        }

        return [
            'width' => $width,
            'height' => $height,
            'filters' => $resize,
        ];
    }

    /**
     * @param array<string, mixed> $query
     */
    private function isValidSignature(array $query, string $files, string $resize): bool
    {
        $sigRaw = $query['sig'] ?? '';
        $sig = is_string($sigRaw) ? $sigRaw : '';
        if ($sig === '' || $this->signingKey === '') {
            return false;
        }

        return hash_equals($this->sign($files, $resize), $sig);
    }

    /**
     * @return array{body: string, contentType: string, etag: string, status: int}
     */
    private function error(int $status, string $message): array
    {
        return [
            'body' => $message,
            'contentType' => 'text/plain; charset=UTF-8',
            'etag' => '',
            'status' => $status,
        ];
    }
}
