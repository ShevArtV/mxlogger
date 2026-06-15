<?php
/**
 * mxLoggerProcess — скоуп логирования одного экземпляра процесса.
 * Фиксирует тэги и process_uid, чтобы все записи одного процесса
 * (например одной покупки) шли с общим идентификатором и по порядку.
 *
 * @package mxlogger
 */
class mxLoggerProcess
{
    /** @var mxLogger $logger */
    protected $logger;

    /** @var string|array $tags */
    protected $tags;

    /** @var string $uid */
    protected $uid;

    public function __construct(mxLogger $logger, $tags, $uid)
    {
        $this->logger = $logger;
        $this->tags = $tags;
        $this->uid = $uid;
    }

    /**
     * Идентификатор экземпляра процесса (process_uid).
     *
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * Тэги процесса (как переданы при создании).
     *
     * @return string|array
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Добавить тэг к процессу (применится к последующим записям).
     *
     * @param string $tag
     * @return $this
     */
    public function addTag($tag)
    {
        $tags = $this->logger->normalizeTags($this->tags);
        foreach ($this->logger->normalizeTags($tag) as $t) {
            if (!in_array($t, $tags, true)) {
                $tags[] = $t;
            }
        }
        $this->tags = $tags;
        return $this;
    }

    /**
     * Записать лог в рамках процесса.
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     * @param array  $options
     * @return mxLoggerLog|null
     */
    public function log($level, $message, array $context = array(), array $options = array())
    {
        $options['process_uid'] = $this->uid;
        return $this->logger->log($this->tags, $level, $message, $context, $options);
    }

    public function debug($message, array $context = array(), array $options = array())
    {
        return $this->log('debug', $message, $context, $options);
    }

    public function info($message, array $context = array(), array $options = array())
    {
        return $this->log('info', $message, $context, $options);
    }

    public function warning($message, array $context = array(), array $options = array())
    {
        return $this->log('warning', $message, $context, $options);
    }

    public function error($message, array $context = array(), array $options = array())
    {
        return $this->log('error', $message, $context, $options);
    }
}
