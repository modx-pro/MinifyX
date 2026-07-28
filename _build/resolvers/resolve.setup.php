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
            $modxMajor = 0;
            if (defined('MODX_VERSION')) {
                $modxMajor = (int) explode('.', MODX_VERSION)[0];
            } elseif (class_exists('MODX\Revolution\modX')) {
                $modxMajor = 3;
            }
            if ($modxMajor < 3) {
                $modx->log(
                    $modx::LOG_LEVEL_ERROR,
                    '[MinifyX] Version 3.0 requires MODX Revolution 3.0 or newer.'
                );
                return false;
            }
            if (version_compare(PHP_VERSION, '8.2.0', '<')) {
                $modx->log(
                    $modx::LOG_LEVEL_ERROR,
                    sprintf('[MinifyX] Version 3.0 requires PHP 8.2+. Current PHP: %s', PHP_VERSION)
                );
                return false;
            }

            require_once MODX_CORE_PATH . 'components/minifyx/src/Support/SettingUpgradeResolver.php';
            $renamedSettings = [
                'munee_cache' => [
                    'key' => 'minifyx_cache',
                    'default' => MODX_CORE_PATH . 'cache/default/minifyx/',
                ],
                'munee_imageProcessor' => [
                    'key' => 'minifyx_imageProcessor',
                    'default' => 'GD',
                ],
            ];
            foreach ($renamedSettings as $oldKey => $migration) {
                $newKey = $migration['key'];
                $newSetting = $modx->getObject('modSystemSetting', ['key' => $newKey]);
                $oldSetting = $modx->getObject('modSystemSetting', ['key' => $oldKey]);
                if (!$oldSetting) {
                    continue;
                }
                if ($newSetting) {
                    if (!\MinifyX\Support\SettingUpgradeResolver::shouldMigrate(
                        true,
                        $newSetting->get('value'),
                        $migration['default']
                    )) {
                        continue;
                    }
                    $newSetting->set('value', $oldSetting->get('value'));
                } else {
                    $newSetting = $modx->newObject('modSystemSetting');
                    $newSetting->fromArray($oldSetting->toArray(), '', true, true);
                    $newSetting->set('key', $newKey);
                    $newSetting->set('namespace', 'minifyx');
                }
                if (!$newSetting->save()) {
                    $modx->log($modx::LOG_LEVEL_ERROR, '[MinifyX] Could not migrate setting ' . $oldKey);
                    return false;
                }
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
                    $modx->log($modx::LOG_LEVEL_ERROR, '[MinifyX] Could not create file groups.php');
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
