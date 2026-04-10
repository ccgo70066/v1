define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/level/index/box_type/'+Fast.api.query('box_type') + location.search,
                    add_url: 'egg/level/add/box_type/'+Fast.api.query('box_type'),
                    edit_url: 'egg/level/edit/box_type/'+Fast.api.query('box_type'),
                    // del_url: 'egg/level/del',
                    multi_url: 'egg/level/multi',
                    // detail_url: 'egg/level/index',
                    table: 'egg_level',
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
                        {field: 'name', title: __('Name')},
                        {field: 'box_type', title: __('Box_type'), searchList: {"1":__('Box_type 1'),"2":__('Box_type 2'),}, formatter: Table.api.formatter.normal},
                        {field: 'weigh', title: __('Weigh')},
                        // {field: 'range_start', title: __('Range_start')},
                        // {field: 'range_end', title: __('Range_end')},
                        {field: 'upgrade_gift_text', title: __('upgrade_gift_ids'), formatter: Table.api.formatter.label},
                        {field: 'pool_sys_percent', title: __('Pool_sys_percent')},
                        {field: 'pool_pubn_percent', title: __('Pool_pubn_percent')},
                        {field: 'pool_pub_percent', title: __('Pool_pub_percent')},
                        {field: 'pool_per_percent', title: __('Pool_per_percent')},
                        {field: 'or_data', title: __('Or_data'), searchList: {"1":__('Or_data 1'),"2":__('Or_data 2'),"3":__('Or_data 3')}, formatter: Table.api.formatter.normal},
                        {field: 'jump_data', title: __('Jump_data'), searchList: {"1":__('Jump_data 1'),"2":__('Jump_data 2'),"3":__('Jump_data 3'),"4":__('Jump_data 4')}, operate:'FIND_IN_SET', formatter: Table.api.formatter.label},
                        // {field: 'b_pool_sys_percent', title: __('b_pool_sys_percent')},
                        // {field: 'b_pool_pub_percent', title: __('b_pool_pub_percent')},
                        // {field: 'b_pool_per_percent', title: __('b_pool_per_percent')},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', data: 'data-time-picker="true"'},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange'},
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
