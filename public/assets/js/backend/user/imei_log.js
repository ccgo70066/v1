define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/imei_log/index' + location.search,
                    add_url: 'user/imei_log/add',
                    edit_url: 'user/imei_log/edit',
                    del_url: 'user/imei_log/del',
                    multi_url: 'user/imei_log/multi',
                    import_url: 'user/imei_log/import',
                    table: 'user_imei_log',
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
                        {field: 'orig_imei', title: __('Orig_imei'), operate: false},
                        {field: 'orig_system', title: __('Orig_system'), searchList: {"IOS":__('Ios'),"ANDROID":__('Android')}, formatter: Table.api.formatter.normal},
                        {field: 'orig_model', title: __('Orig_model'), operate: false},
                        {field: 'imei', title: __('Imei'), operate: false},
                        {field: 'system', title: __('System'), searchList: {"IOS":__('Ios'),"ANDROID":__('Android')}, formatter: Table.api.formatter.normal},
                        {field: 'model', title: __('Model'), operate: false},
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
