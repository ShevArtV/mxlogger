<?php
/**
 * Прямая установка mxLogger на стенд (без сборки transport-пакета).
 * Идемпотентно: создаёт/обновляет таблицу, namespace, категорию, настройки,
 * меню, сниппет и плагин с событиями. Запуск:
 *   /usr/local/php/php-7.4/bin/php <root>/modxbuilder/mxlogger/_install.php
 *
 * @package mxlogger
 */
set_time_limit(0);
$root = dirname(dirname(__DIR__)) . '/'; // .../mspaypalalt/

require_once $root . 'core/config/config.inc.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');
$modx->getService('error', 'error.modError');

$report = array();
$say = function ($m) use (&$report) { $report[] = $m; echo $m . "\n"; };

$corePath = MODX_CORE_PATH . 'components/mxlogger/';
$modelPath = $corePath . 'model/';

/* ---------- 1. Таблицы ---------- */
$modx->addPackage('mxlogger', $modelPath);
$manager = $modx->getManager();
$manager->createObjectContainer('mxLoggerLog');
foreach (array('tags', 'process_uid', 'class', 'function', 'file', 'line', 'trace') as $f) {
    $manager->addField('mxLoggerLog', $f);
}
$manager->addIndex('mxLoggerLog', 'process_uid');
$manager->addIndex('mxLoggerLog', 'class');

$table = $modx->getTableName('mxLoggerLog');
$ftExists = false;
if ($stmt = $modx->query("SHOW INDEX FROM {$table} WHERE Key_name = 'tags'")) {
    $ftExists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$ftExists) {
    $modx->exec("ALTER TABLE {$table} ADD FULLTEXT INDEX `tags` (`tags`)");
    $say('FULLTEXT-индекс tags создан.');
} else {
    $say('FULLTEXT-индекс tags уже есть.');
}
$say('Таблица ' . $table . ' готова.');

/* ---------- 2. Namespace ---------- */
$ns = $modx->getObject('modNamespace', 'mxlogger');
if (!$ns) {
    $ns = $modx->newObject('modNamespace');
    $ns->set('name', 'mxlogger');
}
$ns->set('path', '{core_path}components/mxlogger/');
$ns->set('assets_path', '{assets_path}components/mxlogger/');
$ns->save();
$say('Namespace mxlogger зарегистрирован.');

/* ---------- 3. Категория ---------- */
$category = $modx->getObject('modCategory', array('category' => 'mxLogger'));
if (!$category) {
    $category = $modx->newObject('modCategory');
    $category->set('category', 'mxLogger');
    $category->save();
}
$categoryId = $category->get('id');
$say('Категория mxLogger (id=' . $categoryId . ').');

/* ---------- 4. Системные настройки ---------- */
$settings = array(
    'mxlogger.enabled'         => array('1',     'combo-boolean'),
    'mxlogger.min_level'       => array('debug', 'textfield'),
    'mxlogger.capture_mode'    => array('auto',  'textfield'),
    'mxlogger.tag_filter_mode' => array('auto',  'textfield'),
    'mxlogger.trace_limit'     => array('15',    'numberfield'),
    'mxlogger.args_max_depth'  => array('3',     'numberfield'),
    'mxlogger.args_max_string' => array('512',   'numberfield'),
    'mxlogger.args_max_items'  => array('50',    'numberfield'),
    'mxlogger.filter_user'     => array('',      'textfield'),
    'mxlogger.filter_usergroup'=> array('',      'textfield'),
    'mxlogger.filter_session'  => array('',      'textfield'),
    'mxlogger.filter_cookie'   => array('',       'textfield'),
    'mxlogger.log_lifetime'    => array('604800', 'numberfield'),
    'mxlogger.rotate_interval' => array('3600',   'numberfield'),
);
$createdSettings = 0;
foreach ($settings as $key => $def) {
    if ($modx->getObject('modSystemSetting', $key)) {
        continue; // не перетираем уже настроенное
    }
    $s = $modx->newObject('modSystemSetting');
    $s->fromArray(array(
        'key'       => $key,
        'value'     => $def[0],
        'xtype'     => $def[1],
        'namespace' => 'mxlogger',
        'area'      => 'mxlogger:default',
    ), '', true, true);
    $s->save();
    $createdSettings++;
}
$say('Системные настройки: создано ' . $createdSettings . ' из ' . count($settings) . ' (существующие не тронуты).');

/* ---------- 5. Меню ---------- */
$menu = $modx->getObject('modMenu', array('text' => 'mxlogger'));
if (!$menu) {
    $menu = $modx->newObject('modMenu');
    $menu->fromArray(array(
        'text'        => 'mxlogger',
        'parent'      => 'components',
        'description' => 'mxlogger_menu_desc',
        'icon'        => '',
        'menuindex'   => 0,
        'params'      => '',
        'handler'     => '',
        'namespace'   => 'mxlogger',
        'action'      => 'index',
        'permissions' => '',
    ), '', true, true);
    $menu->save();
    $say('Пункт меню «Компоненты → mxlogger» создан.');
} else {
    $say('Пункт меню уже существует.');
}

/* ---------- 6. Сниппет ---------- */
$snippetFile = $corePath . 'elements/snippets/snippet.mxlogger.php';
if (is_readable($snippetFile)) {
    $code = preg_replace('/^<\?php\s*/', '', file_get_contents($snippetFile));
    $snippet = $modx->getObject('modSnippet', array('name' => 'mxLogger'));
    if (!$snippet) {
        $snippet = $modx->newObject('modSnippet');
        $snippet->set('name', 'mxLogger');
    }
    $snippet->set('description', 'Запись лога mxLogger из чанка/шаблона/Fenom.');
    $snippet->set('category', $categoryId);
    $snippet->set('snippet', $code);
    $snippet->set('static', 0);
    $snippet->save();
    $say('Сниппет mxLogger сохранён.');
} else {
    $say('ВНИМАНИЕ: файл сниппета не найден: ' . $snippetFile);
}

/* ---------- 7. Плагины + события (оба статичные) ---------- */
$pluginSpecs = array(
    array(
        'name'        => 'mxLoggerMiniShop2',
        'description' => 'Логирование действий с корзиной (cart) и заказом (order); сквозной тэг purchase.',
        'file'        => 'plugin.mslogger.php',
        'static_file' => 'mxlogger/elements/plugins/plugin.mslogger.php',
        'events'      => array(
            'msOnAddToCart', 'msOnChangeInCart', 'msOnRemoveFromCart', 'msOnEmptyCart',
            'msOnAddToOrder', 'msOnRemoveFromOrder', 'msOnEmptyOrder',
            'msOnBeforeCreateOrder', 'msOnCreateOrder', 'msOnSubmitOrder',
            'msOnChangeOrderStatus',
        ),
    ),
    array(
        'name'        => 'mxLoggerRotate',
        'description' => 'Ротация старых записей лога mxLogger (троттлинг + батчевое удаление).',
        'file'        => 'plugin.mxloggerrotate.php',
        'static_file' => 'mxlogger/elements/plugins/plugin.mxloggerrotate.php',
        'events'      => array('OnMODXInit'),
    ),
);
foreach ($pluginSpecs as $spec) {
    $pluginFile = $corePath . 'elements/plugins/' . $spec['file'];
    if (!is_readable($pluginFile)) {
        $say('ВНИМАНИЕ: файл плагина не найден: ' . $pluginFile);
        continue;
    }
    $plugin = $modx->getObject('modPlugin', array('name' => $spec['name']));
    if (!$plugin) {
        $plugin = $modx->newObject('modPlugin');
        $plugin->set('name', $spec['name']);
    }
    $plugin->set('description', $spec['description']);
    $plugin->set('category', $categoryId);
    $plugin->set('static', 1);
    $plugin->set('static_file', $spec['static_file']);
    $plugin->set('source', 0);
    $plugin->set('plugincode', '');
    $plugin->set('disabled', 0);
    $plugin->save();
    $pluginId = $plugin->get('id');

    $linked = 0;
    foreach ($spec['events'] as $event) {
        if (!$modx->getObject('modEvent', array('name' => $event))) {
            $say('  пропуск (событие не зарегистрировано): ' . $event);
            continue;
        }
        if (!$modx->getObject('modPluginEvent', array('pluginid' => $pluginId, 'event' => $event))) {
            $pe = $modx->newObject('modPluginEvent');
            $pe->fromArray(array('pluginid' => $pluginId, 'event' => $event, 'priority' => 0, 'propertyset' => 0), '', true, true);
            $pe->save();
            $linked++;
        }
    }
    $say('Плагин ' . $spec['name'] . ' (id=' . $pluginId . '), новых событий: ' . $linked . '.');
}

/* ---------- 8. extension_packages (автозагрузка $modx->mxlogger) ---------- */
$raw = $modx->getOption('extension_packages', null, '[]');
$packages = json_decode($raw, true);
if (!is_array($packages)) { $packages = array(); }
$clean = array();
foreach ($packages as $pkg) {
    if (is_array($pkg) && array_key_exists('mxlogger', $pkg)) { continue; }
    $clean[] = $pkg;
}
$clean[] = array('mxlogger' => array(
    'path' => '[[++core_path]]components/mxlogger/model/',
    'serviceName' => 'mxlogger',
    'serviceClass' => 'mxLogger',
));
$eps = $modx->getObject('modSystemSetting', 'extension_packages');
if (!$eps) {
    $eps = $modx->newObject('modSystemSetting');
    $eps->fromArray(array('key' => 'extension_packages', 'namespace' => 'core', 'area' => 'system', 'xtype' => 'textfield'), '', true, true);
}
$eps->set('value', json_encode(array_values($clean)));
$eps->save();
$say('extension_packages: mxlogger зарегистрирован (доступен как $modx->mxlogger).');

/* ---------- 9. Сброс кэша ---------- */
$modx->cacheManager->refresh();
$say('Кэш сброшен.');
$say('=== Установка mxLogger завершена ===');
