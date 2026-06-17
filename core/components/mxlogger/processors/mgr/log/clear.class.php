<?php
/**
 * Очистка журнала. Уважает текущие фильтры грида, если они переданы:
 *   tags (tag), tags_match, level, process_uid, user_id, class,
 *   date_from, date_to, query, ident.
 * Набор условий строится тем же построителем, что и выборка грида
 * (mxLoggerLogFilters) — поэтому очистка по фильтру удаляет ровно то,
 * что в гриде видно. Без фильтров — очищает весь журнал.
 *
 * @package mxlogger
 * @subpackage processors
 */
require_once dirname(__FILE__) . '/filters.php';

class mxLoggerLogClearProcessor extends modProcessor
{
    public $languageTopics = array('mxlogger:default');

    public function process()
    {
        $where = mxLoggerLogFilters::build($this->modx, $this->getProperties());

        if (empty($where)) {
            // Полная очистка — быстрее прямым DELETE без выборки.
            $removed = $this->modx->exec('DELETE FROM ' . $this->modx->getTableName('mxLoggerLog'));
            $removed = ($removed === false) ? 0 : (int) $removed;
        } else {
            $removed = (int) $this->modx->removeCollection('mxLoggerLog', $where);
        }

        return $this->success(
            $this->modx->lexicon('mxlogger_log_cleared', array('count' => $removed)),
            array('removed' => $removed)
        );
    }
}

return 'mxLoggerLogClearProcessor';
