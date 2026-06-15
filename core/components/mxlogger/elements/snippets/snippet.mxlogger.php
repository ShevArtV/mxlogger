<?php
/**
 * Сниппет mxLogger — запись лога из чанка/шаблона/Fenom.
 *
 * Пример:
 *   [[!mxLogger? &tags=`purchase` &level=`info` &message=`Открыта страница оплаты`]]
 *   [[!mxLogger? &tags=`cart,purchase` &level=`info` &message=`Товар добавлен`]]
 *   {'!mxLogger' | snippet : ['tags' => 'cart,purchase', 'level' => 'error', 'message' => 'Ошибка']}
 *
 * Тэги: lowercase, латиница и цифры, через запятую/пробел. Несоответствующие символы вырезаются.
 *
 * Параметры:
 *   &tags        — тэг(и) процесса через запятую (обязательно; синоним &tag);
 *   &level       — debug|info|warning|error (по умолчанию info);
 *   &message     — текст;
 *   &process_uid — идентификатор экземпляра процесса (опц.);
 *   &context     — JSON-строка с произвольными данными (опц.);
 *   &trace       — 1|full|caller — переопределить режим захвата (опц.).
 *
 * @var modX $modx
 * @var array $scriptProperties
 * @package mxlogger
 */
$corePath = $modx->getOption('mxlogger.core_path', null, $modx->getOption('core_path') . 'components/mxlogger/');
/** @var mxLogger $mxlogger */
$mxlogger = $modx->getService('mxlogger', 'mxLogger', $corePath . 'model/mxlogger/', array('core_path' => $corePath));
if (!($mxlogger instanceof mxLogger)) {
    return '';
}

$tags = $modx->getOption('tags', $scriptProperties, $modx->getOption('tag', $scriptProperties, ''));
if ($tags === '' || $tags === null) {
    return '';
}

$level = $modx->getOption('level', $scriptProperties, 'info');
$message = $modx->getOption('message', $scriptProperties, '');

$context = $modx->getOption('context', $scriptProperties, '');
if (is_string($context) && $context !== '') {
    $decoded = json_decode($context, true);
    $context = is_array($decoded) ? $decoded : array('raw' => $context);
} elseif (!is_array($context)) {
    $context = array();
}

$options = array();
if (($uid = $modx->getOption('process_uid', $scriptProperties, '')) !== '') {
    $options['process_uid'] = $uid;
}
if (($trace = $modx->getOption('trace', $scriptProperties, '')) !== '') {
    $options['trace'] = ($trace === '1' || $trace === 1) ? true : $trace;
}
// Сниппет добавляет свой кадр (modScript) — пропускаем его при разборе backtrace.
$options['skip'] = 1;

$mxlogger->log($tags, $level, $message, $context, $options);

return '';
