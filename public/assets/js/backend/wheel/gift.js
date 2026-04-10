define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'wheel/gift/index/box_type/'+Fast.api.query('box_type') + location.search,
                    add_url: 'wheel/gift/add/box_type/'+Fast.api.query('box_type'),
                    edit_url: 'wheel/gift/edit/box_type/'+Fast.api.query('box_type'),
                    del_url: 'wheel/gift/del',
                    multi_url: 'wheel/gift/multi',
                    detail_url: 'wheel/gift/index',
                    table: 'wheel_gift',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'gift.price',
                sortOrder: 'asc',
                searchFormVisible: false,
                columns: [
                    [
                        {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        // // {field: 'box_type', title: __('Box_type'), searchList: {"1":__('Box_type 1'),"2":__('Box_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'gift.id', title: __('Gift_id')+'ID', operate: false},
                        {field: 'gift.image', title: __('Gift_id')+'图', operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'gift.name', title: __('Gift_id'), operate: false},
                        {field: 'gift.price', title: __('价格'), operate: false},
                        {field: 'broadcast', title: __('Broadcast'), searchList: {"1":__('Broadcast 1'),"0":__('Broadcast 0')}, formatter: Table.api.formatter.normal},
                        // {field: 'light_level', title: __('Light_level'), searchList: {"0":__('Light_level 0'),"1":__('Light_level 1'),"2":__('Light_level 2')}, formatter: Table.api.formatter.normal},
                        // {field: 'voice', title: __('Voice'), searchList: {"0":__('Voice 0'),"1":__('Voice 1'),"2":__('Voice 2')}, formatter: Table.api.formatter.normal},
                        {field: 'show_again', title: __('Show_again'), searchList: {"0":__('Show_again 0'),"1":__('Show_again 1')}, formatter: Table.api.formatter.normal},
                        {field: 'show_rate', title: __('万分比备注'), operate: false},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            $('#c-gift_id').change(function () {
               $('#c-master_gift_id').val($('#c-gift_id').val());
            });
            Controller.api.bindevent();
        },
        edit: function () {
            $('#c-gift_id').change(function () {
                $('#c-master_gift_id').val($('#c-gift_id').val());
            });
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
