<?php

$_lang['minifyx_prop_jsSources'] = 'Список JS файлов для обработки. Можно указывать *.js и *.coffee.';
$_lang['minifyx_prop_cssSources'] = 'Список CSS файлов для обработки. Можно указывать *.css, *.less и *.scss.';

$_lang['minifyx_prop_jsFilename'] = 'Базовое имя готового JS файла.';
$_lang['minifyx_prop_cssFilename'] = 'Базовое имя готового CSS файла.';
$_lang['minifyx_prop_minifyCss'] = 'Включить минификацию CSS?';
$_lang['minifyx_prop_minifyJs'] = 'Включить минификацию JS?';

$_lang['minifyx_prop_registerJs'] = 'Подключение javascript: можно сохранить в плейсхолдер (placeholder), вызвать в теге "head" (startup), разместить перед закрывающим "body" (default) или вывести немедленно (print).';
$_lang['minifyx_prop_registerCss'] = 'Подключение сss: можно сохранить в плейсхолдер (placeholder), вызвать в теге "head" (default) или вывести немедленно (print).';
$_lang['minifyx_prop_jsPlaceholder'] = 'Имя плейсхолдера javascript. Используется, если &registerJs=`placeholder`';
$_lang['minifyx_prop_cssPlaceholder'] = 'Имя плейсхолдера css. Используется,если &registerCss=`placeholder`';

$_lang['minifyx_prop_forceUpdate'] = 'Отключить проверку изменения файлов и перезаписывать новые скрипты и стили каждый раз.';
$_lang['minifyx_prop_cacheFolder'] = 'Директория для хранения готовых файлов.';
$_lang['minifyx_prop_cssGroups'] = 'Названия групп стилей (через запятую).';
$_lang['minifyx_prop_jsGroups'] = 'Названия групп скриптов (через запятую).';
$_lang['minifyx_prop_preHooks'] = 'Список хуков через запятую для предварительной обработки. Хуки могут быть сниппетами или файлами.';
$_lang['minifyx_prop_hooks'] = 'Список хуков через запятую для обработки полученного результата. Хуки могут быть сниппетами или файлами.';
$_lang['minifyx_prop_cssTpl'] = 'Шаблон для файла стилей. Должен быть указан плейсхолдер [[+file]].';
$_lang['minifyx_prop_jsTpl'] = 'Шаблон для файла скриптов. Должен быть указан плейсхолдер [[+file]].';
$_lang['minifyx_prop_version'] = "Версия файла. Добавляется к линку. Укажите любое значение, или '' для отключения, 'auto' для генерирования хэша.";

$_lang['minifyx_prop_mangleJs'] = 'Включить mangling идентификаторов JS через внешний оптимизатор (Terser или esbuild). При недоступном binary используется PHP minifier.';
$_lang['minifyx_prop_jsMangler'] = 'Backend для JS mangling: terser или esbuild.';
$_lang['minifyx_prop_jsManglerPath'] = 'Абсолютный путь к binary terser/esbuild. Пусто — поиск в PATH.';
$_lang['minifyx_prop_preloadCss'] = 'Добавлять companion <link rel="preload" as="style"> для CSS bundle (opt-in).';
$_lang['minifyx_prop_preloadJs'] = 'Добавлять companion preload/modulepreload для JS bundle в head (opt-in).';
$_lang['minifyx_prop_cssPreloadTpl'] = 'Шаблон CSS preload. Обязателен плейсхолдер [[+file]].';
$_lang['minifyx_prop_jsPreloadTpl'] = 'Шаблон JS preload. Обязателен плейсхолдер [[+file]].';
