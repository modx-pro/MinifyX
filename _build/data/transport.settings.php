<?php

$settings = [];

$tmp = [
    'process_registered' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'debug' => [
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
    'cache' => [
        'xtype' => 'textfield',
        'value' => MODX_CORE_PATH . 'cache/default/minifyx/',
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
        'value' => '/assets/components/minifyx/minifyx.php',
    ],
    'imageProcessor' => [
        'xtype' => 'list',
        'value' => 'GD',
        'options' => [
            ['text' => 'GD', 'value' => 'GD'],
            ['text' => 'Imagick', 'value' => 'Imagick'],
        ],
    ],
    'image_signing_key' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'image_signing_keys' => [
        'xtype' => 'textarea',
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
    'image_rate_limit_max' => [
        'xtype' => 'numberfield',
        'value' => 0,
    ],
    'image_rate_limit_window' => [
        'xtype' => 'numberfield',
        'value' => 60,
    ],
    'image_rate_limit_salt' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'mangleJs' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'bundleJsModules' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'sourceMaps' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'parallelBuild' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'jsMangler' => [
        'xtype' => 'list',
        'value' => 'terser',
        'options' => [
            ['text' => 'Terser', 'value' => 'terser'],
            ['text' => 'esbuild', 'value' => 'esbuild'],
        ],
    ],
    'jsManglerPath' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'esbuildPath' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'jsManglerMaxInputBytes' => [
        'xtype' => 'numberfield',
        'value' => 5000000,
    ],
    'preloadCss' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'preloadJs' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'bundleIntegrity' => [
        'xtype' => 'combo-boolean',
        'value' => false,
    ],
    'cors_origin' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'cssPreloadTpl' => [
        'xtype' => 'textfield',
        'value' => '',
    ],
    'jsPreloadTpl' => [
        'xtype' => 'textfield',
        'value' => '',
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
