define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/business/index' + location.search,
                    add_url: 'user/business/add',
                    edit_url: 'user/business/edit',
                    del_url: 'user/business/del',
                    multi_url: 'user/business/multi',
                    import_url: 'user/business/import',
                    table: 'user_business',
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
                        //{checkbox: true},
                        {field: 'id', title: __('用户'),sortable: true},
                        {field: 'user_nickname', title: __('用户昵称'),operate: false},
                        {field: 'role', title: __('Role'), formatter: Table.api.formatter.normal, searchList: {1: __('Role1'), 3: __('Role3'), 4: __('Role4')}},
                        {field: 'amount', title: __('Amount'), operate: false, sortable: true},
                        // {field: 'lock_amount', title: __('Lock_amount'), operate: false,sortable: true},
                        {field: 'reward_amount', title: __('Reward_amount'), operate: false,sortable: true},
                        // {field: 'pledge_amount', title: __('Pledge_amount'), operate: false,sortable: true},
                        {field: 'integral', title: __('Integral'), operate: false,sortable: true},
                        {field: 'level', title: __('Level'),operate: false,sortable: true},
                        {field: 'recharge_amount', title: __('Recharge_amount'), operate: false,sortable: true},
                        {field: 'rewarded_amount', title: __('Rewarded_amount'), operate: false,sortable: true},
                        /*{field: 'payword', title: __('Payword'), operate: false},
                        {field: 'safe_code', title: __('Safe_code'), operate: false},
                        {field: 'shred', title: __('Shred'),operate: false},
                        {field: 'proom_no', title: __('Proom_no'),operate: false},
                        {field: 'union_id', title: __('Union_id'),operate: false},
                        {field: 'create_time', title: __('Create_time'), operate: false, addclass:'datetimerange', autocomplete:false},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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
