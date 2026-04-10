define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/recharge_blacklist/index' + location.search,
                    add_url: 'order/recharge_blacklist/add',
                    //edit_url: 'order/recharge_blacklist/edit',
                    del_url: 'order/recharge_blacklist/del',
                    multi_url: 'order/recharge_blacklist/multi',
                    import_url: 'order/recharge_blacklist/import',
                    table: 'recharge_blacklist',
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
                        /*{checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        // {field: 'user_id', title: __('用户ID')},
                        // {field: 'user.nickname', title: __('User_id')},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2'),"3":__('Type 3')}, formatter: Table.api.formatter.normal},
                        {field: 'number', title: __('number')},
                        {field: 'form', title: __('Form'), operate: false},
                        {field: 'comment', title: __('Comment'), operate: false},
                        {field: 'admin.username', title: __('Admin_id'),operate: false},
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
