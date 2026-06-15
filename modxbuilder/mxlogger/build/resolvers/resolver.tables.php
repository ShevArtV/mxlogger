<?php
/**
 * PHP-резолвер mxLogger: создание/достройка таблицы при install/upgrade.
 * Идемпотентно: createObjectContainer создаёт таблицу со всеми колонками и
 * индексами на чистой установке; addField/addIndex вызываются ТОЛЬКО для
 * реально недостающих (иначе на свежей установке сыпались бы «Duplicate column/key»).
 *
 * @var xPDOTransport $transport
 * @var array $options
 * @var modX $modx
 */
if ($transport->xpdo) {
    /** @var modX $modx */
    $modx =& $transport->xpdo;

    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $modelPath = $modx->getOption('mxlogger.core_path', null, $modx->getOption('core_path') . 'components/mxlogger/') . 'model/';
            $modx->addPackage('mxlogger', $modelPath);

            $manager = $modx->getManager();
            $manager->createObjectContainer('mxLoggerLog');

            $table = $modx->getTableName('mxLoggerLog');

            // Какие колонки и индексы уже есть.
            $cols = array();
            if ($stmt = $modx->query("SHOW COLUMNS FROM {$table}")) {
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $c) {
                    $cols[strtolower($c)] = true;
                }
            }
            $idx = array();
            if ($stmt = $modx->query("SHOW INDEX FROM {$table}")) {
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $idx[$r['Key_name']] = true;
                }
            }

            // Достраиваем только недостающее (актуально при апгрейде со старой схемы).
            foreach (array('tags', 'process_uid', 'class', 'function', 'file', 'line', 'trace') as $field) {
                if (!isset($cols[strtolower($field)])) {
                    $manager->addField('mxLoggerLog', $field);
                }
            }
            foreach (array('process_uid', 'class') as $index) {
                if (!isset($idx[$index])) {
                    $manager->addIndex('mxLoggerLog', $index);
                }
            }
            // FULLTEXT по тэгам (createObjectContainer не всегда его создаёт надёжно).
            if (!isset($idx['tags'])) {
                $modx->exec("ALTER TABLE {$table} ADD FULLTEXT INDEX `tags` (`tags`)");
                $modx->log(modX::LOG_LEVEL_INFO, '[mxlogger] Создан FULLTEXT-индекс tags.');
            }
            break;

        case xPDOTransport::ACTION_UNINSTALL:
            // Таблицу при удалении НЕ дропаем, чтобы не потерять логи.
            // При необходимости очистки — удалить таблицу modx_mxlogger_log вручную.
            break;
    }
}

return true;
