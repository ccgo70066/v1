define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'channel/card/index' + location.search,
                    add_url: 'channel/card/add',
                    edit_url: 'channel/card/edit',
                    del_url: 'channel/card/del',
                    // multi_url: 'channel/card/multi',
                    import_url: 'channel/card/import',
                    table: 'channel_card',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        // {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'code', title: __('Code'), operate: 'LIKE'},
                        {field: 'price', title: __('Price'), operate:false},
                        {field: 'amount', title: __('Amount')},
                        {field: 'give_amount', title: __('额外赠送钻石')},
                        {field: 'system', title: __('System'), searchList: {"Android":__('System android'),"iOS":__('System ios'),"Web":__('System web')}, formatter: Table.api.formatter.normal},
                        {field: 'bage', title: __('Bage'), searchList: {"0":__('Bage 0'),"1":__('Bage 1'),"2":__('Bage 2')}, formatter: Table.api.formatter.normal},
                        {field: 'weigh', title: __('Weigh'), operate: false},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        // {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            $(document).on("fa.event.appendfieldlist", ".btn-append", function (res, data) {
                var tlen = $(".form-inline").length;
                for (var i = 0; i < tlen; i++) {
                    var name_class = "#reward-name-ids-" + i;
                    var type_class = "#reward-type-ids-" + i;
                    var val = {type: $(type_class).val()};
                    $(name_class).data("params", val);
                    $(type_class).data('index', i);
                    $(type_class).on('change', function (o) {
                        var i = $(this).data('index');
                        var name_class = "#reward-name-ids-" + i;
                        var type_class = "#reward-type-ids-" + i;
                        $(name_class + '_text').data("selectPageObject").option.params = {type: $(type_class).val()};
                        $(name_class + '_text').selectPageClear();
                    });
                }
                Form.events.selectpage($(".fieldlist"));
            });

            Controller.api.bindevent();
        },
        edit: function () {
            $(document).on("fa.event.appendfieldlist", ".btn-append", function (res, data) {
                var tlen = $(".form-inline").length;
                for (var i = 0; i < tlen; i++) {
                    var type_class = "#reward-type-ids-" + i;
                    var name_class = "#reward-name-ids-" + i;
                    var val = {type: $(type_class).val()};
                    $(name_class).data("params", val);
                    $(type_class).data('index', i);
                    $(type_class).on('change', function (o) {
                        var i = $(this).data('index');
                        var name_class = "#reward-name-ids-" + i;
                        var type_class = "#reward-type-ids-" + i;
                        $(name_class + '_text').data("selectPageObject").option.params = {type: $(type_class).val()};
                        $(name_class + '_text').selectPageClear();
                    });
                }
                Form.events.selectpage($(".fieldlist"));
            });

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
