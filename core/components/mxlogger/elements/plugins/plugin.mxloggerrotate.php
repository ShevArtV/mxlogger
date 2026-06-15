<?php
/**
 * mxLoggerRotate — ротация (автоудаление) старых записей лога mxLogger.
 *
 * Событие: OnMODXInit. Чтобы не работать на каждом запросе и не уронить сайт
 * на больших объёмах:
 *   - выходим мгновенно, если ротация выключена (log_lifetime = 0);
 *   - троттлинг: реальная чистка не чаще раза в mxlogger.rotate_interval секунд
 *     (метка хранится в кэше);
 *   - удаление порциями (DELETE ... LIMIT) с ограничением числа порций за проход —
 *     остаток дочищается на следующих запусках;
 *   - любые ошибки гасятся (OnMODXInit критичен — исключение белым экраном).
 *
 * @var modX $modx
 * @package mxlogger
 */
$lifetime = (int) $modx->getOption('mxlogger.log_lifetime', null, 0);
if ($lifetime <= 0) {
    return; // ротация выключена — максимально дешёвый выход
}

$interval = (int) $modx->getOption('mxlogger.rotate_interval', null, 3600);
if ($interval < 60) {
    $interval = 60;
}

$cm = $modx->getCacheManager();
if (!$cm) {
    return;
}
$cacheOpt = array(xPDO::OPT_CACHE_KEY => 'mxlogger');
$now = time();
$last = (int) $cm->get('rotate_last', $cacheOpt);
if ($last && ($now - $last) < $interval) {
    return; // ещё рано
}
// Метку ставим СРАЗУ, до удаления — чтобы параллельные запросы не запускали чистку повторно.
$cm->set('rotate_last', $now, $interval * 2, $cacheOpt);

try {
    $table = $modx->escape($modx->getOption(xPDO::OPT_TABLE_PREFIX) . 'mxlogger_log');
    $threshold = $now - $lifetime;

    // Безопасные значения порций — защита от тяжёлого DELETE на больших объёмах.
    $batch = 2000;
    $maxBatches = 10; // не более batch*maxBatches за проход; остальное — на следующих

    $sql = 'DELETE FROM ' . $table . ' WHERE createdon < ' . (int) $threshold . ' LIMIT ' . (int) $batch;
    $removed = 0;
    for ($i = 0; $i < $maxBatches; $i++) {
        $affected = $modx->exec($sql);
        if ($affected === false) {
            break; // ошибка БД — прекращаем
        }
        $removed += (int) $affected;
        if ((int) $affected < $batch) {
            break; // выбрали всё, что было старше порога
        }
    }
    if ($removed > 0) {
        $modx->log(modX::LOG_LEVEL_INFO, '[mxLoggerRotate] Удалено старых записей лога: ' . $removed);
    }
} catch (Exception $e) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[mxLoggerRotate] ' . $e->getMessage());
}

return;
