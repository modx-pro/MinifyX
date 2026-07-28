<?php

declare(strict_types=1);

namespace MinifyX\Integration;

use MinifyX\Model\MinifyX;

final class Modx3ServiceResolver
{
    /**
     * Resolve from the MODX 3 native service container first, then use getService for one migration cycle.
     *
     * @param array<string, mixed> $properties
     */
    public static function resolve(object $modx, array $properties = []): ?MinifyX
    {
        $service = self::resolveNative($modx);
        if (!$service instanceof MinifyX && isset($modx->minifyx) && $modx->minifyx instanceof MinifyX) {
            $service = $modx->minifyx;
        }
        if ($service instanceof MinifyX) {
            $service->reset($properties);

            return $service;
        }

        if (!method_exists($modx, 'getService')) {
            return null;
        }

        $service = $modx->getService(
            'minifyx',
            MinifyX::class,
            self::modelPath(),
            $properties
        );

        return $service instanceof MinifyX ? $service : null;
    }

    /**
     * Typed MODX 3 entry point. Call only after class_exists(\MODX\Revolution\modX::class).
     *
     * @param array<string, mixed> $properties
     */
    public static function resolveForModx3(\MODX\Revolution\modX $modx, array $properties = []): ?MinifyX
    {
        return self::resolve($modx, $properties);
    }

    private static function resolveNative(object $modx): ?MinifyX
    {
        $container = null;
        if (isset($modx->services) && is_object($modx->services)) {
            $container = $modx->services;
        } elseif (method_exists($modx, 'getContainer')) {
            try {
                $candidate = $modx->getContainer();
            } catch (\Throwable) {
                return null;
            }
            if (is_object($candidate)) {
                $container = $candidate;
            }
        }

        if ($container === null || !method_exists($container, 'get')) {
            return null;
        }

        try {
            if (method_exists($container, 'has') && !$container->has('minifyx')) {
                return null;
            }
            $service = $container->get('minifyx');
        } catch (\Throwable) {
            return null;
        }

        return $service instanceof MinifyX ? $service : null;
    }

    private static function modelPath(): string
    {
        $corePath = defined('MODX_CORE_PATH') ? MODX_CORE_PATH : dirname(__DIR__, 2) . '/';

        return rtrim($corePath, '/\\') . '/components/minifyx/model/minifyx/';
    }
}
