define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'channel/package/index' + location.search,
                    add_url: 'channel/package/add',
                    edit_url: 'channel/package/edit',
                    del_url: 'channel/package/del',
                    multi_url: 'channel/package/multi',
                    import_url: 'channel/package/import',
                    table: 'channel_package',
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
                        // {field: 'channel.name', title: __('Channel_id'), searchList: $.getJSON('channel/channel/searchList/key/name')},
                        {field: 'appid', title: __('Appid'), operate: 'LIKE'},
                        {field: 'system', title: __('System'), searchList: {"ANDROID":__('System android'),"IOS":__('System ios')}, formatter: Table.api.formatter.normal},
                        {field: 'version', title: __('Version'), operate: 'LIKE'},
                        {field: 'download', title: __('Download'), operate: false},
                        {field: 'force', title: __('Force'), searchList: {"1":__('Force 1'),"0":__('Force 0')}, formatter: Table.api.formatter.normal},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status},
                        {field: 'weigh', title: __('Weigh'), operate: false,sortable: true},
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

            $('#c-channel_id').change(function () {
                var name = $(this).val();
                $.ajax({
                    url: 'channel/channel/get_appid_by_name',
                    type: 'get',
                    data: {  name: name },
                    success: function (res) {
                        $('#c-appid').val(res);
                    }
                });

            });
        },
        edit: function () {

            Controller.api.bindevent();

            $('#c-channel_id').change(function () {
                var name = $(this).val();
                $.ajax({
                    url: 'channel/channel/get_appid_by_name',
                    type: 'get',
                    data: {  name: name },
                    success: function (res) {
                        $('#c-appid').val(res);
                    }
                });

            });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
