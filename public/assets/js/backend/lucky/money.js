define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'lucky/money/index' + location.search,
                    add_url: 'lucky/money/add',
                    edit_url: 'lucky/money/edit',
                    del_url: 'lucky/money/del',
                    multi_url: 'lucky/money/multi',
                    detail_url: 'lucky/money/index',
                    table: 'lucky_money',
                }
            });

            var table = $("#table");
            table.on('post-body.bs.table',function (){
                $('.btn-add').data('area',['1000px','600px']);
                $('.btn-editone').data('area',['1000px','600px']);

            });
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                search: false,
                columns: [
                    [
                        // {checkbox: true},
                        {field: 'id', title: __('红包编号'),operate: false},
                        {field: 'open_time', title: __('红包雨开始时间'), operate:'RANGE', addclass:'datetimerange'},
                        {field: 'amount', title: __('Amount'),operate: false},
                        {field: 'remain_amount', title: __('剩余红包'),operate: false},
                        {field: 'min_amount', title: __('单次最小金额'),operate: false},
                        {field: 'max_amount', title: __('单次最大金额'),operate: false},
                        {field: 'max_count', title: __('max_count'),operate: false},
                        // {field: 'count', title: __('Count'), operate: false},
                        // {field: 'open_count', title: __('Open_count'), operate: false},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: __('Create_time'), operate:false, addclass:'datetimerange'},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons:[
                                {
                                    name: 'send',
                                    text: __('手动发放'),
                                    title: __('手动发放'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    url: 'lucky/money/send',
                                    confirm: function (row) {
                                        return '确认要立即发放这个红包雨吗'
                                    },
                                    visible: function (row) {
                                        if (row.status == 0) return true;
                                        return false;
                                    },
                                    success: function (data, ret) {
                                        $(".btn-refresh").trigger("click");
                                    },
                                    error: function (data, ret) {
                                        return false;
                                    }
                                },
                            ],
                            formatter: Table.api.formatter.operate}
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
