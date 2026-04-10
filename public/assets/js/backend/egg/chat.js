define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/chat/index' + location.search,
                    // add_url: 'egg/chat/add',
                    // edit_url: 'egg/chat/edit',
                    del_url: 'egg/chat/del',
                    forbid_url: 'egg/chat/forbid',
                    multi_url: 'egg/chat/multi',
                    detail_url: 'egg/chat/index',
                    table: 'egg_chat',
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
                        {field: 'nickname', title: __('User_id')+'昵称', operate: false},
                        {field: 'chat_type', title: __('Chat_type'), searchList: {"1":__('Chat_type 1'),"2":__('Chat_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'content', title: __('content'), operate: false},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange'},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [{
                                name: 'forbid',
                                text: '禁言',
                                title: '禁言用户',
                                classname: 'btn btn-xs btn-success btn-ajax',
                                icon: 'fa fa-wrench',
                                // extend: 'data-area=\'["40%", "45%"]\'',
                                url: 'egg/chat_forbid/add?user_id={user_id}',
                                confirm: '确认要禁言该用户吗?',
                                refresh: true,
                                visible: function (row) {
                                    return row.forbid ==0;
                                }
                            }]}
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
