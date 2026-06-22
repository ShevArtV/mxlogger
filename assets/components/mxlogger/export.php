<?php
/**
 * Потоковый экспорт журнала mxLogger в текстовый файл (.md / .txt).
 *
 * Не процессор: отдаёт файл напрямую (Content-Disposition: attachment),
 * а не JSON — поэтому живёт отдельным эндпоинтом рядом с connector.php.
 *
 * Фильтры берутся из тех же GET-параметров, что и грид, и прогоняются
 * через mxLoggerLogFilters::build() — единый источник правды. Поэтому
 * экспорт отдаёт ровно те записи, что видны в гриде (и что удалила бы
 * очистка по тому же фильтру). Без фильтров — весь журнал.
 *
 * Рендер строк вынесен в mxLoggerLogExporter (exporter.php) — его гоняет тест.
 * Выборка идёт батчами и пишется в php://output потоком, чтобы не держать
 * весь журнал в памяти.
 *
 * @package mxlogger
 */
@set_time_limit(0);

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

/** @var modX $modx */
$modx = new modX();
$modx->initialize('mgr');
$modx->getService('error', 'error.modError');
$modx->lexicon->load('mxlogger:default');

// Доступ только авторизованному менеджеру.
if (!$modx->user || !$modx->user->isAuthenticated('mgr')) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

$corePath = $modx->getOption('mxlogger.core_path', null, $modx->getOption('core_path') . 'components/mxlogger/');
require_once $corePath . 'processors/mgr/log/filters.php';
require_once $corePath . 'processors/mgr/log/exporter.php';

// Формат: md (по умолчанию) или txt — нормализуется внутри экспортёра.
$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : 'md';
$exporter = new mxLoggerLogExporter($modx, $format);

// Свойства фильтра — те же ключи, что принимает getlist/clear.
$filterKeys = array(
    'tags', 'tag', 'tags_match', 'level', 'process_uid', 'user_id',
    'class', 'date_from', 'date_to', 'query', 'ident',
);
$props = array();
foreach ($filterKeys as $k) {
    if (isset($_GET[$k])) {
        $props[$k] = $_GET[$k];
    }
}
$where = mxLoggerLogFilters::build($modx, $props);

// Сколько записей попадёт в выгрузку (для шапки файла).
$total = (int) $modx->getCount('mxLoggerLog', empty($where) ? null : $where);

// Человекочитаемое описание активных фильтров для шапки.
$activeFilters = array();
foreach (array(
    'tags', 'level', 'process_uid', 'ident', 'query', 'class',
    'date_from', 'date_to', 'user_id',
) as $key) {
    if (isset($props[$key]) && trim((string) $props[$key]) !== '') {
        $activeFilters[] = $key . '=' . trim((string) $props[$key]);
    }
}
$filterText = $activeFilters
    ? implode('; ', $activeFilters)
    : $modx->lexicon('mxlogger_export_nofilter');

// --- Отдаём файл потоком -----------------------------------------------

$filename = 'mxlogger-' . date('Ymd-His') . '.' . $exporter->getExtension();

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $exporter->getMime() . '; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

$out = fopen('php://output', 'w');
fwrite($out, $exporter->renderHeader($total, $filterText, date('Y-m-d H:i:s')));

// Хронологический порядок (старые → новые) — естественно для чтения журнала.
$batch = 1000;
$offset = 0;
do {
    $c = $modx->newQuery('mxLoggerLog');
    if (!empty($where)) {
        $c->where($where);
    }
    $c->sortby('createdon', 'ASC');
    $c->sortby('id', 'ASC');
    $c->limit($batch, $offset);

    // getCollection корректно формирует SELECT-колонки и гидрирует объекты —
    // toArray() даёт те же поля, что и грид (getlist::prepareRow).
    $objects = $modx->getCollection('mxLoggerLog', $c);
    foreach ($objects as $object) {
        fwrite($out, $exporter->renderRow($object->toArray()));
    }
    flush();

    $fetched = count($objects);
    $offset += $batch;
} while ($fetched === $batch);

fclose($out);
exit;
