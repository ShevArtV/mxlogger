<?php
/**
 * PHP-резолвер mxLogger: создание таблиц при install/upgrade.
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
            // Создаёт таблицу, если её ещё нет; на upgrade безопасно достраивает недостающие поля/индексы.
            $manager->createObjectContainer('mxLoggerLog');
            $manager->addField('mxLoggerLog', 'tags');
            $manager->addField('mxLoggerLog', 'process_uid');
            $manager->addIndex('mxLoggerLog', 'process_uid');
            $manager->addField('mxLoggerLog', 'class');
            $manager->addField('mxLoggerLog', 'function');
            $manager->addField('mxLoggerLog', 'file');
            $manager->addField('mxLoggerLog', 'line');
            $manager->addField('mxLoggerLog', 'trace');
            $manager->addIndex('mxLoggerLog', 'class');

            // FULLTEXT-индекс по тэгам: addIndex/createObjectContainer не всегда
            // надёжно создают FULLTEXT, поэтому гарантируем его напрямую.
            $table = $modx->getTableName('mxLoggerLog');
            $exists = false;
            if ($stmt = $modx->query("SHOW INDEX FROM {$table} WHERE Key_name = 'tags'")) {
                $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$exists) {
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
