define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/shred_shop_log/index' + location.search,
                    add_url: 'user/shred_shop_log/add',
                    edit_url: 'user/shred_shop_log/edit',
                    del_url: 'user/shred_shop_log/del',
                    multi_url: 'user/shred_shop_log/multi',
                    import_url: 'user/shred_shop_log/import',
                    table: 'shred_shop_log',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                search: false,
                showExport:false,
                showColumns:false,
                showToggle:false,
                columns: [
                    [
                       /* {checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'user_id', title: __('用户ID')},
                        {field: 'user.nickname', title: __('User_id'),operate: false},
                        {field: 'gift.name', title: __('Gift_id'),operate: false},
                        {field: 'price', title: __('Price'), operate: false},
                        {field: 'count', title: __('Count'), operate: false},
                        {field: 'amount', title: __('Amount'), operate: false},
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
