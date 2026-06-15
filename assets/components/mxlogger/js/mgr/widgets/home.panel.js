MxLogger.panel.Home = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        baseCls: 'modx-formpanel',
        cls: 'container',
        items: [{
            html: '<h2>' + _('mxlogger') + '</h2>',
            border: false,
            cls: 'modx-page-header'
        }, {
            xtype: 'modx-tabs',
            defaults: { border: false, autoHeight: true },
            border: true,
            items: [{
                title: _('mxlogger_tab_log'),
                layout: 'anchor',
                items: [{
                    html: '<p class="help">' + _('mxlogger_log_intro') + '</p>',
                    border: false,
                    cls: 'mxlogger-intro'
                }, {
                    xtype: 'mxlogger-grid-log',
                    anchor: '100%'
                }]
            }]
        }]
    });
    MxLogger.panel.Home.superclass.constructor.call(this, config);
};
Ext.extend(MxLogger.panel.Home, MODx.Panel);
Ext.reg('mxlogger-panel-home', MxLogger.panel.Home);


MxLogger.page.Home = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        components: [{
            xtype: 'mxlogger-panel-home',
            renderTo: 'mxlogger-panel-home'
        }]
    });
    MxLogger.page.Home.superclass.constructor.call(this, config);
};
Ext.extend(MxLogger.page.Home, MODx.Component);
Ext.reg('mxlogger-page-home', MxLogger.page.Home);
