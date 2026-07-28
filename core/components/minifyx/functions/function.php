<?php

use MinifyX\Integration\Modx3ServiceResolver;
use MinifyX\Model\MinifyX;

if (!function_exists('minify')) {
    /**
     * Return the formatted amount of memory allocated to PHP
     *
     * @param array $properties
     * @return MinifyX|null
     */
    function minify(array $properties = []): ?MinifyX
    {
        global $modx;
        if (!is_object($modx)) {
            return null;
        }

        $MinifyX = Modx3ServiceResolver::resolve($modx, $properties);
        if (!$MinifyX instanceof MinifyX) {
            $modx->log($modx::LOG_LEVEL_ERROR, '[MinifyX] Service could not be loaded.');

            return null;
        }

        return $MinifyX;
    }
}