<?php

$_lang['minifyx'] = 'MinifyX';
$_lang['area_minifyx_main'] = 'Main';


$_lang['setting_minifyx_process_registered'] = 'Process scripts and styles';
$_lang['setting_minifyx_process_registered_desc'] = 'You can enable automatic processing of all registered scripts'
    . 'and styles of the page using the plugin MinifyX.';
$_lang['setting_minifyx_exclude_registered'] = 'Exclude scripts and styles';
$_lang['setting_minifyx_exclude_registered_desc'] = 'A regular expression for exclude files from processing. By'
    . 'default excludes scripts and styles prepared by snippet MinifyX.';

$_lang['setting_minifyx_process_images'] = 'Process images';
$_lang['setting_minifyx_process_images_desc'] = 'You can enable auto resize of images with specified attributes'
    . '"width" or "height".';
$_lang['setting_minifyx_exclude_images'] = 'Exclude images';
$_lang['setting_minifyx_exclude_images_desc'] = 'A regular expression for exclude images from processing. By default'
    . 'excludes files with "thumb" or size in name.';
$_lang['setting_minifyx_images_filters'] = 'Images filters';
$_lang['setting_minifyx_images_filters_desc'] = 'Default image filters string, for example s[true]. Tag attribute'
    . 'filters="" overrides this setting. Supported subset: resize via'
    . 'width/height, sharpen, brightness[n], contrast[n].';

$_lang['setting_minifyx_minifyJs'] = 'Compress javascript?';
$_lang['setting_minifyx_minifyJs_desc'] = 'You can enable compression javascript compression. All files that have'
    . 'suffix .min in the name will be skipped.';
$_lang['setting_minifyx_minifyCss'] = 'Compress css?';
$_lang['setting_minifyx_minifyCss_desc'] = 'You can enable compression css compression. All files that have suffix'
    . '.min in the name will be skipped.';
$_lang['setting_minifyx_minifyHtml'] = 'Compress HTML?';
$_lang['setting_minifyx_minifyHtml_desc'] = 'Compress the page content before output. Safe blocks (pre, textarea,'
    . 'script, style, conditional comments) are preserved.';

$_lang['setting_minifyx_cssFilename'] = 'Css filename';
$_lang['setting_minifyx_cssFilename_desc'] = 'Specify the name of the prepared css file that will contain all'
    . 'processed scripts. To it will be added the time of creation and suffix'
    . '.min, if compression is enabled.';
$_lang['setting_minifyx_jsFilename'] = 'Javascript filename';
$_lang['setting_minifyx_jsFilename_desc'] = 'Specify the name of the prepared javascript file that will contain all'
    . 'processed scripts. To it will be added the time of creation and suffix'
    . '.min, if compression is enabled.';

$_lang['setting_minifyx_cacheFolder'] = 'Directory for output files';
$_lang['setting_minifyx_cacheFolder_desc'] = 'Specify the directory where the plugin will put the results of it`s'
    . 'work. You can specify a non-existent directory, it will be created'
    . 'automatically.';
$_lang['setting_minifyx_cache'] = 'Image cache directory';
$_lang['setting_minifyx_cache_desc'] = 'Filesystem directory used by the MinifyX image connector.';

$_lang['setting_minifyx_processRawJs'] = 'Process raw javascript?';
$_lang['setting_minifyx_processRawJs_desc'] = 'Do you want to move the raw javascript from the page to the file';
$_lang['setting_minifyx_processRawCss'] = 'Process raw css?';
$_lang['setting_minifyx_processRawCss_desc'] = 'Do you want to move the raw css from the page to the file?';
$_lang['setting_minifyx_forceUpdate'] = 'Regenerate files.';
$_lang['setting_minifyx_forceUpdate_desc'] = 'Disable check of files update and generate new scripts and styles each'
    . 'time.';
$_lang['setting_minifyx_forceDelete'] = 'Remove all files.';
$_lang['setting_minifyx_forceDelete_desc'] = 'Remove all files in the cache directory.';

$_lang['setting_minifyx_connector'] = 'Image connector URL';
$_lang['setting_minifyx_connector_desc'] = 'Public endpoint for on-the-fly image transforms. Default:'
    . '/assets/components/minifyx/minifyx.php';
$_lang['setting_minifyx_imageProcessor'] = 'Image processor';
$_lang['setting_minifyx_imageProcessor_desc'] = 'Image backend: GD or Imagick.';
$_lang['setting_minifyx_image_signing_key'] = 'Image URL signing key';
$_lang['setting_minifyx_image_signing_key_desc'] = 'HMAC key for image URLs. When set, unsigned or invalid signatures'
    . 'are rejected with HTTP 403. Plugin rewrite adds &sig='
    . 'automatically.';
$_lang['setting_minifyx_image_max_pixels'] = 'Max image pixels';
$_lang['setting_minifyx_image_max_pixels_desc'] = 'Reject source images above this width×height product before'
    . ' decode.';
$_lang['setting_minifyx_image_max_bytes'] = 'Max image bytes';
$_lang['setting_minifyx_image_max_bytes_desc'] = 'Reject source image files larger than this size in bytes.';
$_lang['setting_minifyx_bundleIntegrity'] = 'Bundle subresource integrity';
$_lang['setting_minifyx_bundleIntegrity_desc'] = 'Add a SHA-384 integrity hash and crossorigin="anonymous"'
    . ' to generated bundle tags.';
$_lang['setting_minifyx_cors_origin'] = 'Image connector CORS origin';
$_lang['setting_minifyx_cors_origin_desc'] = 'Optional Access-Control-Allow-Origin value for the image connector.'
    . ' Leave empty to disable the header.';
$_lang['setting_minifyx_debug'] = 'Safe bundle debug comments';
$_lang['setting_minifyx_debug_desc'] = 'Emit HTML comments with bundle type, source count, cache status and filename.';
$_lang['setting_minifyx_image_signing_keys'] = 'Image URL signing keys';
$_lang['setting_minifyx_image_signing_keys_desc']
    = 'Comma or newline separated HMAC keys. The first signs; all verify.';
$_lang['setting_minifyx_image_rate_limit_max'] = 'Image requests per window';
$_lang['setting_minifyx_image_rate_limit_max_desc'] = 'Maximum requests per client hash. Set to 0 to disable.';
$_lang['setting_minifyx_image_rate_limit_window'] = 'Image rate-limit window';
$_lang['setting_minifyx_image_rate_limit_window_desc'] = 'Rate-limit window length in seconds.';
$_lang['setting_minifyx_image_rate_limit_salt'] = 'Image rate-limit salt';
$_lang['setting_minifyx_image_rate_limit_salt_desc'] = 'Optional secret salt used when hashing client IP addresses.';
$_lang['setting_minifyx_jsManglerMaxInputBytes'] = 'JS mangler maximum input bytes';
$_lang['setting_minifyx_jsManglerMaxInputBytes_desc'] = 'Reject larger JS input before starting an external process.';
$_lang['setting_minifyx_bundleJsModules'] = 'Bundle ES modules';
$_lang['setting_minifyx_bundleJsModules_desc']
    = 'Use esbuild --bundle for registered type="module" scripts. Disabled by default.';
$_lang['setting_minifyx_esbuildPath'] = 'esbuild binary path';
$_lang['setting_minifyx_esbuildPath_desc']
    = 'Optional dedicated esbuild path for ES module bundling. Leave empty to use PATH.';
$_lang['setting_minifyx_sourceMaps'] = 'Generate source maps';
$_lang['setting_minifyx_sourceMaps_desc']
    = 'Generate external maps for supported Terser/esbuild backends. Disabled by default.';
$_lang['setting_minifyx_parallelBuild'] = 'Parallel warm-cache builds';
$_lang['setting_minifyx_parallelBuild_desc']
    = 'Allow bounded process parallelism in warm-cache CLI. Web requests remain sequential.';
