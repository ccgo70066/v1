define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'prop/noble/index' + location.search,
                    add_url: 'prop/noble/add',
                    edit_url: 'prop/noble/edit',
                    del_url: 'prop/noble/del',
                    multi_url: 'prop/noble/multi',
                    import_url: 'prop/noble/import',
                    table: 'noble',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                search: false,
                columns: [
                    [
                       /* {checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'badge', title: __('Badge'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'shop_badge', title: __('Shop_badge'), operate: false,  formatter: Controller.api.formatter.svga},
                        {field: 'speedup', title: __('Speedup'), operate: false},
                        {field: 'weigh', title: __('Weigh'), operate: false,sortable: true},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false,sortable: true},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            $(document).on('click', '.svga_click', function () {
                Controller.api.events.svga($(this).data('params'));
            });
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
            },
            formatter: {
                svga: function (value, row, index) {

                    if (value) return '<a class="svga_click" data-params="' + value + '">播放</a>'
                }
            },
            events: {
                svga: function (value) {
                    var layer_index = Layer.load(1);
                    var player = new SVGA.Player('#demoCanvas');
                    var parser = new SVGA.Parser('#demoCanvas');
                    parser.load(Backend.api.cdnurl(value), function (videoItem) {
                        $('#demoCanvas').show();
                        Layer.close(layer_index);
                        player.loops = 1;
                        player.clearsAfterStop = true;
                        player.setVideoItem(videoItem);
                        player.startAnimation();
                        player.onFinished(function () {
                            $('#demoCanvas').hide();
                        });
                    });
                }
            }
        }
    };
    return Controller;
});
