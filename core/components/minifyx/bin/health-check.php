<?php

declare(strict_types=1);

use MinifyX\Diagnostics\HealthCheckCommand;

$options = getopt('', ['base-path:', 'cache-path:']);
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "MinifyX Composer autoloader not found.\n");
    exit(2);
}
require_once $autoload;

exit((new HealthCheckCommand())->run(is_array($options) ? $options : []));
