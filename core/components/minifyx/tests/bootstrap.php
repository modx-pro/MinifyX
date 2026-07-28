<?php

declare(strict_types=1);

use MinifyX\Tests\ModxStub;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/ModxStub.php';
require_once __DIR__ . '/Modx3Stub.php';

if (!class_exists('modX')) {
    class_alias(ModxStub::class, 'modX');
}

if (!defined('MODX_BASE_PATH')) {
    define('MODX_BASE_PATH', sys_get_temp_dir() . '/minifyx-tests-base/');
}
if (!defined('MODX_CORE_PATH')) {
    define('MODX_CORE_PATH', sys_get_temp_dir() . '/minifyx-tests-core/');
}
if (!defined('MODX_ASSETS_PATH')) {
    define('MODX_ASSETS_PATH', MODX_BASE_PATH . 'assets/');
}

@mkdir(MODX_BASE_PATH, 0755, true);
@mkdir(MODX_CORE_PATH . 'components/minifyx/hooks', 0755, true);
@mkdir(MODX_ASSETS_PATH . 'components/minifyx/cache', 0755, true);
