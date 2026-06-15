<?php
/**
 * Регистрация системных событий mxLogger на install И upgrade (идемпотентно):
 *   mxlOnBeforeLogSave — до записи лога (можно отменить/изменить);
 *   mxlOnAfterLogSave  — после записи (для уведомлений).
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
            foreach (array('mxlOnBeforeLogSave', 'mxlOnAfterLogSave') as $name) {
                if ($modx->getObject('modEvent', array('name' => $name))) {
                    continue; // уже есть — не трогаем
                }
                $event = $modx->newObject('modEvent');
                $event->fromArray(array(
                    'name'      => $name,
                    'service'   => 6,
                    'groupname' => 'mxLogger',
                ), '', true, true);
                $event->save();
                $modx->log(modX::LOG_LEVEL_INFO, '[mxlogger] Зарегистрировано событие: ' . $name);
            }
            break;

        case xPDOTransport::ACTION_UNINSTALL:
            foreach (array('mxlOnBeforeLogSave', 'mxlOnAfterLogSave') as $name) {
                if ($event = $modx->getObject('modEvent', array('name' => $name))) {
                    $event->remove();
                }
            }
            break;
    }
}

return true;
