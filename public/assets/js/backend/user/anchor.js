define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/anchor/index' + location.search,
                    table: 'anchor',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', visible: false, operate: false},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.nickname', title: __('User.nickname'), operate: false},
                        {
                            field: 'user.avatar',
                            title: __('User.avatar'),
                            operate: false,
                            events: Table.api.events.image,
                            formatter: Table.api.formatter.image
                        },
                        {
                            field: 'user.gender',
                            title: __('性别'),
                            operate: false,
                            searchList: {1: __('男'), 0: __('女')},
                            formatter: Table.api.formatter.status
                        },
                        {
                            field: 'is_verify',  operate: false, title: __('是否实名'), formatter: function (value, row, index) {
                                return '是';
                            }
                        },
                        {
                            field: 'user.voice',
                            title: __('语音'),
                            operate: false,
                            formatter: function (value, row, index) {
                                value = row.video;
                                if (value == null || value == false) return '';
                                return '<video style="max-height: 100px"  width="200px" src="' + Fast.api.cdnurl(value) + '" controls></video>';
                            }
                        },
                        {
                            field: 'user.image',
                            title: __('形象照'),
                            operate: false,
                            formatter: function (value, row, index) {
                                if (row.user.image) {
                                    return Table.api.formatter.images.call(this,row.user.image, row, index);
                                }
                                return '';
                            },
                            events: Table.api.events.image
                        },
                        {
                            field: 'create_time',
                            title: __('申请时间'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            autocomplete: false
                        },
                        {
                            field: 'is_consent', title: __('是否同意协议'),  operate: false, formatter: function (value, row, index) {
                                return '同意';
                            }
                        },
                        {
                            field: 'sign_img',
                            title: '签名',
                            operate: false
                        },
                        {
                            field: 'pact_view',
                            title: '查看协议',
                            table: table,
                            operate: false,
                            events: Table.api.events.buttons,
                            buttons: [
                                {

                                    name: 'pact_view',
                                    text: __('查看协议'),
                                    title: __('主播协议'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa fa-magic',
                                    url: 'user/anchor/pact_view',
                                    success: function (data, ret) {
                                        layer.open({
                                            type: 2,
                                            title: '主播协议',
                                            shadeClose: true,
                                            shade: 0.4,
                                            area: ['380px', '740px'],
                                            content: data
                                        });

                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    error: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        return false;
                                    }
                                },],
                            formatter: Table.api.formatter.buttons
                        },

                        {field: 'admin.username', title: __('Audit_admin'), operate: false},
                        {
                            field: 'audit_time',
                            title: __('Audit_time'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            autocomplete: false
                        },
                        {
                            field: 'status',
                            title: __('Status'),
                            searchList: {"0": '驳回或解约', "1": __('Status 1'), "2": __('Status 2')},
                            formatter: Table.api.formatter.status
                        },
                        {
                            field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'checker',
                                    text: '通过',
                                    title: '通过',
                                    classname: 'btn btn-xs btn-info btn-ajax',
                                    extend: 'data-toggle="tooltip"',
                                    icon: 'fa fa-check',
                                    url: 'user/anchor/checker',
                                    visible: function (row) {
                                        return row.status == 1;
                                    },
                                    success: function () {
                                        $(".btn-refresh").trigger("click");
                                    }
                                },
                                {
                                    name: 'rebut',
                                    text: '驳回',
                                    title: '驳回',
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    extend: 'data-toggle="tooltip"',
                                    icon: 'fa fa-reply',
                                    url: 'user/anchor/rebut',
                                    visible: function (row) {
                                        return row.status == 1
                                    }, success: function () {
                                        $(".btn-refresh").trigger("click");
                                    }
                                },
                            ],
                            formatter: Table.api.formatter.operate
                        }

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
