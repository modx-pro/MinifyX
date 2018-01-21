<?php

$_lang['minifyx'] = 'MinifyX';
$_lang['area_minifyx_main'] = 'Основные';


$_lang['setting_minifyx_process_registered'] = 'Обработка скриптов и стилей';
$_lang['setting_minifyx_process_registered_desc'] = 'Вы можете включить автоматическую сборку и обработку всех зарегистрированных скриптов и стилей страницы при помощи плагина MinifyX.';
$_lang['setting_minifyx_exclude_registered'] = 'Исключить скрипты и стили';
$_lang['setting_minifyx_exclude_registered_desc'] = 'Регулярное выражение для исключения зарегистрированных файлов из обработки. По умолчанию исключаются скрипты и стили, подготовленные сниппетом MinifyX.';

$_lang['setting_minifyx_process_images'] = 'Обработка изображений';
$_lang['setting_minifyx_process_images_desc'] = 'Вы можете включить ресайз изображений, у которых указана высота или ширина.';
$_lang['setting_minifyx_exclude_images'] = 'Исключить изображения';
$_lang['setting_minifyx_exclude_images_desc'] = 'Регулярное выражение для исключения изображений из обработки. По умолчанию исключаются файлы с "thumb" или размером в имени.';
$_lang['setting_minifyx_images_filters'] = 'Фильтры изображений';
$_lang['setting_minifyx_images_filters_desc'] = 'Вы можете добавить строку с дополнительными фильтрами обработки изображений. Подробности смотрите в <a href="http://mun.ee/Usage_Instructions/Images">документации Munee</a>. Если у тега изображения указан атрибут filters="" - он перекроет эту настройку.';

$_lang['setting_minifyx_minifyJs'] = 'Сжимать javascript?';
$_lang['setting_minifyx_minifyJs_desc'] = 'Вы можете включите сжатие javascript. Все файлы, у которых есть в имени суффикс .min будут пропущены.';
$_lang['setting_minifyx_minifyCss'] = 'Сжимать css?';
$_lang['setting_minifyx_minifyCss_desc'] = 'Вы можете включите сжатие css. Все файлы, у которых есть в имени суффикс .min будут пропущены.';

$_lang['setting_minifyx_cssFilename'] = 'Имя готового css';
$_lang['setting_minifyx_cssFilename_desc'] = 'Укажите имя готового css файла, который будет содержать все обработанные стили. К нему будет добавлено время создания и, если включено сжатие - суффикс .min.';
$_lang['setting_minifyx_jsFilename'] = 'Имя готового javascript';
$_lang['setting_minifyx_jsFilename_desc'] = 'Укажите имя готового javascript файла, который будет содержать все обработанные скрипты. К нему будет добавлено время создания и, если включено сжатие - суффикс .min.';

$_lang['setting_minifyx_cacheFolder'] = 'Директория с готовыми файлами';
$_lang['setting_minifyx_cacheFolder_desc'] = 'Укажите директорию, в которую плагин будет складывать результаты своей работы. Можно указывать несуществующую директорию - она будет создана автоматически.';

$_lang['setting_minifyx_processRawJs'] = 'Обрабатывать сырой javascript?';
$_lang['setting_minifyx_processRawJs_desc'] = 'Укажите, нужно ли переносить в файлы сырой javascript, который указан прямо на странице мужду тегами script?';
$_lang['setting_minifyx_processRawCss'] = 'Обрабатывать сырой css?';
$_lang['setting_minifyx_processRawCss_desc'] = 'Укажите, нужно ли переносить в файлы сырой css, который указан прямо на странице мужду тегами style?';
$_lang['setting_minifyx_forceUpdate'] = 'Перезапивывать файлы.';
$_lang['setting_minifyx_forceUpdate_desc'] = 'Отключить проверку изменения файлов и перезаписывать новые скрипты и стили каждый раз.';
$_lang['setting_minifyx_forceDelete'] = 'Удалять все файлы.';
$_lang['setting_minifyx_forceDelete_desc'] = 'Удаляются все файлы из директории для кэшированных файлов.';