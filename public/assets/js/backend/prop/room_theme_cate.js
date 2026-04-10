define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'prop/room_theme_cate/index' + location.search,
                    add_url: 'prop/room_theme_cate/add',
                    edit_url: 'prop/room_theme_cate/edit',
                    // del_url: 'room/room_theme_cate/del',
                    multi_url: 'prop/room_theme_cate/multi',
                    detail_url: 'prop/room_theme_cate/index',
                    table: 'room_theme_cate',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                searchFormVisible:false,
                search: false,
                columns: [
                    [
                        // {checkbox: true},
                        {field: 'id', title: __('ID'), operate: false},
                        {field: 'name', title: __('Name'), operate: false},
                        {field: 'color', title: __('color'), operate: false},
                        // {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime, operate: false},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status,operate: false},
                        {field: 'weigh', title: __('Weigh'), operate: false},
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
