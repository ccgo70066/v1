define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'shop/order/index' + location.search,
                    total_url: 'shop/order/total' + location.search,
                    add_url: 'shop/order/add',
                    edit_url: 'shop/order/edit',
                    del_url: 'shop/order/del',
                    multi_url: 'shop/order/multi',
                    import_url: 'shop/order/import',
                    table: 'shop_order',
                }
            });

            var table = $("#table");

            table.on('load-success.bs.table', function (e, data) {
                // for (let value in data.extend) {
                //     $("#"+value).text(data.extend[value]);
                // }
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                search: false,
                columns: [
                    [
                       /* {checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.nickname', title: __('用户昵称'),operate: false},
                        {field: 'shop.name', title: __('Item_id'),operate: false},
                        {field: 'shop.type', title: __('Item_type'), searchList: {"2":__('Item_type 2'),"3":__('Item_type 3'),"4":__('Item_type 4'),"6":__('Item_type 6'),}, formatter: Table.api.formatter.normal},
                        // {field: 'price', title: __('Price'), operate: false},
                        {field: 'price', title: __('数量'), operate: false},
                        // {field: 'count', title: __('Count'), operate: false},
                        {field: 'count', title: __('数额'), operate: false},
                        {field: 'amount', title: __('Amount'), operate: false},
                        {field: 'orig_amount', title: __('Orig_amount'), operate: false},
                        // {field: 'diss_amount', title: __('Diss_amount'), operate: false},
                        {field: 'diss_amount', title: __('优惠'), operate: false},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.normal},
                        {field: 'system', title: __('System'), searchList: {"1":__('System 1'),"2":__('System 2')}, formatter: Table.api.formatter.normal},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                       /* {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}*/
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            //统计
            var params_location;
            table.on('common-search.bs.table', function (event, table, params, query) {
                let filter = {}, op = {};
                $.each(params.filter, function (index, value, array) {
                    if (value) {
                        filter[index] = value;
                        op[index] = params.op[index];
                    }
                });
                params_location={filter: filter, op: op};
                $('#price_sum').text('0');
                $('#union_reward').text('0');
            });

            $(document).on("click", "#sum_total_click", function () {
                var loading = layer.load();
                var parenttable = table.closest('.bootstrap-table');
                var options = table.bootstrapTable('getOptions');
                var ids = Table.api.selectedids(table);
                var url = options.extend.total_url;
                if (url.indexOf("{ids}") !== -1) {
                    url = Table.api.replaceurl(url, {ids: ids.length > 0 ? ids.join(",") : 0}, table);
                }
                var data = {};
                if (params_location == undefined) {
                    data = {};
                } else {
                    data = {
                        filter: JSON.stringify(params_location.filter),
                        op: JSON.stringify(params_location.op),
                    };
                }

                $.ajax({
                    url: url,
                    type: 'get',
                    data: data,
                    success: function (r) {
                        for (let value in r) {
                            $("#" + value).text(r[value]);
                        }
                        Toastr.success('汇总操作成功');
                        layer.close(loading);
                    },
                    error: function (xhr) {
                        layer.close(loading);
                    }
                });
            });

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
