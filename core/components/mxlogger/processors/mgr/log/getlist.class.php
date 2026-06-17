<?php
/**
 * Список логов с фильтрами для грида.
 *
 * @package mxlogger
 * @subpackage processors
 */
require_once dirname(__FILE__) . '/filters.php';

class mxLoggerLogGetListProcessor extends modObjectGetListProcessor
{
    public $classKey = 'mxLoggerLog';
    public $languageTopics = array('mxlogger:default');
    public $defaultSortField = 'id';
    public $defaultSortDirection = 'DESC';

    /** Реальные колонки таблицы — только по ним можно сортировать. */
    protected $sortable = array(
        'id', 'tags', 'process_uid', 'level', 'message', 'class', 'function',
        'file', 'line', 'user_id', 'session_id', 'ip', 'createdon',
    );

    public function initialize()
    {
        $result = parent::initialize();
        // Защита от сортировки по вычисляемым колонкам (createdon_formatted,
        // caller, username) — иначе SQL-ошибка и пустой грид.
        if (!in_array($this->getProperty('sort'), $this->sortable, true)) {
            $this->setProperty('sort', 'createdon');
        }
        return $result;
    }

    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        // Те же условия применяются и при очистке журнала (clear) — единый
        // источник правды, чтобы очистка по фильтру совпадала с выборкой грида.
        $where = mxLoggerLogFilters::build($this->modx, $this->getProperties());
        if (!empty($where)) {
            $c->where($where);
        }
        return $c;
    }

    public function prepareRow(xPDOObject $object)
    {
        $array = $object->toArray();

        $array['createdon_formatted'] = $array['createdon']
            ? date('Y-m-d H:i:s', $array['createdon'])
            : '';

        $wrapped = trim((string) $array['tags'], ',');
        $array['tags_list'] = $wrapped === '' ? array() : explode(',', $wrapped);

        $array['caller'] = trim(($array['class'] ? $array['class'] . '::' : '') . $array['function']);
        $array['source'] = $array['file'] ? $array['file'] . ($array['line'] ? ':' . $array['line'] : '') : '';

        $username = '';
        if (!empty($array['user_id'])) {
            if ($user = $this->modx->getObject('modUser', (int) $array['user_id'])) {
                $username = $user->get('username');
            }
        }
        $array['username'] = $username;

        // В грид-строке не тащим объёмные поля целиком — они нужны в окне детали.
        $array['message_short'] = mb_strlen((string) $array['message']) > 160
            ? mb_substr((string) $array['message'], 0, 160) . '…'
            : (string) $array['message'];

        return $array;
    }
}

return 'mxLoggerLogGetListProcessor';
