<?php
$properties = [];

$tmp = [
    'jsSources' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'cssSources' => [
        'xtype' => 'textfield',
        'value' => '',
    ],

    'minifyJs' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'minifyCss' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],

    'jsFilename' => [
        'xtype' => 'textfield',
        'value' => 'scripts',
    ],
    'cssFilename' => [
        'xtype' => 'textfield',
        'value' => 'styles',
    ],

    'registerJs' => [
        'xtype' => 'list',
        'value' => 'placeholder',
        'options' => [
            ['name' => 'Placeholder', 'value' => 'placeholder'],
            ['name' => 'Startup script', 'value' => 'startup'],
            ['name' => 'Default', 'value' => 'default'],
            ['name' => 'Print', 'value' => 'print'],
        ],
    ],
    'jsPlaceholder' => [
        'xtype' => 'textfield',
        'value' => 'MinifyX.javascript',
    ],
    'registerCss' => [
        'xtype' => 'list',
        'value' => 'placeholder',
        'options' => [
            ['name' => 'Placeholder', 'value' => 'placeholder'],
            ['name' => 'Default', 'value' => 'default'],
            ['name' => 'Print', 'value' => 'print'],
        ],
    ],
    'cssPlaceholder' => [
        'xtype' => 'textfield',
        'value' => 'MinifyX.css',
    ],
    'jsGroups' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'cssGroups' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'preHooks' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'hooks' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'cssTpl' => [
        'xtype' => 'textfield',
        'value' => '<link rel="stylesheet" href="[[+file]]" type="text/css" />',
    ],
    'jsTpl' => [
        'xtype' => 'textfield',
        'value' => '<script src="[[+file]]"></script>',
    ],
    'forceUpdate' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'version' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
];

foreach ($tmp as $k => $v) {
    $properties[] = array_merge(
        [
            'name' => $k,
            'desc' => PKG_NAME_LOWER . '_prop_' . $k,
            'lexicon' => PKG_NAME_LOWER . ':properties',
        ], $v
    );
}

return $properties;
