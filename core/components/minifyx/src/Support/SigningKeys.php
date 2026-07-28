<?php

declare(strict_types=1);

namespace MinifyX\Support;

final class SigningKeys
{
    /**
     * @param string|list<string> $keys
     * @return list<string>
     */
    public static function normalize($keys): array
    {
        $values = is_array($keys) ? $keys : preg_split('/[\r\n,]+/', (string) $keys);
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $values), static function (string $key): bool {
            return $key !== '';
        }));
    }
}
