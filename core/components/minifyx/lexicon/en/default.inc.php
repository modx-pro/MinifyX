<?php

$_lang['minifyx'] = 'MinifyX';
$_lang['area_minifyx_main'] = 'Main';


$_lang['setting_minifyx_process_registered'] = 'Process scripts and styles';
$_lang['setting_minifyx_process_registered_desc'] = 'You can enable automatic processing of all registered scripts and styles of the page using the plugin MinifyX.';
$_lang['setting_minifyx_exclude_registered'] = 'Exclude scripts and styles';
$_lang['setting_minifyx_exclude_registered_desc'] = 'A regular expression for exclude files from processing. By default excludes scripts and styles prepared by snippet MinifyX.';

$_lang['setting_minifyx_process_images'] = 'Process images';
$_lang['setting_minifyx_process_images_desc'] = 'You can enable auto resize of images with specified attributes "width" or "height".';
$_lang['setting_minifyx_exclude_images'] = 'Exclude images';
$_lang['setting_minifyx_exclude_images_desc'] = 'A regular expression for exclude images from processing. By default excludes files with "thumb" or size in name.';
$_lang['setting_minifyx_images_filters'] = 'Images filters';
$_lang['setting_minifyx_images_filters_desc'] = 'You can specify string with additional image filters. See <a href="http://mun.ee/Usage_Instructions/Images">Munee documentation</a> for details. If the image tag has the attribute filters="" - it will override this setting.';

$_lang['setting_minifyx_minifyJs'] = 'Compress javascript?';
$_lang['setting_minifyx_minifyJs_desc'] = 'You can enable compression javascript compression. All files that have suffix .min in the name will be skipped.';
$_lang['setting_minifyx_minifyCss'] = 'Compress css?';
$_lang['setting_minifyx_minifyCss_desc'] = 'You can enable compression css compression. All files that have suffix .min in the name will be skipped.';

$_lang['setting_minifyx_cssFilename'] = 'Css filename';
$_lang['setting_minifyx_cssFilename_desc'] = 'Specify the name of the prepared css file that will contain all processed scripts. To it will be added the time of creation and suffix .min, if compression is enabled.';
$_lang['setting_minifyx_jsFilename'] = 'Javascript filename';
$_lang['setting_minifyx_jsFilename_desc'] = 'Specify the name of the prepared javascript file that will contain all processed scripts. To it will be added the time of creation and suffix .min, if compression is enabled.';

$_lang['setting_minifyx_cacheFolder'] = 'Directory for output files';
$_lang['setting_minifyx_cacheFolder_desc'] = 'Specify the directory where the plugin will put the results of it`s work. You can specify a non-existent directory, it will be created automatically.';

$_lang['setting_minifyx_processRawJs'] = 'Process raw javascript?';
$_lang['setting_minifyx_processRawJs_desc'] = 'Do you want to move the raw javascript from the page to the file';
$_lang['setting_minifyx_processRawCss'] = 'Process raw css?';
$_lang['setting_minifyx_processRawCss_desc'] = 'Do you want to move the raw css from the page to the file?';
$_lang['setting_minifyx_forceUpdate'] = 'Regenerate files.';
$_lang['setting_minifyx_forceUpdate_desc'] = 'Disable check of files update and generate new scripts and styles each time.';
$_lang['setting_minifyx_forceDelete'] = 'Remove all files.';
$_lang['setting_minifyx_forceDelete_desc'] = 'Remove all files in the cache directory.';