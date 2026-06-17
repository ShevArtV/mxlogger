MxLogger.grid.Log = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        id: 'mxlogger-grid-log',
        url: MxLogger.config.connector_url,
        baseParams: { action: 'mgr/log/getlist' },
        fields: [
            'id', 'tags', 'tags_list', 'process_uid', 'level', 'message', 'message_short',
            'class', 'function', 'file', 'line', 'caller', 'source',
            'user_id', 'username', 'session_id', 'ip',
            'createdon', 'createdon_formatted'
        ],
        autoExpandColumn: 'message_short',
        paging: true,
        remoteSort: true,
        save_action: false,
        anchor: '100%',
        columns: [
            {
                header: '', dataIndex: 'id', width: 34, fixed: true, sortable: false,
                renderer: function() {
                    return '<i class="icon icon-eye mxlogger-eye" title="' + _('mxlogger_view') + '"></i>';
                }
            },
            {
                header: _('mxlogger_col_createdon'), dataIndex: 'createdon',
                width: 195, sortable: true, fixed: true,
                renderer: function(v, m, r) { return r.data.createdon_formatted; }
            },
            {
                header: _('mxlogger_col_level'), dataIndex: 'level', width: 80, fixed: true, sortable: true,
                renderer: function(v) {
                    return '<span class="mxlogger-level mxlogger-level-' + v + '">' + v + '</span>';
                }
            },
            {
                header: _('mxlogger_col_tags'), dataIndex: 'tags', width: 150, sortable: false,
                renderer: function(v, m, r) {
                    var list = r.data.tags_list || [];
                    if (!list.length) { return ''; }
                    var html = '';
                    Ext.each(list, function(t) {
                        html += '<span class="mxlogger-tag">' + Ext.util.Format.htmlEncode(t) + '</span> ';
                    });
                    return html;
                }
            },
            {
                header: _('mxlogger_col_process'), dataIndex: 'process_uid', width: 110, sortable: true,
                renderer: function(v) { return v ? '<tt>' + v + '</tt>' : ''; }
            },
            { header: _('mxlogger_col_message'), dataIndex: 'message_short', sortable: false },
            {
                header: _('mxlogger_col_caller'), dataIndex: 'class', width: 200, sortable: true,
                renderer: function(v, m, r) {
                    if (r.data.source) { m.attr = 'ext:qtip="' + Ext.util.Format.htmlEncode(r.data.source) + '"'; }
                    return Ext.util.Format.htmlEncode(r.data.caller || '');
                }
            },
            {
                header: _('mxlogger_col_user'), dataIndex: 'user_id', width: 100, sortable: true,
                renderer: function(v, m, r) { return r.data.username || (r.data.user_id ? r.data.user_id : _('mxlogger_guest')); }
            }
        ],
        tbar: this.getTopBar(config),
        listeners: {
            rowdblclick: { fn: this.viewLog, scope: this },
            cellclick: { fn: this.onCellClick, scope: this }
        }
    });
    MxLogger.grid.Log.superclass.constructor.call(this, config);
    this._getTopBarFields();
};
Ext.extend(MxLogger.grid.Log, MODx.grid.Grid, {

    getTopBar: function(config) {
        return [
            {
                xtype: 'button', itemId: 'filter_tag', text: _('mxlogger_filter_tag'),
                menu: new Ext.menu.Menu({ items: [{ text: '…', disabled: true }] }),
                listeners: { render: { fn: this._initTagMenu, scope: this } }
            },
            {
                xtype: 'combo', itemId: 'filter_level', mode: 'local',
                emptyText: _('mxlogger_filter_level'), width: 110, editable: false,
                triggerAction: 'all', valueField: 'level', displayField: 'level',
                store: new Ext.data.ArrayStore({
                    fields: ['level'],
                    data: [[''], ['debug'], ['info'], ['warning'], ['error']]
                }),
                listeners: { select: { fn: this.filterChange, scope: this } }
            },
            {
                xtype: 'textfield', itemId: 'filter_process', emptyText: _('mxlogger_filter_process'), width: 130,
                listeners: { change: { fn: this.filterChange, scope: this }, render: this._bindEnter, scope: this }
            },
            {
                xtype: 'textfield', itemId: 'filter_ident', emptyText: _('mxlogger_filter_ident'), width: 210,
                listeners: { change: { fn: this.filterChange, scope: this }, render: this._bindEnter, scope: this }
            },
            '-',
            {
                xtype: 'xdatetime', itemId: 'filter_date_from',
                dateFormat: 'Y-m-d', timeFormat: 'H:i', width: 280, timeWidth: 100,
                emptyText: _('mxlogger_filter_date_from'),
                listeners: { change: { fn: this.filterChange, scope: this } }
            },
            {
                xtype: 'xdatetime', itemId: 'filter_date_to',
                dateFormat: 'Y-m-d', timeFormat: 'H:i', width: 280, timeWidth: 100,
                emptyText: _('mxlogger_filter_date_to'),
                listeners: { change: { fn: this.filterChange, scope: this } }
            },
            '->',
            {
                xtype: 'textfield', itemId: 'filter_query', emptyText: _('mxlogger_search'), width: 180,
                listeners: { change: { fn: this.filterChange, scope: this }, render: this._bindEnter, scope: this }
            },
            {
                xtype: 'button', text: _('mxlogger_btn_reset'), handler: this.resetFilters, scope: this
            },
            '-',
            {
                xtype: 'button', text: _('mxlogger_btn_clear'), cls: 'red',
                handler: this.clearLog, scope: this
            }
        ];
    },

    _bindEnter: function(f) {
        f.el.on('keydown', function(e) {
            if (e.getKey() === e.ENTER) { this.filterChange(); }
        }, this);
    },

    _getTopBarFields: function() {
        var tb = this.getTopToolbar();
        this._f = {
            tag: tb.getComponent('filter_tag'),
            level: tb.getComponent('filter_level'),
            process: tb.getComponent('filter_process'),
            ident: tb.getComponent('filter_ident'),
            date_from: tb.getComponent('filter_date_from'),
            date_to: tb.getComponent('filter_date_to'),
            query: tb.getComponent('filter_query')
        };
    },

    filterChange: function() {
        var f = this._f;
        var fmt = function(field) {
            var v = field.getValue();
            return v && v.format ? v.format('Y-m-d H:i:s') : '';
        };
        var tagList = this._collectTags();
        this.getStore().baseParams.tags = tagList.join(',');
        this.getStore().baseParams.tags_match = 'all';
        this.getStore().baseParams.level = f.level.getValue();
        this.getStore().baseParams.process_uid = f.process.getValue();
        this.getStore().baseParams.ident = f.ident.getValue();
        this.getStore().baseParams.date_from = fmt(f.date_from);
        this.getStore().baseParams.date_to = fmt(f.date_to);
        this.getStore().baseParams.query = f.query.getValue();
        this.getBottomToolbar().changePage(1);
    },

    resetFilters: function() {
        // Чистим визуальные поля (по возможности).
        Ext.each(['level', 'process', 'ident', 'date_from', 'date_to', 'query'], function(k) {
            var fld = this._f[k];
            if (!fld) { return; }
            if (fld.clearValue) { fld.clearValue(); }
            if (fld.reset) { fld.reset(); }
        }, this);
        // Снимаем все чекбоксы тэгов (без событий) и сбрасываем подпись кнопки.
        this._uncheckTags();
        // Главное: обнуляем baseParams напрямую (а не через чтение полей, которые
        // могут сохранять значение) и перезагружаем грид с первой страницы.
        Ext.apply(this.getStore().baseParams, {
            tags: '', tags_match: '', level: '', process_uid: '', ident: '',
            date_from: '', date_to: '', query: ''
        });
        this.getBottomToolbar().changePage(1);
    },

    /* ---------- Мультивыбор тэгов (кнопка + меню чекбоксов) ---------- */

    _initTagMenu: function(btn) {
        var grid = this;
        grid._tagBtn = btn;
        MxLogger.request('mgr/log/gettags', {}, function(data) {
            if (!btn.menu) { return; }
            btn.menu.removeAll();
            var rows = (data && data.results) ? data.results : [];
            if (!rows.length) {
                btn.menu.add({ text: _('mxlogger_tags_empty'), disabled: true });
                return;
            }
            Ext.each(rows, function(r) {
                btn.menu.add(new Ext.menu.CheckItem({
                    text: r.tag,
                    hideOnClick: false,
                    checkHandler: function() { grid._onTagCheck(); }
                }));
            });
            btn.menu.add('-');
            btn.menu.add({
                text: _('mxlogger_btn_reset'),
                handler: function() { grid._clearTags(); }
            });
        }, grid);
    },

    _collectTags: function() {
        var tags = [];
        if (this._tagBtn && this._tagBtn.menu) {
            this._tagBtn.menu.items.each(function(it) {
                if (it.checked) { tags.push(it.text); }
            });
        }
        return tags;
    },

    _onTagCheck: function() {
        var tags = this._collectTags();
        this._tagBtn.setText(tags.length
            ? _('mxlogger_filter_tag') + ' (' + tags.length + ')'
            : _('mxlogger_filter_tag'));
        this.filterChange();
    },

    _uncheckTags: function() {
        if (this._tagBtn && this._tagBtn.menu) {
            this._tagBtn.menu.items.each(function(it) {
                if (it.checked && it.setChecked) { it.setChecked(false, true); }
            });
            this._tagBtn.setText(_('mxlogger_filter_tag'));
        }
    },

    _clearTags: function() {
        this._uncheckTags();
        this.filterChange();
    },

    /* Быстрый фильтр из окна детали (клик по значению). */
    applyQuickFilter: function(type, value) {
        switch (type) {
            case 'process':
                if (this._f.process && this._f.process.setValue) { this._f.process.setValue(value); }
                break;
            case 'level':
                if (this._f.level && this._f.level.setValue) { this._f.level.setValue(value); }
                break;
            case 'ident':
                if (this._f.ident && this._f.ident.setValue) { this._f.ident.setValue(value); }
                break;
            case 'query':
                if (this._f.query && this._f.query.setValue) { this._f.query.setValue(value); }
                break;
            case 'tag':
                this._setSingleTag(value);
                return;
            default:
                return;
        }
        this.filterChange();
    },

    _setSingleTag: function(tag) {
        if (this._tagBtn && this._tagBtn.menu) {
            this._tagBtn.menu.items.each(function(it) {
                if (it.setChecked) { it.setChecked(it.text === tag, true); }
            });
        }
        this._onTagCheck();
    },

    clearLog: function() {
        var bp = this.getStore().baseParams;
        // Те же фильтры, что и в гриде (getlist) — очистка удалит ровно то,
        // что сейчас отфильтровано. Если ни одного фильтра нет — весь журнал.
        var params = {
            tags: bp.tags, tags_match: bp.tags_match, level: bp.level,
            process_uid: bp.process_uid, ident: bp.ident, query: bp.query,
            date_from: bp.date_from, date_to: bp.date_to
        };
        var hasFilter = false;
        Ext.iterate(params, function(k, v) {
            if (k !== 'tags_match' && v) { hasFilter = true; }
        });
        var msg = hasFilter ? _('mxlogger_log_clear_confirm') : _('mxlogger_log_clear_confirm_all');
        Ext.Msg.confirm(_('mxlogger_btn_clear'), msg, function(e) {
            if (e !== 'yes') { return; }
            MxLogger.request('mgr/log/clear', params, function() { this.refresh(); }, this);
        }, this);
    },

    onCellClick: function(grid, rowIndex, columnIndex, e) {
        if (e.getTarget('.mxlogger-eye')) {
            this.viewLog(grid, rowIndex);
        }
    },

    viewLog: function(grid, rowIndex) {
        var row = this.getStore().getAt(rowIndex);
        MxLogger.request('mgr/log/get', { id: row.data.id }, function(data) {
            if (!data.success) { return; }
            var w = MODx.load({ xtype: 'mxlogger-window-view', record: data.object, grid: this });
            w.show();
        }, this);
    }
});
Ext.reg('mxlogger-grid-log', MxLogger.grid.Log);


/* Комбобокс уникальных тэгов */
MxLogger.combo.Tag = function(config) {
    config = config || {};
    Ext.applyIf(config, {
        name: 'tag',
        hiddenName: 'tag',
        displayField: 'tag',
        valueField: 'tag',
        triggerAction: 'all',
        editable: true,
        forceSelection: false,
        typeAhead: true,
        queryParam: 'query',
        url: MxLogger.config.connector_url,
        baseParams: { action: 'mgr/log/gettags' },
        fields: ['tag'],
        pageSize: 0
    });
    MxLogger.combo.Tag.superclass.constructor.call(this, config);
};
Ext.extend(MxLogger.combo.Tag, MODx.combo.ComboBox);
Ext.reg('mxlogger-combo-tag', MxLogger.combo.Tag);
