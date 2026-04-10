define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/task_log/index' + location.search,
                    add_url: 'user/task_log/add',
                    edit_url: 'user/task_log/edit',
                    del_url: 'user/task_log/del',
                    multi_url: 'user/task_log/multi',
                    import_url: 'user/task_log/import',
                    table: 'user_task_log',
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
                search: false,
                columns: [
                    [
                       /* {checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.nickname', title: __('用户昵称'),operate: false},
                        {field: 'key', title: __('Key'), operate: 'LIKE'},
                        {field: 'task.name', title: __('任务名称'), operate: false},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'date', title: __('Date'), operate: false, addclass:'datetimerange', autocomplete:false},
                        {field: 'target', title: __('Target'),operate: false},
                        {field: 'finish_target', title: __('Finish_target'),operate: false},
                        {field: 'complete', title: __('Complete'), searchList: {"1":__('Complete 1'),"2":__('Complete 2'),"0":__('Complete 0')}, formatter: Table.api.formatter.normal},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                       /* {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}*/
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
