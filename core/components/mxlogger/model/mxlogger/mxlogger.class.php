<?php
/**
 * mxLogger — удобное логирование процессов в MODX 2.
 *
 * Базовый сценарий:
 *   $mxl = $modx->getService('mxlogger', 'mxLogger', MODX_CORE_PATH . 'components/mxlogger/model/mxlogger/');
 *   $mxl->info('Покупка', 'Корзина создана', ['cart' => $id]);
 *
 * Сценарий процесса (один экземпляр — один process_uid):
 *   $p = $mxl->process('Покупка');
 *   $p->info('Старт оплаты', ['order' => 42]);
 *   $p->error('Платёж отклонён', ['code' => 'declined']);
 *
 * @package mxlogger
 */
class mxLogger
{
    /** @var modX $modx */
    public $modx;

    /** @var array $config */
    public $config = array();

    /** Уровни и их числовой вес для сравнения с min_level. */
    const LEVELS = array(
        'debug'   => 10,
        'info'    => 20,
        'warning' => 30,
        'error'   => 40,
    );

    /** @var bool|null Кэш результата проверки фильтра в рамках запроса. */
    protected $allowedCache = null;

    /** @var bool Флаг «мы внутри обработки события записи» — защита от рекурсии. */
    protected $inEvent = false;

    /** @var array Собственные классы — пропускаются при разборе backtrace. */
    protected $ownClasses = array('mxLogger', 'mxLoggerProcess');

    /**
     * @var array Диспетчерские кадры фреймворка, которые тоже пропускаем, чтобы
     * «Источник» указывал на реальный код, а не на механику вызова событий/плагинов.
     * Формат: 'Класс::метод' либо '::функция' (для псевдо-кадров include/eval).
     */
    protected $noiseFrames = array(
        '::invokeEvent',           // любой *::invokeEvent (modX, miniShop2 и т.п.) — см. isNoiseFrame
        'modScript::process',
        'modPlugin::process',
        'modElement::process',
        '::include', '::include_once', '::require', '::require_once', '::eval',
        '::call_user_func', '::call_user_func_array',
    );

    public function __construct(modX &$modx, array $config = array())
    {
        $this->modx = $modx;

        $corePath = $this->modx->getOption('mxlogger.core_path', $config, $this->modx->getOption('core_path') . 'components/mxlogger/');
        $assetsUrl = $this->modx->getOption('mxlogger.assets_url', $config, $this->modx->getOption('assets_url') . 'components/mxlogger/');

        $this->config = array_merge(array(
            'core_path'     => $corePath,
            'model_path'    => $corePath . 'model/',
            'processors_path' => $corePath . 'processors/',
            'assets_url'    => $assetsUrl,
            'connector_url' => $assetsUrl . 'connector.php',

            // Поведение логирования (берётся из системных настроек, переопределяется $config).
            'enabled'        => (bool) $this->modx->getOption('mxlogger.enabled', $config, true),
            'min_level'      => $this->modx->getOption('mxlogger.min_level', $config, 'debug'),
            'capture_mode'   => $this->modx->getOption('mxlogger.capture_mode', $config, 'auto'),
            'trace_limit'    => (int) $this->modx->getOption('mxlogger.trace_limit', $config, 15),
            'args_max_depth' => (int) $this->modx->getOption('mxlogger.args_max_depth', $config, 3),
            'args_max_string' => (int) $this->modx->getOption('mxlogger.args_max_string', $config, 512),
            'args_max_items' => (int) $this->modx->getOption('mxlogger.args_max_items', $config, 50),

            // Whitelist-фильтры (по умолчанию пусто — пишем всё).
            'filter_user'      => (string) $this->modx->getOption('mxlogger.filter_user', $config, ''),
            'filter_usergroup' => (string) $this->modx->getOption('mxlogger.filter_usergroup', $config, ''),
            'filter_session'   => (string) $this->modx->getOption('mxlogger.filter_session', $config, ''),
            'filter_cookie'    => (string) $this->modx->getOption('mxlogger.filter_cookie', $config, ''),
        ), $config);

        $this->modx->addPackage('mxlogger', $this->config['model_path']);
        // Лексикон нужен только для менеджерного UI. При автозагрузке через
        // extension_packages сервис создаётся очень рано (на _loadExtensionPackages),
        // когда $modx->lexicon ещё не инициализирован — поэтому грузим аккуратно.
        if ($this->modx->lexicon) {
            $this->modx->lexicon->load('mxlogger:default');
        }
    }

    /* ============================================================
     *  Публичный API
     * ============================================================ */

    /**
     * Записать лог.
     *
     * @param string|array $tags    Один или несколько тэгов процесса (например 'purchase' или ['cart','purchase']).
     *                              Нормализуются: lowercase, только [a-z0-9], дубликаты убираются.
     * @param string $level   debug|info|warning|error.
     * @param string $message Текст сообщения.
     * @param array  $context Произвольные данные.
     * @param array  $options Доп. опции: process_uid, trace (bool|'full'|'caller'), user_id, skip (int — доп. кадров backtrace).
     * @return mxLoggerLog|null Сохранённый объект или null, если запись отфильтрована/выключена.
     */
    public function log($tags, $level, $message, array $context = array(), array $options = array())
    {
        if (!$this->config['enabled']) {
            return null;
        }

        $level = $this->normalizeLevel($level);
        if (!$this->levelEnabled($level)) {
            return null;
        }
        if (!$this->isAllowed()) {
            return null;
        }

        $caller = $this->captureCaller($level, $options);

        $tagsArr = $this->normalizeTags($tags);
        $fields = array(
            'tags'        => $this->wrapTags($tagsArr),
            'process_uid' => isset($options['process_uid']) ? (string) $options['process_uid'] : null,
            'level'       => $level,
            'message'     => (string) $message,
            'context'     => $context ?: null,
            'class'       => $caller['class'],
            'function'    => $caller['function'],
            'file'        => $caller['file'],
            'line'        => $caller['line'],
            'trace'       => $caller['trace'],
            'user_id'     => $this->getUserId(),
            'session_id'  => $this->getSessionId(),
            'ip'          => $this->getIp(),
            'createdon'   => time(),
        );

        // Событие ДО записи. Плагин может ОТМЕНИТЬ запись
        // ($modx->event->returnedValues['prevent'] = true) или ИЗМЕНИТЬ любое поле
        // ($modx->event->returnedValues['<поле>'] = значение; для 'tags' можно массив).
        // Гард inEvent: если лог вызван из обработчика события — события не дёргаем
        // (защита от бесконечной рекурсии).
        if (!$this->inEvent) {
            $this->inEvent = true;
            $rv = null;
            try {
                $this->modx->invokeEvent('mxlOnBeforeLogSave', array_merge($fields, array(
                    'tags_list' => $tagsArr,
                    'options'   => $options,
                    'mxlogger'  => $this,
                )));
                if ($this->modx->event && $this->modx->event->name === 'mxlOnBeforeLogSave') {
                    $rv = $this->modx->event->returnedValues;
                }
            } catch (\Throwable $e) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[mxLogger] Ошибка в обработчике mxlOnBeforeLogSave: ' . $e->getMessage());
            }
            $this->inEvent = false;

            if (is_array($rv) && !empty($rv)) {
                if (!empty($rv['prevent']) || !empty($rv['cancel'])) {
                    return null;
                }
                foreach ($rv as $k => $v) {
                    if (array_key_exists($k, $fields)) {
                        $fields[$k] = $v;
                    }
                }
                if (isset($rv['tags']) && is_array($rv['tags'])) {
                    $fields['tags'] = $this->wrapTags($this->normalizeTags($rv['tags']));
                }
            }
        }

        /** @var mxLoggerLog $log */
        $log = $this->modx->newObject('mxLoggerLog');
        $log->fromArray($fields, '', true, true);

        if ($log->save() === false) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[mxLogger] Не удалось сохранить запись лога для тэгов "' . $log->get('tags') . '"');
            return null;
        }

        // Событие ПОСЛЕ записи — для уведомлений и пр. Ошибки обработчика не должны
        // ломать логирование/запрос.
        if (!$this->inEvent) {
            $this->inEvent = true;
            try {
                $this->modx->invokeEvent('mxlOnAfterLogSave', array_merge($fields, array(
                    'id'        => $log->get('id'),
                    'tags_list' => $this->unwrapTags($fields['tags']),
                    'object'    => $log,
                    'mxlogger'  => $this,
                )));
            } catch (\Throwable $e) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[mxLogger] Ошибка в обработчике mxlOnAfterLogSave: ' . $e->getMessage());
            }
            $this->inEvent = false;
        }

        return $log;
    }

    public function debug($tags, $message, array $context = array(), array $options = array())
    {
        return $this->log($tags, 'debug', $message, $context, $options);
    }

    public function info($tags, $message, array $context = array(), array $options = array())
    {
        return $this->log($tags, 'info', $message, $context, $options);
    }

    public function warning($tags, $message, array $context = array(), array $options = array())
    {
        return $this->log($tags, 'warning', $message, $context, $options);
    }

    public function error($tags, $message, array $context = array(), array $options = array())
    {
        return $this->log($tags, 'error', $message, $context, $options);
    }

    /**
     * Начать процесс — вернуть скоуп-логгер с фиксированными тэгами и process_uid.
     *
     * @param string|array $tags Один или несколько тэгов процесса.
     * @param string|null  $uid  Идентификатор экземпляра; если null — генерируется автоматически.
     * @return mxLoggerProcess
     */
    public function process($tags, $uid = null)
    {
        require_once dirname(__FILE__) . '/mxloggerprocess.class.php';
        if ($uid === null) {
            $uid = $this->generateUid();
        }
        return new mxLoggerProcess($this, $tags, $uid);
    }

    /* ============================================================
     *  Тэги
     * ============================================================ */

    /**
     * Нормализовать тэги: lowercase, только [a-z0-9], без пустых и дубликатов.
     *
     * @param string|array $tags
     * @return array
     */
    public function normalizeTags($tags)
    {
        if (!is_array($tags)) {
            // Допускаем строку с разделителями (запятая/пробел).
            $tags = preg_split('/[\s,]+/', (string) $tags);
        }
        $out = array();
        foreach ($tags as $tag) {
            $tag = preg_replace('/[^a-z0-9]/', '', strtolower((string) $tag));
            if ($tag !== '' && !in_array($tag, $out, true)) {
                $out[] = $tag;
            }
        }
        return $out;
    }

    /**
     * Обернуть список тэгов в CSV-строку с граничными запятыми: ,cart,purchase,
     * Это позволяет точечный LIKE '%,tag,%' и корректную токенизацию FULLTEXT.
     *
     * @param array $tags
     * @return string
     */
    public function wrapTags(array $tags)
    {
        return empty($tags) ? '' : ',' . implode(',', $tags) . ',';
    }

    /**
     * Развернуть CSV-обёртку тэгов обратно в массив.
     *
     * @param string $wrapped
     * @return array
     */
    public function unwrapTags($wrapped)
    {
        $wrapped = trim((string) $wrapped, ',');
        return $wrapped === '' ? array() : explode(',', $wrapped);
    }

    /**
     * Сгенерировать уникальный идентификатор экземпляра процесса.
     *
     * @return string
     */
    public function generateUid()
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Exception $e) {
            return substr(md5(uniqid('mxl', true)), 0, 16);
        }
    }

    /* ============================================================
     *  Фильтрация записи (enabled / min_level / whitelist)
     * ============================================================ */

    protected function normalizeLevel($level)
    {
        $level = strtolower((string) $level);
        return isset(self::LEVELS[$level]) ? $level : 'info';
    }

    protected function levelEnabled($level)
    {
        $min = $this->normalizeLevel($this->config['min_level']);
        return self::LEVELS[$level] >= self::LEVELS[$min];
    }

    /**
     * Разрешена ли запись логов в текущем запросе по whitelist-фильтрам.
     * Если ни один фильтр не задан — разрешено всё. Если задан хотя бы один —
     * запись допускается, только когда выполняется хотя бы одно из условий.
     *
     * @return bool
     */
    public function isAllowed()
    {
        if ($this->allowedCache !== null) {
            return $this->allowedCache;
        }

        $filters = array(
            'filter_user'      => trim($this->config['filter_user']),
            'filter_usergroup' => trim($this->config['filter_usergroup']),
            'filter_session'   => trim($this->config['filter_session']),
            'filter_cookie'    => trim($this->config['filter_cookie']),
        );
        $active = array_filter($filters, 'strlen');
        if (empty($active)) {
            return $this->allowedCache = true;
        }

        $allowed = false;
        if (isset($active['filter_user']) && $this->matchUser($active['filter_user'])) {
            $allowed = true;
        }
        if (!$allowed && isset($active['filter_usergroup']) && $this->matchUserGroup($active['filter_usergroup'])) {
            $allowed = true;
        }
        if (!$allowed && isset($active['filter_session']) && $this->matchSession($active['filter_session'])) {
            $allowed = true;
        }
        if (!$allowed && isset($active['filter_cookie']) && $this->matchCookie($active['filter_cookie'])) {
            $allowed = true;
        }

        return $this->allowedCache = $allowed;
    }

    /** Список id/username пользователей через запятую. */
    protected function matchUser($value)
    {
        $id = $this->getUserId();
        if (!$id) {
            return false;
        }
        $username = '';
        if ($user = $this->modx->user) {
            $username = (string) $user->get('username');
        }
        foreach (array_map('trim', explode(',', $value)) as $needle) {
            if ($needle === '') {
                continue;
            }
            if ((string) $needle === (string) $id || ($username !== '' && strcasecmp($needle, $username) === 0)) {
                return true;
            }
        }
        return false;
    }

    /** Список id/имён групп через запятую. */
    protected function matchUserGroup($value)
    {
        if (!$this->modx->user || !$this->getUserId()) {
            return false;
        }
        $needles = array_filter(array_map('trim', explode(',', $value)), 'strlen');
        if (empty($needles)) {
            return false;
        }
        foreach ($needles as $needle) {
            if (ctype_digit($needle)) {
                if ($this->userInGroupId((int) $needle)) {
                    return true;
                }
            } elseif ($this->modx->user->isMember($needle)) {
                return true;
            }
        }
        return false;
    }

    protected function userInGroupId($groupId)
    {
        $groups = $this->modx->user->getUserGroups();
        return in_array((int) $groupId, array_map('intval', $groups), true);
    }

    /** Список идентификаторов сессии через запятую. */
    protected function matchSession($value)
    {
        $sid = $this->getSessionId();
        if ($sid === '') {
            return false;
        }
        foreach (array_map('trim', explode(',', $value)) as $needle) {
            if ($needle !== '' && $needle === $sid) {
                return true;
            }
        }
        return false;
    }

    /**
     * Кука. Форматы значения настройки:
     *   "имя"            — достаточно наличия куки;
     *   "имя=значение"   — кука должна иметь указанное значение.
     * Можно перечислять несколько через запятую.
     */
    protected function matchCookie($value)
    {
        foreach (array_map('trim', explode(',', $value)) as $rule) {
            if ($rule === '') {
                continue;
            }
            if (strpos($rule, '=') !== false) {
                list($name, $expected) = array_map('trim', explode('=', $rule, 2));
                if ($name !== '' && isset($_COOKIE[$name]) && (string) $_COOKIE[$name] === $expected) {
                    return true;
                }
            } elseif (isset($_COOKIE[$rule])) {
                return true;
            }
        }
        return false;
    }

    /* ============================================================
     *  Захват контекста вызова (backtrace)
     * ============================================================ */

    /**
     * Определить режим захвата для уровня и собрать данные о вызывающем коде.
     *
     * @return array{class:?string,function:?string,file:?string,line:?int,trace:?array}
     */
    protected function captureCaller($level, array $options)
    {
        $mode = $this->resolveCaptureMode($level, $options);
        $empty = array('class' => null, 'function' => null, 'file' => null, 'line' => null, 'trace' => null);
        if ($mode === 'off') {
            return $empty;
        }

        $withArgs = ($mode === 'full');
        $skip = isset($options['skip']) ? (int) $options['skip'] : 0;
        // Доп. классы-прокладки вызывающего кода (фасады/обёртки над логгером),
        // которые тоже пропускаем при поиске источника. Можно указать точное имя
        // класса или префикс пространства имён (если строка заканчивается на «\»).
        $extraClasses = !empty($options['skip_classes']) ? (array) $options['skip_classes'] : array();
        $btOptions = $withArgs ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS;
        $limit = $withArgs ? 0 : ($this->config['trace_limit'] + 8);
        $frames = debug_backtrace($btOptions, $limit);

        // Найти первый «настоящий» кадр: пропускаем собственные классы компонента,
        // диспетчерские кадры фреймворка (события/плагины, include/eval) и классы
        // из skip_classes, чтобы источник указывал на реальный код.
        $i = 0;
        $count = count($frames);
        while ($i < $count && $this->isInternalFrame($frames[$i], $extraClasses)) {
            $i++;
        }
        $i += $skip;
        if ($i >= $count) {
            return $empty;
        }

        $callerFrame = $frames[$i];
        // file/line вызова лога находятся в предыдущем (внутреннем) кадре.
        $callSite = $i > 0 ? $frames[$i - 1] : $callerFrame;

        $result = array(
            'class'    => isset($callerFrame['class']) ? $callerFrame['class'] : null,
            'function' => isset($callerFrame['function']) ? $callerFrame['function'] : null,
            'file'     => isset($callSite['file']) ? $callSite['file'] : null,
            'line'     => isset($callSite['line']) ? (int) $callSite['line'] : null,
            'trace'    => null,
        );

        if ($withArgs) {
            $result['trace'] = array(
                'params' => isset($callerFrame['args']) ? $this->sanitizeArgs($callerFrame['args']) : array(),
                'stack'  => $this->buildStack(array_slice($frames, $i)),
            );
        }

        return $result;
    }

    /**
     * Является ли кадр «внутренним» (собственный класс компонента или
     * диспетчерская механика фреймворка) — такой кадр пропускаем при поиске
     * настоящего источника вызова.
     *
     * @param array $frame
     * @param array $extraClasses Доп. классы-прокладки (точное имя или префикс ns со «\» на конце).
     * @return bool
     */
    protected function isInternalFrame(array $frame, array $extraClasses = array())
    {
        $cls = isset($frame['class']) ? $frame['class'] : '';
        $fn = isset($frame['function']) ? $frame['function'] : '';

        if ($cls !== '') {
            if (in_array($cls, $this->ownClasses, true)) {
                return true;
            }
            foreach ($extraClasses as $skipClass) {
                if ($skipClass === '') {
                    continue;
                }
                if ($cls === $skipClass
                    || (substr($skipClass, -1) === '\\' && strpos($cls, $skipClass) === 0)) {
                    return true;
                }
            }
        }
        // Любой *::invokeEvent (modX, miniShop2, …) — диспетчер событий.
        if ($fn === 'invokeEvent') {
            return true;
        }
        return in_array($cls . '::' . $fn, $this->noiseFrames, true);
    }

    protected function resolveCaptureMode($level, array $options)
    {
        if (isset($options['trace'])) {
            if ($options['trace'] === true || $options['trace'] === 'full') {
                return 'full';
            }
            if ($options['trace'] === false) {
                return 'caller';
            }
            if (is_string($options['trace'])) {
                return $options['trace'];
            }
        }
        $mode = $this->config['capture_mode'];
        if ($mode === 'auto') {
            return self::LEVELS[$level] >= self::LEVELS['warning'] ? 'full' : 'caller';
        }
        return in_array($mode, array('off', 'caller', 'full'), true) ? $mode : 'caller';
    }

    /**
     * Свернуть стэк вызовов в компактный список строк.
     *
     * @param array $frames
     * @return array
     */
    protected function buildStack(array $frames)
    {
        $stack = array();
        $limit = $this->config['trace_limit'];
        foreach ($frames as $frame) {
            if (count($stack) >= $limit) {
                break;
            }
            $fn = isset($frame['function']) ? $frame['function'] : '';
            if (isset($frame['class'])) {
                $type = isset($frame['type']) ? $frame['type'] : '::';
                $fn = $frame['class'] . $type . $fn;
            }
            $location = '';
            if (isset($frame['file'])) {
                $location = $frame['file'] . (isset($frame['line']) ? ':' . $frame['line'] : '');
            }
            $stack[] = array('call' => $fn, 'at' => $location);
        }
        return $stack;
    }

    /**
     * Рекурсивно привести аргументы к безопасному для хранения виду.
     * Объекты заменяются на «object(Класс)», строки/массивы ограничиваются.
     *
     * @param mixed $value
     * @param int   $depth
     * @return mixed
     */
    public function sanitizeArgs($value, $depth = 0)
    {
        if (is_object($value)) {
            if ($value instanceof Closure) {
                return 'Closure';
            }
            return 'object(' . get_class($value) . ')';
        }
        if (is_resource($value)) {
            return 'resource(' . get_resource_type($value) . ')';
        }
        if (is_string($value)) {
            $max = $this->config['args_max_string'];
            if ($max > 0 && strlen($value) > $max) {
                return substr($value, 0, $max) . '…(' . strlen($value) . ')';
            }
            return $value;
        }
        if (is_array($value)) {
            if ($depth >= $this->config['args_max_depth']) {
                return '…array(' . count($value) . ')';
            }
            $out = array();
            $i = 0;
            foreach ($value as $k => $v) {
                if ($i++ >= $this->config['args_max_items']) {
                    $out['…'] = 'truncated(' . count($value) . ')';
                    break;
                }
                $out[$k] = $this->sanitizeArgs($v, $depth + 1);
            }
            return $out;
        }
        // int, float, bool, null
        return $value;
    }

    /* ============================================================
     *  Идентификация запроса
     * ============================================================ */

    public function getUserId()
    {
        return $this->modx->user ? (int) $this->modx->user->get('id') : 0;
    }

    public function getSessionId()
    {
        $sid = session_id();
        return $sid !== false ? (string) $sid : '';
    }

    public function getIp()
    {
        foreach (array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR') as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '';
    }
}
