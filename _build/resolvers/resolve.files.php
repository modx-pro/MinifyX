<?php
/**
 * @var xPDOObject $object
 * @var array $options
 */
if ($object->xpdo) {
    /** @var modX $modx */
    $modx =& $object->xpdo;

    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $legacyPaths = [
                MODX_CORE_PATH . 'components/minifyx/munee/munee.phar',
                MODX_CORE_PATH . 'components/minifyx/munee',
            ];
            foreach ($legacyPaths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                } elseif (is_dir($path)) {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );
                    foreach ($files as $file) {
                        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
                    }
                    @rmdir($path);
                }
            }

            $vendorAutoload = MODX_CORE_PATH . 'components/minifyx/vendor/autoload.php';
            if (!is_file($vendorAutoload)) {
                $modx->log(
                    $modx::LOG_LEVEL_ERROR,
                    '[MinifyX] vendor/autoload.php is missing. Reinstall the package or run composer install '
                    . 'in core/components/minifyx/.'
                );
            }

            $report = [];
            $groups = MODX_CORE_PATH . 'components/minifyx/config/groups.php';
            if (is_file($groups)) {
                $report[] = 'groups.php present';
            }
            $hooksDir = MODX_CORE_PATH . 'components/minifyx/hooks/';
            if (is_dir($hooksDir)) {
                foreach (glob($hooksDir . '*.php') ?: [] as $hook) {
                    $report[] = 'hook:' . basename($hook);
                }
            }
            if ($report !== []) {
                $modx->log($modx::LOG_LEVEL_INFO, '[MinifyX] Migration report: ' . implode(', ', $report));
            }
            break;

        case xPDOTransport::ACTION_UNINSTALL:
            break;
    }
}

return true;
