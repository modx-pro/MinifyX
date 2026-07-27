<?php

declare(strict_types=1);

namespace MinifyX\Image;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use MinifyX\Contract\ImageProcessorInterface;

final class InterventionImageProcessor implements ImageProcessorInterface
{
    private ImageManager $manager;
    private int $maxPixels;
    private int $maxBytes;
    private int $maxDimension;

    public function __construct(
        string $driver = 'GD',
        int $maxPixels = 20_000_000,
        int $maxBytes = 20_000_000,
        int $maxDimension = 4096
    ) {
        $driverClass = strtolower($driver) === 'imagick' && extension_loaded('imagick')
            ? ImagickDriver::class
            : GdDriver::class;
        $this->manager = new ImageManager(new $driverClass());
        $this->maxPixels = $maxPixels;
        $this->maxBytes = $maxBytes;
        $this->maxDimension = max(1, $maxDimension);
    }

    public function transform(string $absolutePath, array $options = []): string
    {
        if (!is_file($absolutePath)) {
            throw new \InvalidArgumentException('Image not found.');
        }

        $size = filesize($absolutePath);
        if ($size === false || $size > $this->maxBytes) {
            throw new \RuntimeException('Image exceeds maximum allowed file size.');
        }

        $info = @getimagesize($absolutePath);
        if ($info === false) {
            throw new \RuntimeException('Unable to read image dimensions.');
        }
        $pixels = ((int) $info[0]) * ((int) $info[1]);
        if ($pixels > $this->maxPixels) {
            throw new \RuntimeException('Image exceeds maximum allowed pixel count.');
        }

        $width = isset($options['width']) ? (int) $options['width'] : null;
        $height = isset($options['height']) ? (int) $options['height'] : null;
        if ($width !== null) {
            $width = max(1, min($width, $this->maxDimension));
        }
        if ($height !== null) {
            $height = max(1, min($height, $this->maxDimension));
        }
        if ($width !== null && $height !== null && ($width * $height) > $this->maxPixels) {
            throw new \RuntimeException('Requested output exceeds maximum allowed pixel count.');
        }

        $image = $this->manager->read($absolutePath);

        if ($width || $height) {
            $image->scale(width: $width ?: null, height: $height ?: null);
        }

        $filters = (string) ($options['filters'] ?? '');
        if (str_contains($filters, 's[true]') || str_contains($filters, 'sharpen')) {
            $image->sharpen(10);
        }
        if (preg_match('/brightness\[(-?\d+)\]/', $filters, $m)) {
            $image->brightness((int) $m[1]);
        }
        if (preg_match('/contrast\[(-?\d+)\]/', $filters, $m)) {
            $image->contrast((int) $m[1]);
        }

        $encoded = $image->encodeByPath($absolutePath);

        return (string) $encoded;
    }

    public function contentType(string $absolutePath): string
    {
        $info = @getimagesize($absolutePath);

        return is_array($info) ? (string) $info['mime'] : 'application/octet-stream';
    }
}
