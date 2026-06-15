<?php
/**
 * Регистрация mxLogger в системной настройке extension_packages — чтобы сервис
 * автозагружался на init и был доступен как $modx->mxlogger->log(...) без getService().
 * Запись добавляется/удаляется идемпотентно, не затирая чужие пакеты.
 *
 * @var xPDOTransport $transport
 * @var array $options
 * @var modX $modx
 */
if ($transport->xpdo) {
    /** @var modX $modx */
    $modx =& $transport->xpdo;

    $readPackages = function () use ($modx) {
        $raw = $modx->getOption('extension_packages', null, '[]');
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : array();
    };
    $stripMxlogger = function ($packages) {
        $out = array();
        foreach ($packages as $pkg) {
            if (is_array($pkg) && array_key_exists('mxlogger', $pkg)) {
                continue;
            }
            $out[] = $pkg;
        }
        return $out;
    };
    $save = function ($packages) use ($modx) {
        $setting = $modx->getObject('modSystemSetting', 'extension_packages');
        if (!$setting) {
            $setting = $modx->newObject('modSystemSetting');
            $setting->fromArray(array(
                'key'       => 'extension_packages',
                'namespace' => 'core',
                'area'      => 'system',
                'xtype'     => 'textfield',
            ), '', true, true);
        }
        $setting->set('value', json_encode(array_values($packages)));
        $setting->save();
    };

    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $packages = $stripMxlogger($readPackages());
            // Ядро для getService собирает путь как path + packageName + '/',
            // поэтому path = …/model/, а класс грузится из …/model/mxlogger/.
            $packages[] = array('mxlogger' => array(
                'path' => '[[++core_path]]components/mxlogger/model/',
                'serviceName' => 'mxlogger',
                'serviceClass' => 'mxLogger',
            ));
            $save($packages);
            break;

        case xPDOTransport::ACTION_UNINSTALL:
            $save($stripMxlogger($readPackages()));
            break;
    }

    $modx->getCacheManager()->refresh(array('system_settings' => array()));
}

return true;
