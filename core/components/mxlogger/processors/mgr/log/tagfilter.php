<?php
/**
 * Общий помощник фильтрации логов по тэгам (CSV-колонка tags + FULLTEXT).
 * Тэги нормализованы до [a-z0-9], поэтому безопасно инлайнятся в SQL.
 *
 * @package mxlogger
 * @subpackage processors
 */
class mxLoggerLogTagFilter
{
    /** Дефолтный innodb_ft_min_token_size — тэги короче не индексируются FULLTEXT. */
    const FT_MIN_TOKEN = 3;

    /**
     * Применить фильтр по тэгам к запросу.
     *
     * Колонка указывается без алиаса таблицы — джойнов нет, неоднозначности тоже,
     * а DELETE/SELECT по-разному именуют алиас. MATCH(tags) валиден в обоих случаях.
     *
     * @param modX       $modx
     * @param xPDOQuery  $c
     * @param mixed      $raw   Строка/массив тэгов из запроса.
     * @param string     $match 'any' (OR) | 'all' (AND).
     * @return array Нормализованные применённые тэги.
     */
    public static function apply(modX $modx, xPDOQuery $c, $raw, $match = 'any')
    {
        $tags = self::normalize($raw);
        $clause = self::clause($modx, $tags, $match);
        if ($clause !== '') {
            $c->where($clause);
        }
        return $tags;
    }

    /**
     * Построить сырое SQL-условие по тэгам (без алиаса таблицы — валидно и в
     * SELECT, и в DELETE). Тэги санитизированы до [a-z0-9] — инъекция исключена.
     *
     * @param modX  $modx
     * @param mixed $raw   Строка/массив тэгов или уже нормализованный массив.
     * @param string $match 'any' (OR) | 'all' (AND).
     * @return string Условие или '' если тэгов нет.
     */
    public static function clause(modX $modx, $raw, $match = 'any')
    {
        $tags = self::normalize($raw);
        if (empty($tags)) {
            return '';
        }
        $match = ($match === 'all') ? 'all' : 'any';

        $mode = $modx->getOption('mxlogger.tag_filter_mode', null, 'auto');
        $hasShort = false;
        foreach ($tags as $t) {
            if (strlen($t) < self::FT_MIN_TOKEN) {
                $hasShort = true;
                break;
            }
        }
        $useFulltext = ($mode === 'fulltext') || ($mode === 'auto' && !$hasShort);

        if ($useFulltext) {
            $expr = ($match === 'all')
                ? '+' . implode(' +', $tags)
                : implode(' ', $tags);
            return "MATCH(tags) AGAINST('" . $expr . "' IN BOOLEAN MODE)";
        }

        $glue = ($match === 'all') ? ' AND ' : ' OR ';
        $parts = array();
        foreach ($tags as $t) {
            $parts[] = "tags LIKE '%," . $t . ",%'";
        }
        return '(' . implode($glue, $parts) . ')';
    }

    /**
     * Нормализовать тэги: lowercase, [a-z0-9], без пустых и дубликатов.
     *
     * @param mixed $raw
     * @return array
     */
    public static function normalize($raw)
    {
        if (!is_array($raw)) {
            $raw = preg_split('/[\s,]+/', (string) $raw);
        }
        $out = array();
        foreach ($raw as $tag) {
            $tag = preg_replace('/[^a-z0-9]/', '', strtolower((string) $tag));
            if ($tag !== '' && !in_array($tag, $out, true)) {
                $out[] = $tag;
            }
        }
        return $out;
    }
}
