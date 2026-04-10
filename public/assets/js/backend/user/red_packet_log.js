define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/red_packet_log/index' + location.search,
                    total_url: 'user/red_packet_log/total' + location.search,//统计
                    add_url: 'user/red_packet_log/add',
                    edit_url: 'user/red_packet_log/edit',
                    del_url: 'user/red_packet_log/del',
                    multi_url: 'user/red_packet_log/multi',
                    import_url: 'user/red_packet_log/import',
                    table: 'red_packet_log',
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
                        /*{checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.nickname', title: __('发送者昵称'),operate: false},
                        {field: 'to_user_id', title: __('To_user_id')},
                        {field: 'touser.nickname', title: __('接受者昵称'),operate: false},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'service_charge', title: __('Service_charge'), operate: false},
                        {field: 'amount', title: __('金幣'), operate: false},
                        {field: 'remarks', title: __('Remarks'), operate: false},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        // {field: 'update_time', title: __('领取时间'), operate: false, addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            // 统计
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
                $('#amount_sum').text('0');
                $('#service_charge_sum').text('0');
            });

            $(document).on("click", "#sum_total_click", function () {
                var loading = layer.load();
                // if (params_location == undefined) {
                //     Toastr.error('请先提交汇总条件后再进行汇总操作');
                //     return;
                // }
                // if (total_count > 5000000) {
                //     Toastr.error('汇总范围过大,请缩小汇总条件减小汇总范围 次数控制在5m以内');
                //     return;
                // }
                var parenttable = table.closest('.bootstrap-table');
                var options = table.bootstrapTable('getOptions');
                var ids = Table.api.selectedids(table);
                var url = options.extend.total_url;
                if (url.indexOf("{ids}") !== -1) {
                    url = Table.api.replaceurl(url, {ids: ids.length > 0 ? ids.join(",") : 0}, table);
                }
                var data = {};
                if (params_location != undefined) {
                    data = {
                        filter: JSON.stringify(params_location.filter),
                        op: JSON.stringify(params_location.op)
                    };
                }

                $.ajax({
                    url: url,
                    type: 'get',
                    data: data,
                    success: function (r) {
                        console.info(r);
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
