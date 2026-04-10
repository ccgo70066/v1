define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'egg/intact_log/index' + location.search,
                    sum_url: 'egg/intact_log/sum' + location.search,
                    add_url: 'egg/intact_log/add',
                    // edit_url: 'egg/intact_log/edit',
                    // del_url: 'egg/intact_log/del',
                    multi_url: 'egg/intact_log/multi',
                    detail_url: 'egg/intact_log/index',
                    table: 'egg_intact_log',
                }
            });

            var table = $("#table");
            var total_count;
            table.on('load-success.bs.table', function (e, data) {
                // for (let value in data.extend) {
                //     $("#"+value).text(data.extend[value]);
                // }

                $('#total_count').text(data.total);
                total_count = data.total;
            });
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'egg_intact_log.id',
                sortName: 'egg_intact_log.id',
                columns: [
                    [
                        // {checkbox: true},
                        {field: 'id', title: __('Id'), visible: false, operate: false},
                        {field: 'user_id', title: __('User_id') + 'ID', operate: '='},
                        {field: 'user.nickname', title: __('用户昵称'), operate: false},
                        {field: 'user.actor_status', title: __('Actor_status'), searchList: {"1": __('Actor_status 1'), "2": __('Actor_status 2'), "3": __('Actor_status 3')}, formatter: Table.api.formatter.normal},
                        {field: 'level_name', title: __('Level_name'), operate: '=', searchList: $.getJSON('egg/level/index/option/search_list?key=name&name=name')},
                        {field: 'box_type', title: __('Box_type'), searchList: {"1": __('Box_type 1'), "2": __('Box_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'count_type', title: __('Count_type'), searchList: {"1": __('Count_type 1'), "10": __('Count_type 10'), "100": __('Count_type 100')}, formatter: Table.api.formatter.normal},
                        {field: 'content', title: __('content'), operate: false},
                        {
                            field: 'gift_other', title: __('雷霆'), operate: false,
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: '雷霆一击',
                                    title: '雷霆一击',
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-list',
                                    extend: 'data-toggle="tooltip"',
                                    url: function (row, value, index) {
                                        return 'egg/intact_log/detail?ids=' + row.id;
                                    },
                                    visible: function (row) {
                                        if (row.gift_other) return true;
                                    },
                                }
                            ],
                            formatter: Table.api.formatter.buttons,
                        },
                        {field: 'amount', title: __('Amount'), operate: 'BETWEEN'},
                        {field: 'use_amount', title: __('Use_amount'), operate: false},
                        {
                            field: 'use_amount', title: __('差值'), operate: false,
                            formatter: function (value, row, index) {
                                return (row.use_amount - row.amount * 0.85).toFixed(2);
                            }
                        },

                        {field: 'room_id', title: __('Room_id')},

                        {field: 'ip', title: __('登录ip'), operate: '='},
                        {field: 'ip_count', title: __('数量'), operate: false},
                        {field: 'imei', title: __('登录imei'), operate: false},
                        {field: 'egg_intact_log.imei', title: __('登录imei'), visible: false, operate: '='},
                        {field: 'imei_count', title: __('数量'), operate: false},

                        {field: 'create_time', title: __('Create_time'), operate: 'RANGE', addclass: 'datetimerange', data: 'data-time-picker="true"', defaultValue: Controller.api.getDay(-7)},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange'},
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
                params_location = {filter: filter, op: op};
                $('#total_used_amount').text('0');
                $('#total_gian_amount').text('0');
                $('#percent').text('0');
            });

            $(document).on("click", ".btn-sum", function () {
                if (params_location == undefined) {
                    Toastr.error('请先提交汇总条件后再进行汇总操作');
                    return;
                }
                if (total_count > 5000000) {
                    Toastr.error('汇总范围过大,请缩小汇总条件减小汇总范围 次数控制在5m以内');
                    return;
                }
                var parenttable = table.closest('.bootstrap-table');
                var options = table.bootstrapTable('getOptions');
                var ids = Table.api.selectedids(table);
                var url = options.extend.sum_url;
                if (url.indexOf("{ids}") !== -1) {
                    url = Table.api.replaceurl(url, {ids: ids.length > 0 ? ids.join(",") : 0}, table);
                }
                // url += '&filter=' + JSON.stringify(params_location.filter);
                // url += '&op=' + JSON.stringify(params_location.op);
                var data = {
                    filter: JSON.stringify(params_location.filter),
                    op: JSON.stringify(params_location.op)
                };
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
        detail: function () {
            Controller.api.bindevent();
        },
        sum: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            getDay: function (day) {
                let date = new Date();
                let dateStr = date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate();
                let start = new Date();
                let seconds = start.getTime() + 1000 * 60 * 60 * 24 * (day + 1);
                start.setTime(seconds);
                let startStr = start.getFullYear() + '-' + (start.getMonth() + 1) + '-' + start.getDate();
                return startStr + ' 00:00:00 - ' + dateStr + ' 23:59:59';
            },
            formatter: {
                browser: function (value, row, index) {
                    //这里我们直接使用row的数据
                    if (value)
                        return '<a class="btn btn-xs btn-browser" url="example/bootstraptable/detail">' + '一击' + '</a>';
                },
            },
            events: {//绑定事件的方法
                browser: {
                    'click .btn-browser': function (e, value, row, index) {
                        e.stopPropagation();
                        Layer.alert("该行数据为: <code>" + JSON.stringify(row.gift_other) + "</code>");
                    }
                },
            }
        }
    };
    return Controller;
});
