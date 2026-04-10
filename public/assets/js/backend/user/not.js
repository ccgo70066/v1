define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/not/index',
                    add_url: 'user/not/add',
                    //edit_url: 'user/not/edit',
                    //del_url: 'user/not/del',
                    multi_url: 'user/not/multi',
                    table: 'user',
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
                sortName: 'user.id',
                fixedColumns: true,
                fixedRightNumber: 1,
                search: false,
                columns: [
                    [
                        //{checkbox: true},
                        {field: 'id', title: __('用户Id'), sortable: true,operate: false},
                        {field: 'nickname', title: __('用户昵称'), operate: false},
                        {field: 'mobile', title: __('手机号'), operate: false},
                        {field: 'business.recharge_amount', title: __('累加充值'), operate: 'BETWEEN', sortable: true,visible: false},
                        {field: 'logintime', title: __('最后登录时间'), formatter: Table.api.formatter.datetime, operate: false, addclass: 'datetimerange', sortable: true,},

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
        add_amount:function () {
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
        update_comment: function () {
            Controller.api.bindevent();
        },
        allow_other_imei_login: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                // Form.api.bindevent($("form[role=form]"));
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
                        label="<div style='white-space: nowrap; text-overflow:ellipsis; overflow: hidden; max-width:" + width + "px;' >" + value + "</div>";
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
