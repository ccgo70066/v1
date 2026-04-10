define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/config_back/index/box_type/'+Fast.api.query('box_type')+'/count_type/'+Fast.api.query('count_type') + location.search,
                    add_url: 'egg/config_back/add/box_type/'+Fast.api.query('box_type')+'/count_type/'+Fast.api.query('count_type'),
                    edit_url: 'egg/config_back/edit/box_type/'+Fast.api.query('box_type')+'/count_type/'+Fast.api.query('count_type'),
                    del_url: 'egg/config_back/del',
                    multi_url: 'egg/config_back/multi',
                    detail_url: 'egg/config_back/index',
                    table: 'egg_config_back',
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
                        {field: 'box_type', title: __('Box_type'), searchList: {"1":__('Box_type 1'),"2":__('Box_type 2')}, formatter: Table.api.formatter.normal},
                        // {field: 'count_type', title: __('Count_type'), searchList: {"10":__('Count_type 10'),"100":__('Count_type 100')}, formatter: Table.api.formatter.normal},
                        {field: 'title', title: __('Title')},
                        // {field: 'level_name', title: __('Level_id')},
                        {field: 'range_start', title: __('Range_start'), operate: false},
                        {field: 'range_end', title: __('Range_end'), operate: false},
                        // {field: 'count', title: __('Count')},
                        {field: 'weigh_config', title: __('Config')+'', formatter: Table.api.formatter.label, operate: false},
                        {field: 'all', title: __('期望值'), operate: false},
                        {field: 'weigh', title: __('Weigh')},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange'},
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
