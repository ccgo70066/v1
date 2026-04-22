define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/withdraw/index' + location.search,
                    total_url: 'user/withdraw/total' + location.search,
                    add_url: 'user/withdraw/add',
                    /*edit_url: 'user/withdraw/edit',
                    del_url: 'user/withdraw/del',*/
                    batch_examine_url: 'user/withdraw/batch_examine',
                    batch_payment_url: 'user/withdraw/batch_payment',
                    multi_url: 'user/withdraw/multi',
                    import_url: 'user/withdraw/import',
                    table: 'user_withdraw',
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
                sortName: 'create_time',
                fixedColumns: true,
                fixedRightNumber: 1,
                search: false,
                columns: [
                    [
                        {checkbox: true},
                        /*  {field: 'id', title: __('Id')},*/
                        {field: 'withdraw_no', title: __('提现订单号')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user_nickname', title: __('用户昵称'),operate:false},
                        {field: 'account_name', title: __('提现账号'), table: table, events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'detail',
                                    text: function (row) {
                                        return row.account_name?row.account_name:row.alipay_name;
                                    },
                                    title: '详情',
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    extend: 'data-toggle="tooltip" data-area=\'["55%", "95%"]\'',
                                    url: 'user/withdraw/detail',
                                },
                            ],
                            formatter: Table.api.formatter.operate,operate: false},
                        {field: 'amount', title: __('提现收益'), operate: false},
                        // {field: 'less_amount', title: __('剩余收益'), operate: false},
                        {field: 'fee', title: __('手续费'), operate: false},
                        {field: 'payment_amount', title: __('打款金额'), operate: false},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"-1":__('Status -1'),"-2":__('Status -2')}, formatter: Table.api.formatter.status},
                        {field: 'payment_name', title: __('Payment_way_id'), operate:false},
                        {field: 'payment_fee', title: __('Payment_fee'), operate:false},
                        {field: 'operate_username', title: __('Operate_id'),operate: false},
                        {field: 'operate_time', title: __('Operate_time'), operate: false, addclass:'datetimerange', autocomplete:false},
                        {field: 'finance_username', title: __('Finance_id'),operate: false},
                        // {field: 'finance_time', title: __('Finance_time'), operate:false, addclass:'datetimerange', autocomplete:false},
                        {field: 'finance_time', title: __('到账时间'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'create_time', title: __('Create_time'), operate: false, addclass:'datetimerange', autocomplete:false, sortable: true},
                        //{field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: '操作', table: table, events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'examine',
                                    text: '一审',
                                    title: '一审',
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    extend: 'data-toggle="tooltip"',
                                    icon: 'fa fa-check',
                                    url: 'user/withdraw/examine',
                                    visible: function (row) {
                                        return (row.status==0)?true:false;
                                    }
                                },
                                {
                                    name: 'payment',
                                    text: '打款',
                                    title: '打款',
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    extend: 'data-toggle="tooltip"',
                                    icon: 'fa fa-check',
                                    url: 'user/withdraw/payment',
                                    visible: function (row) {
                                        return row.status == 1 ? true : false;
                                    }
                                },
                                {
                                    name: 'rebut',
                                    text: '',
                                    title: '驳回',
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    extend: 'data-toggle="tooltip"',
                                    icon: 'fa fa-reply',
                                    url: 'user/withdraw/rebut',
                                    visible: function (row) {
                                        return (row.status==0 || row.status==1)?true:false;
                                    }
                                },

                            ],
                            formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            $(document).on("click", "#batch_examine", function () {
                var ids = Table.api.selectedids(table);
                var options = table.bootstrapTable('getOptions');
                var url = options.extend.batch_examine_url;
                if (url.indexOf("{ids}") !== -1) {
                    url = Table.api.replaceurl(url, {ids: ids.length > 0 ? ids.join(",") : 0}, table);
                }
                $.ajax({
                    url: url,
                    type: 'get',
                    data: {ids: ids},
                    success: function (data) {
                        Layer.msg(data.msg);
                        table.trigger("uncheckbox");
                        table.bootstrapTable('refresh');
                    },
                    error: function (xhr) {
                        Layer.msg('操作失败');

                    }
                });
            });
            // $(document).on("click", "#batch_payment", function () {
            //     var ids = Table.api.selectedids(table);
            //     var options = table.bootstrapTable('getOptions');
            //     var url = options.extend.batch_payment_url;
            //     if (url.indexOf("{ids}") !== -1) {
            //         url = Table.api.replaceurl(url, {ids: ids.length > 0 ? ids.join(",") : 0}, table);
            //     }
            //     $.ajax({
            //         url: url,
            //         type: 'get',
            //         data: {ids: ids},
            //         success: function () {
            //             Layer.msg('操作成功');
            //             table.trigger("uncheckbox");
            //             table.bootstrapTable('refresh');
            //         },
            //         error: function (xhr) {
            //             Layer.msg('操作失败');
            //
            //         }
            //     });
            // });

            $(document).on("click", "#batch_payment", function () {
                var ids = Table.api.selectedids(table);
                Layer.open({
                    type: 2,
                    title: '批量线下打款',
                    shadeClose: true,
                    shade: false,
                    maxmin: true, //开启最大化最小化按钮
                    area: ['800px', '400px'],
                    content: 'withdraw/batch_payment?ids='+ids,
                });
            });
            // 批量线下打款

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
            });

            $(document).on("click", "#sum_total_click", function () {
                var loading = layer.load();
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
        batch_payment: function () {
            Controller.api.bindevent();
        },
        detail: function () {
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
