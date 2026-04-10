define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'admin_option_log/index' + location.search,
                    add_url: 'admin_option_log/add',
                    edit_url: 'admin_option_log/edit',
                    del_url: 'admin_option_log/del',
                    multi_url: 'admin_option_log/multi',
                    import_url: 'admin_option_log/import',
                    table: 'admin_option_log',
                }
            });

            var table = $("#table");
            table.on('load-success.bs.table', function (e, data) {
                for (let value in data.extend) {
                    $("#"+value).text(data.extend[value]);
                }
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'admin_option_log.id',
                fixedColumns: true,
                fixedRightNumber: 1,
                search: false,
                columns: [
                    [
                       /* {checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'admin.nickname', title: __('Admin_id')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.nickname', title: __('用户昵称'), operate: false},
                        {field: 'option', title: __('Option'), searchList: {"1":__('Option 1'),"2":__('Option 2'),"3":__('Option 3'),"4":__('Option 4'),"5":__('Option 5'),"6":__('Option 6'),"9":__('Option 9')}, formatter: Table.api.formatter.normal},
                        {field: 'amount', title: __('数量'), operate: false},
                        {field: 'content', title: __('Content'), operate: false},
                        {field: 'comment', title: __('Comment'), operate: false},
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
