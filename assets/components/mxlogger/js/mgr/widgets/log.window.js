MxLogger.window.View = function(config) {
    config = config || {};
    var r = config.record || {};

    // Кликабельное значение-фильтр: применит фильтр к гриду и закроет окно.
    var fval = function(type, value, display) {
        if (value === undefined || value === null || value === '') { return ''; }
        var d = Ext.util.Format.htmlEncode(display !== undefined ? display : value);
        if (!type) { return d; }
        return '<a class="mxlogger-fval" data-ftype="' + type + '" data-fval="' +
            Ext.util.Format.htmlEncode(value) + '" ext:qtip="' + _('mxlogger_click_filter') + '">' + d + '</a>';
    };

    var tagsHtml = '';
    Ext.each(r.tags_list || [], function(t) {
        var e = Ext.util.Format.htmlEncode(t);
        tagsHtml += '<a class="mxlogger-fval mxlogger-tag" data-ftype="tag" data-fval="' + e +
            '" ext:qtip="' + _('mxlogger_click_filter') + '">' + e + '</a> ';
    });

    var rows = [
        [_('mxlogger_col_createdon'), Ext.util.Format.htmlEncode(r.createdon_formatted || '')],
        [_('mxlogger_col_level'), fval('level', r.level)],
        [_('mxlogger_col_tags'), tagsHtml],
        [_('mxlogger_col_process'), fval('process', r.process_uid)],
        [_('mxlogger_col_caller'), fval('query', r.caller)],
        [_('mxlogger_field_source'), fval('query', r.source)],
        [_('mxlogger_col_user'), fval('ident', r.username || r.user_id,
            (r.username || '') + (r.user_id ? ' (#' + r.user_id + ')' : ''))],
        [_('mxlogger_field_session'), fval('ident', r.session_id)],
        [_('mxlogger_field_ip'), fval('ident', r.ip)]
    ];
    var head = '<table class="mxlogger-detail">';
    Ext.each(rows, function(row) {
        if (!row[1]) { return; }
        head += '<tr><th>' + row[0] + '</th><td>' + row[1] + '</td></tr>';
    });
    head += '</table>';

    var pre = function(body) {
        if (body === undefined || body === null || body === '') {
            return '<div class="mxlogger-empty">' + _('mxlogger_no_data') + '</div>';
        }
        return '<pre class="mxlogger-pre">' + Ext.util.Format.htmlEncode(body) + '</pre>';
    };
    var msgHtml = r.message
        ? '<div class="mxlogger-msg">' + Ext.util.Format.htmlEncode(r.message) + '</div>'
        : '';

    Ext.applyIf(config, {
        title: _('mxlogger_log') + ' #' + r.id,
        width: 760,
        autoHeight: true,
        modal: true,
        closeAction: 'close',
        items: [
            {
                xtype: 'panel', border: false, bodyCssClass: 'mxlogger-detail-body',
                html: head
            },
            {
                xtype: 'tabpanel',
                activeTab: 0,
                height: 340,
                deferredRender: false,
                border: true,
                defaults: { autoScroll: true, bodyCssClass: 'mxlogger-detail-body', padding: 8 },
                items: [
                    { title: _('mxlogger_field_context'), html: msgHtml + pre(r.context_pretty) },
                    { title: _('mxlogger_field_trace'), html: pre(r.trace_pretty) }
                ]
            }
        ],
        buttons: [{
            text: _('close'),
            handler: function() { this.close(); },
            scope: this
        }],
        listeners: {
            afterrender: function(w) {
                w.el.on('click', function(e) {
                    var el = e.getTarget('.mxlogger-fval', 5, true);
                    if (!el) { return; }
                    e.stopEvent();
                    if (w.grid && w.grid.applyQuickFilter) {
                        w.grid.applyQuickFilter(el.getAttribute('data-ftype'), el.getAttribute('data-fval'));
                    }
                    w.close();
                });
            }
        }
    });
    MxLogger.window.View.superclass.constructor.call(this, config);
};
Ext.extend(MxLogger.window.View, MODx.Window);
Ext.reg('mxlogger-window-view', MxLogger.window.View);
