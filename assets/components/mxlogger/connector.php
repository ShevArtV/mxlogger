<?php
/**
 * Менеджерный коннектор mxLogger.
 *
 * @package mxlogger
 */
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';

/** @var modX $modx */
$corePath = $modx->getOption('mxlogger.core_path', null, $modx->getOption('core_path') . 'components/mxlogger/');

/** @var mxLogger $mxlogger */
$mxlogger = $modx->getService(
    'mxlogger',
    'mxLogger',
    $corePath . 'model/mxlogger/',
    array('core_path' => $corePath)
);
$modx->lexicon->load('mxlogger:default');

$modx->request->handleRequest(array(
    'processors_path' => $corePath . 'processors/',
    'location' => '',
));
