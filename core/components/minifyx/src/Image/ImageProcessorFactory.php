<?php

declare(strict_types=1);

namespace MinifyX\Image;

use MinifyX\Contract\ImageProcessorInterface;

final class ImageProcessorFactory
{
    public static function create(
        string $driver = 'GD',
        int $maxPixels = 20_000_000,
        int $maxBytes = 20_000_000,
        int $maxDimension = 4096
    ): ImageProcessorInterface {
        if (strtolower($driver) === 'imagick' && extension_loaded('imagick')) {
            return new ImagickImageProcessor($maxPixels, $maxBytes, $maxDimension);
        }

        return new GdImageProcessor($maxPixels, $maxBytes, $maxDimension);
    }
}
