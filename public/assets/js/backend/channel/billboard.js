define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'channel/billboard/index' + location.search,
                    add_url: 'channel/billboard/add',
                    edit_url: 'channel/billboard/edit',
                    del_url: 'channel/billboard/del',
                    multi_url: 'channel/billboard/multi',
                    import_url: 'channel/billboard/import',
                    table: 'channel_billboard',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                fixedColumns: true,
                fixedRightNumber: 1,
                search: false,
                columns: [
                    [
                       /* {checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        // {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2')}, formatter: Table.api.formatter.normal,operate: false},
                        {field: 'position', title: __('Position'), searchList: {"1":__('Position 1'),"6":__('Position 6')}, operate: false, formatter: Table.api.formatter.label},
                        {field: 'title', title: __('Title'), operate: 'LIKE'},
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'action', title: __('Action'), searchList: {"0":__('Action 0'),"1":__('Action 1'),"7":__('Action 7'),"5":__('Action 5'),"8":__('Action 8')}, formatter: Table.api.formatter.normal},
                        {field: 'action_url', title: __('Action_url'), operate: false, formatter: Table.api.formatter.url},
                        //{field: 'ios_action_url', title: __('Ios_action_url'), operate: false, formatter: Table.api.formatter.url},
                        {field: 'show_start_time', title: __('Show_start_time'), operate: false, addclass:'datetimerange', autocomplete:false},
                        {field: 'show_end_time', title: __('Show_end_time'), operate: false, addclass:'datetimerange', autocomplete:false},
                        {field: 'weigh', title: __('Weigh'), operate: false,sortable: true},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0'),"-1":__('Status -1')}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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
