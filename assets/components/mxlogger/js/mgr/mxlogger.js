var MxLogger = function(config) {
    config = config || {};
    MxLogger.superclass.constructor.call(this, config);
};
Ext.extend(MxLogger, Ext.Component, {
    page: {}, window: {}, grid: {}, panel: {}, combo: {}, config: {}
});
Ext.reg('mxlogger', MxLogger);

MxLogger.page = {};
MxLogger.window = {};
MxLogger.grid = {};
MxLogger.panel = {};
MxLogger.combo = {};
MxLogger.config = {};

/**
 * Унифицированный вызов коннектора компонента.
 */
MxLogger.request = function(action, params, cb, scope) {
    Ext.Ajax.request({
        url: MxLogger.config.connector_url,
        params: Ext.apply({ action: action }, params || {}),
        success: function(r) {
            var data = Ext.decode(r.responseText);
            if (cb) { cb.call(scope || window, data); }
        }
    });
};
