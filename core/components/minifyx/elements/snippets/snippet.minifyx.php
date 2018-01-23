<?php
/** @var array $scriptProperties */
/** @var MinifyX $MinifyX */
if (isset($modx->minifyx) && $modx->minifyx instanceof MinifyX) {
    $MinifyX = $modx->minifyx;
    $MinifyX->reset($scriptProperties);
} else {
    $MinifyX = $modx->getService('minifyx', 'MinifyX', MODX_CORE_PATH . 'components/minifyx/model/minifyx/', $scriptProperties);
}

$sources = $MinifyX->prepareSources();

foreach ($sources as $type => $value) {
    if (empty($value)) {continue;}
//    $filename = $MinifyX->config[$type.'Filename'] . '_';
//    $extension = $MinifyX->config[$type.'Ext'];
    $register = $MinifyX->config['register'.ucfirst($type)];
    $placeholder = !empty($MinifyX->config[$type.'Placeholder'])
        ? $MinifyX->config[$type.'Placeholder']
        : '';

    $files = $MinifyX->prepareFiles($value, $type);
    $properties = array(
        'minify' => $MinifyX->config['minify'.ucfirst($type)]
            ? 'true'
            : 'false',
    );
    $result = $MinifyX->Munee($files, $properties);
    // Register file on frontend
    if ($MinifyX->saveFile($result)) {
        $tag = str_replace('[[+file]]', $MinifyX->getFileUrl(), $type == 'css' ? $cssTpl : $jsTpl);
        switch ($register) {
        	case 'placeholder':
                if ($register == 'placeholder' && $placeholder) {
                    $modx->setPlaceholder($placeholder, $tag);
                }
        		break;
            case 'print':
                return $tag;
            default:
                if ($type == 'css') {
                    $modx->regClientCSS($tag);
                }
                else {
                    if ($register == 'startup') {
                        $modx->regClientStartupScript($tag);
                    }
                    else {
                        $modx->regClientScript($tag);
                    }
                }
        }
    }
}

return;