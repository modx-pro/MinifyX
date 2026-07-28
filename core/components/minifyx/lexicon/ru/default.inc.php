<?php

$_lang['minifyx'] = 'MinifyX';
$_lang['area_minifyx_main'] = 'Основные';


$_lang['setting_minifyx_process_registered'] = 'Обработка скриптов и стилей';
$_lang['setting_minifyx_process_registered_desc']
    = 'Вы можете включить автоматическую сборку'
    . ' и обработку всех зарегистрированных скриптов'
    . ' и стилей страницы при помощи плагина MinifyX.';
$_lang['setting_minifyx_exclude_registered'] = 'Исключить скрипты и стили';
$_lang['setting_minifyx_exclude_registered_desc']
    = 'Регулярное выражение для исключения'
    . ' зарегистрированных файлов из обработки.'
    . ' По умолчанию исключаются скрипты и стили,'
    . ' подготовленные сниппетом MinifyX.';

$_lang['setting_minifyx_process_images'] = 'Обработка изображений';
$_lang['setting_minifyx_process_images_desc']
    = 'Вы можете включить ресайз изображений, у которых указана'
    . ' высота или ширина.';
$_lang['setting_minifyx_exclude_images'] = 'Исключить изображения';
$_lang['setting_minifyx_exclude_images_desc']
    = 'Регулярное выражение для исключения'
    . ' изображений из обработки.'
    . ' По умолчанию исключаются файлы с "thumb" или размером'
    . ' в имени.';
$_lang['setting_minifyx_images_filters'] = 'Фильтры изображений';
$_lang['setting_minifyx_images_filters_desc']
    = 'Строка фильтров по умолчанию, например s[true]. Атрибут filters=""'
    . ' у тега перекрывает настройку. Поддерживается: resize через'
    . ' width/height, sharpen, brightness[n], contrast[n].';

$_lang['setting_minifyx_minifyJs'] = 'Сжимать javascript?';
$_lang['setting_minifyx_minifyJs_desc']
    = 'Включает сжатие javascript. Все файлы, у которых есть в имени'
    . ' суффикс .min будут пропущены.';
$_lang['setting_minifyx_minifyCss'] = 'Сжимать css?';
$_lang['setting_minifyx_minifyCss_desc']
    = 'Включает сжатие css. Все файлы, у которых есть в имени'
    . ' суффикс .min будут пропущены.';
$_lang['setting_minifyx_minifyHtml'] = 'Сжимать HTML?';
$_lang['setting_minifyx_minifyHtml_desc']
    = 'Сжимает HTML перед выводом. Блоки pre, textarea, script, style'
    . ' и conditional comments сохраняются.';

$_lang['setting_minifyx_cssFilename'] = 'Имя готового css';
$_lang['setting_minifyx_cssFilename_desc']
    = 'Укажите имя готового css файла, который будет содержать все'
    . ' обработанные стили. К нему будет добавлено время создания и,'
    . ' если включено сжатие - суффикс .min.';
$_lang['setting_minifyx_jsFilename'] = 'Имя готового javascript';
$_lang['setting_minifyx_jsFilename_desc']
    = 'Укажите имя готового javascript файла,'
    . ' который будет содержать все обработанные скрипты.'
    . ' К нему будет добавлено время создания и,'
    . ' если включено сжатие - суффикс .min.';

$_lang['setting_minifyx_cacheFolder'] = 'Директория с готовыми файлами';
$_lang['setting_minifyx_cacheFolder_desc']
    = 'Укажите директорию, в которую плагин'
    . ' будет складывать результаты своей работы.'
    . ' Можно указывать несуществующую директорию -'
    . ' она будет создана автоматически.';
$_lang['setting_minifyx_cache'] = 'Директория кэша изображений';
$_lang['setting_minifyx_cache_desc'] = 'Файловая директория кэша image connector MinifyX.';

$_lang['setting_minifyx_processRawJs'] = 'Обрабатывать сырой javascript?';
$_lang['setting_minifyx_processRawJs_desc']
    = 'Укажите, нужно ли переносить в файлы сырой javascript,'
    . ' который указан прямо на странице мужду тегами script?';
$_lang['setting_minifyx_processRawCss'] = 'Обрабатывать сырой css?';
$_lang['setting_minifyx_processRawCss_desc']
    = 'Укажите, нужно ли переносить в файлы сырой css,'
    . ' который указан прямо на странице мужду тегами style?';
$_lang['setting_minifyx_forceUpdate'] = 'Перезапивывать файлы';
$_lang['setting_minifyx_forceUpdate_desc']
    = 'Отключить проверку изменения файлов и перезаписывать новые'
    . ' скрипты и стили каждый раз.';
$_lang['setting_minifyx_forceDelete'] = 'Удалять все файлы';
$_lang['setting_minifyx_forceDelete_desc']
    = 'Удаляются все файлы из директории для кэшированных файлов.';

$_lang['setting_minifyx_connector'] = 'URL image connector';
$_lang['setting_minifyx_connector_desc']
    = 'Публичный endpoint для on-the-fly трансформации изображений.'
    . ' По умолчанию: /assets/components/minifyx/minifyx.php';
$_lang['setting_minifyx_imageProcessor'] = 'Обработчик изображений';
$_lang['setting_minifyx_imageProcessor_desc'] = 'Backend изображений: GD или Imagick.';
$_lang['setting_minifyx_image_signing_key'] = 'Ключ подписи URL изображений';
$_lang['setting_minifyx_image_signing_key_desc']
    = 'HMAC-ключ для URL изображений. Если задан, unsigned'
    . ' или неверная подпись отклоняются с HTTP 403.'
    . ' Plugin rewrite добавляет &sig= автоматически.';
$_lang['setting_minifyx_image_max_pixels'] = 'Максимум пикселей изображения';
$_lang['setting_minifyx_image_max_pixels_desc']
    = 'Отклонять исходники с width×height выше этого лимита'
    . ' до decode.';
$_lang['setting_minifyx_image_max_bytes'] = 'Максимум байт изображения';
$_lang['setting_minifyx_image_max_bytes_desc']
    = 'Отклонять файлы изображений больше указанного'
    . ' размера в байтах.';
$_lang['setting_minifyx_bundleIntegrity'] = 'Subresource integrity для бандлов';
$_lang['setting_minifyx_bundleIntegrity_desc']
    = 'Добавлять SHA-384 integrity и crossorigin="anonymous"'
    . ' к тегам собранных бандлов.';
$_lang['setting_minifyx_cors_origin'] = 'CORS origin image connector';
$_lang['setting_minifyx_cors_origin_desc']
    = 'Необязательное значение Access-Control-Allow-Origin'
    . ' для image connector. Пустое значение отключает заголовок.';
$_lang['setting_minifyx_debug'] = 'Безопасные debug-комментарии бандлов';
$_lang['setting_minifyx_debug_desc']
    = 'Добавлять HTML-комментарии с типом, числом исходников,'
    . ' статусом кэша и именем бандла.';
$_lang['setting_minifyx_image_signing_keys'] = 'Ключи подписи URL изображений';
$_lang['setting_minifyx_image_signing_keys_desc']
    = 'HMAC-ключи через запятую или новую строку.'
    . ' Первый подписывает, все проверяют.';
$_lang['setting_minifyx_image_rate_limit_max'] = 'Запросов изображений за окно';
$_lang['setting_minifyx_image_rate_limit_max_desc']
    = 'Максимум запросов на client hash. 0 отключает лимит.';
$_lang['setting_minifyx_image_rate_limit_window'] = 'Окно rate limit изображений';
$_lang['setting_minifyx_image_rate_limit_window_desc'] = 'Длина окна rate limit в секундах.';
$_lang['setting_minifyx_image_rate_limit_salt'] = 'Salt rate limit изображений';
$_lang['setting_minifyx_image_rate_limit_salt_desc']
    = 'Секретный salt для хеширования IP клиента.';
$_lang['setting_minifyx_jsManglerMaxInputBytes'] = 'Максимальный input JS mangler';
$_lang['setting_minifyx_jsManglerMaxInputBytes_desc']
    = 'Отклонять больший JS input до запуска внешнего процесса.';
$_lang['setting_minifyx_bundleJsModules'] = 'Собирать ES-модули';
$_lang['setting_minifyx_bundleJsModules_desc']
    = 'Использовать esbuild --bundle для зарегистрированных'
    . ' type="module" скриптов.'
    . ' По умолчанию выключено.';
$_lang['setting_minifyx_esbuildPath'] = 'Путь к esbuild';
$_lang['setting_minifyx_esbuildPath_desc']
    = 'Отдельный путь к esbuild для сборки ES-модулей.'
    . ' Пустое значение использует PATH.';
$_lang['setting_minifyx_sourceMaps'] = 'Создавать source maps';
$_lang['setting_minifyx_sourceMaps_desc']
    = 'Создавать внешние карты для поддерживаемых Terser/esbuild backend.'
    . ' По умолчанию выключено.';
$_lang['setting_minifyx_parallelBuild'] = 'Параллельный прогрев кэша';
$_lang['setting_minifyx_parallelBuild_desc']
    = 'Разрешить ограниченный параллелизм процессов в warm-cache CLI.'
    . ' Web-запросы остаются последовательными.';
