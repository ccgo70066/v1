define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/car/index' + location.search,
                    add_url: 'user/car/add',
                    edit_url: 'user/car/edit',
                    del_url: 'user/car/del',
                    multi_url: 'user/car/multi',
                    import_url: 'user/car/import',
                    table: 'user_car',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: false,
                fixedRightNumber: 1,
                search: false,
                columns: [
                    [
                       /* {checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user_nickname', title: __('用户昵称'),operate: false},
                        {field: 'car.name', title: __('Car_id'),operate: false},
                        {field: 'from_by', title: __('From_by'), searchList: {"1":__('From_by 1'),"2":__('From_by 2')}, formatter: Table.api.formatter.normal},
                        //{field: 'buy_price', title: __('Buy_price'), operate: false},
                        {field: 'expired_days', title: __('Expired_days'),operate: false,formatter: function (value, row, index) {
                                if (row.expired_days ==-1) return '<span class="label label-primary">永久</span>';
                                return row.expired_days;
                            }},
                        {field: 'expired_time', title: __('Expired_time'), operate: false, addclass:'datetimerange', autocomplete:false},
                        {field: 'use_time', title: __('Use_time'), operate: false, addclass:'datetimerange', autocomplete:false},
                        {field: 'use_status', title: __('Use_status'), searchList: {"0":__('Use_status 0'),"1":__('Use_status 1'),"2":__('Use_status 2')}, formatter: Table.api.formatter.status},
                        {field: 'is_wear', title: __('Is_wear'), searchList: {"0":__('Is_wear 0'),"1":__('Is_wear 1')}, formatter: Table.api.formatter.normal},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        /*{field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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
