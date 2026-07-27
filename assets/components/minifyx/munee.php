<?php

declare(strict_types=1);

/**
 * Compatibility image/asset endpoint.
 * Prefer signed URLs: ?files=...&resize=...&sig=...
 */

use MinifyX\Cache\AtomicFilesystemCache;
use MinifyX\Image\ImageController;
use MinifyX\Image\InterventionImageProcessor;
use MinifyX\Image\PathGuard;

if (!defined('MODX_API_MODE')) {
    define('MODX_API_MODE', true);
}

$indexCandidates = [
    dirname(__DIR__, 4) . '/index.php',
    dirname(__DIR__, 5) . '/index.php',
];

$bootstrapped = false;
foreach ($indexCandidates as $index) {
    if (is_file($index)) {
        require_once $index;
        $bootstrapped = true;
        break;
    }
}

if (!$bootstrapped) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'MinifyX: MODX bootstrap not found.';
    exit;
}

$autoload = MODX_CORE_PATH . 'components/minifyx/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

if (empty($_GET['files'])) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Missing files parameter.';
    exit;
}

$cacheDir = MODX_CORE_PATH . 'cache/default/munee/images/';
$cache = new AtomicFilesystemCache($cacheDir);
$cache->prepare();

$driver = (string) $modx->getOption('munee_imageProcessor', null, 'GD', true);
$signingKey = (string) $modx->getOption('minifyx_image_signing_key', null, '', true);
$maxPixels = (int) $modx->getOption('minifyx_image_max_pixels', null, 20000000, true);
$maxBytes = (int) $modx->getOption('minifyx_image_max_bytes', null, 20000000, true);

$controller = new ImageController(
    new PathGuard(MODX_BASE_PATH),
    new InterventionImageProcessor($driver, $maxPixels, $maxBytes),
    $cache,
    $signingKey
);

$result = $controller->handle($_GET);
http_response_code($result['status']);
if ($result['etag'] !== '') {
    header('ETag: ' . $result['etag']);
    header('Cache-Control: public, max-age=86400');
}
header('Content-Type: ' . $result['contentType']);
if ($result['status'] !== 304) {
    echo $result['body'];
}
