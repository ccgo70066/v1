define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/noble/index' + location.search,
                    add_url: 'user/noble/add',
                    edit_url: 'user/noble/edit',
                    del_url: 'user/noble/del',
                    multi_url: 'user/noble/multi',
                    import_url: 'user/noble/import',
                    table: 'user_noble',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'end_time',
                fixedColumns: true,
                fixedRightNumber: 1,
                search: false,
                columns: [
                    [
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.nickname', title: __('用户昵称'), operate: false},
                        {field: 'noble.badge', title: __('徽章图'),  events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false},
                        {field: 'noble_id', title: __('贵族名称'),visible:false,searchList: $.getJSON('prop/noble/search_list')},
                        {field: 'noble.name', title: __('贵族名称'), operate: false},

                        {field: 'is_expire', title: __('超出保护期'), searchList: {"1":__('是'),"2":__('否')}, formatter: Table.api.formatter.normal},
                        {field: 'update_time', title: __('购买 / 续费时间'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'start_time', title: __('Start_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'end_time', title: __('过期时间'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime,sortable: true},
                        {field: 'union_room_hide', title: __('房间隐身'), searchList: {"0":__('无权限'),"1":__('开启'),"2":__('未开启')}, formatter: Table.api.formatter.normal},
                        {field: 'name_color', title: __('炫彩昵称'), searchList: {"0":__('无权限'),"1":__('开启'),"2":__('未开启')}, formatter: Table.api.formatter.normal},

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
