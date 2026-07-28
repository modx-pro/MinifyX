<?php

declare(strict_types=1);

namespace MinifyX\Image;

use MinifyX\Contract\ImageProcessorInterface;

final class ImagickImageProcessor implements ImageProcessorInterface
{
    private int $maxPixels;
    private int $maxBytes;
    private int $maxDimension;

    public function __construct(
        int $maxPixels = 20_000_000,
        int $maxBytes = 20_000_000,
        int $maxDimension = 4096
    ) {
        $this->maxPixels = $maxPixels;
        $this->maxBytes = $maxBytes;
        $this->maxDimension = max(1, $maxDimension);
    }

    public function transform(string $absolutePath, array $options = []): string
    {
        if (!extension_loaded('imagick') || !class_exists('Imagick')) {
            throw new \RuntimeException('Imagick extension is not available.');
        }
        if (!is_file($absolutePath)) {
            throw new \InvalidArgumentException('Image not found.');
        }

        $size = filesize($absolutePath);
        if ($size === false || $size > $this->maxBytes) {
            throw new \RuntimeException('Image exceeds maximum allowed file size.');
        }

        /** @var \Imagick $image */
        $image = new \Imagick($absolutePath);
        $geometry = $image->getImageGeometry();
        $srcWidth = (int) ($geometry['width'] ?? 0);
        $srcHeight = (int) ($geometry['height'] ?? 0);
        if ($srcWidth <= 0 || $srcHeight <= 0 || ($srcWidth * $srcHeight) > $this->maxPixels) {
            $image->destroy();
            throw new \RuntimeException('Image exceeds maximum allowed pixel count.');
        }

        $targetWidth = isset($options['width']) ? (int) $options['width'] : null;
        $targetHeight = isset($options['height']) ? (int) $options['height'] : null;
        if ($targetWidth !== null) {
            $targetWidth = max(1, min($targetWidth, $this->maxDimension));
        }
        if ($targetHeight !== null) {
            $targetHeight = max(1, min($targetHeight, $this->maxDimension));
        }

        if ($targetWidth !== null || $targetHeight !== null) {
            if ($targetWidth !== null && $targetHeight !== null) {
                $image->resizeImage($targetWidth, $targetHeight, \Imagick::FILTER_LANCZOS, 1, true);
            } elseif ($targetWidth !== null) {
                $image->resizeImage($targetWidth, 0, \Imagick::FILTER_LANCZOS, 1);
            } else {
                $image->resizeImage(0, (int) $targetHeight, \Imagick::FILTER_LANCZOS, 1);
            }
        }

        $filters = (string) ($options['filters'] ?? '');
        if (str_contains($filters, 's[true]') || str_contains($filters, 'sharpen')) {
            $image->sharpenImage(0, 1);
        }
        if (preg_match('/brightness\[(-?\d+)\]/', $filters, $m)) {
            $image->modulateImage(100 + (int) $m[1], 100, 100);
        }
        if (preg_match('/contrast\[(-?\d+)\]/', $filters, $m)) {
            $image->brightnessContrastImage((int) $m[1], 0);
        }

        $body = (string) $image->getImageBlob();
        $image->destroy();
        if ($body === '') {
            throw new \RuntimeException('Unable to encode image.');
        }

        return $body;
    }

    public function contentType(string $absolutePath): string
    {
        $info = @getimagesize($absolutePath);

        return is_array($info) ? (string) $info['mime'] : 'application/octet-stream';
    }
}
