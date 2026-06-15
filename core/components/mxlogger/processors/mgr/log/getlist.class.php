<?php
/**
 * Список логов с фильтрами для грида.
 *
 * @package mxlogger
 * @subpackage processors
 */
require_once dirname(__FILE__) . '/tagfilter.php';

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
        $level = $this->getProperty('level');
        $processUid = $this->getProperty('process_uid');
        $userId = $this->getProperty('user_id');
        $class = $this->getProperty('class');
        $query = $this->getProperty('query');
        $dateFrom = $this->getProperty('date_from');
        $dateTo = $this->getProperty('date_to');

        mxLoggerLogTagFilter::apply(
            $this->modx, $c,
            $this->getProperty('tags', $this->getProperty('tag')),
            $this->getProperty('tags_match', 'any')
        );

        if (!empty($level)) {
            $c->where(array('level' => $level));
        }
        if (!empty($processUid)) {
            $c->where(array('process_uid' => $processUid));
        }
        if ($userId !== null && $userId !== '') {
            $c->where(array('user_id' => (int) $userId));
        }
        if (!empty($class)) {
            $c->where(array('class:LIKE' => '%' . $class . '%'));
        }
        if (!empty($dateFrom) && ($tsFrom = strtotime($dateFrom))) {
            $c->where(array('createdon:>=' => $tsFrom));
        }
        if (!empty($dateTo) && ($tsTo = strtotime($dateTo))) {
            $c->where(array('createdon:<=' => $tsTo));
        }
        if (!empty($query)) {
            // Поиск по тексту: сообщение, источник (класс/метод), файл/строка.
            // Ищем как по отдельным колонкам, так и по склеенным формам, которые
            // видны в гриде: «class::function» и «file:line».
            // Тэги/процесс/сессия/ip — отдельные фильтры. Значение экранируем quote().
            $q = $this->modx->quote('%' . $query . '%');
            $c->where('(' .
                'message LIKE ' . $q .
                ' OR class LIKE ' . $q .
                ' OR function LIKE ' . $q .
                ' OR file LIKE ' . $q .
                ' OR CAST(line AS CHAR) LIKE ' . $q .
                ' OR CONCAT(class, \'::\', function) LIKE ' . $q .
                ' OR CONCAT(file, \':\', line) LIKE ' . $q .
            ')');
        }

        // Отдельный фильтр по пользователю / сессии / ip — отдельной группой
        // (AND к остальным), чтобы комбинировать с текстовым поиском.
        // Пользователь: по user_id (если число) и по username (подзапрос к modUser).
        $ident = $this->getProperty('ident');
        if (!empty($ident)) {
            $q = $this->modx->quote('%' . $ident . '%');
            $usersTable = $this->modx->getTableName('modUser');
            $conds = array(
                'session_id LIKE ' . $q,
                'ip LIKE ' . $q,
                'user_id IN (SELECT id FROM ' . $usersTable . ' WHERE username LIKE ' . $q . ')',
            );
            if (ctype_digit((string) $ident)) {
                $conds[] = 'user_id = ' . (int) $ident;
            }
            $c->where('(' . implode(' OR ', $conds) . ')');
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
