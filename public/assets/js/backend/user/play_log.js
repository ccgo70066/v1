define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/play_log/index' + location.search,
                    add_url: 'user/play_log/add',
                    edit_url: 'user/play_log/edit',
                    del_url: 'user/play_log/del',
                    multi_url: 'user/play_log/multi',
                    import_url: 'user/play_log/import',
                    table: 'user_play_log',
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
                       /* {checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'user_id', title: __('User_id')},
                        {field: 'user.nickname', title: __('用户昵称')},
                        {field: 'date', title: __('Date'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'room_id', title: __('Room_id'),searchList: $.getJSON('user/play_log/index/option/get_room')},
                        {field: 'room_name', title: __('房间名'), operate: false, formatter: function (value, row, index) {
                                if(value) return value;
                                if(row.room_id==0) return 'app在线时长';
                                if(row.room_id>10000000) return  '个人房';
                            }},
                        {field: 'second', title: __('Second'),operate: false},
                        {field: 'user.appid', title: __('渠道'),searchList: $.getJSON('user/play_log/index/option/get_channel'), formatter:function (value, row, index){
                                return row.channel;
                            }},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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
