define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'api/field_confuse/index' + location.search,
                    add_url: 'api/field_confuse/add',
                    edit_url: 'api/field_confuse/edit',
                    del_url: 'api/field_confuse/del',
                    multi_url: 'api/field_confuse/multi',
                    import_url: 'api/field_confuse/import',
                    table: 'api_field_confuse',
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
                        {field: 'package_name', title: __('Package_name'), operate: 'LIKE'},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'encrypt_field', title: __('Encrypt_field'), operate: 'LIKE'},
                        {field: 'memo', title: __('Memo'), operate: 'LIKE'},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            $(document).on('click', '.btn-batch-add', function () {
                var ids = Table.api.selectedids(table);
                var url = 'api/field_confuse/add_batch';
                if (url.indexOf("{ids}") !== -1) {
                    url = Table.api.replaceurl(url, {ids: ids.length > 0 ? ids.join(",") : 0}, table);
                }
                Fast.api.open(url,
                    // $(this).data("original-title") || $(this).attr("title") || __('Add'),
                    __('add_batch'),
                    $(this).data() || {});
            });
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
        add_batch: function () {
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
