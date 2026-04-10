define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/business_log_h/index?user_id=' + location.search,
                    add_url: 'user/business_log_h/add',
                    edit_url: 'user/business_log_h/edit',
                    del_url: 'user/business_log_h/del',
                    multi_url: 'user/business_log_h/multi',
                    import_url: 'user/business_log_h/import',
                    table: 'user_business_log',
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
                showExport:false,
                showColumns:false,
                showToggle:false,
                showSearch: false,
                columns: [
                    [
                        /*{checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'user_id', title: __('User_id'),operate: '='},
                        {field: 'user.nickname', title: __('用户昵称'),operate: false},
                        {field: 'type', title: __('Type'), searchList: {"2":__('Type 2'),"3":__('Type 3'),"4":__('Type 4'),"5":__('Type 5')}, formatter: Table.api.formatter.normal},
                        {field: 'cate', title: __('Cate'),searchList: {"0":__('Cate 0'),"1":__('Cate 1')}, formatter: Table.api.formatter.normal},
                        {field: 'origin_amount', title: __('Origin_amount'), operate: false},
                        {field: 'amount', title: __('Amount'), operate: false},
                        {field: 'change_amount', title: __('Change_amount'), operate: false},
                        {field: 'comment', title: __('Comment'), operate: false},
                        {field: 'from', title: __('From'), searchList: {"0":__('From 0'),"1":__('From 1'),"2":__('From 2'),"3":__('From 3'),"4":__('From 4'),"5":__('From 5'),"7":__('From 7'),"8":__('From 8'),"9":__('From 9'),"11":__('From 11'),"12":__('From 12'),"13":__('From 13')}, formatter: Table.api.formatter.normal},
                        //{field: 'room_id', title: __('Room_id')},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                       /* {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}*/
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            $("label[for='user_id']").parent("div").addClass('hidden');
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
