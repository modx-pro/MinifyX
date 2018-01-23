<?php

$_lang['minifyx_prop_jsSources'] = 'Comma-separated list of JS files for processing. You can specify a *.js and *.coffee.';
$_lang['minifyx_prop_cssSources'] = 'Comma-separated list of CSS files for processing. You can specify a *.css, *.less and *.scss.';

$_lang['minifyx_prop_jsFilename'] = 'Base name of destination js file, without extension';
$_lang['minifyx_prop_cssFilename'] = 'Base name of destination css file, without extension';
$_lang['minifyx_prop_minifyCss'] = 'Enable CSS minify?';
$_lang['minifyx_prop_minifyJs'] = 'Enable JS minify?';

$_lang['minifyx_prop_registerJs'] = 'How to register javascript? You can save it in the placeholder, call it in the tag "head" (startup), place before the closing "body" (default) or output immediately (print).';
$_lang['minifyx_prop_registerCss'] = 'How to register CSS? You can save it in the placeholder, call in the tag "head" (default) or output immediately (print).';
$_lang['minifyx_prop_jsPlaceholder'] = 'Name of javascript placeholder. Will be used only if &registerJs=`placeholder`';
$_lang['minifyx_prop_cssPlaceholder'] = 'Name of css placeholder. Will be used only if &registerCss=`placeholder`';

$_lang['minifyx_prop_forceUpdate'] = 'Disable check of files update and generate new scripts and styles each time.';
$_lang['minifyx_prop_cacheFolder'] = 'The folder to the cache files from the site base URL';
$_lang['minifyx_prop_cssGroups'] = 'Comma separated list of css groups.';
$_lang['minifyx_prop_jsGroups'] = 'Comma separated list of js groups.';
$_lang['minifyx_prop_preHooks'] = 'Comma separated list of hooks that are executed before processing. A hook can be a snippet or file.';
$_lang['minifyx_prop_hooks'] = 'Comma separated list of hooks that are executed after processing. A hook can be a snippet or file.';
$_lang['minifyx_prop_cssTpl'] = 'Css file template for output. Placeholder "[[+file]]" must exists.';
$_lang['minifyx_prop_jsTpl'] = 'Template fo js file. Placeholder "[[+file]]" must exists.';
$_lang['minifyx_prop_forceUpdate'] = 'Disable check of files update and resave new scripts and styles each time.';