define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/account/index' + location.search,
                    add_url: 'user/account/add',
                    edit_url: 'user/account/edit',
                    del_url: 'user/account/del',
                    multi_url: 'user/account/multi',
                    import_url: 'user/account/import',
                    table: 'user_account',
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
                        {field: 'user.nickname', title: __('用户昵称'),operate: false},
                        {field: 'account_name', title: __('Account_name'), operate: 'LIKE'},
                        {field: 'bank_name', title: __('Bank_name'), operate: false},
                        {field: 'bank_number', title: __('Bank_number'), operate: false},
                        {field: 'bank_logo', title: __('Bank_logo'), events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false},
                        {field: 'branch_name', title: __('Branch_name'), operate: false},
                        {field: 'province_city', title: __('Province_city'), operate: false},
                        {field: 'alipay_number', title: __('Alipay_number'), operate: false},
                        {field: 'alipay_name', title: __('Alipay_name'), operate: false},
                        {field: 'mobile', title: __('Mobile'), operate: false},
                        {field: 'card_number', title: __('Card_number'), operate: false},
                        {field: 'card_front_img', title: __('Card_front_img'), events: Table.api.events.image, formatter: function (value, row, index){
                                if (row.card_front_img) {
                                    return Table.api.formatter.image.call(this, row.card_front_img, row, index);
                                }
                                return '';
                            }, operate: false},
                        {field: 'card_back_img', title: __('Card_back_img'), events: Table.api.events.image, formatter: function (value, row, index){
                                if (row.card_back_img) {
                                    return Table.api.formatter.image.call(this, row.card_back_img, row, index);
                                }
                                return '';
                            }, operate: false},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        /*{field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}*/
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
