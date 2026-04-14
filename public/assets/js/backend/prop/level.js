define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'prop/level/index' + location.search,
                    add_url: 'prop/level/add',
                    edit_url: 'prop/level/edit',
                    del_url: 'prop/level/del',
                    multi_url: 'prop/level/multi',
                    import_url: 'prop/level/import',
                    table: 'level',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'grade',
                sortOrder: 'asc',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'grade', title: __('Grade')},
                        {field: 'scope', title: __('Scope')},
                        {field: 'icon', title: __('Icon'), operate: 'LIKE', formatter: Table.api.formatter.image},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
