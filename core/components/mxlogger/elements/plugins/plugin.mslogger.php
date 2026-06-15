<?php
/**
 * Плагин mxLoggerMiniShop2 — логирует действия с корзиной и оформление заказа miniShop2.
 *
 * Тэги:
 *   cart     — все действия с корзиной;
 *   order    — все действия с заказом;
 *   purchase — сквозной тэг для всех событий покупки (корзина + заказ).
 *
 * Логируются только значимые действия (info/error). Все события одной воронки
 * в рамках сессии объединяются общим process_uid.
 *
 * @var modX $modx
 * @package mxlogger
 */
$eventName = $modx->event->name;

$corePath = $modx->getOption('mxlogger.core_path', null, $modx->getOption('core_path') . 'components/mxlogger/');
/** @var mxLogger $mxl */
$mxl = $modx->getService('mxlogger', 'mxLogger', $corePath . 'model/mxlogger/', array('core_path' => $corePath));
if (!($mxl instanceof mxLogger)) {
    return;
}

// Безопасное представление значения для context (объекты — только имя класса).
$val = function ($v) {
    if (is_scalar($v) || $v === null || is_array($v)) {
        return $v;
    }
    return is_object($v) ? 'object(' . get_class($v) . ')' : gettype($v);
};

$CART = array('cart', 'purchase');
$ORDER = array('order', 'purchase');

$tags = null;
$level = 'info';
$message = '';
$context = array();

switch ($eventName) {

    /* ---------- Корзина ---------- */
    case 'msOnAddToCart':
        $tags = $CART;
        $message = 'Товар добавлен в корзину';
        $context = array('key' => $key);
        break;

    case 'msOnChangeInCart':
        $tags = $CART;
        $message = 'Изменено количество в корзине';
        $context = array('key' => $key, 'count' => $count);
        break;

    case 'msOnRemoveFromCart':
        $tags = $CART;
        $message = 'Товар удалён из корзины';
        $context = array('key' => $key);
        break;

    case 'msOnEmptyCart':
        $tags = $CART;
        $message = 'Корзина очищена';
        break;

    /* ---------- Заказ ---------- */
    case 'msOnAddToOrder':
        $tags = $ORDER;
        $message = 'Поле заказа заполнено';
        $context = array('field' => $key, 'value' => $val($value));
        break;

    case 'msOnRemoveFromOrder':
        $tags = $ORDER;
        $message = 'Поле заказа удалено';
        $context = array('field' => $key);
        break;

    case 'msOnEmptyOrder':
        $tags = $ORDER;
        $message = 'Заказ очищен';
        break;

    case 'msOnBeforeCreateOrder':
        $tags = $ORDER;
        $message = 'Создание заказа (до)';
        $context = isset($msOrder) && is_object($msOrder)
            ? array('num' => $msOrder->get('num'), 'cost' => $msOrder->get('cost'))
            : array();
        break;

    case 'msOnCreateOrder':
        $tags = $ORDER;
        $message = 'Заказ создан';
        $context = isset($msOrder) && is_object($msOrder)
            ? array(
                'order_id' => $msOrder->get('id'),
                'num'      => $msOrder->get('num'),
                'cost'     => $msOrder->get('cost'),
            )
            : array();
        break;

    case 'msOnSubmitOrder':
        $tags = $ORDER;
        $message = 'Оформление заказа (submit)';
        $context = array('fields' => (isset($data) && is_array($data)) ? array_keys($data) : array());
        break;

    case 'msOnChangeOrderStatus':
        $tags = $ORDER;
        $message = 'Статус заказа изменён';
        $statusName = null;
        if (isset($status) && ($st = $modx->getObject('msOrderStatus', $status))) {
            $statusName = $st->get('name');
        }
        $context = array(
            'order_id'    => (isset($order) && is_object($order)) ? $order->get('id') : null,
            'num'         => (isset($order) && is_object($order)) ? $order->get('num') : null,
            'old_status'  => isset($old_status) ? $old_status : null,
            'status'      => isset($status) ? $status : null,
            'status_name' => $statusName,
        );
        break;

    default:
        // Событие, на которое плагин не настроен — игнорируем.
        return;
}

// Для событий корзины добавляем её содержимое (items: id, count, price, options…).
// get() отдаёт массив корзины и не дёргает побочных событий (в отличие от status()).
if (in_array('cart', (array) $tags, true) && isset($cart) && is_object($cart) && method_exists($cart, 'get')) {
    $context['cart'] = $mxl->sanitizeArgs($cart->get());
}

// Сырые параметры события ms2 (product, count, key, value, status, cart…).
// У плагина они лежат в $modx->event->params. Рекурсивно санитизируем:
// объекты → object(Класс), с лимитами глубины/длины.
$eventParams = ($modx->event && is_array($modx->event->params)) ? $modx->event->params : array();
if (!empty($eventParams)) {
    $context['event_params'] = $mxl->sanitizeArgs($eventParams);
}
$context['event'] = $eventName;

// Объединяем все события одной покупательской воронки общим process_uid (по сессии).
// Режим захвата — по умолчанию (auto): caller для info, полный стек для error.
$options = array();
$sid = session_id();
if ($sid) {
    $options['process_uid'] = 'ms_' . substr(md5($sid), 0, 12);
}

$mxl->log($tags, $level, $message, $context, $options);

return;
