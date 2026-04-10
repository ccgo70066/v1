define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/blacklist/index' + location.search,
                    add_url: 'user/blacklist/add',
                    del_url: 'user/blacklist/del',
                    multi_url: 'user/blacklist/multi',
                    import_url: 'user/blacklist/import',
                    table: 'blacklist',
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
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2'),"3":__('Type 3')}, formatter: Table.api.formatter.normal},
                        {field: 'form', title: __('提示类型'), searchList: {"1":__('封禁提示'),"2":__('网络异常提示')}, formatter: Table.api.formatter.normal},
                        {field: 'number', title: __('Number'), operate: 'LIKE'},
                        {field: 'break_rule', title: __('Break_rule'), operate: false},
                        {field: 'admin.username', title: __('Creator'),operate: false},
                        {field: 'create_time', title: __('封禁开始时间'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'end_time', title: __('封禁结束时间'), operate: false, addclass:'datetimerange', autocomplete:false},
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
