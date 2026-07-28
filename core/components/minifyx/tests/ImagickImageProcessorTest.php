<?php

declare(strict_types=1);

namespace MinifyX\Tests;

use MinifyX\Image\ImagickImageProcessor;

final class ImagickImageProcessorTest extends TestCase
{
    public function testImagickResizesImage(): void
    {
        if (!extension_loaded('imagick')) {
            self::markTestSkipped('Imagick extension required');
        }

        $file = sys_get_temp_dir() . '/minifyx-imagick-' . uniqid('', true) . '.png';
        $image = new \Imagick();
        $image->newImage(20, 10, 'red', 'png');
        $image->writeImage($file);
        $image->destroy();

        $body = (new ImagickImageProcessor())->transform($file, ['width' => 10, 'height' => 5]);
        $result = new \Imagick();
        $result->readImageBlob($body);
        self::assertSame(10, $result->getImageWidth());
        self::assertSame(5, $result->getImageHeight());
        $result->destroy();
        @unlink($file);
    }
}
