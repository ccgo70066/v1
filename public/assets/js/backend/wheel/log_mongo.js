define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'wheel/log_mongo/index' + location.search,
                    sum_url: 'wheel/log_mongo/sum' + location.search,
                    detail_url: 'wheel/log_mongo/index',
                    table: 'wheel_log',
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
                pk: '_id',
                search:false,
                showExport:false,
                showColumns:false,
                showToggle:false,
                showSearch: false,
                columns: [
                    [
                        // {checkbox: true},
                        // {field: 'aa_wheel_log.id', title: __('Id'), formatter: function (value, row, index) { return row.id; }},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'nickname', title: __('用户昵称'), operate: false},

                        {field: 'box_type', title: __('Box_type'), searchList: {"1":__('Box_type 1'),"2":__('Box_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'count_type', title: __('Count_type'), searchList: {"1":__('Count_type 1'),"10":__('Count_type 10'),"100":__('Count_type 100')}, formatter: Table.api.formatter.normal},
                        {field: 'log.level_id', title: __('Level_id'), operate: '=', searchList: $.getJSON('wheel/log_mongo/index/option/load_level/'), formatter: function (value, row, index) {
                                return row.level_name;
                            }},
                        {field: 'used_amount', title: __('Used_amount'), operate: false, formatter: function (value, row, index) {
                                return value;
                            }},
                        {field: 'log.gift_id', title: __('礼物'), searchList: $.getJSON('wheel/log_mongo/index/option/load_gift'), formatter: function (value, row, index) {
                                return row.gift_name;
                            }},
                        {field: 'log.gift_value', title: __('礼物价值'), operate: false},
                        // {field: 'count', title: __('数量'), operate: false},
                        {field: 'room_id', title: __('Room_id')},
                        {field: 'log.box_index', title: __('序号'), operate: false},
                        {field: 'log.weigh_name', title: __('Weigh_name'), operate: '=', searchList: $.getJSON('wheel/log_mongo/index/option/load_weigh')},
                        {field: 'log.jump_status', title: __('Jump_status'), searchList: {0:__('Jump_status 0'),1:__('Jump_status 1'),2:__('Jump_status 2'),3:__('Jump_status 3'),4:__('Jump_status 4'),5:__('Jump_status 5'),6:__('Jump_status 6')}, formatter: Table.api.formatter.status, operate: '='},
                        {field: 'log.pool_sys_before', title: __('Pool_sys_before'),  operate: false},
                        {field: 'log.pool_sys_after', title: __('Pool_sys_after'),  operate: false},
                        {field: 'log.pool_sys_diff', title: __('Pool_sys_diff'),  operate: false},
                        {field: 'log.pool_pub_before', title: __('Pool_pub_before'),  operate: false, sortable: true},
                        {field: 'log.pool_pub_after', title: __('Pool_pub_after'),  operate: false},
                        {field: 'log.pool_pub_diff', title: __('Pool_pub_diff'),  operate: false},
                        {field: 'log.pool_per_before', title: __('Pool_per_before'),  operate: false},
                        {field: 'log.pool_per_after', title: __('Pool_per_after'),  operate: false},
                        {field: 'log.pool_per_diff', title: __('Pool_per_diff'),  operate: false},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime, data: 'data-time-picker="true"',  data: 'data-time-picker="true"'},

                        //
                        // {field: 'ip', title: __('登录ip'),  operate: '='},
                        // {field: 'ip_count', title: __('ip数量'),  operate: false},
                        // {field: 'imei', title: __('登录imei'),  operate: '='},
                        // {field: 'imei_count', title: __('imei数量'),  operate: false},
                        //
                        // {field: 'create_time', title: __('Create_time'), operate:false, addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        // {field: 'create_time', title: __('Create_time'), visible: false, operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime, data: 'data-time-picker="true"',  data: 'data-time-picker="true"'},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
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
                    // Toastr.error('汇总范围过大,请缩小汇总条件减小汇总范围 次数控制在5m以内');
                    // return;
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
                let seconds=start.getTime()+1000*60*60*24*(day+1);
                start.setTime(seconds);
                let startStr = start.getFullYear() + '-' + (start.getMonth() + 1) + '-' + start.getDate();
                return startStr + ' 00:00:00 - ' + dateStr + ' 23:59:59';
            }
        }
    };
    return Controller;
});
