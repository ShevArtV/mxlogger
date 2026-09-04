<?php
/**
 * Список уникальных тэгов — для комбобокса фильтра.
 * Тэги хранятся в CSV-колонке tags, поэтому разворачиваем и схлопываем в PHP.
 *
 * @package mxlogger
 * @subpackage processors
 */
class mxLoggerLogGetTagsProcessor extends modProcessor
{
    public $languageTopics = array('mxlogger:default');
    /** Коннектор доступен в обход контроллера CMP — проверка прав нужна в самом процессоре. */
    public $permission = 'mxlogger_view';

    // Базовый modProcessor::checkPermissions() — return true; свойство $permission
    // само по себе ничего не проверяет, в отличие от modObjectProcessor.
    public function checkPermissions()
    {
        return $this->modx->hasPermission($this->permission);
    }

    public function process()
    {
        $query = preg_replace('/[^a-z0-9]/', '', strtolower((string) $this->getProperty('query', '')));

        $c = $this->modx->newQuery('mxLoggerLog');
        $c->where(array('tags:!=' => ''));
        if ($query !== '') {
            $c->where(array('tags:LIKE' => '%' . $query . '%'));
        }
        $c->groupby('tags');
        $c->select(array('tags'));

        $unique = array();
        if ($c->prepare() && $c->stmt->execute()) {
            foreach ($c->stmt->fetchAll(PDO::FETCH_COLUMN) as $wrapped) {
                $wrapped = trim((string) $wrapped, ',');
                if ($wrapped === '') {
                    continue;
                }
                foreach (explode(',', $wrapped) as $tag) {
                    if ($tag === '' || isset($unique[$tag])) {
                        continue;
                    }
                    if ($query !== '' && strpos($tag, $query) === false) {
                        continue;
                    }
                    $unique[$tag] = true;
                }
            }
        }

        ksort($unique);
        $tags = array();
        foreach (array_keys($unique) as $tag) {
            $tags[] = array('tag' => $tag);
        }

        return $this->outputArray($tags, count($tags));
    }
}

return 'mxLoggerLogGetTagsProcessor';
