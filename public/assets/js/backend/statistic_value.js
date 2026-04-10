define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'statistic_value/index' + location.search,
                    add_url: 'statistic_value/add',
                    edit_url: 'statistic_value/edit',
                    del_url: 'statistic_value/del',
                    multi_url: 'statistic_value/multi',
                    import_url: 'statistic_value/import',
                    table: 'statistic_value',
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
                        {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'date', title: __('Date'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'hour', title: __('Hour'), operate: 'LIKE'},
                        {field: 'recharge_amount', title: __('Recharge_amount'), operate:false},
                        {field: 'system_add_amount', title: __('System_add_amount'), operate:false},
                        {field: 'income_amount', title: __('Income_amount'), operate:false},
                        {field: 'union_reward_amount', title: __('Union_reward_amount'), operate:false},
                        {field: 'total_amount', title: __('Total_amount'), operate:false},
                        {field: 'egg', title: __('Egg'), operate:false},
                        {field: 'wheel', title: __('Wheel'), operate:false},
                        {field: 'box_1', title: __('Box_1'), operate:false},
                        {field: 'box_2', title: __('Box_2'), operate:false},
                        {field: 'gift', title: __('Gift'), operate:false},
                        {field: 'shop', title: __('Shop'), operate:false},
                        {field: 'vip', title: __('Vip'), operate:false},
                        {field: 'system_sub', title: __('System_sub'), operate:false},
                        {field: 'total_used', title: __('Total_used'), operate:false},
                        {field: 'total_balance', title: __('Total_balance'), operate:false},
                        {field: 'total_bag', title: __('Total_bag'), operate:false},
                        {field: 'withdraw', title: __('Withdraw'), operate:false},
                        {field: 'withdrawing', title: __('Withdrawing'), operate:false},
                        {field: 'not_withdraw', title: __('Not_withdraw'), operate:false},
                        {field: 'redpack', title: __('Redpack'), operate:false},
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
