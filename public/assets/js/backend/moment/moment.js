define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'moment/moment/index' + location.search,
                    add_url: 'moment/moment/add',
                    audit_ok_url: 'moment/moment/batch_audit/option/ok',
                    audit_reply_url: 'moment/moment/batch_audit/option/reply',
                    del_url: 'moment/moment/del',
                    multi_url: 'moment/moment/multi',
                    import_url: 'moment/moment/import',
                    table: 'moment',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                search: false,
                columns: [
                    [
                        {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.nickname', title: __('用户昵称'),operate: false},
                        {field: 'business.role', title: __('用户身份') ,operate: '=', searchList: {1:'用户',3:'家族成员',4:'族长'}, formatter: Table.api.formatter.label},
                        {field: 'content', title: __('Content'),cellStyle: function () {return {css: {"min-width": "150px","max-width": "250px","text-overflow": "ellipsis", "white-space": "initial","word-wrap":"break-word","word-break":"break-all"}};},operate: 'like'},
                        {field: 'images', title: __('图片/语音/视频'),operate: false,formatter:function (value, row, index){
                                if (row.images) {
                                    return Table.api.formatter.images.call(this, row.images, row, index);
                                }
                                value = row.video || row.audio;
                                if (value == null || value == false) return '';
                                var str = '<video style="max-height: 100px"  width="200px" src="' + Fast.api.cdnurl(value) + '" controls></video>';
                                return str;
                            },events: Table.api.events.image},
                        //{field: 'publish', title: __('Publish'), searchList: {"1":__('Publish 1'),"2":__('Publish 2'),"0":__('Publish 0')}, formatter: Table.api.formatter.normal},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"-1":__('Status -1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'admin.nickname', title: __('Audit_admin'),operate: false},
                        {field: 'auditor_time', title: __('Auditor_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        //{field: 'rebut_flag', title: __('Rebut_flag'), formatter: Table.api.formatter.flag},
                        //{field: 'block_status', title: __('Block_status'), searchList: {"1":__('Block_status 1'),"0":__('Block_status 0')}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        //{field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons: [{
                                name: '通过',
                                text: '通过',
                                title: '通过',
                                classname: 'btn btn-xs btn-info btn-magic btn-ajax',
                                // extend: 'data-area=\'["40%", "65%"]\'',
                                url: 'moment/moment/index/option/ok',
                                confirm: '你确认审核通过,允许发布吗?',
                                visible: function (row) {
                                    return row.status == 2 ? true : false;
                                },
                                success: function (data, ret) {
                                    $(".btn-refresh").trigger("click");
                                },

                            },{
                                name: '驳回',
                                text: '驳回',
                                title: '驳回',
                                classname: 'btn btn-xs btn-primary btn-magic btn-ajax',
                                // extend: 'data-area=\'["40%", "65%"]\'',
                                url: 'moment/moment/index/option/reply',
                                confirm: '你确认审核驳回,禁止发布吗?',
                                visible: function (row) {
                                    return row.status == 2 ? true : false;
                                },
                                success: function (data, ret) {
                                    $(".btn-refresh").trigger("click");
                                },

                            },{
                                name: '评论',
                                text: '评论',
                                title: '评论',
                                classname: 'btn btn-xs btn-success btn-dialog',
                                icon: '',
                                // extend: 'data-area=\'["40%", "65%"]\'',
                                url: 'moment/comment/index?moment_id={id}',
                            }],
                            formatter: Table.api.formatter.operate}
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
