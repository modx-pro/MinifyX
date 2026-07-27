<?php

$settings = [];

$tmp = [
    'process_registered' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'process_images' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'exclude_registered' => [
        'xtype' => 'textarea',
        'value' => '#(scripts|styles)_[a-z0-9]{10}\.#i',
    ],
    'exclude_images' => [
        'xtype' => 'textarea',
        'value' => '#(thumb|/\d+x\d+/)#i',
    ],
    'images_filters' => [
        'xtype' => 'textfield',
        'value' => 's[true]',
    ],

    'minifyJs' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'minifyCss' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],

    'processRawJs' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'processRawCss' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],

    'jsFilename' => [
        'xtype' => 'textfield',
        'value' => 'all',
    ],
    'cssFilename' => [
        'xtype' => 'textfield',
        'value' => 'all',
    ],
    'cacheFolder' => [
        'xtype' => 'textfield',
        'value' => '/assets/components/minifyx/cache/',
    ],
    'forceUpdate' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'forceDelete' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'minifyHtml' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'connector' => [
        'xtype' => 'textfield',
        'value' => '/assets/components/minifyx/munee.php',
    ],
    'image_signing_key' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'image_max_pixels' => [
        'xtype' => 'numberfield',
        'value' => 20000000,
    ],
    'image_max_bytes' => [
        'xtype' => 'numberfield',
        'value' => 20000000,
    ],
];

foreach ($tmp as $k => $v) {
    /* @var modSystemSetting $setting */
    $setting = $modx->newObject('modSystemSetting');
    $setting->fromArray(array_merge(
        [
            'key' => PKG_NAME_LOWER . '_' . $k,
            'namespace' => PKG_NAME_LOWER,
            'area' => PKG_NAME_LOWER . '_main',
        ], $v
    ), '', true, true);

    $settings[] = $setting;
}

unset($tmp);
return $settings;
