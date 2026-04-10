define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'general/parcel/index' + location.search,
                    add_url: 'general/parcel/add',
                    edit_url: 'general/parcel/edit',
                    del_url: 'general/parcel/del',
                    multi_url: 'general/parcel/multi',
                    import_url: 'general/parcel/import',
                    table: 'parcel',
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
                        /*{checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'type', title: __('Type'), searchList: {"2":__('Type 2')}, formatter: Table.api.formatter.normal},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        // {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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
