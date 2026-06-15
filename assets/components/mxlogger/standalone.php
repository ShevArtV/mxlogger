<?php
/**
 * mxLogger standalone — просмотр логов В ОБХОД MODX.
 *
 * Не бутстрапит MODX: берёт только параметры БД из core/config/config.inc.php
 * (простой файл с переменными — цел, даже если приложение MODX не грузится)
 * и читает таблицу mxlogger_log напрямую через PDO.
 *
 * CLI (всегда доступно, по SSH):
 *   php standalone.php tag=cart level=error q=текст process=ms_xxx since="2026-06-01" limit=50
 *
 * WEB (нужен ключ, иначе 403):
 *   standalone.php?key=СЕКРЕТ&tag=cart&level=error&q=...&limit=100
 *   Ключ задаётся: env MXLOGGER_TOKEN ИЛИ файлом
 *   core/components/mxlogger/standalone.key (одна строка). Нет ключа → веб закрыт.
 *
 * Запасной доступ к БД, если config.inc.php недоступен: env MXLOGGER_DSN /
 * MXLOGGER_DB_USER / MXLOGGER_DB_PASS / MXLOGGER_TABLE_PREFIX.
 *
 * @package mxlogger
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

$isCli = (PHP_SAPI === 'cli');
$root = dirname(__DIR__, 3) . '/'; // assets/components/mxlogger/ -> корень MODX

/* ---------- Параметры подключения к БД ---------- */
$dsn = $user = $pass = null;
$tablePrefix = 'modx_';
$configFile = $root . 'core/config/config.inc.php';
if (is_readable($configFile)) {
    // config.inc.php лишь определяет переменные — безопасно подключить.
    require $configFile;
    $dsn = isset($database_dsn) ? $database_dsn : null;
    $user = isset($database_user) ? $database_user : null;
    $pass = isset($database_password) ? $database_password : null;
    if (isset($table_prefix)) { $tablePrefix = $table_prefix; }
}
// Запасной вариант — переменные окружения.
if (!$dsn && getenv('MXLOGGER_DSN')) {
    $dsn = getenv('MXLOGGER_DSN');
    $user = getenv('MXLOGGER_DB_USER');
    $pass = getenv('MXLOGGER_DB_PASS');
    if (getenv('MXLOGGER_TABLE_PREFIX')) { $tablePrefix = getenv('MXLOGGER_TABLE_PREFIX'); }
}
$table = $tablePrefix . 'mxlogger_log';

/* ---------- Авторизация для веба ---------- */
if (!$isCli) {
    $expected = getenv('MXLOGGER_TOKEN');
    if (!$expected) {
        $keyFile = $root . 'core/components/mxlogger/standalone.key';
        if (is_readable($keyFile)) { $expected = trim(file_get_contents($keyFile)); }
    }
    $given = isset($_GET['key']) ? (string) $_GET['key'] : '';
    if (!$expected || !hash_equals((string) $expected, $given)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit("403 — доступ запрещён.\nЗадайте ключ (env MXLOGGER_TOKEN или core/components/mxlogger/standalone.key) и передайте ?key=...");
    }
}

/* ---------- Параметры запроса ---------- */
$src = $isCli ? mxl_cli_params($argv) : $_GET;
$f = array(
    'id'      => isset($src['id']) ? (int) $src['id'] : 0,
    'tag'     => isset($src['tag']) ? trim($src['tag']) : '',
    'level'   => isset($src['level']) ? trim($src['level']) : '',
    'q'       => isset($src['q']) ? trim($src['q']) : '',
    'process' => isset($src['process']) ? trim($src['process']) : '',
    'ident'   => isset($src['ident']) ? trim($src['ident']) : '',
    'since'   => isset($src['since']) ? trim($src['since']) : '',
    'until'   => isset($src['until']) ? trim($src['until']) : '',
);
$limit = isset($src['limit']) ? (int) $src['limit'] : 100;
$limit = max(1, min(2000, $limit));
// Показывать context/trace целиком (без усечения) в CLI.
// Запрос одной записи по id — всегда разворачиваем полностью.
$GLOBALS['MXL_FULL'] = !empty($src['full']) || !empty($f['id']);
// Принудительный цвет: color=1 (вкл) / color=0 (выкл). Иначе — авто по TTY.
$GLOBALS['MXL_COLOR'] = isset($src['color'])
    ? !in_array((string) $src['color'], array('0', 'off', 'no', 'false'), true)
    : null;

/* ---------- Подключение и выборка ---------- */
$rows = array();
$error = null;
try {
    if (!$dsn) {
        throw new RuntimeException('Не удалось получить параметры БД (нет config.inc.php и env MXLOGGER_DSN).');
    }
    $pdo = new PDO($dsn, $user, $pass, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ));

    $where = array();
    $args = array();
    if (!empty($f['id'])) {
        $where[] = 'id = ?';
        $args[] = (int) $f['id'];
    }
    if ($f['tag'] !== '') {
        $where[] = 'tags LIKE ?';
        $args[] = '%,' . preg_replace('/[^a-z0-9]/', '', strtolower($f['tag'])) . ',%';
    }
    if ($f['level'] !== '') {
        $where[] = 'level = ?';
        $args[] = $f['level'];
    }
    if ($f['process'] !== '') {
        $where[] = 'process_uid = ?';
        $args[] = $f['process'];
    }
    if ($f['ident'] !== '') {
        // Пользователь / сессия / ip. username — подзапросом к таблице пользователей.
        $usersTable = $tablePrefix . 'users';
        $idLike = '%' . $f['ident'] . '%';
        $sub = 'session_id LIKE ? OR ip LIKE ? OR user_id IN (SELECT id FROM `' . $usersTable . '` WHERE username LIKE ?)';
        $args[] = $idLike;
        $args[] = $idLike;
        $args[] = $idLike;
        if (ctype_digit($f['ident'])) {
            $sub .= ' OR user_id = ?';
            $args[] = (int) $f['ident'];
        }
        $where[] = '(' . $sub . ')';
    }
    if ($f['since'] !== '' && ($ts = strtotime($f['since']))) {
        $where[] = 'createdon >= ?';
        $args[] = $ts;
    }
    if ($f['until'] !== '' && ($tu = strtotime($f['until']))) {
        $where[] = 'createdon <= ?';
        $args[] = $tu;
    }
    if ($f['q'] !== '') {
        $where[] = '(message LIKE ? OR class LIKE ? OR function LIKE ? OR file LIKE ? OR CONCAT(class, \'::\', function) LIKE ? OR CONCAT(file, \':\', line) LIKE ?)';
        $like = '%' . $f['q'] . '%';
        for ($i = 0; $i < 6; $i++) { $args[] = $like; }
    }
    $sql = 'SELECT id, tags, process_uid, level, message, class, function, file, line, user_id, session_id, ip, createdon, context, trace'
        . ' FROM `' . $table . '`'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY id DESC LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}

/* ---------- Вывод ---------- */
if ($isCli) {
    mxl_render_cli($rows, $error, $f, $limit);
} else {
    mxl_render_html($rows, $error, $f, $limit);
}

/* ============================================================ */

function mxl_cli_params($argv)
{
    $p = array();
    foreach (array_slice((array) $argv, 1) as $a) {
        if (strpos($a, '=') !== false) {
            list($k, $v) = explode('=', $a, 2);
            $p[ltrim($k, '-')] = $v;
        }
    }
    return $p;
}

function mxl_caller($r)
{
    $c = trim(($r['class'] ? $r['class'] . '::' : '') . $r['function']);
    return $c;
}

function mxl_colors()
{
    static $on = null;
    if ($on === null) {
        if (isset($GLOBALS['MXL_COLOR']) && $GLOBALS['MXL_COLOR'] !== null) {
            $on = (bool) $GLOBALS['MXL_COLOR'];
        } elseif (getenv('NO_COLOR') !== false) {
            $on = false;
        } elseif (function_exists('posix_isatty')) {
            $on = @posix_isatty(STDOUT);
        } else {
            $on = true; // CLI без posix — считаем, что терминал
        }
    }
    return $on;
}

function mxl_c($text, $code)
{
    return mxl_colors() ? "\033[" . $code . 'm' . $text . "\033[0m" : $text;
}

function mxl_level_badge($level)
{
    $map = array(
        'error'   => '1;37;41',
        'warning' => '30;43',
        'info'    => '1;37;44',
        'debug'   => '1;37;100',
    );
    $code = isset($map[$level]) ? $map[$level] : '7';
    return mxl_c(' ' . strtoupper(str_pad((string) $level, 7)) . ' ', $code);
}

function mxl_block($label, $json)
{
    $lines = explode("\n", mxl_pretty($json));
    $truncated = false;
    if (!$GLOBALS['MXL_FULL'] && count($lines) > 14) {
        $lines = array_slice($lines, 0, 14);
        $truncated = true;
    }
    $out = '  ' . mxl_c($label . ':', '1;36') . "\n";
    foreach ($lines as $ln) {
        $out .= '    ' . $ln . "\n";
    }
    if ($truncated) {
        $out .= mxl_c('    … усечено, добавь full=1 для полного ' . $label, '33') . "\n";
    }
    return $out;
}

function mxl_render_cli($rows, $error, $f, $limit)
{
    if ($error) {
        fwrite(STDERR, mxl_c('ОШИБКА: ', '1;31') . $error . "\n");
        exit(1);
    }
    // Запрос одной записи по id — детальный вид; иначе — таблица.
    if (!empty($f['id'])) {
        mxl_render_detail($rows);
    } else {
        mxl_render_table($rows, $limit);
    }
}

function mxl_pad($s, $w)
{
    $s = (string) $s;
    $len = mb_strlen($s);
    if ($len > $w) {
        return mb_substr($s, 0, max(0, $w - 1)) . '…';
    }
    return $s . str_repeat(' ', $w - $len);
}

function mxl_level_cell($level, $w)
{
    $colors = array('error' => '1;31', 'warning' => '1;33', 'info' => '1;36', 'debug' => '1;90');
    $code = isset($colors[$level]) ? $colors[$level] : '0';
    return mxl_c(mxl_pad(strtoupper((string) $level), $w), $code);
}

function mxl_render_table($rows, $limit)
{
    $W = array('id' => 6, 'time' => 19, 'lvl' => 7, 'tags' => 16, 'msg' => 40, 'src' => 28, 'ui' => 22);
    $gap = '  ';
    $row = function ($id, $time, $lvl, $tags, $msg, $src, $ui) use ($gap) {
        return $id . $gap . $time . $gap . $lvl . $gap . $tags . $gap . $msg . $gap . $src . $gap . $ui;
    };
    $hdr = $row(
        mxl_pad('ID', $W['id']), mxl_pad('Время', $W['time']), mxl_pad('Ур', $W['lvl']),
        mxl_pad('Тэги', $W['tags']), mxl_pad('Сообщение', $W['msg']),
        mxl_pad('Источник', $W['src']), mxl_pad('User/IP', $W['ui'])
    );
    echo "\n" . mxl_c($hdr, '1') . "\n";
    echo mxl_c(str_repeat('─', mb_strlen($hdr)), '90') . "\n";

    foreach (array_reverse($rows) as $r) {
        $when = $r['createdon'] ? date('Y-m-d H:i:s', $r['createdon']) : '';
        $tags = trim((string) $r['tags'], ',');
        $src = mxl_caller($r);
        if ($src !== '' && $r['line']) { $src .= ':' . $r['line']; }
        $ui = $r['user_id'] . ' / ' . $r['ip'];
        echo $row(
            mxl_c(mxl_pad('#' . $r['id'], $W['id']), '90'),
            mxl_c(mxl_pad($when, $W['time']), '90'),
            mxl_level_cell($r['level'], $W['lvl']),
            mxl_c(mxl_pad($tags, $W['tags']), '36'),
            mxl_pad($r['message'], $W['msg']),
            mxl_c(mxl_pad($src, $W['src']), '90'),
            mxl_c(mxl_pad($ui, $W['ui']), '90')
        ) . "\n";
    }
    echo "\n" . mxl_c('записей: ' . count($rows) . ' (limit ' . $limit . ')', '1')
        . mxl_c('   подробнее: добавь id=<номер>', '33') . "\n";
}

function mxl_render_detail($rows)
{
    if (!$rows) {
        echo mxl_c("запись не найдена\n", '33');
        return;
    }
    foreach ($rows as $r) {
        $when = $r['createdon'] ? date('Y-m-d H:i:s', $r['createdon']) : '';
        $tags = '';
        foreach (array_filter(explode(',', trim((string) $r['tags'], ','))) as $t) {
            $tags .= mxl_c('#' . $t, '36') . ' ';
        }
        echo "\n" . mxl_c($when, '90') . '  ' . mxl_level_badge($r['level']) . '  '
            . mxl_c('#' . $r['id'], '90') . '  ' . trim($tags) . "\n";
        echo '  ' . mxl_c($r['message'], '1') . "\n";
        $src = mxl_caller($r);
        $meta = '@ ' . ($src !== '' ? $src : '—') . ' (' . basename((string) $r['file']) . ':' . $r['line'] . ')'
            . '   uid=' . $r['process_uid'] . '   user=' . $r['user_id'] . '   ip=' . $r['ip'];
        echo '  ' . mxl_c($meta, '90') . "\n";
        if (!empty($r['context'])) { echo mxl_block('context', $r['context']); }
        if (!empty($r['trace'])) { echo mxl_block('trace', $r['trace']); }
    }
    echo "\n";
}

function mxl_render_html($rows, $error, $f, $limit)
{
    $h = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
    $key = isset($_GET['key']) ? $_GET['key'] : '';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>mxLogger standalone</title><style>';
    echo 'body{font:13px/1.45 -apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#f4f6f8;color:#222}';
    echo 'header{background:#1e2329;color:#fff;padding:10px 16px;font-weight:bold}';
    echo 'form{padding:10px 16px;background:#fff;border-bottom:1px solid #dde3e8;display:flex;gap:8px;flex-wrap:wrap;align-items:center}';
    echo 'input,select{padding:5px 7px;border:1px solid #c3ccd4;border-radius:3px;font:inherit}';
    echo 'button{padding:6px 14px;border:0;border-radius:3px;background:#2f8fd6;color:#fff;cursor:pointer}';
    echo 'a.reset{padding:6px 14px;border-radius:3px;background:#9aa4ad;color:#fff;text-decoration:none}';
    echo 'a.reset:hover{background:#828c95}';
    echo 'label.dt{display:inline-flex;align-items:center;gap:4px;color:#555}';
    echo 'table{width:100%;border-collapse:collapse;background:#fff}';
    echo 'th,td{padding:6px 9px;border-bottom:1px solid #eef1f4;text-align:left;vertical-align:top}';
    echo 'th{background:#f0f3f6;position:sticky;top:0}';
    echo 'tt{font-family:Menlo,Consolas,monospace;font-size:11px}';
    echo '.lvl{display:inline-block;padding:1px 6px;border-radius:3px;color:#fff;font-size:11px;text-transform:uppercase}';
    echo '.lvl-debug{background:#9aa4ad}.lvl-info{background:#2f8fd6}.lvl-warning{background:#e0922f}.lvl-error{background:#d6402f}';
    echo '.tag{display:inline-block;padding:0 6px;margin:1px;border-radius:3px;background:#e4e9ee;font-size:11px;font-family:monospace}';
    echo 'pre{margin:4px 0 0;padding:6px 8px;background:#1e2329;color:#d6dee6;border-radius:3px;font-size:11px;max-height:160px;overflow:auto;white-space:pre-wrap;word-break:break-word}';
    echo 'details{margin-top:3px}.muted{color:#9aa4ad}';
    echo '</style></head><body>';
    echo '<header>mxLogger standalone — доступ к логам в обход MODX</header>';

    echo '<form method="get">';
    echo '<input type="hidden" name="key" value="' . $h($key) . '">';
    echo '<input name="tag" value="' . $h($f['tag']) . '" placeholder="Тэг" size="10">';
    echo '<select name="level"><option value="">Уровень</option>';
    foreach (array('debug', 'info', 'warning', 'error') as $lv) {
        echo '<option' . ($f['level'] === $lv ? ' selected' : '') . '>' . $lv . '</option>';
    }
    echo '</select>';
    echo '<input name="process" value="' . $h($f['process']) . '" placeholder="Процесс (uid)" size="16">';
    echo '<input name="ident" value="' . $h($f['ident']) . '" placeholder="Польз/сессия/IP" size="16">';
    echo '<label class="dt">С <input type="datetime-local" name="since" value="' . $h($f['since']) . '"></label>';
    echo '<label class="dt">До <input type="datetime-local" name="until" value="' . $h($f['until']) . '"></label>';
    echo '<input name="q" value="' . $h($f['q']) . '" placeholder="Поиск по тексту" size="20">';
    echo '<input name="limit" value="' . (int) $limit . '" title="Лимит" size="4">';
    echo '<button type="submit">Показать</button>';
    echo '<a class="reset" href="?key=' . $h(rawurlencode($key)) . '">Сбросить</a>';
    echo '</form>';

    if ($error) {
        echo '<p style="padding:16px;color:#d6402f">Ошибка: ' . $h($error) . '</p></body></html>';
        return;
    }

    echo '<table><thead><tr><th>Время</th><th>Ур.</th><th>Тэги</th><th>Процесс</th><th>Сообщение</th><th>Источник</th><th>Польз/IP</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $when = $r['createdon'] ? date('Y-m-d H:i:s', $r['createdon']) : '';
        echo '<tr>';
        echo '<td><tt>' . $h($when) . '</tt></td>';
        echo '<td><span class="lvl lvl-' . $h($r['level']) . '">' . $h($r['level']) . '</span></td>';
        echo '<td>';
        foreach (array_filter(explode(',', trim((string) $r['tags'], ','))) as $t) {
            echo '<span class="tag">' . $h($t) . '</span>';
        }
        echo '</td>';
        echo '<td><tt>' . $h($r['process_uid']) . '</tt></td>';
        echo '<td>' . $h($r['message']);
        if (!empty($r['context'])) {
            echo '<details><summary class="muted">context</summary><pre>' . $h(mxl_pretty($r['context'])) . '</pre></details>';
        }
        if (!empty($r['trace'])) {
            echo '<details><summary class="muted">trace</summary><pre>' . $h(mxl_pretty($r['trace'])) . '</pre></details>';
        }
        echo '</td>';
        echo '<td><tt>' . $h(mxl_caller($r)) . '</tt><br><span class="muted">' . $h(basename((string) $r['file']) . ':' . $r['line']) . '</span></td>';
        echo '<td>' . $h($r['user_id']) . '<br><span class="muted">' . $h($r['ip']) . '</span></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p class="muted" style="padding:10px 16px">Записей: ' . count($rows) . '</p>';
    echo '</body></html>';
}

function mxl_pretty($json)
{
    $d = json_decode((string) $json, true);
    if ($d === null) { return (string) $json; }
    return json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
