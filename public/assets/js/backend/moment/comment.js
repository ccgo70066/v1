define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'moment/comment/index' + location.search,
                    add_url: 'moment/comment/add',
                    //edit_url: 'moment/comment/edit',
                    del_url: 'moment/comment/del',
                    multi_url: 'moment/comment/multi',
                    import_url: 'moment/comment/import',
                    table: 'moment_comment',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                searchFormVisible: false,
                search: false,
                columns: [
                    [
                       /* {checkbox: true},*/
                        {field: 'id', title: __('Id')},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'moment_id', title: __('Moment_id')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.nickname', title: __('User_id'), operate: false},
                        //{field: 'to_user_id', title: __('To_user_id')},
                        //{field: 'touser.nickname', title: __('To_user_id')},
                        {field: 'content', title: __('Content'), operate: false},
                        {field: 'create_time', title: __('Create_time'), operate: false, addclass:'datetimerange', autocomplete:false},
                        /*{field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},*/
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
