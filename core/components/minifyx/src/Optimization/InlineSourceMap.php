<?php

declare(strict_types=1);

namespace MinifyX\Optimization;

final class InlineSourceMap
{
    /**
     * @return array{code: string, map: ?string}
     */
    public static function extract(string $code): array
    {
        $pattern = '~(?:/\*|//)[#@]\s*sourceMappingURL=data:application/json;'
            . '(?:charset=utf-8;)?base64,([A-Za-z0-9+/=]+)(?:\s*\*/)?\s*$~';
        if (preg_match($pattern, $code, $match) !== 1) {
            return ['code' => $code, 'map' => null];
        }
        $map = base64_decode($match[1], true);
        if (!is_string($map) || json_decode($map, true) === null) {
            return ['code' => $code, 'map' => null];
        }

        return [
            'code' => rtrim((string) preg_replace($pattern, '', $code)),
            'map' => $map,
        ];
    }
}
