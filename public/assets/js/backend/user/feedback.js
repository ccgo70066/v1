define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/feedback/index' + location.search,
                    add_url: 'user/feedback/add',
                    edit_url: 'user/feedback/edit',
                    //del_url: 'user/feedback/del',
                    multi_url: 'user/feedback/multi',
                    import_url: 'user/feedback/import',
                    table: 'user_feedback',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                search: false,
                columns: [
                    [
                        /*{checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'user_id', title: __('User_id')},
                        {field: 'form', title: __('Form'), searchList: {"1":__('Form 1'),"2":__('Form 2')}, formatter: Table.api.formatter.normal},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2'),"3":__('Type 3')}, formatter: Table.api.formatter.normal},
                        {field: 'tag', title: __('Tag'), operate: 'LIKE', formatter: Table.api.formatter.flag},
                        {field: 'target_id', title: __('Target_id')},
                        //{field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'image', title: __('Image'),operate: false,formatter:function (value, row, index){
                                if (row.image) {
                                    return Table.api.formatter.images.call(this, row.image, row, index);
                                }
                            },events: Table.api.events.image},
                        {field: 'comment', title: __('Comment'),cellStyle: function () {return {css: {"min-width": "80px","max-width": "100px","text-overflow": "ellipsis", "white-space": "initial","word-wrap":"break-word","word-break":"break-all"}};},operate: false},
                        {field: 'audit_remark', title: __('Audit_remark'), operate: 'LIKE',cellStyle: function () {return {css: {"min-width": "80px","max-width": "100px","text-overflow": "ellipsis", "white-space": "initial","word-wrap":"break-word","word-break":"break-all"}};}},
                        {field: 'audit_status', title: __('Audit_status'), searchList: {"1":__('Audit_status 1'),"2":__('Audit_status 2'),"3":__('Audit_status 3')}, formatter: Table.api.formatter.normal},
                        {field: 'admin_name', title: __('Audit_admin'),operate: false},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        //{field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'edit',
                                    text: '处理',
                                    title: '处理',
                                    classname: 'btn btn-xs btn-warning btn-dialog',
                                    extend: 'data-toggle="tooltip"',
                                    icon: 'fa fa-check',
                                    url: 'user/feedback/edit',
                                    refresh: true,
                                    visible: function (row) {
                                        return (row.audit_status==1)?true:false;
                                    }
                                },
                            ],
                            formatter: Table.api.formatter.operate}
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
                // Form.api.bindevent($("form[role=form]"));
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
        }
    };
    return Controller;
});
