define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/ident/index' + location.search,
                    add_url: 'user/ident/add',
                    edit_url: 'user/ident/edit',
                    // del_url: 'user/ident/del',
                    multi_url: 'user/ident/multi',
                    import_url: 'user/ident/import',
                    table: 'user_ident',
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
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'real_name', title: __('Real_name'), operate: 'LIKE'},
                        {field: 'card_number', title: __('Card_number'), operate: 'LIKE'},
                        {field: 'front_image', title: __('Front_image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'back_image', title: __('Back_image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"-1":__('Status -1')}, formatter: Table.api.formatter.status},
                        {field: 'operator', title: __('Operator')},
                        {field: 'operator_time', title: __('Operator_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'edit',
                                    text: '审核',
                                    title: '审核',
                                    classname: 'btn btn-xs btn-warning btn-dialog',
                                    extend: 'data-toggle="tooltip"',
                                    icon: 'fa fa-check',
                                    url: 'user/ident/edit',
                                    refresh: true,
                                    visible: function (row) {
                                        return (row.status==0)?true:false;
                                    }
                                },
                            ],
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            var selectRow = parent.$('#table').bootstrapTable('getSelections');
            if (selectRow.length > 0) {
                selectRow = selectRow[0]
                var rowArr = $("form").serializeArray();
                rowArr.forEach(function (item, index) {
                    var key = item.name.slice(item.name.indexOf("[") + 1, item.name.indexOf("]"));
                    if (!['delete_time'].includes(key)) {
                        $('[name="' + item.name + '"]').val(selectRow[key]);
                    }
                })
            }
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
