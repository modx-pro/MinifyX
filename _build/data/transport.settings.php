<?php

$settings = array();

$tmp = array(
	'process_registered' => array(
		'xtype' => 'combo-boolean',
		'value' => false,
	),
	'process_images' => array(
		'xtype' => 'combo-boolean',
		'value' => false,
	),
	'exclude_registered' => array(
		'xtype' => 'textarea',
		'value' => '#(scripts|styles)_[a-z0-9]{10}\.#i',
	),
	'exclude_images' => array(
		'xtype' => 'textarea',
		'value' => '#(thumb|/\d+x\d+/)#i',
	),
	'images_filters' => array(
		'xtype' => 'textfield',
		'value' => 's[true]',
	),

	'minifyJs' => array(
		'xtype' => 'combo-boolean',
		'value' => false
	),
	'minifyCss' => array(
		'xtype' => 'combo-boolean',
		'value' => false
	),

	'processRawJs' => array(
		'xtype' => 'combo-boolean',
		'value' => false
	),
	'processRawCss' => array(
		'xtype' => 'combo-boolean',
		'value' => false
	),

	'jsFilename' => array(
		'xtype' => 'textfield',
		'value' => 'all',
	),
	'cssFilename' => array(
		'xtype' => 'textfield',
		'value' => 'all',
	),
	'cacheFolder' => array(
		'xtype' => 'textfield',
		'value' => '/assets/components/minifyx/cache/',
	),
    'forceUpdate' => array(
        'xtype' => 'combo-boolean',
        'value' => false
    ),
    'forceDelete' => array(
        'xtype' => 'combo-boolean',
        'value' => false
    ),
    'minifyHtml' => array(
        'xtype' => 'combo-boolean',
        'value' => false
    ),
);

foreach ($tmp as $k => $v) {
	/* @var modSystemSetting $setting */
	$setting = $modx->newObject('modSystemSetting');
	$setting->fromArray(array_merge(
		array(
			'key' => PKG_NAME_LOWER.'_'.$k,
			'namespace' => PKG_NAME_LOWER,
			'area' => PKG_NAME_LOWER.'_main',
		), $v
	),'',true,true);

	$settings[] = $setting;
}

unset($tmp);
return $settings;
