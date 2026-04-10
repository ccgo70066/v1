define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'prop/noble_privilege/index' + location.search,
                    add_url: 'prop/noble_privilege/add',
                    edit_url: 'prop/noble_privilege/edit',
                    del_url: 'prop/noble_privilege/del',
                    multi_url: 'prop/noble_privilege/multi',
                    import_url: 'prop/noble_privilege/import',
                    table: 'noble_privilege',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                search: false,
                columns: [
                    [
                       /* {checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'label', title: __('Label'), operate: false},
                        {field: 'has_switch', title: __('Has_switch'), searchList: {"1":__('Has_switch 1'),"0":__('Has_switch 0')}, table: table, formatter: Table.api.formatter.toggle},
                        {field: 'weigh', title: __('Weigh'), operate: false,sortable: true},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false,sortable: true},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
