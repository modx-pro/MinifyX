<?php

declare(strict_types=1);

namespace MinifyX\Support;

final class SettingUpgradeResolver
{
    /** @var array<string, string> */
    public const RENAMES = [
        'munee_cache' => 'minifyx_cache',
        'munee_imageProcessor' => 'minifyx_imageProcessor',
    ];

    /**
     * Copy legacy values only when their replacement is absent.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function resolve(array $settings): array
    {
        foreach (self::RENAMES as $oldKey => $newKey) {
            if (!array_key_exists($newKey, $settings) && array_key_exists($oldKey, $settings)) {
                $settings[$newKey] = $settings[$oldKey];
            }
        }

        return $settings;
    }

    public static function shouldMigrate(bool $newExists, mixed $newValue, mixed $transportDefault): bool
    {
        return !$newExists || (string) $newValue === (string) $transportDefault;
    }

    /**
     * Read a new MODX option first and fall back to its legacy key only when unset.
     *
     * @return mixed
     */
    public static function read(object $modx, string $newKey, string $oldKey, mixed $default = null): mixed
    {
        if (!method_exists($modx, 'getOption')) {
            return $default;
        }

        $unset = new \stdClass();
        $value = $modx->getOption($newKey, null, $unset);
        if ($value !== $unset) {
            return $value;
        }

        return $modx->getOption($oldKey, null, $default);
    }
}
