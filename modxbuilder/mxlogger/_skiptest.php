<?php
$root = dirname(dirname(__DIR__)) . '/';
require_once $root . 'core/config/config.inc.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
$m = new modX();
$m->initialize('web');
$m->addPackage('mxlogger', MODX_CORE_PATH . 'components/mxlogger/model/');

class MxlSkipWrap
{
    public static function doLog($mxl, $useSkip)
    {
        $opts = $useSkip ? array('skip_classes' => array('MxlSkipWrap')) : array();
        return $mxl->info('skiptest', 'via wrapper', array(), $opts);
    }
}

function mxlSkipCaller($mxl, $useSkip)
{
    return MxlSkipWrap::doLog($mxl, $useSkip);
}

$mxl = $m->mxlogger;
$r1 = mxlSkipCaller($mxl, false); // без skip_classes — источник будет обёртка
$r2 = mxlSkipCaller($mxl, true);  // со skip_classes — источник реальный вызывающий

echo "БЕЗ skip_classes: class=[" . $r1->get('class') . "] function=[" . $r1->get('function') . "]\n";
echo "СО  skip_classes: class=[" . $r2->get('class') . "] function=[" . $r2->get('function') . "]\n";
echo "(ожидаем: без -> MxlSkipWrap::doLog; со -> ::mxlSkipCaller)\n";

$m->removeCollection('mxLoggerLog', array('tags:LIKE' => '%,skiptest,%'));
echo "тестовые записи удалены\n";
