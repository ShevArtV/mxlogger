<?php
/**
 * Рендер записей журнала mxLogger в текстовые форматы (.md / .txt).
 *
 * Вынесен из эндпоинта assets/.../export.php, чтобы логику можно было
 * прогонять тестом в обход HTTP/авторизации.
 *
 * На вход renderRow() ожидает массив полей записи в формате
 * mxLoggerLog::toArray() (как отдаёт грид в getlist::prepareRow):
 *   id, tags, process_uid, level, message, context, class, function,
 *   file, line, trace, user_id, session_id, ip, createdon.
 *
 * @package mxlogger
 * @subpackage processors
 */
class mxLoggerLogExporter
{
    /** @var modX */
    protected $modx;
    /** @var string md|txt */
    protected $format;
    /** @var array кэш user_id => username */
    protected $userCache = array();

    public function __construct(modX $modx, $format)
    {
        $this->modx = $modx;
        $this->format = ($format === 'txt') ? 'txt' : 'md';
    }

    public function getFormat()
    {
        return $this->format;
    }

    public function getExtension()
    {
        return $this->format;
    }

    public function getMime()
    {
        return $this->format === 'md' ? 'text/markdown' : 'text/plain';
    }

    /**
     * Привести JSON-поле (context/trace) к читаемой строке.
     * После toArray() поле типа JSON может прийти уже массивом, а не строкой.
     *
     * @param mixed $raw
     * @return string '' если пусто
     */
    public static function decodeJson($raw)
    {
        if (is_array($raw) || is_object($raw)) {
            return ($raw === array() || $raw === null)
                ? ''
                : json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $raw = (string) $raw;
        if ($raw === '' || $raw === 'null' || $raw === '[]' || $raw === '{}') {
            return '';
        }
        $data = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && $data !== null) {
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $raw;
    }

    /** Имя пользователя по user_id (с кэшем), '' для гостя/неизвестного. */
    protected function resolveUser($userId)
    {
        $userId = (int) $userId;
        if (!$userId) {
            return '';
        }
        if (!array_key_exists($userId, $this->userCache)) {
            $user = $this->modx->getObject('modUser', $userId);
            $this->userCache[$userId] = $user ? $user->get('username') : '';
        }
        return $this->userCache[$userId];
    }

    /** Человекочитаемая подпись пользователя: «username (id)» / «id» / «guest». */
    protected function userLabel(array $r)
    {
        $username = $this->resolveUser(isset($r['user_id']) ? $r['user_id'] : 0);
        if ($username !== '') {
            return $username . ' (' . (int) $r['user_id'] . ')';
        }
        return !empty($r['user_id']) ? (string) (int) $r['user_id'] : 'guest';
    }

    /** Шапка файла. */
    public function renderHeader($total, $filterText, $exportedAt)
    {
        $title = $this->modx->lexicon('mxlogger_export_title');
        $lDate = $this->modx->lexicon('mxlogger_export_date');
        $lFilter = $this->modx->lexicon('mxlogger_export_filter');
        $lCount = $this->modx->lexicon('mxlogger_export_count');

        if ($this->format === 'md') {
            return '# ' . $title . "\n\n"
                . '- ' . $lDate . ': ' . $exportedAt . "\n"
                . '- ' . $lFilter . ': ' . $filterText . "\n"
                . '- ' . $lCount . ': ' . (int) $total . "\n\n---\n\n";
        }
        return $title . "\n"
            . $lDate . ': ' . $exportedAt . "\n"
            . $lFilter . ': ' . $filterText . "\n"
            . $lCount . ': ' . (int) $total . "\n\n";
    }

    /** Одна запись в выбранном формате. */
    public function renderRow(array $r)
    {
        return $this->format === 'txt' ? $this->renderTxt($r) : $this->renderMd($r);
    }

    protected function renderMd(array $r)
    {
        $dt = !empty($r['createdon']) ? date('Y-m-d H:i:s', (int) $r['createdon']) : '';
        $level = strtoupper((string) (isset($r['level']) ? $r['level'] : ''));
        $tags = trim((string) (isset($r['tags']) ? $r['tags'] : ''), ',');
        $tags = $tags === '' ? '' : str_replace(',', ', ', $tags);
        $caller = trim((!empty($r['class']) ? $r['class'] . '::' : '') . (isset($r['function']) ? $r['function'] : ''));
        $source = !empty($r['file']) ? $r['file'] . (!empty($r['line']) ? ':' . $r['line'] : '') : '';

        $out = '## [' . $level . '] ' . $dt . ' · #' . (int) $r['id'] . "\n\n";
        if ($tags !== '') {
            $out .= '- **tags:** ' . $tags . "\n";
        }
        if (!empty($r['process_uid'])) {
            $out .= '- **process:** `' . $r['process_uid'] . "`\n";
        }
        if ($caller !== '' || $source !== '') {
            $out .= '- **caller:** ' . ($caller !== '' ? '`' . $caller . '`' : '')
                . ($source !== '' ? ' — ' . $source : '') . "\n";
        }
        $out .= '- **user:** ' . $this->userLabel($r) . "\n";
        if (!empty($r['session_id'])) {
            $out .= '- **session:** `' . $r['session_id'] . "`\n";
        }
        if (!empty($r['ip'])) {
            $out .= '- **ip:** ' . $r['ip'] . "\n";
        }
        $out .= "\n" . trim((string) (isset($r['message']) ? $r['message'] : '')) . "\n";

        $context = self::decodeJson(isset($r['context']) ? $r['context'] : '');
        if ($context !== '') {
            $out .= "\n<details><summary>context</summary>\n\n```json\n" . $context . "\n```\n\n</details>\n";
        }
        $trace = self::decodeJson(isset($r['trace']) ? $r['trace'] : '');
        if ($trace !== '') {
            $out .= "\n<details><summary>trace</summary>\n\n```json\n" . $trace . "\n```\n\n</details>\n";
        }
        return $out . "\n---\n\n";
    }

    protected function renderTxt(array $r)
    {
        $dt = !empty($r['createdon']) ? date('Y-m-d H:i:s', (int) $r['createdon']) : '';
        $level = strtoupper((string) (isset($r['level']) ? $r['level'] : ''));
        $tags = trim((string) (isset($r['tags']) ? $r['tags'] : ''), ',');
        $tags = $tags === '' ? '' : str_replace(',', ', ', $tags);
        $caller = trim((!empty($r['class']) ? $r['class'] . '::' : '') . (isset($r['function']) ? $r['function'] : ''));
        $source = !empty($r['file']) ? $r['file'] . (!empty($r['line']) ? ':' . $r['line'] : '') : '';

        $out = str_repeat('=', 72) . "\n";
        $out .= '#' . (int) $r['id'] . '  ' . $dt . '  [' . $level . "]\n";
        if ($tags !== '') {
            $out .= 'tags:    ' . $tags . "\n";
        }
        if (!empty($r['process_uid'])) {
            $out .= 'process: ' . $r['process_uid'] . "\n";
        }
        if ($caller !== '' || $source !== '') {
            $out .= 'caller:  ' . trim($caller . '  ' . $source) . "\n";
        }
        $out .= 'user:    ' . $this->userLabel($r) . "\n";
        if (!empty($r['session_id'])) {
            $out .= 'session: ' . $r['session_id'] . "\n";
        }
        if (!empty($r['ip'])) {
            $out .= 'ip:      ' . $r['ip'] . "\n";
        }
        $out .= str_repeat('-', 72) . "\n";
        $out .= trim((string) (isset($r['message']) ? $r['message'] : '')) . "\n";

        $context = self::decodeJson(isset($r['context']) ? $r['context'] : '');
        if ($context !== '') {
            $out .= "\ncontext:\n" . $context . "\n";
        }
        $trace = self::decodeJson(isset($r['trace']) ? $r['trace'] : '');
        if ($trace !== '') {
            $out .= "\ntrace:\n" . $trace . "\n";
        }
        return $out . "\n";
    }
}
