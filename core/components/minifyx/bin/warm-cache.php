<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects

use MinifyX\Adapter\ArrayModxAdapter;
use MinifyX\Config;
use MinifyX\ServiceFactory;
use MinifyX\Tooling\BoundedCommandRunner;
use MinifyX\Tooling\WarmCacheCommand;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "MinifyX Composer autoloader not found.\n");
    exit(2);
}
require_once $autoload;

$options = getopt('', ['base-path:', 'cache-path:', 'groups-file:', 'group:', 'parallel', 'jobs:']);
$options = is_array($options) ? $options : [];
$basePath = isset($options['base-path']) ? (string) $options['base-path'] : '';
$modx = null;

$bootstrapCandidates = [];
if ($basePath !== '') {
    $bootstrapCandidates[] = rtrim($basePath, '/\\') . '/index.php';
} else {
    $componentRoot = dirname(__DIR__);
    $bootstrapCandidates = [
        dirname($componentRoot, 3) . '/index.php',
        dirname($componentRoot, 4) . '/index.php',
    ];
}
foreach ($bootstrapCandidates as $index) {
    if (!is_file($index)) {
        continue;
    }
    if (!defined('MODX_API_MODE')) {
        define('MODX_API_MODE', true);
    }
    require_once $index;
    $scope = get_defined_vars();
    $candidateModx = $scope['modx'] ?? null;
    $modx = is_object($candidateModx) ? $candidateModx : null;
    if ($basePath === '') {
        $basePath = defined('MODX_BASE_PATH') ? (string) MODX_BASE_PATH : '';
    }
    break;
}

if ($basePath === '' || !is_dir($basePath)) {
    fwrite(STDERR, "Use --base-path=/path/to/modx when MODX cannot be bootstrapped.\n");
    exit(2);
}
$basePath = rtrim((string) realpath($basePath), '/\\') . DIRECTORY_SEPARATOR;
$groupsFile = isset($options['groups-file'])
    ? (string) $options['groups-file']
    : dirname(__DIR__) . '/config/groups.php';
if (!is_file($groupsFile)) {
    fwrite(STDERR, "Groups file not found: " . $groupsFile . "\n");
    exit(2);
}
$groups = include $groupsFile;
if (!is_array($groups)) {
    fwrite(STDERR, "Groups file must return an array of named source lists.\n");
    exit(2);
}
foreach ($groups as $name => $files) {
    if (!is_string($name) || !is_array($files)) {
        fwrite(STDERR, "Groups file must contain named source lists.\n");
        exit(2);
    }
}

$selected = $options['group'] ?? [];
$selected = is_array($selected) ? $selected : ($selected === '' ? [] : [$selected]);
$selected = array_values(array_unique(array_filter(array_map('strval', $selected), 'strlen')));
$cachePath = isset($options['cache-path'])
    ? (string) $options['cache-path']
    : $basePath . 'assets/components/minifyx/cache/';
$configValues = [
    'cacheFolder' => '/assets/components/minifyx/cache/',
    'cacheFolderPath' => $cachePath,
    'cssFilename' => 'styles',
    'jsFilename' => 'scripts',
    'minifyCss' => false,
    'minifyJs' => false,
    'mangleJs' => false,
    'bundleJsModules' => false,
    'sourceMaps' => false,
    'parallelBuild' => false,
    'forceUpdate' => false,
    'hash_length' => 10,
    'hooksPath' => dirname(__DIR__) . '/hooks/',
    'hooks' => [],
    'preHooks' => [],
    'esbuildPath' => '',
    'jsManglerPath' => '',
    'jsManglerMaxInputBytes' => 5000000,
];
if (is_object($modx) && method_exists($modx, 'getOption')) {
    $settingDefaults = [
        'cacheFolder' => '/assets/components/minifyx/cache/',
        'cssFilename' => 'styles',
        'jsFilename' => 'scripts',
        'minifyCss' => false,
        'minifyJs' => false,
        'mangleJs' => false,
        'bundleJsModules' => false,
        'sourceMaps' => false,
        'parallelBuild' => false,
        'forceUpdate' => false,
        'jsMangler' => 'terser',
        'jsManglerPath' => '',
        'esbuildPath' => '',
        'jsManglerMaxInputBytes' => 5000000,
        'hooks' => '',
        'preHooks' => '',
    ];
    foreach ($settingDefaults as $key => $default) {
        $configValues[$key] = $modx->getOption('minifyx_' . $key, null, $default, true);
    }
}
if (!isset($options['cache-path'])) {
    $cachePath = $basePath . ltrim((string) $configValues['cacheFolder'], '/\\');
}
$configValues['cacheFolderPath'] = $cachePath;
$config = new Config($configValues);

if (is_object($modx)) {
    $pipeline = ServiceFactory::createFromLegacyModx($modx, $config->all(), $groups);
} else {
    $adapter = new ArrayModxAdapter([], 'web', $basePath, $basePath . 'core/', $basePath . 'assets/');
    $pipeline = ServiceFactory::create($adapter, $config, $groups);
}

$parallel = array_key_exists('parallel', $options)
    || (is_object($modx)
        && method_exists($modx, 'getOption')
        && (bool) $modx->getOption('minifyx_parallelBuild', null, false, true));
$runner = new BoundedCommandRunner();
if ($parallel && count($selected === [] ? $groups : $selected) > 1 && $runner->isSupported()) {
    $names = $selected === [] ? array_keys($groups) : $selected;
    sort($names, SORT_STRING);
    $commands = [];
    foreach ($names as $name) {
        $commands[] = [
            PHP_BINARY,
            __FILE__,
            '--cache-path=' . $cachePath,
            '--groups-file=' . $groupsFile,
            '--group=' . $name,
            '--base-path=' . $basePath,
        ];
    }
    $jobs = min(16, max(2, (int) ($options['jobs'] ?? 2)));
    $rows = $runner->run($commands, $jobs);
    $summary = ['success' => true, 'parallel' => true, 'groups' => [], 'compilerCalls' => 0];
    foreach ($rows as $row) {
        $child = json_decode($row['stdout'], true);
        if ($row['exitCode'] !== 0 || !is_array($child)) {
            $error = trim($row['stderr']);
            if ($error === '') {
                $error = 'Worker failed with exit code ' . $row['exitCode'] . '.';
            }
            $summary['success'] = false;
            $summary['groups'][] = ['success' => false, 'error' => $error];
            continue;
        }
        $summary['success'] = $summary['success'] && !empty($child['success']);
        $summary['groups'] = array_merge($summary['groups'], (array) ($child['groups'] ?? []));
        $summary['compilerCalls'] += (int) ($child['compilerCalls'] ?? 0);
    }
} else {
    $summary = (new WarmCacheCommand($pipeline))->warm($groups, $selected);
    $summary['parallel'] = false;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(!empty($summary['success']) ? 0 : 1);
