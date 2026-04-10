define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/create/index' + location.search,
                    add_url: 'user/create/add',
                    add_batch_url: 'user/create/add_batch',
                    edit_url: 'user/create/edit',
                    del_url: 'user/create/del',
                    multi_url: 'user/create/multi',
                    detail_url: 'user/create/index',
                    table: 'user_create',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                showExport: true,
                exportDataType: "all",
                exportTypes: ['excel'],
                search: false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), operate: false},
                        {field: 'nickname', title: __('nickname'), operate: false},
                        {field: 'mobile', title: __('Mobile'), operate: '='},
                        {field: 'password', title: __('Password'), operate: false},
                        {field: 'admin_id', title: __('Admin_id'), searchList: $.getJSON('user/create/index/option/load_admin'), visible: false},
                        {field: 'admin_nickname', title: __('Admin_id'), operate: false},
                        {field: 'comment', title: __('comment'), operate: false},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange'},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', operate: false},
                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            Controller.api.bind_add_batch(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        add_batch: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            bind_add_batch: function (table) {
                //Bootstrap-table的父元素,包含table,toolbar,pagnation
                var parenttable = table.closest('.bootstrap-table');
                //Bootstrap-table配置
                var options = table.bootstrapTable('getOptions');
                //Bootstrap操作区
                var toolbar = $(options.toolbar, parenttable);
                // 添加按钮事件
                $(toolbar).on('click', '.btn-add-batch', function () {
                    var ids = Table.api.selectedids(table);
                    var url = options.extend.add_batch_url;
                    if (url.indexOf("{ids}") !== -1) {
                        url = Table.api.replaceurl(url, {ids: ids.length > 0 ? ids.join(",") : 0}, table);
                    }
                    Fast.api.open(url, $(this).attr('title'), $(this).data() || {});
                });
            }
        }
    };
    return Controller;
});
