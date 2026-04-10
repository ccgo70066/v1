define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'lucky/money_log/index' + location.search,
                    // add_url: 'lucky/money_log/add',
                    // edit_url: 'lucky/money_log/edit',
                    // del_url: 'lucky/money_log/del',
                    // multi_url: 'lucky/money_log/multi',
                    detail_url: 'lucky/money_log/index',
                    table: 'lucky_money_log',
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
                        // {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'lucky_money_id', title: __('红包编号'), formatter: function (value, row, index) {
                                let url = "lucky/money?lucky_money.id=" + value;
                                //方式一,直接返回class带有addtabsit的链接,这可以方便自定义显示内容
                                return '<a href="' + url + '" class="label label-success addtabsit" title="' + __("Search %s", value) + '">' + value + '</a>';
                            }},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.nickname', title: __('用户昵称'), operate: false},
                        {field: 'amount', title: __('Amount'), operate:'BETWEEN'},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange'},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange'},
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