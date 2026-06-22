<?php
/**
 * Тест рендера экспорта журнала (mxLoggerLogExporter).
 *
 * Прогоняется на стенде с живой БД — проверяет логику в обход HTTP/авторизации:
 *   - decodeJson: массив/строка/пусто → корректная строка, без литерала «Array»;
 *   - renderRow на реальных записях: id/уровень/поля заполнены (регресс #0 [] guest);
 *   - JSON-поля context/trace рендерятся как JSON, а не «Array».
 *
 * Запуск (одно SSH-подключение):
 *   ssh hostland '/usr/local/php/php-7.4/bin/php' < modxbuilder/mxlogger/tests/test_export.php
 *
 * Корень MODX берётся из env MXLOGGER_BASE или argv[1], дефолт — стенд Hostland.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = getenv('MXLOGGER_BASE');
if (!$base && isset($argv[1])) {
    $base = $argv[1];
}
if (!$base) {
    $base = '/home/host1860015/art-sites.ru/htdocs/mspaypalalt/';
}
$base = rtrim($base, '/') . '/';

require_once $base . 'config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
$modx->getService('error', 'error.modError');
$modx->lexicon->load('mxlogger:default');

$corePath = $modx->getOption('mxlogger.core_path', null, $modx->getOption('core_path') . 'components/mxlogger/');
require_once $corePath . 'processors/mgr/log/exporter.php';

$pass = 0;
$fail = 0;
function check($cond, $name)
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  PASS: $name\n";
    } else {
        $fail++;
        echo "  FAIL: $name\n";
    }
}

echo "== decodeJson ==\n";
check(mxLoggerLogExporter::decodeJson(array('a' => 1, 'b' => 'тест')) === json_encode(array('a' => 1, 'b' => 'тест'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'массив -> pretty JSON');
check(mxLoggerLogExporter::decodeJson('') === '', 'пустая строка -> пусто');
check(mxLoggerLogExporter::decodeJson(array()) === '', 'пустой массив -> пусто');
check(mxLoggerLogExporter::decodeJson('null') === '', 'строка null -> пусто');
check(mxLoggerLogExporter::decodeJson('{"x":1}') === json_encode(array('x' => 1), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'JSON-строка -> pretty JSON');
check(strpos(mxLoggerLogExporter::decodeJson(array('x' => array('y' => 'z'))), 'Array') === false, 'нет литерала "Array"');

echo "== реальные записи ==\n";
$c = $modx->newQuery('mxLoggerLog');
$c->sortby('id', 'DESC');
$c->limit(10);
$objects = $modx->getCollection('mxLoggerLog', $c);
check(count($objects) > 0, 'выборка вернула записи (' . count($objects) . ')');

$exMd = new mxLoggerLogExporter($modx, 'md');
$exTxt = new mxLoggerLogExporter($modx, 'txt');

$sampleMd = '';
$sampleTxt = '';
$traceChecked = false;
$contextChecked = false;

foreach ($objects as $o) {
    $r = $o->toArray();
    $id = (int) $r['id'];
    $md = $exMd->renderRow($r);
    $txt = $exTxt->renderRow($r);
    if ($sampleMd === '') {
        $sampleMd = $md;
        $sampleTxt = $txt;
    }

    check($id > 0, "id > 0 (#$id)");
    check(strpos($md, '#' . $id) !== false, "md содержит реальный id #$id");
    check(strpos($txt, '#' . $id) !== false, "txt содержит реальный id #$id");
    check(strpos($md, 'Array') === false, "md #$id без литерала Array");
    check(strpos($txt, 'Array') === false, "txt #$id без литерала Array");
    if ($r['level'] !== '' && $r['level'] !== null) {
        check(strpos($md, '[' . strtoupper($r['level']) . ']') !== false, "md #$id содержит уровень " . $r['level']);
    }

    // Если у записи есть context/trace — проверяем, что отрендерился JSON-блок.
    if (!$contextChecked && mxLoggerLogExporter::decodeJson($r['context']) !== '') {
        $contextChecked = true;
        check(strpos($md, 'context') !== false && strpos($md, '```json') !== false, "md #$id: context отрендерен JSON-блоком");
    }
    if (!$traceChecked && mxLoggerLogExporter::decodeJson($r['trace']) !== '') {
        $traceChecked = true;
        check(strpos($md, 'trace') !== false && strpos($md, '```json') !== false, "md #$id: trace отрендерен JSON-блоком");
    }
}

if (!$traceChecked) {
    echo "  SKIP: ни у одной записи нет trace\n";
}
if (!$contextChecked) {
    echo "  SKIP: ни у одной записи нет context\n";
}

echo "== шапка ==\n";
$hdr = $exMd->renderHeader(count($objects), 'level=error', '2026-06-22 00:00:00');
check(strpos($hdr, 'mxLogger') !== false, 'шапка содержит заголовок');
check(strpos($hdr, 'level=error') !== false, 'шапка содержит описание фильтра');

echo "\n--- ОБРАЗЕЦ (первая запись, md) ---\n";
echo $sampleMd;
echo "--- ОБРАЗЕЦ (первая запись, txt) ---\n";
echo $sampleTxt;

echo "\n=== ИТОГ: $pass passed, $fail failed ===\n";
exit($fail ? 1 : 0);
