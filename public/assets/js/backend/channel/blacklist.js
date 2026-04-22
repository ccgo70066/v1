define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'channel/blacklist/index' + location.search,
                    add_url: 'channel/blacklist/add',
                    edit_url: 'channel/blacklist/edit',
                    del_url: 'channel/blacklist/del',
                    multi_url: 'channel/blacklist/multi',
                    import_url: 'channel/blacklist/import',
                    table: 'channel_blacklist',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                search: false,
                columns: [
                    [
                       /* {checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'system', title: __('System'), searchList: {"1":__('System 1'),"2":__('System 2')}, formatter: Table.api.formatter.normal},
                        {field: 'version', title: __('Version'), operate: 'LIKE'},
                        // {field: 'channel.name', title: __('渠道名称'), operate: 'LIKE'},
                        // {field: 'appid', title: __('Appid'), operate: 'LIKE'},
                        {
                            field: 'item_code', title: __('Item_code'), searchList: {
                                "ITEM_002": __('Item_code item_002'),
                                "ITEM_003": __('Item_code item_003'),
                                "ITEM_004": __('Item_code item_004'),
                                "ITEM_005": __('Item_code item_005'),
                                "ITEM_006": __('Item_code item_006')
                            }, operate: 'FIND_IN_SET', formatter: Table.api.formatter.label
                        },
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
