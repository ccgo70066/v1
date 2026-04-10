define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'apilog/index' + location.search,
                    del_url: 'apilog/index/del',
                    del_all_url: 'apilog/index/del_all',
                    multi_url: 'apilog/index/multi',
                    table: 'apilog',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'createtime',
                columns: [
                    [
                        { checkbox: true },
                        // { field: 'id', title: __('Id'), operate: false },
                        { field: 'user_id', title: __('UserID'), formatter: Table.api.formatter.search },
                        // { field: 'username', title: __('UserName'), formatter: Table.api.formatter.search, operate: 'like'},
                        { field: 'url', title: __('Url'), formatter: Table.api.formatter.url },

                        {
                            field: 'method', title: __('Method'),
                            searchList: { "GET": "GET", "POST": "POST", "PUT": "PUT", "DELETE": "DELETE" },
                            formatter: Table.api.formatter.normal
                        },
                        {
                            field: 'ip', title: __('Ip'), formatter: function (value, row, index) {
                                var html = '<a class="btn btn-xs btn-ip bg-success" data-toggle="tooltip" data-original-title="点击搜索' + value + '"><i class="fa fa-map-marker"></i> ' + value + '</a>';
                                if (row.banip == false)
                                    html += '<a class="btn btn-xs btn-dialog btn-banip" data-status=0><i class="fa fa-toggle-on" data-toggle="tooltip" data-original-title="点击禁止该IP访问"></i></a>';
                                else {
                                    html += '<a class="btn btn-xs btn-dialog btn-banip" data-status=1><i class="fa fa-toggle-off" data-toggle="tooltip" data-original-title="点击允许该IP访问"></i></a>';
                                }
                                return html;
                            },
                            events: Controller.api.events.ip
                        },
                        {
                            field: 'ua', title: __('Ua'), formatter: function (value, row, index) {
                                return '<a class="btn btn-xs btn-browser">' + ((!value) ? '' : (value.split(" ")[0])) + '</a>';
                            },
                            events: Controller.api.events.browser
                        },
                        { field: 'controller', title: __('Controller'), operate: 'like'},
                        { field: 'action', title: __('Action') },
                        { field: 'param', title: __('param'), visible: false, operate: 'like', placeholder: '模糊查询'},

                        { field: 'time', title: __('Time'), sortable: true , operate: false},
                        { field: 'code', title: __('Code'), formatter: Table.api.formatter.search },
                        { field: 'api_code', title: __('接口code') },
                        {
                            field: 'createtime', title: __('Createtime'), operate: 'RANGE', sortable: true, addclass: 'datetimerange',
                            autocomplete: false,
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons: [{
                                name: 'detail',
                                text: __('Detail'),
                                icon: 'fa fa-list',
                                classname: 'btn btn-info btn-xs btn-detail btn-dialog',
                                url: 'apilog/index/detail'
                            }
                            ],
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            $(document).on("click", ".btn-del-all", function () {
                Layer.confirm(
                    __('Are you sure you want to delete or turncate?'),
                    {icon: 3, title: __('Warning'), offset: 0, shadeClose: true, btn: [__('OK'), __('Cancel')]},
                    function (index) {
                        Backend.api.ajax({url: $.fn.bootstrapTable.defaults.extend.del_all_url,}, function () {
                            table.bootstrapTable('refresh');
                            Layer.close(index);
                        });

                    }
                );
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
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            events: {
                ip: {
                    'click .btn-ip': function (e, value, row, index) {
                        e.stopPropagation();
                        var container = $("#table").data("bootstrap.table").$container;
                        $("form.form-commonsearch [name='ip']", container).val(value);
                        $("form.form-commonsearch", container).trigger('submit');
                    },
                    'click .btn-banip': function (e, value, row, index) {
                        e.stopPropagation();
                        if (row.banip == false)
                            layer.prompt({ title: '请输入封禁时长(分钟),0为永久封禁', value: '0' }, function (text, index) {
                                layer.close(index);
                                $.post('apilog/index/banip', { status: 0, ip: value, time: text }, function (res) {
                                    if (res.code == 1) {
                                        $('#table').bootstrapTable('refresh');
                                    }
                                })
                            });
                        else {
                            $.post('apilog/index/banip', { status: 1, ip: value }, function (res) {
                                if (res.code == 1) {
                                    $('#table').bootstrapTable('refresh');
                                }
                            })
                        }
                    }
                },
                browser: {
                    'click .btn-browser': function (e, value, row, index) {
                        e.stopPropagation();
                        var container = $("#table").data("bootstrap.table").$container;
                        $("form.form-commonsearch [name='ua']", container).val(value);
                        $("form.form-commonsearch", container).trigger('submit');
                    }
                },
            }
        }
    };
    return Controller;
});
