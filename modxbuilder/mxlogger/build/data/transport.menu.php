<?php
/**
 * Пункт меню CMP mxLogger (захардкожен — пакет самодостаточен).
 * Раздел «Компоненты» → mxLogger.
 *
 * @var modxBuilder $this
 * @var string $categoryName
 * @var string $namespace
 */

$menus = array();

/** @var modMenu $menu */
$menu = $this->modx->newObject('modMenu');
$menu->fromArray(array(
    'text'        => 'mxlogger',
    'parent'      => 'components',
    'description' => 'mxlogger_menu_desc',
    'icon'        => '',
    'menuindex'   => 0,
    'params'      => '',
    'handler'     => '',
    'namespace'   => $namespace,
    'action'      => 'index',
    'permissions' => '',
), '', true, true);

$menus[] = $menu;

return $menus;
