<?php
/**
 * Удалить запись лога.
 *
 * @package mxlogger
 * @subpackage processors
 */
class mxLoggerLogRemoveProcessor extends modObjectRemoveProcessor
{
    public $classKey = 'mxLoggerLog';
    public $languageTopics = array('mxlogger:default');
    public $objectType = 'mxlogger.log';
}

return 'mxLoggerLogRemoveProcessor';
