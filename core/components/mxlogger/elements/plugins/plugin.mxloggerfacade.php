<?php
/**
 * mxLoggerFacade — короткий доступ к логгеру: $modx->mxl->info(...).
 *
 * Событие: OnMODXInit (приоритет ниже прочих, чтобы фасад был готов раньше).
 * Вешает сервис mxLogger на $modx->mxl, чтобы из любого сниппета/плагина/чанка
 * можно было звать $modx->mxl->info()/debug()/warning()/error()/process() без
 * getService(). Создание сервиса дешёвое (конструктор лишь сохраняет $modx,
 * настройки читаются лениво при записи).
 *
 * @var modX $modx
 * @package mxlogger
 */
if (isset($modx->mxl)) {
    return;
}

$corePath = $modx->getOption('mxlogger.core_path', null, $modx->getOption('core_path') . 'components/mxlogger/');
$service = $modx->getService('mxlogger', 'mxLogger', $corePath . 'model/mxlogger/', array('core_path' => $corePath));
if ($service) {
    $modx->mxl = $service;
}

return;
