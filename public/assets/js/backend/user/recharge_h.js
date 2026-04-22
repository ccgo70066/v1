define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/recharge_h/index' + location.search,
                    table: 'user_recharge',
                }
            });

            var table = $("#table");
            var total_count = 0;
            table.on('load-success.bs.table', function (e, data) {
                // for (let value in data.extend) {
                //     $("#"+value).text(data.extend[value]);
                // }
                total_count = data.total;
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                search: false,
                showExport: false,
                showColumns: false,
                showToggle: false,
                showSearch: false,
                columns: [
                    [
                        /* {checkbox: true},
                         {field: 'id', title: __('Id')},*/
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'trade_no', title: __('Trade_no'), operate: 'LIKE'},
                        {field: 'user_id', title: __('用户ID')},
                        {field: 'user.nickname', title: __('User_id'), operate: false},
                        {field: 'pay_amount', title: __('Pay_amount'), operate:'BETWEEN'},
                        {field: 'amount', title: __('Amount')},
                        {field: 'give_amount', title: __('额外赠送钻石')},
                        // {field: 'payway', title: __('Payway'), operate: 'LIKE'},
                        //{field: 'currency', title: __('Currency'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"3":__('Status 3'),"4":__('Status 4'),"5":__('Status 5')}, formatter: Table.api.formatter.status},
                        {field: 'ip', title: __('Ip'), operate: 'LIKE'},
                        {field: 'appid', title: __('Appid'), operate: false},
                        // {field: 'user_recharge.appid', title: __('Appid'), operate: 'LIKE'},
                        {field: 'system', title: __('System'), searchList: {"1":__('System 1'),"2":__('System 2'),"3":__('System 3'),"4":__('System 4')}, formatter: Table.api.formatter.normal, operate: false},
                        {field: 'user_recharge.system', visible:false, title: __('System'), searchList: {"1":__('System 1'),"2":__('System 2'),"3":__('System 3'),"4":__('System 4')}, formatter: Table.api.formatter.normal},
                        {field: 'profit_status', title: __('Profit_status'), searchList: {"0":__('Profit_status 0'),"1":__('Profit_status 1')}, formatter: Table.api.formatter.status},
                        {field: 'is_reorder', title: __('补单'), searchList: {"0":__('否'),"1":__('是')}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        // {field: 'way_code', title: __('支付方式'), operate: false},
                        {field: 'way_code_name', title: __('支付方式'), operate: false},
                        {field: 'company', title: __('支付公司'), operate: false},
                        {field: 'company_code', title: __('支付公司'), visible: false, searchList: $.getJSON('user/recharge/get_pay_company'), operate: '='},
                        {field: 'open_way', title: __('调用方式'), operate: '=',searchList: {"H5i":__('内置浏览器H5'),"H5":__('外置浏览器H5'),"SDK":__('SDK')}},
                        {field: 'pay_channel', title: __('支付渠道'), operate: false},
                        {field: 'pay_channel_id', title: __('支付渠道'), visible: false,searchList: $.getJSON('user/recharge/get_pay_channel'), operate: '='},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'delete',
                                    text: '删除订单',
                                    title: '删除订单',
                                    classname: 'btn btn-xs btn-warning btn-magic btn-ajax',
                                    url: 'user/recharge_h/delete',
                                    confirm: '你确认操作删除该订单吗?',
                                    success: function (data, ret) {
                                        $(".btn-refresh").trigger("click");
                                    },
                                }
                            ]}
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
                params_location = {filter: filter, op: op};
                $('#price_sum').text('0');
                $('#union_reward').text('0');
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
                if (params_location == undefined) {
                    data = {
                        total_count: total_count,
                    };
                } else {
                    data = {
                        filter: JSON.stringify(params_location.filter),
                        op: JSON.stringify(params_location.op),
                        total_count: total_count,
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
                console.log(123);
                Form.api.bindevent($("form[role=form]"));
            },
            hide_user_id: function () {
                if (Config.isset_user) {
                    return false;
                } else {
                    return '=';
                }
            }
        }
    };
    return Controller;
});
