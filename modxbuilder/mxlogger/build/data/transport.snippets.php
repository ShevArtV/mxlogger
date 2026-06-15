<?php
/**
 * Сниппет mxLogger (захардкожен из файла-исходника — пакет самодостаточен).
 *
 * @var modxBuilder $this
 * @var string $categoryName
 * @var string $namespace
 */

$snippets = array();

$file = $this->config['source_core'] . 'elements/snippets/snippet.mxlogger.php';
if (!is_readable($file)) {
    $this->modx->log(modX::LOG_LEVEL_ERROR, '[mxlogger] Не найден файл сниппета: ' . $file);
    return $snippets;
}

$content = preg_replace('/^<\?php\s*/', '', file_get_contents($file));

/** @var modSnippet $snippet */
$snippet = $this->modx->newObject('modSnippet');
$snippet->fromArray(array(
    'id'          => 0,
    'name'        => 'mxLogger',
    'description' => 'Запись лога mxLogger из чанка/шаблона/Fenom.',
    'snippet'     => $content,
    'static'      => 0,
), '', true, true);

$snippets[] = $snippet;

return $snippets;
