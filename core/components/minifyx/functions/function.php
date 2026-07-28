<?php

use MinifyX\Model\MinifyX;

if (!function_exists('minify')) {
    /**
     * Return the formatted amount of memory allocated to PHP
     *
     * @param array $properties
     * @return MinifyX|null
     */
    function minify(array $properties = array())
    {
        global $modx;
        if (isset($modx->minifyx) && $modx->minifyx instanceof MinifyX) {
            /** @var MinifyX $MinifyX */
            $MinifyX = $modx->minifyx;
            $MinifyX->reset($properties);
        } else {
            $MinifyX = $modx->getService(
                'minifyx',
                MinifyX::class,
                MODX_CORE_PATH . 'components/minifyx/model/minifyx/',
                $properties
            );
        }

        if (!$MinifyX instanceof MinifyX) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[MinifyX] Service could not be loaded.');

            return null;
        }

        return $MinifyX;
    }
}