define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'channel/channel/index' + location.search,
                    add_url: 'channel/channel/add',
                    edit_url: 'channel/channel/edit',
                    del_url: 'channel/channel/del',
                    multi_url: 'channel/channel/multi',
                    import_url: 'channel/channel/import',
                    table: 'channel',
                }
            });

            var table = $("#table");
            table.on('post-body.bs.table',function (){
                $('.btn-editone').data('area',['60%','80%']);
                $('.btn-add').data('area',['60%','80%']);
            });
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                search: false,
                columns: [
                    [
                        // {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'appid', title: __('Appid'), operate: 'LIKE'},
                        {
                            field: 'payway_name',
                            title: __('payway'),
                            operate: false,
                            formatter: Table.api.formatter.label
                        },
                        // {field: 'platform', title: __('Platform'), searchList: {"1":__('Platform 1'),"2":__('Platform 2'),"3":__('Platform 3')}, formatter: Table.api.formatter.normal},
                        {
                            field: 'status',
                            title: __('Status'),
                            searchList: {"1": __('Status 1'), "0": __('Status 0')},
                            formatter: Table.api.formatter.status
                        },
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {
                            field: 'create_time',
                            title: __('Create_time'),
                            operate: false,
                            addclass: 'datetimerange',
                            autocomplete: false,
                        },
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            // $("#c-payway").data("params", function () {
            //     return {custom: {status: 1, company_id: ['in', $("#c-company_id").val()]}};
            // });
            Controller.api.bindevent();
        },
        edit: function () {

            // $("#c-payway").data("params", function () {
            //     return {custom: {status: 1, company_id: ['in', $("#c-company_id").val()]}};
            // });
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
