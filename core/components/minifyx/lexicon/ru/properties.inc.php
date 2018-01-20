<?php

$_lang['minifyx_prop_jsSources'] = 'Список JS файлов для обработки. Можно указывать *.js и *.coffee.';
$_lang['minifyx_prop_cssSources'] = 'Список CSS файлов для обработки. Можно указывать *.css, *.less и *.scss.';

$_lang['minifyx_prop_jsFilename'] = 'Базовое имя готового JS файла.';
$_lang['minifyx_prop_cssFilename'] = 'Базовое имя готового CSS файла.';
$_lang['minifyx_prop_minifyCss'] = 'Включить минификацию CSS?';
$_lang['minifyx_prop_minifyJs'] = 'Включить минификацию JS?';

$_lang['minifyx_prop_registerJs'] = 'Подключение javascript: можно сохранить в плейсхолдер (placeholder), вызвать в теге "head" (startup) или разместить перед закрывающим "body" (default).';
$_lang['minifyx_prop_registerCss'] = 'Подключение сss: можно сохранить в плейсхолдер (placeholder) или вызвать в теге "head" (default).';
$_lang['minifyx_prop_jsPlaceholder'] = 'Имя плейсхолдера javascript. Используется, если &registerJs=`placeholder`';
$_lang['minifyx_prop_cssPlaceholder'] = 'Имя плейсхолдера css. Используется,если &registerCss=`placeholder`';

$_lang['minifyx_prop_forceUpdate'] = 'Отключить проверку изменения файлов и генерировать новые скрипты и стили каждый раз.';
$_lang['minifyx_prop_cacheFolder'] = 'Директория для хранения готовых файлов.';
$_lang['minifyx_prop_cssGroups'] = 'Названия групп стилей (через запятую).';
$_lang['minifyx_prop_jsGroups'] = 'Названия групп скриптов (через запятую).';
$_lang['minifyx_prop_preHooks'] = 'Список хуков через запятую для предварительной обработки. Хук может быть сниппетом или файлом.';
$_lang['minifyx_prop_hooks'] = 'Список хуков через запятую для пост обработки. Хук может быть сниппетом или файлом.';
