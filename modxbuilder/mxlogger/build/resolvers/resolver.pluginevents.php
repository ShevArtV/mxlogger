<?php
/**
 * Привязки плагинов mxLogger к событиям — priority -10 на install И upgrade (идемпотентно).
 *
 * Логгер должен идти первым в цепочке: modX::invokeEvent() сбрасывает returnedValues
 * перед каждым плагином, а miniShop2 читает их после всей цепочки. Логгер в конце
 * затирал данные плагина сайта (receiver/properties на msOnSubmitOrder).
 * Резолвер правит и привязки, уже стоящие на сайте с другим priority.
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
            $priority = -10;
            $names = array('mxLoggerFacade', 'mxLoggerMiniShop2', 'mxLoggerRotate');
            $total = 0;
            $fixed = 0;
            foreach ($names as $name) {
                /** @var modPlugin $plugin */
                if (!$plugin = $modx->getObject('modPlugin', array('name' => $name))) {
                    continue;
                }
                /** @var modPluginEvent $pe */
                foreach ($modx->getCollection('modPluginEvent', array('pluginid' => $plugin->get('id'))) as $pe) {
                    $total++;
                    if ((int)$pe->get('priority') === $priority) {
                        continue;
                    }
                    $pe->set('priority', $priority);
                    if ($pe->save()) {
                        $fixed++;
                    }
                }
            }
            $modx->log(modX::LOG_LEVEL_INFO, "[mxlogger] Привязки плагинов к событиям: priority {$priority} у {$total}, исправлено {$fixed}.");

            // eventMap лежит в кэше контекстов (context_settings) — пересобираем его всегда:
            // на upgrade priority мог переписать уже vehicle плагинов (fixed = 0), а кэш остался старым.
            if ($total > 0) {
                $q = $modx->newQuery('modContext');
                $q->select($modx->escape('key'));
                $keys = $q->prepare() && $q->stmt->execute() ? $q->stmt->fetchAll(PDO::FETCH_COLUMN) : array();
                if (!empty($keys)) {
                    $modx->cacheManager->refresh(array('context_settings' => array('contexts' => $keys)));
                }
            }
            break;
    }
}

return true;
