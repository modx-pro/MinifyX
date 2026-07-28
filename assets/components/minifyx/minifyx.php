<?php

declare(strict_types=1);

/**
 * MinifyX image/asset endpoint.
 * Prefer signed URLs: ?files=...&resize=...&sig=...
 */

use MinifyX\Cache\AtomicFilesystemCache;
use MinifyX\Image\ImageController;
use MinifyX\Image\ImageProcessorFactory;
use MinifyX\Image\ImageRateLimiter;
use MinifyX\Image\PathGuard;
use MinifyX\Support\SettingUpgradeResolver;

if (!defined('MODX_API_MODE')) {
    define('MODX_API_MODE', true);
}

$indexCandidates = [
    dirname(__DIR__, 3) . '/index.php',
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

$cacheDir = (string) SettingUpgradeResolver::read(
    $modx,
    'minifyx_cache',
    'munee_cache',
    MODX_CORE_PATH . 'cache/default/minifyx/'
);
$cacheDir = rtrim($cacheDir, '/\\') . '/images/';
$cache = new AtomicFilesystemCache($cacheDir);
$cache->prepare();

$driver = (string) SettingUpgradeResolver::read(
    $modx,
    'minifyx_imageProcessor',
    'munee_imageProcessor',
    'GD'
);
$signingKeys = (string) $modx->getOption('minifyx_image_signing_keys', null, '', true);
if (trim($signingKeys) === '') {
    $signingKeys = (string) $modx->getOption('minifyx_image_signing_key', null, '', true);
}
$maxPixels = (int) $modx->getOption('minifyx_image_max_pixels', null, 20000000, true);
$maxBytes = (int) $modx->getOption('minifyx_image_max_bytes', null, 20000000, true);
$rateLimitMax = (int) $modx->getOption('minifyx_image_rate_limit_max', null, 0, true);
$rateLimitWindow = (int) $modx->getOption('minifyx_image_rate_limit_window', null, 60, true);
$rateLimitSalt = (string) $modx->getOption('minifyx_image_rate_limit_salt', null, '', true);
$clientIp = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
    ? $_SERVER['REMOTE_ADDR']
    : 'unknown';
$clientHash = hash('sha256', $clientIp . '|' . $rateLimitSalt);
$rateLimiter = new ImageRateLimiter($cacheDir . 'rate-limit/', $rateLimitMax, $rateLimitWindow);
if (!$rateLimiter->isAllowed($clientHash)) {
    http_response_code(429);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Retry-After: ' . max(1, $rateLimitWindow));
    echo 'Image request rate limit exceeded.';
    exit;
}

$controller = new ImageController(
    new PathGuard(MODX_BASE_PATH),
    ImageProcessorFactory::create($driver, $maxPixels, $maxBytes),
    $cache,
    $signingKeys
);

$result = $controller->handle($_GET);

$corsOrigin = (string) $modx->getOption('minifyx_cors_origin', null, '', true);
if ($corsOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $corsOrigin);
    header('Vary: Origin');
}

http_response_code($result['status']);
if ($result['etag'] !== '') {
    header('ETag: ' . $result['etag']);
    header('Cache-Control: public, max-age=86400');
}
header('Content-Type: ' . $result['contentType']);
if ($result['status'] !== 304) {
    echo $result['body'];
}
