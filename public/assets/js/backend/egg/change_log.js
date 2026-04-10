define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/change_log/index' + location.search,
                    add_url: 'egg/change_log/add',
                    edit_url: 'egg/change_log/edit',
                    del_url: 'egg/change_log/del',
                    multi_url: 'egg/change_log/multi',
                    detail_url: 'egg/change_log/index',
                    table: 'egg_change_log',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'egg_change_log.id',
                searchFormVisible: false,
                columns: [
                    [
                        // {checkbox: true},
                        {field: 'id', title: __('Id'),visible:false,operate: false},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.nickname', title: __('用户昵称')},
                        {field: 'box_type', title: __('Box_type'), searchList: {"1":__('Box_type 1'),"2":__('Box_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"-1":__('Type -1')}, formatter: Table.api.formatter.normal},
                        {field: 'before', title: __('Before'), operate:'BETWEEN'},
                        {field: 'after', title: __('After'), operate:'BETWEEN'},
                        {field: 'diff', title: __('Diff'), operate:'BETWEEN'},
                        {field: 'admin.nickname', title: __('Operator')},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', data:'data-time-picker="true"'},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', data:'data-time-picker="true"'},
                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
