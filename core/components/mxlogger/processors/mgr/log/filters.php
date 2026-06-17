<?php
/**
 * Общий построитель условий фильтрации логов для грида.
 * Используется и в getlist (выборка), и в clear (очистка), чтобы фильтры
 * очистки гарантированно совпадали с тем, что видно в гриде.
 *
 * Все условия задаются без алиаса таблицы — валидно и в SELECT, и в DELETE.
 * Значения экранируются quote(); тэги нормализованы до [a-z0-9].
 *
 * @package mxlogger
 * @subpackage processors
 */
require_once dirname(__FILE__) . '/tagfilter.php';

class mxLoggerLogFilters
{
    /**
     * Построить массив условий ($where) по свойствам процессора.
     * Формат совместим с xPDOQuery::where() и modX::removeCollection().
     *
     * @param modX  $modx
     * @param array $p Свойства процессора (getProperties()).
     * @return array
     */
    public static function build(modX $modx, array $p)
    {
        $where = array();

        $clause = mxLoggerLogTagFilter::clause(
            $modx,
            self::raw($p, 'tags', self::raw($p, 'tag')),
            self::raw($p, 'tags_match', 'any')
        );
        if ($clause !== '') {
            $where[] = $clause;
        }

        $level = self::val($p, 'level');
        if ($level !== '') {
            $where['level'] = $level;
        }

        $processUid = self::val($p, 'process_uid');
        if ($processUid !== '') {
            $where['process_uid'] = $processUid;
        }

        $userId = self::raw($p, 'user_id');
        if ($userId !== null && $userId !== '') {
            $where['user_id'] = (int) $userId;
        }

        $class = self::val($p, 'class');
        if ($class !== '') {
            $where['class:LIKE'] = '%' . $class . '%';
        }

        $dateFrom = self::val($p, 'date_from');
        if ($dateFrom !== '' && ($tsFrom = strtotime($dateFrom))) {
            $where['createdon:>='] = $tsFrom;
        }
        $dateTo = self::val($p, 'date_to');
        if ($dateTo !== '' && ($tsTo = strtotime($dateTo))) {
            $where['createdon:<='] = $tsTo;
        }

        $query = self::val($p, 'query');
        if ($query !== '') {
            // Поиск по тексту: сообщение, источник (класс/метод), файл/строка.
            // Ищем как по отдельным колонкам, так и по склеенным формам, которые
            // видны в гриде: «class::function» и «file:line».
            $q = $modx->quote('%' . $query . '%');
            $where[] = '(' .
                'message LIKE ' . $q .
                ' OR class LIKE ' . $q .
                ' OR function LIKE ' . $q .
                ' OR file LIKE ' . $q .
                ' OR CAST(line AS CHAR) LIKE ' . $q .
                ' OR CONCAT(class, \'::\', function) LIKE ' . $q .
                ' OR CONCAT(file, \':\', line) LIKE ' . $q .
            ')';
        }

        // Пользователь / сессия / ip — отдельной группой (AND к остальным).
        // Пользователь: по user_id (если число) и по username (подзапрос к modUser).
        $ident = self::val($p, 'ident');
        if ($ident !== '') {
            $q = $modx->quote('%' . $ident . '%');
            $usersTable = $modx->getTableName('modUser');
            $conds = array(
                'session_id LIKE ' . $q,
                'ip LIKE ' . $q,
                'user_id IN (SELECT id FROM ' . $usersTable . ' WHERE username LIKE ' . $q . ')',
            );
            if (ctype_digit((string) $ident)) {
                $conds[] = 'user_id = ' . (int) $ident;
            }
            $where[] = '(' . implode(' OR ', $conds) . ')';
        }

        return $where;
    }

    /**
     * Есть ли хотя бы один активный фильтр (т.е. очистка будет не полной).
     *
     * @param modX  $modx
     * @param array $p
     * @return bool
     */
    public static function hasAny(modX $modx, array $p)
    {
        return self::build($modx, $p) !== array();
    }

    /** Значение свойства как обрезанная строка ('' если нет). */
    private static function val(array $p, $k)
    {
        return isset($p[$k]) ? trim((string) $p[$k]) : '';
    }

    /** Сырое значение свойства с дефолтом. */
    private static function raw(array $p, $k, $default = null)
    {
        return isset($p[$k]) ? $p[$k] : $default;
    }
}
