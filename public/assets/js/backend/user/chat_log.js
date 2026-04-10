define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/chat_log/index' + location.search,
                    add_url: 'user/chat_log/add',
                    //edit_url: 'user/chat_log/edit',
                    // del_url: 'user/chat_log/del',
                    multi_url: 'user/chat_log/multi',
                    import_url: 'user/chat_log/import',
                    table: 'chat_log',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                search: false,
                columns: [
                    [
                        // {checkbox: true},
                        //{field: 'id', title: __('Id')},
                        {field: 'user_id', title: __('发送人ID')},
                        {field: 'user.nickname', title: __('发送人昵称'),operate:false},
                        {field: 'to_user_id', title: __('接收人ID')},
                        {field: 'touser.nickname', title: __('接收人昵称'),operate:false},
                        {field: 'content', title: __('Content'),operate: 'like',cellStyle: function () {return {css: {"min-width": "200px","max-width": "300px","text-overflow": "ellipsis", "white-space": "initial","word-wrap":"break-word","word-break":"break-all"}};},
                            formatter:function (value, row, index) {
                            if (row.type && row.type =='image') {
                                return Table.api.formatter.image.call(this, value, row, index);
                            }
                            if (row.type && row.type == 'voice') {
                                return  '<audio  controls  style="max-height: 100px"  width="200px" src="' + Fast.api.cdnurl(value) + '" </audio>';
                            }
                            return value;
                        },events: Table.api.events.image},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'chat_list',operate: false, title: '历史记录', table: table,
                            buttons: [{
                                name: 'chat_list',
                                text: '历史记录',
                                title: '历史记录',
                                icon: 'fa',
                                classname: 'btn btn-xs btn-info btn-dialog',
                                extend: 'data-area=\'["90%", "90%"]\'',
                                // url: 'user/chat_log/chat_list',
                                url: function (row) {
                                    return 'user/chat_log/chat_list?user_id=' + row.user_id+'&to_user_id='+row.to_user_id;
                                },
                                callback: function (data) {
                                    //
                                    // location.reload();
                                },
                                visible: function (row) {
                                    //返回true时按钮显示,返回false隐藏
                                    return true;
                                },
                            }],
                            formatter: Table.api.formatter.buttons
                        },
                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
