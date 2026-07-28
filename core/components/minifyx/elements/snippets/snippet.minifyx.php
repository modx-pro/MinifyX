<?php
/**
 * @var array $scriptProperties
 * @var \MinifyX\Model\MinifyX $MinifyX
 */
use MinifyX\Integration\Modx3ServiceResolver;
use MinifyX\Model\MinifyX;

$autoload = MODX_CORE_PATH . 'components/minifyx/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$MinifyX = Modx3ServiceResolver::resolve($modx, $scriptProperties);
if (!$MinifyX instanceof MinifyX) {
    $modx->log($modx::LOG_LEVEL_ERROR, '[MinifyX] Service could not be loaded.');

    return '';
}

return $MinifyX->run();
