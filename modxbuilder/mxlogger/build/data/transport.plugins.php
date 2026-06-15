<?php
/**
 * Плагины mxLogger (статичные, код из файлов):
 *   - mxLoggerMiniShop2 — логирование корзины/заказа miniShop2;
 *   - mxLoggerRotate    — ротация старых логов на OnMODXInit.
 *
 * @var modxBuilder $this
 * @var string $categoryName
 * @var string $namespace
 */

$specs = array(
    array(
        'name'        => 'mxLoggerMiniShop2',
        'description' => 'Логирование действий с корзиной (тэг cart) и заказом (тэг order); сквозной тэг purchase.',
        'file'        => 'plugin.mslogger.php',
        'static_file' => 'mxlogger/elements/plugins/plugin.mslogger.php',
        'events'      => array(
            'msOnAddToCart', 'msOnChangeInCart', 'msOnRemoveFromCart', 'msOnEmptyCart',
            'msOnAddToOrder', 'msOnRemoveFromOrder', 'msOnEmptyOrder',
            'msOnBeforeCreateOrder', 'msOnCreateOrder', 'msOnSubmitOrder',
            'msOnChangeOrderStatus',
        ),
    ),
    array(
        'name'        => 'mxLoggerRotate',
        'description' => 'Ротация старых записей лога mxLogger (троттлинг + батчевое удаление).',
        'file'        => 'plugin.mxloggerrotate.php',
        'static_file' => 'mxlogger/elements/plugins/plugin.mxloggerrotate.php',
        'events'      => array('OnMODXInit'),
    ),
);

$plugins = array();
foreach ($specs as $spec) {
    $file = $this->config['source_core'] . 'elements/plugins/' . $spec['file'];
    if (!is_readable($file)) {
        $this->modx->log(modX::LOG_LEVEL_ERROR, '[mxlogger] Не найден файл плагина: ' . $file);
        continue;
    }

    /** @var modPlugin $plugin */
    $plugin = $this->modx->newObject('modPlugin');
    $plugin->fromArray(array(
        'name'        => $spec['name'],
        'description' => $spec['description'],
        // Статичный элемент: код берётся из файла. static_file — относительно core/components/.
        'static'      => 1,
        'static_file' => $spec['static_file'],
        'source'      => 0,
        'disabled'    => 0,
    ), '', true, true);

    $pluginEvents = array();
    foreach ($spec['events'] as $event) {
        /** @var modPluginEvent $pe */
        $pe = $this->modx->newObject('modPluginEvent');
        $pe->fromArray(array(
            'event'       => $event,
            'priority'    => 0,
            'propertyset' => 0,
        ), '', true, true);
        $pluginEvents[] = $pe;
    }
    $plugin->addMany($pluginEvents, 'PluginEvents');

    $plugins[] = $plugin;
}

return $plugins;
