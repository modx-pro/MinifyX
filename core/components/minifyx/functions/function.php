<?php

if (!function_exists('minify')) {
    /**
     * Return the formatted amount of memory allocated to PHP
     * @param array $properties
     * @return MinifyX
     */
    function minify(array $properties = array())
    {
        global $modx;
        if (isset($modx->minifyx) && $modx->minifyx instanceof MinifyX) {
            /** @var MinifyX $MinifyX */
            $MinifyX = $modx->minifyx;
            $MinifyX->reset($properties);
        } else {
            $MinifyX = $modx->getService('minifyx', 'MinifyX', MODX_CORE_PATH . 'components/minifyx/model/minifyx/', $properties);
        }

        return $MinifyX;
    }
}