define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/gift_log/index' + location.search,
                    total_url: 'user/gift_log/total' + location.search,//统计
                    table: 'gift_log',
                }
            });

            var table = $("#table");
            table.on('load-success.bs.table', function (e, data) {
                // for (let value in data.extend) {
                //     $("#" + value).text(data.extend[value]);
                // }
            });
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                // sortName: 'id',
                search: false,
                columns: [
                    [
                        // {checkbox: true},
                        {field: 'id', title: __('Id'), visible: false, operate: false},
                        {
                            field: 'room_id', title: __('Room_id'), formatter: function (value) {
                                if (value == 0) return '-';
                                return value;
                            }
                        },
                        {
                            field: 'gift_id',
                            title: '礼物',
                            operate: '=',
                            searchList: $.getJSON('prop/gift/search_list'),
                            sortOrder:'price desc',
                            formatter: function (value, row, index) {
                                return row.gift.name;
                            }
                        },
                        {field: 'gift.image', title: '礼物图片', formatter: Table.api.formatter.image, operate: false},
                        {field: 'count', title: '礼物数量', operate: false},
                        {field: 'gift_val', title: __('Gift_val'), operate: false},
                        // {field: 'price_type', title: __('Price_type'), searchList: {"1":__('Price_type 1'),"2":__('Price_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'user_id', title: __('送礼人ID')},
                        {field: 'givers.nickname', title: __('Givers.nickname'), operate: false},
                        {field: 'to_user_id', title: __('收礼人ID')},
                        {field: 'receivers.nickname', title: __('Receivers.nickname'), operate: false},
                        {
                            field: 'type',
                            title: __('Type'),
                            searchList: {"1": __('Type 1'),"2": __('Type 2'),"3": __('Type 3'), "4": __('Type 4')},
                            formatter: Table.api.formatter.normal
                        },
                        { field: 'room_reward_rate', title: __('厅主收益率'), formatter: function (value, row, index) {
                                return value * 100 + '%';
                            },operate: false},
                        {
                            field: 'create_time',
                            title: __('送礼时间'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime,
                            data: 'data-time-picker="true"'
                        },
                        // {field: 'update_time', title: __('Update_time'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

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
