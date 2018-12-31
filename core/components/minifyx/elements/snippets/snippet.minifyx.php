<?php
/** @var array $scriptProperties */
/** @var MinifyX $MinifyX */
if (isset($modx->minifyx) && $modx->minifyx instanceof MinifyX) {
    $MinifyX = $modx->minifyx;
    $MinifyX->reset($scriptProperties);
} else {
    $MinifyX = $modx->getService('minifyx', 'MinifyX', MODX_CORE_PATH . 'components/minifyx/model/minifyx/', $scriptProperties);
}

return $MinifyX->run();