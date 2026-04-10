define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'prop/shop_item/index' + location.search,
                    add_url: 'prop/shop_item/add',
                    edit_url: 'prop/shop_item/edit',
                    del_url: 'prop/shop_item/del',
                    multi_url: 'prop/shop_item/multi',
                    import_url: 'prop/shop_item/import',
                    table: 'shop_item',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                fixedColumns: true,
                fixedRightNumber: 1,
                search: false,
                columns: [
                    [
                        /*{checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'type', title: __('Type'), searchList: { "2": __('Type 2'), "3": __('Type 3'), "6": __('Type 6'), "8": __('Type 8')}, formatter: Table.api.formatter.normal},
                        {field: 'item_id', title: __('Item_id')},
                        {field: 'price', title: __('Price'), operate: false, formatter: function (value, row, index) {
                                return parseInt(value);
                            }},
                        {field: 'days', title: __('Days'),operate: false,formatter: function (value, row, index) {
                                if (row.days ==-1) return '<span class="label label-primary">永久</span>';
                                return row.days;
                            }},
                        {field: 'show', title: __('Show'), searchList: {"1":__('Show 1'),"2":__('Show 2')}, operate:'FIND_IN_SET', formatter: Table.api.formatter.label},
                        {field: 'weigh', title: __('Weigh'), operate: false,sortable: true},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status},
                        // {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false,sortable: true},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {

            $('#c-type').on('change', function () {
                $("#c-item_id").selectPageClear();
                $('#c-price').attr('placeholder', '');
            });



            $('#c-item_id').data('params', function () {
                const type = $('#c-type').val();
                return {custom: {type: type}};
            });
            $('#c-item_id').on('change', function () {
                var type = $('#c-type').val();
                var id = $('#c-item_id').val();

                $.ajax({
                    type: 'GET',
                    url: 'prop/shop_item/get_item_name',//查询
                    data: {type: type, id: id},
                    async: false,
                    success: function (data) {
                        if (data != false) {
                            $('#c-name').val(data.name);
                            $('#c-name_en').val(data.name_en);
                        }
                    },
                });
            });
            Controller.api.bindevent();
        },
        edit: function () {

            $('#c-type').on('change', function () {
                $("#c-item_id").selectPageClear();
                $('#c-price').attr('placeholder', '');
            });



            $('#c-item_id').data('params', function () {
                const type = $('#c-type').val();
                return {custom: {type: type}};
            });
            $('#c-item_id').on('change', function () {
                var type = $('#c-type').val();
                var id = $('#c-item_id').val();

                $.ajax({
                    type: 'GET',
                    url: 'prop/shop_item/get_item_name',//查询
                    data: {type: type, id: id},
                    async: false,
                    success: function (data) {
                        if (data != false) {
                            $('#c-name').val(data.name);
                            $('#c-name_en').val(data.name_en);
                        }
                    },
                });
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
