define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'prop/tail/index' + location.search,
                    add_url: 'prop/tail/add',
                    edit_url: 'prop/tail/edit',
                    del_url: 'prop/tail/del',
                    multi_url: 'prop/tail/multi',
                    import_url: 'prop/tail/import',
                    table: 'tail',
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
                search: false,
                columns: [
                    [
                        /*{checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'face_image', title: __('Face_image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'cover', title: __('Cover'), operate: false, formatter: Controller.api.formatter.svga},
                        {field: 'days', title: __('Days'),operate: false},
                        {field: 'is_renew', title: __('Is_renew'), searchList: {"0":__('Is_renew 0'),"1":__('Is_renew 1')}, formatter: Table.api.formatter.normal},
                        {field: 'is_sell', title: __('Is_sell'), searchList: {"0":__('Is_sell 0'),"1":__('Is_sell 1')}, formatter: Table.api.formatter.normal},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Status 1'),"0":__('Status 0')}, formatter: Table.api.formatter.status},
                        {field: 'intro', title: __('Intro'), operate: false},
                        {field: 'weigh', title: __('Weigh'), operate: false,sortable: true},
                        // {field: 'create_time', title: __('Create_time'), operate: false, addclass:'datetimerange', autocomplete:false,sortable: true},
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
