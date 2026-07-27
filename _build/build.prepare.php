<?php

$root = dirname(__DIR__) . '/';
$component = $root . 'core/components/minifyx/';

// Remove legacy Munee submodule leftovers from the package tree.
$legacyMunee = $component . 'munee/';
if (is_dir($legacyMunee)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($legacyMunee, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($legacyMunee);
}

$vendorAutoload = $component . 'vendor/autoload.php';
if (!is_file($vendorAutoload)) {
    fwrite(
        STDERR,
        "MinifyX prepare: vendor/autoload.php is missing.\n"
        . "Run: composer install --no-dev --optimize-autoloader\n"
        . "inside core/components/minifyx/ before building the transport package.\n"
    );
    exit(1);
}
