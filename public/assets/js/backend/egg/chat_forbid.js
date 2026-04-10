define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/chat_forbid/index' + location.search,
                    add_url: 'egg/chat_forbid/add',
                    // edit_url: 'egg/chat_forbid/edit',
                    del_url: 'egg/chat_forbid/del',
                    multi_url: 'egg/chat_forbid/multi',
                    detail_url: 'egg/chat_forbid/index',
                    table: 'egg_chat_forbid',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        // {checkbox: true},
                        {field: 'id', title: __('Id'),visible:false,operate: false},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user_nickname', title: __('User_id')+'昵称', operate: false},
                        {field: 'admin_id', title: __('Admin_id'), formatter: function (value, row, index) {
                                return row.admin_nickname;
                            }, searchList: $.getJSON('auth/admin/search_list/key/id/name/nickname')},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange'},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange'},
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