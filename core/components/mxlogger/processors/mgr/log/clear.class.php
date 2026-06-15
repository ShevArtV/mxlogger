<?php
/**
 * Очистка журнала. Уважает текущие фильтры грида, если они переданы:
 *   tags (tag), tags_match, level, process_uid, user_id, date_from, date_to.
 * Без фильтров — очищает весь журнал.
 *
 * @package mxlogger
 * @subpackage processors
 */
require_once dirname(__FILE__) . '/tagfilter.php';

class mxLoggerLogClearProcessor extends modProcessor
{
    public $languageTopics = array('mxlogger:default');

    public function process()
    {
        $where = array();

        $clause = mxLoggerLogTagFilter::clause(
            $this->modx,
            $this->getProperty('tags', $this->getProperty('tag')),
            $this->getProperty('tags_match', 'any')
        );
        if ($clause !== '') {
            $where[] = $clause;
        }

        $level = $this->getProperty('level');
        if (!empty($level)) {
            $where['level'] = $level;
        }
        $processUid = $this->getProperty('process_uid');
        if (!empty($processUid)) {
            $where['process_uid'] = $processUid;
        }
        $userId = $this->getProperty('user_id');
        if ($userId !== null && $userId !== '') {
            $where['user_id'] = (int) $userId;
        }
        $dateFrom = $this->getProperty('date_from');
        if (!empty($dateFrom) && ($tsFrom = strtotime($dateFrom))) {
            $where['createdon:>='] = $tsFrom;
        }
        $dateTo = $this->getProperty('date_to');
        if (!empty($dateTo) && ($tsTo = strtotime($dateTo))) {
            $where['createdon:<='] = $tsTo;
        }

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
