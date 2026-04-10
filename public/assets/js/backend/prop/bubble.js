define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'prop/bubble/index' + location.search,
                    add_url: 'prop/bubble/add',
                    edit_url: 'prop/bubble/edit',
                    del_url: 'prop/bubble/del',
                    multi_url: 'prop/bubble/multi',
                    import_url: 'prop/bubble/import',
                    table: 'bubble',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                fixedColumns: true,
                fixedRightNumber: 1,
                search: false,
                columns: [
                    [
                        /*{checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'cover', title: __('Cover'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'days', title: __('Days'),operate: false},
                        {field: 'is_renew', title: __('Is_renew'), searchList: {"0":__('Is_renew 0'),"1":__('Is_renew 1')}, formatter: Table.api.formatter.normal},
                        {field: 'is_sell', title: __('Is_sell'), searchList: {"0":__('Is_sell 0'),"1":__('Is_sell 1')}, formatter: Table.api.formatter.normal},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status},
                        {field: 'intro', title: __('Intro'), operate: false},
                        {field: 'weigh', title: __('Weigh'), operate: false,sortable: true},
                        {field: 'color', title: __('Color'), operate: 'LIKE'},
                        // {field: 'create_time', title: __('Create_time'), operate: false, addclass:'datetimerange', autocomplete:false,sortable: true},
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
