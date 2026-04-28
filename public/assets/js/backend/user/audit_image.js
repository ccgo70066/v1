define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/audit_image/index' + location.search,
                    audit_ok_url: 'user/audit_image/batch_audit/option/ok',
                    audit_reply_url: 'user/audit_image/batch_audit/option/reply',
                    add_url: 'user/audit_image/add',
                   /* edit_url: 'user/audit_image/edit',*/
                    del_url: 'user/audit_image/del',
                    multi_url: 'user/audit_image/multi',
                    import_url: 'user/audit_image/import',
                    table: 'user_audit_image',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                search: false,
                columns: [
                    [
                        {checkbox: true},
                        /*{field: 'id', title: __('Id')},*/
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user_nickname', title: __('用户昵称') ,operate: false},
                        {field: 'business.role', title: __('用户身份') ,operate: '=', searchList: {1:'用户',2:'厅主',3:'主播',4:'运营'}, formatter: Table.api.formatter.label},
                        {field: 'img_type', title: __('Img_type'), searchList: {"avatar":__('Img_type avatar'),"image":__('Img_type image')}, formatter: Table.api.formatter.normal},
                        {field: 'url', title: __('图片'), events: Table.api.events.image, formatter: Table.api.formatter.image,  operate: false},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'admin_nickname',title: __('Auditor'),operate: false},
                        {field: 'auditor_time', title: __('Auditor_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [{
                                name: 'audit',
                                text: '审核通过',
                                title: '审核通过',
                                classname: 'btn btn-xs btn-info btn-magic btn-ajax',
                                url: 'user/audit_image/audit/option/ok',
                                confirm: '你确认审核通过吗?',
                                visible:function (row) {
                                    if (row.status == 0) return true;
                                    return false;
                                },
                                success: function (data, ret) {
                                    $(".btn-refresh").trigger("click");
                                },


                            },{
                                name: 'audit',
                                text: '审核驳回',
                                title: '审核驳回',
                                classname: 'btn btn-xs btn-primary btn-magic btn-ajax',
                                url: 'user/audit_image/audit/option/reply',
                                confirm: '你确认审核驳回?',
                                success: function (data, ret) {
                                    $(".btn-refresh").trigger("click");
                                },
                                visible:function (row) {
                                    if (row.status == 0) return true;
                                    return false;
                                },
                            },{
                                name: 'del',
                                text: '删除',
                                title: '删除',
                                classname: 'btn btn-xs btn-warning btn-ajax',
                                url: 'user/audit_image/del',
                                success: function (data, ret) {
                                    $(".btn-refresh").trigger("click");
                                },
                                visible:function (row) {
                                    return true;
                                },
                            }
                            ],
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            $(document).on("click", "#batch_audit_ok", function () {
                var ids = Table.api.selectedids(table);
                var options = table.bootstrapTable('getOptions');
                var url = options.extend.audit_ok_url;
                if (url.indexOf("{ids}") !== -1) {
                    url = Table.api.replaceurl(url, {ids: ids.length > 0 ? ids.join(",") : 0}, table);
                }
                $.ajax({
                    url: url,
                    type: 'get',
                    data: {ids: ids},
                    success: function () {
                        Layer.msg('操作成功');
                        table.trigger("uncheckbox");
                        table.bootstrapTable('refresh');
                    },
                    error: function (xhr) {
                        Layer.msg('操作失败');

                    }
                });
            });
            $(document).on("click", "#batch_audit_reply", function () {
                var ids = Table.api.selectedids(table);
                var options = table.bootstrapTable('getOptions');
                var url = options.extend.audit_reply_url;
                if (url.indexOf("{ids}") !== -1) {
                    url = Table.api.replaceurl(url, {ids: ids.length > 0 ? ids.join(",") : 0}, table);
                }
                $.ajax({
                    url: url,
                    type: 'get',
                    data: {ids: ids},
                    success: function () {
                        Layer.msg('操作成功');
                        table.trigger("uncheckbox");
                        table.bootstrapTable('refresh');
                    },
                    error: function (xhr) {
                        Layer.msg('操作失败');

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
