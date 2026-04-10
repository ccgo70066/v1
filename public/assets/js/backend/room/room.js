define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'room/room/index' + location.search,
                    // add_url: 'room/room/add',
                    edit_url: 'room/room/edit',
                    // multi_url: 'room/room/multi',
                    detail_url: 'room/room/index',
                    table: 'room',
                }
            });

            var table = $("#table");
            table.on('post-body.bs.table',function (){
                $('.btn-editone').data('area',['500px','500px']);
                $('.btn-lucky').data('area',['35%','50%']);

            });
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'asc',
                search: false,
                columns: [
                    [
                        // {checkbox: true},
                        {field: 'id', title: __('Id'), operate: 'like', sortable:true},
                        {field: 'beautiful_id', title:__('Beautiful_id'), operate: 'like'},
                        {field: 'cover', title: __('Cover'),formatter: Table.api.formatter.image,events: Table.api.events.image, operate: false},
                        {field: 'name', title: __('Name'), operate: 'like'},
                        {field: 'owner_id', title: __('厅主ID')},
                        {field: 'im_roomid', title: __('IM房间号'),operate: false},
                        {field: 'theme_id', title: __('Roomthemecate.name'),visible:false, searchList: $.getJSON('prop/room_theme_cate/get_cate_list'), formatter: Table.api.formatter.status},
                        {field: 'roomthemecate.name', title: __('Roomthemecate.name'), operate: false},
                        {field: 'hot', title: __('Hot'), operate: false},
                        {field: 'is_lock', title: __('Is_lock'), searchList: {"1":__('Is_lock 1'),"0":__('Is_lock 0')}, formatter: Table.api.formatter.normal, operate: false},
                        // {field: 'is_commend', title: __('Is_commend'), searchList: {"1":__('Is_commend 1'),"0":__('Is_commend 0')}, formatter: Table.api.formatter.normal, operate: false},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"-1":__('Status -1')}, formatter: Table.api.formatter.status},
                        {field: 'admin_name', title: __('审核人'),operate: false},
                        {field: 'is_show', title: __('Is_show'), searchList: {"1":__('Is_show 1'),"0":__('Is_show 0')}, formatter: Table.api.formatter.normal},
                        {field: 'union_id', title: __('Union_id')},
                        {field: 'union.name', title: __('Union.name'), operate: false},
                        {field: 'show_sort', title: __('Show_sort'),operate: false},
                        // {field: 'create_time', title: __('Create_time'), addclass:'datetimerange', formatter: Table.api.formatter.datetime, operate: false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'delete',
                                    text: '删除房间',
                                    title: '删除房间',
                                    icon: 'fa',
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    // extend: 'data-area=\'["40%", "65%"]\'',
                                    url: 'room/room/delete',
                                    confirm: '删除后APP和后台不再展示且无法再找回,确定删除吗?',
                                    visible: function (row) {
                                        return row.status != -1;
                                    },
                                    success: function () {
                                        $(".btn-refresh").trigger("click");
                                    }
                                },
                                {
                                    name: 'delete',
                                    text: '确认删除',
                                    title: '确认删除',
                                    icon: 'fa',
                                    classname: 'btn btn-xs btn-warning btn-magic btn-ajax',
                                    // extend: 'data-area=\'["40%", "65%"]\'',
                                    url: 'room/room/delete',
                                    confirm: '删除后APP和后台不再展示且无法再找回,确定删除吗?',
                                    visible: function (row) {
                                        return row.status == -1;
                                    },
                                    success: function () {
                                        $(".btn-refresh").trigger("click");
                                    }
                                },
                                {
                                    name: 'reject_delete',
                                    text: '驳回删除',
                                    title: '驳回删除',
                                    icon: 'fa',
                                    classname: 'btn btn-xs btn-warning btn-magic btn-ajax',
                                    // extend: 'data-area=\'["40%", "65%"]\'',
                                    url: 'room/room/reject_delete',
                                    confirm: '确定驳回删除吗?',
                                    visible: function (row) {
                                        return row.status == -1;
                                    },
                                    success: function () {
                                        $(".btn-refresh").trigger("click");
                                    }
                                },
                                {

                                    name: 'master_log',
                                    text: '厅主变更记录',
                                    title: '厅主变更记录',
                                    icon: 'fa',
                                    classname: 'btn btn-xs btn-default btn-magic btn-dialog',
                                    // extend: 'data-area=\'["40%", "65%"]\'',
                                    url: 'room/room/master_log',
                                },
                                {
                                    name: 'update_beautiful',
                                    text: '更改靓号',
                                    title: '更改靓号',
                                    icon: 'fa',
                                    classname: 'btn btn-xs btn-warning btn-magic btn-dialog',
                                    // extend: 'data-area=\'["40%", "65%"]\'',
                                    url: 'room/room/update_beautiful',
                                }],
                            formatter: Table.api.formatter.operate
                        },

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
        delete:function () {
            Controller.api.bindevent();
        },
        update_beautiful: function () {
            Controller.api.bindevent();
        },
        create: function () {

            Form.api.bindevent($("form[role=form]"),function(){},function(){},function(){

                Layer.confirm('敏感操作,请仔细核对和确认,确认进行吗?',{
                    title: '提示',
                    btn: ['确认无误','取消'] //按钮
                },function (index) {
                    Form.api.submit($("form[role=form]"),function (){
                        parent.Toastr.success('提交成功');
                        parent.$(".btn-refresh").trigger("click");
                        var index_top = parent.Layer.getFrameIndex(window.name);
                        parent.Layer.close(index_top);
                    },function (){},function () {
                        Layer.close(index);
                        // var index_top = parent.Layer.getFrameIndex(window.name);
                        // parent.Layer.close(index_top);
                    });
                },function (){
                });
                return false;
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
