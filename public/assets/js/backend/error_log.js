define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'error_log/index' + location.search,
                    del_url: 'error_log/del',
                    del_all_url: 'error_log/del_all',
                    table: 'aa_error_log',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: '_id',
                sortName: '_id',
                columns: [
                    [
                        {checkbox: true},
                        // { field: 'id', title: __('Id'), operate: false },
                        {field: 'dir', title: __('项目'), operate: '='},
                        {field: 'error_message', title: __('错误信息'), operate: false},
                        {field: 'file', title: __('文件'), operate: false, formatter: function (value, row, index) {
                            return  value+':'+row.line;
                            }},
                        // {field: 'line', title: __('代码行'), operate: false},
                        // { field: 'error_last', title: __('定位'), operate: false,formatter: function (index,row) {
                        //         return row.error_trace[0];
                        //     }},

                        {
                            field: 'create_time', title: __('Createtime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            formatter: Table.api.formatter.datetime,
                            data: 'data-time-picker="true"'
                        },
                        {
                            field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [{
                                name: 'detail',
                                text: __('Detail'),
                                extend: 'data-area=\'["80%", "80%"]\'',
                                icon: 'fa fa-list',
                                classname: 'btn btn-info btn-xs btn-detail btn-dialog',
                                url: 'error_log/detail'
                            }],
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
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
