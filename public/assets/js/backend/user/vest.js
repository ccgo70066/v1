define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/vest/index' + location.search,
                    add_url: 'user/vest/add',
                    edit_url: 'user/vest/edit',
                    del_url: 'user/vest/del',
                    multi_url: 'user/vest/multi',
                    import_url: 'user/vest/import',
                    table: 'user_vest',
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
                        {field: 'user_id', title: __('User_id')},
                        {field: 'account', title: __('Account'), operate: 'LIKE'},
                        {field: 'password', title: __('Password'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'receiver.id', title: __('Receiver')+'ID'},
                        {field: 'receiver.nickname', title: __('Receiver')},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'update_time', title: __('领取时间'), operate:'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: function (v, r, i) {
                                return r.receiver.id != undefined ? v : '-';
                            }},
                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
