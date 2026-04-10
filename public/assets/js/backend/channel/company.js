define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'channel/company/index' + location.search,
                    add_url: 'channel/company/add',
                    edit_url: 'channel/company/edit',
                    del_url: 'channel/company/del',
                    multi_url: 'channel/company/multi',
                    import_url: 'channel/company/import',
                    table: 'channel_company',
                }
            });

            var table = $("#table");
            table.on('post-body.bs.table',function (){
                $('.btn-editone').data('area',['66%','90%']);
                $('.btn-add').data('area',['66%','90%']);
            });
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
                        /*{checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'name', title: __('Name'), operate: false},
                        {field: 'id', title: __('Name'),visible:false, searchList: $.getJSON('channel/company/index/option/search_list')},
                        {field: 'code', title: __('Code'), operate: 'LIKE'},
                        {field: 'contacts', title: __('Contacts'), operate: false},
                        {field: 'contacts_way', title: __('Contacts_way'), operate: false},
                        // {field: 'mch_name', title: __('Mch_name'), operate: false},
                        {field: 'mch_id', title: __('Mch_id'), operate:'='},
                        /*{field: 'mch_name', title: __('Mch_name'), operate: false},
                        {field: 'mch_key', title: __('Mch_key'), operate: 'LIKE'},
                        {field: 'app_id', title: __('App_id'), operate: 'LIKE'},
                        {field: 'app_secret', title: __('App_secret'), operate: 'LIKE'},
                        {field: 'sign_type', title: __('Sign_type'), searchList: {"1":__('Sign_type 1'),"2":__('Sign_type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'notify_prefix', title: __('Notify_prefix'), operate: 'LIKE'},
                        {field: 'notify_url', title: __('Notify_url'), operate: 'LIKE', formatter: Table.api.formatter.url},*/
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status,operate: false},
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
            // $('#c-name').bind('input propertychange change',function () {
            //     var name = $(this).val();
            //     $.ajax({
            //         url: 'channel/company/createCodeByName',
            //         type: 'get',
            //         data: {  name: name },
            //         success: function (res) {
            //             $('#c-code').val(res);
            //         }
            //     });
            // });
            Controller.api.bindevent();
        },
        edit: function () {
            // $('#c-name').bind('input propertychange change',function () {
            //     var name = $(this).val();
            //     $.ajax({
            //         url: 'channel/company/createCodeByName',
            //         type: 'get',
            //         data: {  name: name },
            //         success: function (res) {
            //             $('#c-code').val(res);
            //         }
            //     });
            //
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
