<?php
$properties = array();

$tmp = array(
	'jsSources' => array(
		'xtype' => 'textfield',
		'value' => '',
	),
	'cssSources' => array(
		'xtype' => 'textfield',
		'value' => '',
	),

	'minifyJs' => array(
		'xtype' => 'combo-boolean',
		'value' => false
	),
	'minifyCss' => array(
		'xtype' => 'combo-boolean',
		'value' => false
	),


	'jsFilename' => array(
		'xtype' => 'textfield',
		'value' => 'scripts',
	),
	'cssFilename' => array(
		'xtype' => 'textfield',
		'value' => 'styles',
	),

	'registerJs' => array(
		'xtype' => 'list',
		'value' => 'placeholder',
		'options' => array(
			array('name' => 'Placeholder', 'value' => 'placeholder'),
			array('name' => 'Startup script', 'value' => 'startup'),
			array('name' => 'Default', 'value' => 'default'),
            array('name' => 'Print', 'value' => 'print'),
		)
	),
	'jsPlaceholder' => array(
		'xtype' => 'textfield',
		'value' => 'MinifyX.javascript',
	),
	'registerCss' => array(
		'xtype' => 'list',
		'value' => 'placeholder',
		'options' => array(
			array('name' => 'Placeholder', 'value' => 'placeholder'),
			array('name' => 'Default', 'value' => 'default'),
			array('name' => 'Print', 'value' => 'print'),
		)
	),
	'cssPlaceholder' => array(
		'xtype' => 'textfield',
		'value' => 'MinifyX.css',
	),
    'jsGroups' => array(
        'xtype' => 'textfield',
        'value' => '',
    ),
    'cssGroups' => array(
        'xtype' => 'textfield',
        'value' => '',
    ),
    'preHooks' => array(
        'xtype' => 'textfield',
        'value' => '',
    ),
    'hooks' => array(
        'xtype' => 'textfield',
        'value' => '',
    ),
    'cssTpl' => array(
        'xtype' => 'textfield',
        'value' => '<link rel="stylesheet" href="[[+file]]" type="text/css" />',
    ),
    'jsTpl' => array(
        'xtype' => 'textfield',
        'value' => '<script type="text/javascript" src="[[+file]]"></script>',
    ),
    'forceUpdate' => array(
        'xtype' => 'combo-boolean',
        'value' => false
    ),
);

foreach ($tmp as $k => $v) {
	$properties[] = array_merge(
		array(
			'name' => $k,
			'desc' => PKG_NAME_LOWER . '_prop_' . $k,
			'lexicon' => PKG_NAME_LOWER . ':properties',
		), $v
	);
}

return $properties;
