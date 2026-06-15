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

            // Charset: текстовые колонки (message/context/trace) должны принимать
            // мультибайт (кириллица и пр.). На части серверов createObjectContainer
            // создаёт таблицу в дефолтном charset сервера (напр. latin1) → INSERT
            // кириллицы падает с «1366 Incorrect string value». Приводим таблицу к
            // utf8mb4. Безопасно по длине индексов: макс. B-tree на тексте — class
            // varchar(190)=760 байт (< 767 даже на старом MySQL), tags — FULLTEXT
            // (лимит префикса не применяется).
            $needConvert = true;
            if ($stmt = $modx->query("SHOW FULL COLUMNS FROM {$table} WHERE Field = 'message'")) {
                $col = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!empty($col['Collation']) && stripos($col['Collation'], 'utf8mb4') === 0) {
                    $needConvert = false;
                }
            }
            if ($needConvert) {
                try {
                    $modx->exec("ALTER TABLE {$table} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $modx->log(modX::LOG_LEVEL_INFO, '[mxlogger] Таблица приведена к utf8mb4.');
                } catch (\Throwable $e) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[mxlogger] Не удалось привести таблицу к utf8mb4: ' . $e->getMessage());
                }
            }
            break;

        case xPDOTransport::ACTION_UNINSTALL:
            // Таблицу при удалении НЕ дропаем, чтобы не потерять логи.
            // При необходимости очистки — удалить таблицу modx_mxlogger_log вручную.
            break;
    }
}

return true;
