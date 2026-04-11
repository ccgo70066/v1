define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'room/admin/index' + location.search,
                    // add_url: 'room/admin/add',
                    // edit_url: 'room/admin/edit',
                    // del_url: 'room/admin/del',
                    multi_url: 'room/admin/multi',
                    import_url: 'room/admin/import',
                    table: 'room_admin',
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
                        {field: 'id', title: __('Id')},
                        {field: 'room_id', title: __('Room_id')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'role', title: __('Role'), searchList: {"1":__('Role 1'),"2":__('Role 2'),"3":__('Role 3')}, formatter: Table.api.formatter.normal},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"-1":__('Status -1'),"2":__('Status 2'),"-2":__('Status -2')}, formatter: Table.api.formatter.status},
                        {field: 'reason', title: __('Reason'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                        buttons:[
                            {
                                name: 'check_join',
                                text: '同意',
                                icon: 'fa fa-check',
                                classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                url: 'room/admin/check_join?agree=1&ids={id}',
                                confirm: '确定同意吗?',
                                refresh: true,
                                visible: function (row) {
                                    return row.status == 0;
                                }
                            },
                            {
                                name: 'check_join',
                                text: '拒绝',
                                icon: 'fa fa-check',
                                classname: 'btn btn-xs btn-danger btn-magic btn-ajax',
                                url: 'room/admin/check_join?agree=0&ids={id}',
                                confirm: '确定拒绝吗?',
                                refresh: true,
                                visible: function (row) {
                                    return row.status == 0;
                                }
                            },
                            {
                                name: 'check_leave',
                                text: '同意',
                                icon: 'fa fa-check',
                                classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                url: 'room/admin/check_leave?agree=1&ids={id}',
                                confirm: '确定同意吗?',
                                refresh: true,
                                visible: function (row) {
                                    return row.status == 2;
                                }
                            },
                            {
                                name: 'check_leave',
                                text: '拒绝',
                                icon: 'fa fa-check',
                                classname: 'btn btn-xs btn-danger btn-magic btn-ajax',
                                url: 'room/admin/check_leave?agree=0&ids={id}',
                                confirm: '确定拒绝吗?',
                                refresh: true,
                                visible: function (row) {
                                    return row.status == 2;
                                }
                            }



                        ]}
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
