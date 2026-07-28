<?php

declare(strict_types=1);

namespace MinifyX\Support;

final class SubresourceIntegrity
{
    public static function sha384(string $content): string
    {
        return 'sha384-' . base64_encode(hash('sha384', $content, true));
    }
}
