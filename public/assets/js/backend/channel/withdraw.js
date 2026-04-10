define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'channel/withdraw/index' + location.search,
                    add_url: 'channel/withdraw/add',
                    /*edit_url: 'user/withdraw/edit',
                    del_url: 'user/withdraw/del',*/
                    multi_url: 'channel/withdraw/multi',
                    import_url: 'channel/withdraw/import',
                    table: 'user_withdraw',
                }
            });

            var table = $("#table");
            table.on('load-success.bs.table', function (e, data) {
                for (let value in data.extend) {
                    $("#"+value).text(data.extend[value]);
                }
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'create_time',
                fixedColumns: true,
                fixedRightNumber: 1,
                search: false,
                columns: [
                    [
                       /* {checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'withdraw_no', title: __('提现订单号')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.nickname', title: __('用户昵称'),operate:false},
                        {field: 'account_name', title: __('提现账号'), table: table, events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'detail',
                                    text: function (row) {
                                        return row.account_name;
                                    },
                                    title: '详情',
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    extend: 'data-toggle="tooltip"',
                                    url: 'user/withdraw/detail',
                                },
                            ],
                            formatter: Table.api.formatter.operate,operate: false},
                        {field: 'amount', title: __('提现收益'), operate: false},
                        // {field: 'less_amount', title: __('剩余收益'), operate: false},
                        {field: 'fee', title: __('手续费(收益)'), operate: false},
                        {field: 'payment_amount', title: __('打款金额(元)'), operate: false},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"-1":__('Status -1'),"-2":__('Status -2')}, formatter: Table.api.formatter.status},
                        {field: 'payment.name', title: __('Payment_way_id'), operate:false},
                        {field: 'payment_fee', title: __('Payment_fee'), operate:false},
                        {field: 'operate.username', title: __('Operate_id'),operate: false},
                        {field: 'operate_time', title: __('Operate_time'), operate: false, addclass:'datetimerange', autocomplete:false},
                        {field: 'finance.username', title: __('Finance_id'),operate: false},
                        // {field: 'finance_time', title: __('Finance_time'), operate:false, addclass:'datetimerange', autocomplete:false},
                        {field: 'finance_time', title: __('到账时间'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        //{field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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
        rebut: function () {
            Form.api.bindevent($("form[role=form]"),function(){},function(){},function(){

                Layer.confirm('敏感操作,请仔细核对和确认,确认进行吗?',{
                    title: '提示',
                    btn: ['确认无误','取消'] //按钮
                },function (index) {
                    Form.api.submit($("form[role=form]"),function (){
                        parent.Toastr.success('提交成功');
                        parent.$(".btn-refresh").trigger("click");
                        var index_top = parent.Layer.getFrameIndex(window.name);
                        parent.Layer.close(index_top);
                    },function (){},function () {
                        Layer.close(index);
                        // var index_top = parent.Layer.getFrameIndex(window.name);
                        // parent.Layer.close(index_top);
                    });
                },function (){
                });
                return false;
            });
        },
        examine: function () {
            Form.api.bindevent($("form[role=form]"),function(){},function(){},function(){

                Layer.confirm('敏感操作,请仔细核对和确认,确认进行吗?',{
                    title: '提示',
                    btn: ['确认无误','取消'] //按钮
                },function (index) {
                    Form.api.submit($("form[role=form]"),function (){
                        parent.Toastr.success('提交成功');
                        parent.$(".btn-refresh").trigger("click");
                        var index_top = parent.Layer.getFrameIndex(window.name);
                        parent.Layer.close(index_top);
                    },function (){},function () {
                        Layer.close(index);
                        // var index_top = parent.Layer.getFrameIndex(window.name);
                        // parent.Layer.close(index_top);
                    });
                },function (){
                });
                return false;
            });
        },
        payment: function () {
            Form.api.bindevent($("form[role=form]"),function(){},function(){},function(){

                Layer.confirm('敏感操作,请仔细核对和确认,确认进行吗?',{
                    title: '提示',
                    btn: ['确认无误','取消'] //按钮
                },function (index) {
                    Form.api.submit($("form[role=form]"),function (){
                        // parent.Toastr.success('提交成功');
                        parent.$(".btn-refresh").trigger("click");
                        var index_top = parent.Layer.getFrameIndex(window.name);
                        parent.Layer.close(index_top);
                    },function (){},function () {
                        Layer.close(index);
                        // var index_top = parent.Layer.getFrameIndex(window.name);
                        // parent.Layer.close(index_top);
                    });
                },function (){
                });
                return false;
            });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
