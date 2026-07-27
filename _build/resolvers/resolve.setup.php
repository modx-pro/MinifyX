<?php
/**
 * Resolves setup-options settings
 *
 * @var xPDOObject $object
 * @var array $options
 */

if ($object->xpdo) {
	/** @var modX $modx */
	$modx =& $object->xpdo;

	$success = false;
	switch ($options[xPDOTransport::PACKAGE_ACTION]) {
		case xPDOTransport::ACTION_INSTALL:
		case xPDOTransport::ACTION_UPGRADE:
            $modxMajor = 2;
            if (defined('MODX_VERSION')) {
                $modxMajor = (int) explode('.', MODX_VERSION)[0];
            } elseif (class_exists('MODX\Revolution\modX')) {
                $modxMajor = 3;
            }
            $phpMin = $modxMajor >= 3 ? '8.2.0' : '7.4.0';
            if (version_compare(PHP_VERSION, $phpMin, '<')) {
                $modx->log(
                    modX::LOG_LEVEL_ERROR,
                    sprintf(
                        '[MinifyX] MODX %d requires PHP %s+. Current PHP: %s',
                        $modxMajor,
                        $phpMin,
                        PHP_VERSION
                    )
                );
                return false;
            }

            $file = MODX_CORE_PATH . 'components/minifyx/config/groups.php';
            if (!file_exists($file)) {
                if (!file_exists(MODX_CORE_PATH . 'components/minifyx/config')) {
                    mkdir(MODX_CORE_PATH . 'components/minifyx/config');
                }
                $content = <<<content
<?php

return array(
    /*'baseCss' => [
      '[[+assets_url]]style1.css',
      '{assets_url}style2.css'
    ],
    'someJs' => [
        ...
    ]*/
);
content;

                if (!file_put_contents($file, $content)) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[MinifyX] Could not create file groups.php');
                    return false;
                }
            }
			$success = true;
			break;

		case xPDOTransport::ACTION_UNINSTALL:
			$success = true;
			break;
	}

	return $success;
}
