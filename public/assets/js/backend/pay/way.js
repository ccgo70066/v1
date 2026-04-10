define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'pay/way/index' + location.search,
                    add_url: 'pay/way/add',
                    edit_url: 'pay/way/edit',
                    del_url: 'pay/way/del',
                    table: 'pay_way',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                columns: [
                    [
                        {field: 'code', title: 'Code', operate: false},
                        {field: 'image', title: __('图标'),operate: false, events: Table.api.events.image, formatter: function (value) {
                                if (value) {

                                    value = value.replace(/\s+/g,""); //过滤掉空格
                                    value = value ? value : '/assets/img/blank.gif';
                                    var classname = typeof this.classname !== 'undefined' ? this.classname : 'img-center';
                                    return '<a href="javascript:"><img style="max-height: 30px" class="' + classname + '" src="' + Fast.api.cdnurl(value) + '" /></a>';
                                }
                                if (value == null) return '';
                            }},
                        {field: 'name', title: __('Name')},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: __('Create_time'), operate:false, addclass:'datetimerange', autocomplete:false},
                        {field: 'update_time', title: __('Update_time'), operate:false, addclass:'datetimerange', autocomplete:false},
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
