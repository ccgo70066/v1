define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/config_per/index/box_type/'+Fast.api.query('box_type')+'/count_type/'+Fast.api.query('count_type') + location.search,
                    add_url: 'egg/config_per/add/box_type/'+Fast.api.query('box_type')+'/count_type/'+Fast.api.query('count_type'),
                    edit_url: 'egg/config_per/edit/box_type/'+Fast.api.query('box_type')+'/count_type/'+Fast.api.query('count_type'),
                    del_url: 'egg/config_per/del',
                    multi_url: 'egg/config_per/multi',
                    detail_url: 'egg/config_per/index',
                    table: 'egg_config_per',
                }
            });

            var table = $("#table");
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                searchFormVisible: false,
                columns: [
                    [
                        // {checkbox: true},
                        {field: 'id', title: __('Id'),visible:false,operate: false},
                        {field: 'title', title: __('Title')},
                        {field: 'box_type', title: __('Box_type'), searchList: {"1":__('Box_type 1'),"2":__('Box_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'count_type', title: __('Count_type'), searchList: {"10":__('Count_type 10'),"100":__('Count_type 100')}, formatter: Table.api.formatter.normal},

                        {field: 'level_name', title: __('Level_id'), operate: false},
                        {field: 'range_start', title: __('Range_start'), operate: false},
                        {field: 'range_end', title: __('Range_end'), operate: false},
                        {field: 'weigh_config', title: __('Config')+'', formatter: Table.api.formatter.label, operate: false},
                        {field: 'all', title: __('期望值'), operate: false},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status},
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
        set: function () {
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