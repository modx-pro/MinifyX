<?php

if (!defined('MODX_API_MODE')) {
	define('MODX_API_MODE', true);
}
if (file_exists(dirname(dirname(dirname(dirname(__FILE__)))).'/index.php')) {
    /** @noinspection PhpIncludeInspection */
    require_once dirname(dirname(dirname(dirname(__FILE__)))).'/index.php';
}
else {
    require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/index.php';
}
$modx->getService('error','error.modError');

$MinifyX = $modx->getService('minifyx','MinifyX', MODX_CORE_PATH.'components/minifyx/model/minifyx/');
//$MinifyX = new MinifyX($modx, array());

if (!empty($_GET['files'])) {
	$files = $MinifyX->prepareFiles($_GET['files']);

	$options = array(
		'image' => array(
			'checkReferrer' => $modx->getOption('munee_checkReferrer', null, 'true', true),
			'allowedFiltersTimeLimit' => $modx->getOption('munee_allowedFiltersTimeLimit', null, -1, true),
			'numberOfAllowedFilters' => $modx->getOption('munee_numberOfAllowedFilters', null, 100, true),
			'imageProcessor' => $modx->getOption('munee_imageProcessor', null, 'GD', true),
			'placeholders' => $modx->fromJSON(
				$modx->getOption('munee_placeholders', null, '{"*":"http://placehold.it/500x500/&text=MinifyX"}', true)
			),
		),
		'setHeaders' => true,
		'maxAge' => 0
	);

	echo $MinifyX->Munee($files, $options);
}
