<?php
/**
 * Системные настройки mxLogger (захардкожены — пакет самодостаточен).
 *
 * @var modxBuilder $this
 * @var string $categoryName
 * @var string $namespace
 */

$definitions = array(
    'mxlogger.enabled'         => array('value' => '1',     'xtype' => 'combo-boolean'),
    'mxlogger.min_level'       => array('value' => 'debug', 'xtype' => 'textfield'),
    'mxlogger.capture_mode'    => array('value' => 'auto',  'xtype' => 'textfield'),
    'mxlogger.tag_filter_mode' => array('value' => 'auto',  'xtype' => 'textfield'),
    'mxlogger.trace_limit'     => array('value' => '15',    'xtype' => 'numberfield'),
    'mxlogger.args_max_depth'  => array('value' => '3',     'xtype' => 'numberfield'),
    'mxlogger.args_max_string' => array('value' => '512',   'xtype' => 'numberfield'),
    'mxlogger.args_max_items'  => array('value' => '50',    'xtype' => 'numberfield'),
    'mxlogger.filter_user'     => array('value' => '',      'xtype' => 'textfield'),
    'mxlogger.filter_usergroup'=> array('value' => '',      'xtype' => 'textfield'),
    'mxlogger.filter_session'  => array('value' => '',      'xtype' => 'textfield'),
    'mxlogger.filter_cookie'   => array('value' => '',      'xtype' => 'textfield'),
    'mxlogger.log_lifetime'    => array('value' => '604800', 'xtype' => 'numberfield'),
    'mxlogger.rotate_interval' => array('value' => '3600',   'xtype' => 'numberfield'),
);

$settings = array();
foreach ($definitions as $key => $def) {
    /** @var modSystemSetting $setting */
    $setting = $this->modx->newObject('modSystemSetting');
    $setting->fromArray(array(
        'key'       => $key,
        'value'     => $def['value'],
        'xtype'     => $def['xtype'],
        'namespace' => $namespace,
        'area'      => isset($def['area']) ? $def['area'] : 'mxlogger:default',
        'editedon'  => null,
    ), '', true, true);
    $settings[] = $setting;
}

unset($definitions, $def, $key, $setting);

return $settings;
