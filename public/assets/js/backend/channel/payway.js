define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'channel/payway/index' + location.search,
                    add_url: 'channel/payway/add',
                    edit_url: 'channel/payway/edit',
                    del_url: 'channel/payway/del',
                    multi_url: 'channel/payway/multi',
                    import_url: 'channel/payway/import',
                    table: 'channel_payway',
                }
            });

            var table = $("#table");
            table.on('post-body.bs.table',function (){
                $('.btn-editone').data('area',['60%','90%']);
                $('.btn-add').data('area',['60%','90%']);
            });
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                sortOrder:'asc',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        // {checkbox: true},
                        // {field: 'id', title: __('Id'),operate: false},
                        {field: 'pay_name', title: __('渠道名称'), operate: 'LIKE'},
                        {field: 'app_pay_name', title: __('App_pay_name'), operate: 'LIKE'},
                        // {field: 'pay_code', title: __('Code'), operate: 'LIKE'},
                        {field: 'company.name', title: __('Company_id'), operate: false},
                        {field: 'company_id', title: __('Company_id'),visible:false, searchList: $.getJSON('channel/company/index/option/search_list')},
                        {field: 'payway.name', title: __('Pay_way'), operate: false, formatter: Table.api.formatter.normal},
                        {field: 'open_way', title: __('Open_way'), searchList: {"1":__('Open_way 1'),"2":__('Open_way 2'),"3":__('Open_way 3')}, formatter: Table.api.formatter.normal},
                        {field: 'card_name', title: __('Card_ids'), operate: false, formatter: Table.api.formatter.label},
                        {field: 'weigh', title: __('Weigh'), operate: false, sortable: true},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: __('Create_time'), operate:false, addclass:'datetimerange', autocomplete:false, sortable: true},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            $("#c-card_ids").data("params", function(){
                return {custom: {status:1, system: $("#c-card_system").val()}};
            });
            Controller.api.bindevent();
        },
        edit: function () {
            $("#c-card_ids").data("params", function(){
                return {custom: {status:1, system: $("#c-card_system").val()}};
            });
            $("#c-card_system").change(function(){
                $('#c-card_ids').selectPageClear();
            });
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
