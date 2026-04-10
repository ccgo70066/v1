define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'auth/cache_reset/index' + location.search,
                    // add_url: 'auth/cache_reset/add',
                    // edit_url: 'auth/cache_reset/edit',
                    // del_url: 'auth/cache_reset/del',
                    multi_url: 'auth/cache_reset/multi',
                    import_url: 'auth/cache_reset/import',
                    table: 'cache_reset',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'key',
                sortName: 'key',
                fixedColumns: true,
                fixedRightNumber: 1,
                search: false,
                commonSearch: true,
                searchFormVisible: false,
                pagination: false,
                columns: [
                    [
                        {field: 'name', title: __('缓存名称')},
                        {field: 'key', title: __('缓存键名')},
                        {
                            field: 'group_id',
                            title: __('组'),
                            searchList: $.getJSON('auth/cache_reset/index/option/getGroup'),
                            visible: false,
                        },
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate,
                            buttons: [{
                                name: 'show',
                                text: '查看',
                                icon: 'fa fa-eye',
                                title: '查看缓存内容',
                                classname: 'btn  btn-xs btn-info btn-dialog',
                                extend: 'data-toggle="tooltip"',
                                url: 'auth/cache_reset/show',
                            }, {
                                name: 'reset',
                                text: '重建',
                                icon: 'fa fa-wrench',
                                classname: 'btn  btn-xs btn-info btn-ajax',
                                confirm: '你确认要重新建立缓存吗',
                                extend: 'data-toggle="tooltip"',
                                title: '重新建立缓存',
                                url: 'auth/cache_reset/reset',
                            }]
                        }

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
