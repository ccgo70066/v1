define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/demo_log/index' + location.search,
                    add_url: 'egg/demo_log/add',
                    edit_url: 'egg/demo_log/edit',
                    del_url: 'egg/demo_log/del',
                    multi_url: 'egg/demo_log/multi',
                    detail_url: 'egg/demo_log/index',
                    table: 'egg_demo_log',
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
                columns: [
                    [
                        // {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'nickname', title: __('用户'), operate: false},
                        {field: 'box_type', title: __('Box_type'), searchList: {"1":__('Box_type 1'),"2":__('Box_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'count_type', title: __('Count_type'), searchList: {"1":__('Count_type 1'),"10":__('Count_type 10'),"100":__('Count_type 100')}, formatter: Table.api.formatter.normal},
                        {field: 'level_id', title: __('Level_id'), operate: '=', searchList: $.getJSON('egg/level/search_list'), formatter: function (value, row, index) {
                                return row.level_name;
                            }},
                        {field: 'used_amount', title: __('Used_amount'), operate: false, formatter: function (value, row, index) {
                                return parseFloat(value);
                            }},
                        {field: 'gift_id', title: __('礼物'), searchList: $.getJSON('egg/log/index/option/load_gift'), formatter: function (value, row, index) {
                                return row.gift_name;
                            }},
                        {field: 'gift_price', title: __('礼物价格'), operate: false},
                        {field: 'count', title: __('数量'), operate: 'between'},
                        {field: 'room_id', title: __('Room_id')},
                        {field: 'weigh_name', title: __('Weigh_name'), operate: '=', searchList: $.getJSON('egg/log/index/option/load_weigh')},
                        {field: 'jump_status', title: __('Jump_status'), searchList: {"0":__('Jump_status 0'),"1":__('Jump_status 1'),"2":__('Jump_status 2'),"3":__('Jump_status 3'),"4":__('Jump_status 4'),"5":__('Jump_status 5'),"6":__('Jump_status 6')}, formatter: Table.api.formatter.status, operate: '='},
                        // {field: 'jump_status', title: __('Jump_status'), searchList: {"0":__('Jump_status 0'),"1":__('Jump_status 1'),"2":__('Jump_status 2'),"3":__('Jump_status 3'),"4":__('Jump_status 4'),"5":__('Jump_status 5'),"6":__('Jump_status 6')}, formatter: Table.api.formatter.status, operate: false},
                        // {field: 'pool_sys_before', title: __('Pool_sys_before'),  operate: false},
                        // {field: 'pool_sys_after', title: __('Pool_sys_after'),  operate: false},
                        // {field: 'pool_sys_diff', title: __('Pool_sys_diff'),  operate: false},
                        // {field: 'pool_pub_after', title: __('Pool_pub_after'),  operate: false},
                        // {field: 'pool_pub_before', title: __('Pool_pub_before'),  operate: false},
                        // {field: 'pool_pub_diff', title: __('Pool_pub_diff'),  operate: false},
                        // {field: 'pool_per_after', title: __('Pool_per_after'),  operate: false},
                        // {field: 'pool_per_before', title: __('Pool_per_before'),  operate: false},
                        // {field: 'pool_per_diff', title: __('Pool_per_diff'),  operate: false},
                        // {field: 'box_index', title: __('Box_index'), operate: false},
                        {field: 'create_time', title: __('Create_time'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'aa_egg_demo_log.create_time', title: __('Create_time'), visible: false, operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
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