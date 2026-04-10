define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'general/share_song/index' + location.search,
                    add_url: 'general/share_song/add',
                    edit_url: 'general/share_song/edit',
                    del_url: 'general/share_song/del',
                    multi_url: 'general/share_song/multi',
                    import_url: 'general/share_song/import',
                    table: 'share_song',
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
                        /*{checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'title', title: __('Title'), operate: 'LIKE'},
                        {field: 'author', title: __('Author'), operate: false},
                        {field: 'file', title: __('File'), operate: false, formatter: function (v, r, i) {
                                return Table.api.formatter.url(Fast.api.cdnurl(v));
                            }},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status},
                        {field: 'weigh', title: __('Weigh'), operate: false,sortable: true},
                        // {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false,sortable: true},
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
