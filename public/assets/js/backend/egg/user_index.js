define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/user_index/index' + location.search,
                    add_url: 'egg/user_index/add',
                    edit_url: 'egg/user_index/edit',
                    // del_url: 'egg/user_index/del',
                    multi_url: 'egg/user_index/multi',
                    // detail_url: 'egg/user_index/index',
                    table: 'egg_user_index',
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
                sortName: 'egg_user_index.id',
                showExport: true,
                exportTypes: ['excel'],
                columns: [
                    [
                        // {checkbox: true},
                        {field: 'id', title: __('Id'),visible:false,operate: false},
                        {field: 'user_id', title: __('User_id'), operate: '='},
                        {field: 'user.nickname', title: __('用户')+'昵称', operate: false},
                        {field: 'user.actor_status', title: __('Actor_status'), searchList: {"1":__('Actor_status 1'),"2":__('Actor_status 2'),"3":__('Actor_status 3')}, formatter: Table.api.formatter.normal},
                        {field: 'box_type', title: __('Box_type'), searchList: {"1":__('Box_type 1'),"2":__('Box_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'count', title: __('Count'), operate: false},
                        {field: 'sys_10_count', title: __('Sys_10_count'), operate: false},
                        {field: 'sys_100_count', title: __('Sys_100_count'), operate: false},
                        // {field: 'demo_100_count', title: __('Demo_100_count'), operate: false},
                        {field: 'pool', title: __('Pool'), operate:'BETWEEN', operate: false},

                        {field: 'total_used', title: __('Total_used'), operate:  false},
                        {field: 'total_lucre', title: __('Total_lucre'), operate:  false},
                        {field: 'total_lucre', title: __('总爆率'), operate:  false, formatter: function(value, row, index){
                                if (row.total_lucre == '0.00' || row.total_used == '0.00') return '0%';

                                let percent=row.total_lucre/row.total_used*100;
                                return percent.toFixed(2)+'%';
                            }},
                        {field: 'total_lucre', title: __('总爆率差值'), operate:  false,
                            formatter: function (value, row, index) {
                                let diff = row.total_used * 1.1764 - row.total_lucre;
                                let color = diff > 0 ? 'black' : 'red';
                                return '<span class="text-' + color + '" >' + diff.toFixed(2) + '</span>';
                            }},
                        {field: 'total_lucre', title: __('总盈亏差值'), operate:  false, formatter: function(value, row, index){
                                let percent = row.total_lucre * 0.85 - row.total_used;
                                return percent.toFixed(2);
                            }},
                        {field: 'today_used', title: __('Today_used'), operate:  false},
                        {field: 'today_lucre', title: __('Today_lucre'), operate:  false},
                        {field: 'today_lucre', title: __('今日爆率'), operate:  false, formatter: function(value, row, index){
                                if (row.today_lucre == '0.00' || row.today_used == '0.00') return '0%';
                                let percent=row.today_lucre/row.today_used*100;
                                return percent.toFixed(2)+'%';
                            }},
                        {field: 'total_lucre', title: __('今日爆率差值'), operate:  false,
                            formatter: function (value, row, index) {
                                let diff = row.today_used * 1.1764 - row.today_lucre;
                                let color = diff > 0 ? 'black' : 'red';
                                return '<span class="text-' + color + '" >' + diff.toFixed(2) + '</span>';
                            }},
                        {field: 'total_lucre', title: __('今日盈亏差值'), operate:  false, formatter: function(value, row, index){
                                let percent = row.today_lucre * 0.85 - row.today_used;
                                return percent.toFixed(2);
                            }},

                        {field: 'sys_100_hammer', title: __('sys_100_hammer'), operate: false},
                        {field: 'base_100_threshold', title: __('base_100_threshold'), operate: false},

                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', data:'data-time-picker="true"'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
