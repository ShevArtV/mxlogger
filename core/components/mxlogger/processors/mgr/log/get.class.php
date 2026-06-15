<?php
/**
 * Получить одну запись лога (для окна детали).
 *
 * @package mxlogger
 * @subpackage processors
 */
class mxLoggerLogGetProcessor extends modObjectGetProcessor
{
    public $classKey = 'mxLoggerLog';
    public $languageTopics = array('mxlogger:default');
    public $objectType = 'mxlogger.log';

    public function cleanup()
    {
        $array = $this->object->toArray();
        $array['createdon_formatted'] = $array['createdon']
            ? date('Y-m-d H:i:s', $array['createdon'])
            : '';
        $wrapped = trim((string) $array['tags'], ',');
        $array['tags_list'] = $wrapped === '' ? array() : explode(',', $wrapped);
        $array['tags_display'] = implode(', ', $array['tags_list']);

        $array['caller'] = trim(($array['class'] ? $array['class'] . '::' : '') . $array['function']);
        $array['source'] = $array['file'] ? $array['file'] . ($array['line'] ? ':' . $array['line'] : '') : '';

        // Красиво отформатированный JSON для просмотра.
        $array['context_pretty'] = $this->prettyJson($this->object->get('context'));
        $array['trace_pretty'] = $this->prettyJson($this->object->get('trace'));

        if (!empty($array['user_id']) && ($user = $this->modx->getObject('modUser', (int) $array['user_id']))) {
            $array['username'] = $user->get('username');
        } else {
            $array['username'] = '';
        }

        return $this->success('', $array);
    }

    protected function prettyJson($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = $decoded === null ? $value : $decoded;
        }
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

return 'mxLoggerLogGetProcessor';
