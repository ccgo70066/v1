define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'general/admin_option_log/index' + location.search,
                    add_url: 'general/admin_option_log/add',
                    edit_url: 'general/admin_option_log/edit',
                    del_url: 'general/admin_option_log/del',
                    multi_url: 'general/admin_option_log/multi',
                    import_url: 'general/admin_option_log/import',
                    table: 'admin_option_log',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'admin.nickname', title: __('Admin_id')},
                        {field: 'user.nickname', title: __('User_id')},
                        {field: 'option', title: __('Option'), searchList: {"1":__('Option 1'),"2":__('Option 2')}, formatter: Table.api.formatter.normal},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'content', title: __('Content'), operate: false},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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
