define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'room/statistic/index' + location.search,
                    add_url: 'room/statistic/add',
                    edit_url: 'room/statistic/edit',
                    del_url: 'room/statistic/del',
                    multi_url: 'room/statistic/multi',
                    detail_url: 'room/statistic/index',
                    table: 'room_statistic',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'room_statistic.room_id',
                sortOrder: 'asc',
                search: false,
                columns: [
                    [
                        // {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        // {field: 'date', title: __('Date'), operate:'RANGE', addclass:'datetimerange', data:'data-time-picker="true"'},
                        {field: 'room_id', title: __('Room_id')+'ID', sortable: true},
                        {field: 'room.name', title: __('Room_id')+'名称', operate:false},
                        {field: 'egg_used', title: __('Egg_used'), operate: false},
                        {field: 'egg_gain', title: __('Egg_gain'), operate: false},
                        {field: 'percent', title: __('火之预言爆率'), operate: false,
                            formatter: function (value, row, index) {
                                if (row.egg_used == 0) {
                                    return 0;
                                }
                                return (row.egg_gain / row.egg_used).toFixed(4);
                            }},
                        {field: 'percent', title: __('火之预言盈亏'), operate: false,
                            formatter: function (value, row, index) {
                                let number = (row.egg_used - row.egg_gain * 0.85).toFixed(2);
                                let color=number>=0?'green': 'red';
                                return '<span style="color: '+color+'">'+number+'</span>';
                            }},
                        {field: 'wheel_used', title: __('Wheel_used'), operate: false},
                        {field: 'wheel_gain', title: __('Wheel_gain'), operate: false},
                        {field: 'percent', title: __('宝藏之旅爆率'), operate: false,
                            formatter: function (value, row, index) {
                                if (row.wheel_used == 0) {
                                    return 0;
                                }
                                return (row.wheel_gain / row.wheel_used).toFixed(4);
                            }},
                        {field: 'percent', title: __('宝藏之旅盈亏'), operate: false,
                            formatter: function (value, row, index) {
                                let number = (row.wheel_used - row.wheel_gain * 0.85).toFixed(2);
                                let color=number>=0?'green': 'red';
                                return '<span style="color: '+color+'">'+number+'</span>'; 
                            }},
                        
                        {field: 'total_used', title: __('total_used'), operate: false, sortable: true},
                        {field: 'total_gain', title: __('total_gain'), operate: false, sortable: true},
                        {field: 'percent', title: __('总爆率'), operate: false,
                            formatter: function (value, row, index) {
                                if (row.total_used == 0) {
                                    return 0;
                                }
                                return (row.total_gain / row.total_used).toFixed(4);
                            }},
                        {field: 'percent', title: __('总盈亏'), operate: false,
                            formatter: function (value, row, index) {
                                let number = (row.total_used - row.total_gain * 0.85).toFixed(2);
                                let color=number>=0?'green': 'red';
                                return '<span style="color: '+color+'">'+number+'</span>';
                            }}, 
                        
                        
                        // {field: 'active', title: __('Active'), searchList: {"1":__('Active 1'),"0":__('Active 0')}, formatter: Table.api.formatter.normal},
                        {field: 'create_time', title: __('Create_time'), operate:false, addclass:'datetimerange', data:'data-time-picker="true"'},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', data:'data-time-picker="true"'},
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
