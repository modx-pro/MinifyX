<?php

declare(strict_types=1);

namespace MinifyX\Image;

use MinifyX\Contract\ImageProcessorInterface;

final class GdImageProcessor implements ImageProcessorInterface
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
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('GD extension is not available.');
        }
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

        $srcWidth = (int) $info[0];
        $srcHeight = (int) $info[1];
        if (($srcWidth * $srcHeight) > $this->maxPixels) {
            throw new \RuntimeException('Image exceeds maximum allowed pixel count.');
        }

        $src = $this->createFromFile($absolutePath, (int) $info[2]);
        if ($src === false) {
            throw new \RuntimeException('Unable to decode image.');
        }

        $targetWidth = isset($options['width']) ? (int) $options['width'] : null;
        $targetHeight = isset($options['height']) ? (int) $options['height'] : null;
        if ($targetWidth !== null) {
            $targetWidth = max(1, min($targetWidth, $this->maxDimension));
        }
        if ($targetHeight !== null) {
            $targetHeight = max(1, min($targetHeight, $this->maxDimension));
        }

        $dstWidth = $srcWidth;
        $dstHeight = $srcHeight;
        if ($targetWidth !== null && $targetHeight !== null) {
            $dstWidth = $targetWidth;
            $dstHeight = $targetHeight;
        } elseif ($targetWidth !== null) {
            $dstWidth = $targetWidth;
            $dstHeight = (int) max(1, round($srcHeight * ($targetWidth / $srcWidth)));
        } elseif ($targetHeight !== null) {
            $dstHeight = $targetHeight;
            $dstWidth = (int) max(1, round($srcWidth * ($targetHeight / $srcHeight)));
        }

        if (($dstWidth * $dstHeight) > $this->maxPixels) {
            imagedestroy($src);
            throw new \RuntimeException('Requested output exceeds maximum allowed pixel count.');
        }

        $dst = imagecreatetruecolor($dstWidth, $dstHeight);
        if ($dst === false) {
            imagedestroy($src);
            throw new \RuntimeException('Unable to allocate image buffer.');
        }

        if (in_array((int) $info[2], [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefilledrectangle($dst, 0, 0, $dstWidth, $dstHeight, $transparent);
            }
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
        imagedestroy($src);

        $filters = (string) ($options['filters'] ?? '');
        if (str_contains($filters, 's[true]') || str_contains($filters, 'sharpen')) {
            if (function_exists('imagefilter')) {
                @imagefilter($dst, IMG_FILTER_EDGEDETECT);
            }
        }
        if (preg_match('/brightness\[(-?\d+)\]/', $filters, $m)) {
            if (function_exists('imagefilter')) {
                @imagefilter($dst, IMG_FILTER_BRIGHTNESS, (int) $m[1]);
            }
        }
        if (preg_match('/contrast\[(-?\d+)\]/', $filters, $m)) {
            if (function_exists('imagefilter')) {
                @imagefilter($dst, IMG_FILTER_CONTRAST, (int) $m[1]);
            }
        }

        return $this->encode($dst, $absolutePath, (int) $info[2]);
    }

    public function contentType(string $absolutePath): string
    {
        $info = @getimagesize($absolutePath);

        return is_array($info) ? (string) $info['mime'] : 'application/octet-stream';
    }

    /**
     * @return \GdImage|resource|false
     */
    private function createFromFile(string $path, int $type)
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($path);
            case IMAGETYPE_GIF:
                return @imagecreatefromgif($path);
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    return @imagecreatefromwebp($path);
                }

                return false;
            default:
                return false;
        }
    }

    /**
     * @param \GdImage|resource $image
     */
    private function encode($image, string $sourcePath, int $type): string
    {
        ob_start();
        $ok = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $ok = imagejpeg($image, null, 90);
                break;
            case IMAGETYPE_PNG:
                $ok = imagepng($image, null, 6);
                break;
            case IMAGETYPE_GIF:
                $ok = imagegif($image);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) {
                    $ok = imagewebp($image, null, 90);
                    break;
                }
                $ok = imagejpeg($image, null, 90);
                break;
            default:
                $ok = imagejpeg($image, null, 90);
        }

        imagedestroy($image);
        $body = (string) ob_get_clean();
        if (!$ok || $body === '') {
            throw new \RuntimeException('Unable to encode image for ' . basename($sourcePath) . '.');
        }

        return $body;
    }
}
