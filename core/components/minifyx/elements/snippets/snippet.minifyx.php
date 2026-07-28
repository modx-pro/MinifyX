<?php
/**
 * @var array $scriptProperties
 * @var \MinifyX\Model\MinifyX $MinifyX
 */
use MinifyX\Model\MinifyX;

if (isset($modx->minifyx) && $modx->minifyx instanceof MinifyX) {
    $MinifyX = $modx->minifyx;
    $MinifyX->reset($scriptProperties);
} else {
    $MinifyX = $modx->getService(
        'minifyx',
        MinifyX::class,
        MODX_CORE_PATH . 'components/minifyx/model/minifyx/',
        $scriptProperties
    );
}

if (!$MinifyX instanceof MinifyX) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[MinifyX] Service could not be loaded.');

    return '';
}

return $MinifyX->run();
