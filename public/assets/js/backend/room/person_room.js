define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'room/person_room/index' + location.search,
                    // add_url: 'room/person_room/add',
                    // edit_url: 'room/person_room/edit',
                    // del_url: 'room/person_room/del',
                    // multi_url: 'room/person_room/multi',
                    detail_url: 'room/person_room/index',
                    table: 'room',
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
                        {field: 'id', title: __('ID')},
                        {field: 'beautiful_id', title: __('Beautiful_id')},
                        {field: 'name', title: __('Name'), operate: 'like'},
                        {field: 'cover', title: __('Cover'), events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false},
                        {field: 'owner_id', title: __('Owner_id')},
                        {field: 'room_cate', title: __('分类'), operate: false},
                        {field: 'is_close', title: __('Is_close'), searchList: {"0":__('Is_close 0'),"1":__('Is_close 1')}, formatter: Table.api.formatter.normal},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3')}, formatter: Table.api.formatter.normal},
                        {field: 'create_time', title: __('Createtime'),  addclass:'datetimerange', formatter: Table.api.formatter.datetime, operate: false},
                        {field: 'im_roomid', title: __('Im_roomid'), operate: false},
                        {field: 'status', title: __('Status'), operate: false,visible:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,

                            buttons: [
                                {
                                    name: 'close',
                                    text: '封禁房间',
                                    title:'封禁房间',
                                    icon: 'fa',
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    url: 'room/person_room/close',
                                    refresh:true,
                                    confirm: '确定要封禁此房间吗？',
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        if (row.status == 0) return  false;
                                        return true;
                                    },
                                },
                                {
                                    name: 'open',
                                    text: '解封房间',
                                    title:'解封房间',
                                    icon: 'fa',
                                    classname: 'btn btn-xs btn-default btn-magic btn-ajax',
                                    url: 'room/person_room/open',
                                    refresh:true,
                                    confirm: '确定要解封此房间吗？',
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        if (row.status == 0) return  true;
                                    },
                                }],
                            formatter: Table.api.formatter.operate
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
        close: function () {
            Controller.api.bindevent();
        },
        open: function () {
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
