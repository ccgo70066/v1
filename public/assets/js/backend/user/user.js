define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/user/index' + location.search,
                    total_url: 'user/user/total' + location.search, //统计
                    add_url: 'user/user/add',
                    //edit_url: 'user/user/edit',
                    //del_url: 'user/user/del',
                    multi_url: 'user/user/multi',
                    table: 'user',
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
                sortName: 'user.createtime',
                fixedColumns: true,
                fixedRightNumber: 1,
                // commonSearch: false,
                search: false,
                columns: [
                    [
                        {field: 'id', title: __('Id'), sortable: true},
                        {field: 'nickname', title: __('Nickname'), operate: 'LIKE'},
                        {field: 'business.role', title: __('Role'), formatter: Table.api.formatter.normal, searchList: {1: __('Role1'), 2: __('Role2'), 3: __('Role3'), 4: __('Role4')}},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        {field: 'avatar', title: __('Avatar'), events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false},
                        {field: 'business.amount', title: __('金幣'), operate: false, sortable: true},
                        {
                            field: 'business.reward_amount', title: __('收益'), operate: false,
                            formatter: function (value, row, index) {
                                return parseFloat(value);
                            }
                        },
                        {field: 'level_icon', title: __('Level'), operate: false, sortable: true, formatter: Table.api.formatter.image},
                        {field: 'gender', title: __('Gender'), visible: false, searchList: {1: __('Male'), 0: __('Female')}},
                        {field: 'channel.name', title: __('Appid'), formatter: Table.api.formatter.label, operate: false},
                        {field: 'system', title: __('System'), searchList: {"1": __('System 1'), "2": __('System 2')}, formatter: Table.api.formatter.normal},
                        {field: 'logintime', title: __('Logintime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'loginip', title: __('Loginip'), formatter: Table.api.formatter.search, operate: 'like', placeholder: '支持模糊搜索'},
                        {field: 'jointime', title: __('Jointime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'is_online', title: __('Is_online'), searchList: {"0": __('Is_online 0'), "1": __('Is_online 1')}, formatter: Table.api.formatter.normal},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status, searchList: {normal: __('Normal'), hidden: __('Hidden'), death: __('Death')}},
                        {field: 'imei', title: __('imei'), operate: 'LIKE', formatter: Table.api.formatter.content, width: '200px'},
                        {
                            field: 'operate', title: __('Operate'), width: 150, table: table, events: Table.api.events.operate,
                            buttons: [{
                                name: 'detail',
                                text: __('Detail'),
                                icon: 'fa fa-list',
                                classname: 'btn  btn-xs btn-info btn-detail btn-dialog',
                                url: 'user/user/detail',
                            }, {
                                dropdown: '操作',
                                name: 'add_amount',
                                text: '后台上分',
                                title: '后台上分',
                                classname: 'btn btn-xs  btn-dialog',
                                icon: 'fa fa-wrench',
                                url: 'user/user/add_amount?nick={nickname}',
                            }, {
                                dropdown: '操作',
                                name: 'remove_amount',
                                text: '后台下分',
                                title: '后台下分',
                                classname: 'btn btn-xs  btn-dialog',
                                icon: 'fa fa-wrench',
                                extend: '',
                                url: 'user/user/remove_amount?nick={nickname}',
                            }, {
                                dropdown: '操作',
                                name: 'set_illegal',
                                text: '违规设置',
                                title: '违规设置',
                                classname: 'btn btn-xs  btn-dialog',
                                icon: 'fa fa-wrench',
                                // extend: 'data-area=\'["40%", "45%"]\'',
                                url: 'user/user/set_illegal',
                                refresh: true,
                            }, {
                                dropdown: '操作',
                                name: 'add_blacklist',
                                text: '封禁用户',
                                title: '封禁用户',
                                classname: 'btn btn-xs  btn-dialog',
                                icon: 'fa fa-wrench',
                                // extend: 'data-area=\'["40%", "45%"]\'',
                                url: 'user/user/add_blacklist',
                                refresh: true,
                                visible: function (row) {
                                    return row.status == 'normal';
                                }
                            }, {
                                dropdown: '操作',
                                name: 'remove_blacklist',
                                text: '解禁用户',
                                title: '解禁用户',
                                classname: 'btn btn-xs  btn-ajax',
                                icon: 'fa fa-wrench',
                                // extend: 'data-area=\'["40%", "45%"]\'',
                                url: 'user/user/remove_blacklist',
                                confirm: function (row) {
                                    return '确认要解禁用户[' + row.nickname + ']吗?';
                                },
                                refresh: true,
                                visible: function (row) {
                                    return row.status == 'hidden';
                                }
                            }, {
                                dropdown: '操作',
                                name: 'clear_value',
                                text: '清空背包',
                                title: '清空背包',
                                classname: 'btn btn-xs  btn-dialog',
                                icon: 'fa fa-wrench',
                                // extend: 'data-area=\'["40%", "45%"]\'',
                                url: 'user/user/clear_value',
                                // confirm: function (row) { return '确定进行清空用户['+row.nickname+']背包礼物,币以及收益操作吗?';},
                            }, {
                                name: 'business_log',
                                text: '资产明细',
                                title: '资产明细',
                                classname: 'btn btn-xs  btn-dialog',
                                icon: 'fa fa-list',
                                extend: 'data-area=\'["90%", "90%"]\'',
                                url: function (row) {
                                    return 'user/business_log?user_id=' + row.id;
                                },
                                dropdown: '操作',
                            }, {
                                name: 'recharge_log',
                                text: '充值明细',
                                title: '充值明细',
                                classname: 'btn btn-xs  btn-dialog',
                                icon: 'fa fa-list',
                                extend: 'data-area=\'["90%", "90%"]\'',
                                url: function (row) {
                                    return 'user/recharge?user_id=' + row.id + '&createtime=""';
                                },
                                dropdown: '操作',
                            }, {
                                dropdown: '操作',
                                name: 'update_password',
                                text: '重置密码',
                                title: '重置密码',
                                classname: 'btn btn-xs  btn-dialog',
                                icon: 'fa fa-wrench',
                                extend: '',
                                url: 'user/user/update_password'
                            }, {
                                dropdown: '操作',
                                name: 'clear_login_limit',
                                text: '清理登陆限制',
                                title: '清理登陆限制',
                                classname: 'btn btn-xs  btn-ajax',
                                icon: 'fa fa-wrench',
                                extend: '',
                                url: 'user/user/clear_login_limit'
                            }, {
                                dropdown: '操作',
                                name: 'set_role',
                                text: '取消运营身份',
                                title: '取消运营身份',
                                classname: 'btn btn-xs  btn-ajax',
                                icon: 'fa fa-wrench',
                                url: 'user/user/set_role/type/0',
                                confirm: '确认操作吗?',
                                refresh: true,
                                visible: function (row) {
                                    return row.business.role != 1;
                                }
                            }, {
                                dropdown: '操作',
                                name: 'set_role',
                                text: '设置运营身份',
                                title: '设置运营身份',
                                classname: 'btn btn-xs  btn-ajax',
                                icon: 'fa fa-wrench',
                                url: 'user/user/set_role/type/1',
                                confirm: '确认操作吗?',
                                refresh: true,
                                visible: function (row) {
                                    return row.business.role == 1;
                                }
                            }
                            ],
                            formatter: Table.api.formatter.operate
                        }
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
        add_amount: function () {
            Controller.api.bindevent();
        },

        add_noble: function () {
            Controller.api.bindevent();
        },
        add_blacklist: function () {
            Controller.api.bindevent();
        },
        update_appid: function () {
            Controller.api.bindevent();
        },
        relation_handle: function () {
            Controller.api.bindevent();
        },
        remove_amount: function () {
            Controller.api.bindevent();
        },
        clear_value: function () {
            Controller.api.bindevent();
        },
        set_illegal: function () {
            Controller.api.bindevent();
            // $('.btn-trash').remove();
        },
        set_behaviour: function () {
            Controller.api.bindevent();
        },
        update_password: function () {
            Controller.api.bindevent();
        },
        update_beautiful_id: function () {
            Controller.api.bindevent();
        },
        update_comment: function () {
            Controller.api.bindevent();
        },
        allow_other_imei_login: function () {
            Controller.api.bindevent();
        },
        reset_role: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                // Form.api.bindevent($("form[role=form]"));
                Form.api.bindevent($("form[role=form]"), function () {
                }, function () {
                }, function () {

                    Layer.confirm('敏感操作,请仔细核对和确认,确认进行吗?', {
                        title: '提示',
                        btn: ['确认无误', '取消'] //按钮
                    }, function (index) {
                        Form.api.submit($("form[role=form]"), function () {
                            parent.Toastr.success('提交成功');
                            parent.$(".btn-refresh").trigger("click");
                            var index_top = parent.Layer.getFrameIndex(window.name);
                            parent.Layer.close(index_top);
                        }, function () {
                        }, function () {
                            Layer.close(index);
                            // var index_top = parent.Layer.getFrameIndex(window.name);
                            // parent.Layer.close(index_top);
                        });
                    }, function () {
                    });
                    return false;
                });
            },
            formatter: {
                label: function (value, row, index) {
                    var that = this;
                    value = value === null ? '' : value.toString();
                    var colorArr = {index: 'success', hot: 'warning', recommend: 'danger', 'new': 'info'};
                    //如果字段列有定义custom
                    if (typeof this.custom !== 'undefined') {
                        colorArr = $.extend(colorArr, this.custom);
                    }
                    var field = this.field;
                    if (typeof this.customField !== 'undefined' && typeof row[this.customField] !== 'undefined') {
                        value = row[this.customField];
                        field = this.customField;
                    }

                    var width = this.width != undefined ? this.width : 150;

                    //渲染Flag
                    var html = [];
                    var arr = value.split(',');
                    var color, display, label;
                    $.each(arr, function (i, value) {
                        value = value === null ? '' : value.toString();
                        if (value == '')
                            return true;
                        color = value && typeof colorArr[value] !== 'undefined' ? colorArr[value] : 'primary';
                        display = typeof that.searchList !== 'undefined' && typeof that.searchList[value] !== 'undefined' ? that.searchList[value] : __(value.charAt(0).toUpperCase() + value.slice(1));
                        // label = '<span class="label label-' + color + '"  style="white-space: nowrap; text-overflow: ellipsis; overflow: hidden; max-width:'+width+'px ">' + display + '</span>';
                        label = "<div style='white-space: nowrap; text-overflow:ellipsis; overflow: hidden; max-width:" + width + "px;' >" + value + "</div>";
                        if (that.operate) {
                            html.push('<a href="javascript:;" class="searchit" data-toggle="tooltip" title="' + __('Click to search %s', display) + '" data-field="' + field + '" data-value="' + value + '">' + label + '</a>');
                        } else {
                            html.push(label);
                        }
                    });
                    return html.join(' ');
                }
            }
        }
    };
    return Controller;
});
