<?php
/**
 * Контроллер CMP mxLogger — журнал логов.
 *
 * @package mxlogger
 */
class mxLoggerIndexManagerController extends modExtraManagerController
{
    /** @var mxLogger $mxlogger */
    public $mxlogger;

    public function initialize()
    {
        $corePath = $this->modx->getOption('mxlogger.core_path', null, $this->modx->getOption('core_path') . 'components/mxlogger/');
        $this->mxlogger = $this->modx->getService(
            'mxlogger',
            'mxLogger',
            $corePath . 'model/mxlogger/',
            array('core_path' => $corePath)
        );
        parent::initialize();
    }

    public function getLanguageTopics()
    {
        return array('mxlogger:default');
    }

    public function checkPermissions()
    {
        return true;
    }

    public function getPageTitle()
    {
        return $this->modx->lexicon('mxlogger');
    }

    public function loadCustomCssJs()
    {
        $assetsUrl = $this->mxlogger->config['assets_url'];
        $assetsPath = $this->mxlogger->config['assets_path'];
        $connectorUrl = $this->mxlogger->config['connector_url'];

        // Cache-busting: ?v=<mtime файла>, чтобы новые версии CSS/JS не залипали
        // в кэше браузера и в объединённом кэше менеджера (compress_js/compress_css).
        $v = function ($rel) use ($assetsPath) {
            $file = $assetsPath . $rel;
            return is_file($file) ? '?v=' . filemtime($file) : '';
        };

        $this->addCss($assetsUrl . 'css/mgr/main.css' . $v('css/mgr/main.css'));
        $this->addJavascript($assetsUrl . 'js/mgr/mxlogger.js' . $v('js/mgr/mxlogger.js'));
        $this->addLastJavascript($assetsUrl . 'js/mgr/widgets/log.window.js' . $v('js/mgr/widgets/log.window.js'));
        $this->addLastJavascript($assetsUrl . 'js/mgr/widgets/log.grid.js' . $v('js/mgr/widgets/log.grid.js'));
        $this->addLastJavascript($assetsUrl . 'js/mgr/widgets/home.panel.js' . $v('js/mgr/widgets/home.panel.js'));

        $this->addHtml('<script type="text/javascript">
        MxLogger.config = ' . $this->modx->toJSON(array(
            'connector_url' => $connectorUrl,
            'assets_url'    => $assetsUrl,
        )) . ';
        Ext.onReady(function() {
            MODx.load({ xtype: "mxlogger-page-home" });
        });
        </script>');
    }

    public function getTemplateFile()
    {
        return $this->mxlogger->config['core_path'] . 'templates/home.tpl';
    }

    /**
     * Отдать контент напрямую, без файла-шаблона.
     *
     * @return string
     */
    public function getContent(array $scriptProperties = array())
    {
        return '<div id="mxlogger-panel-home"></div>';
    }
}
