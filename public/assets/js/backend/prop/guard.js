define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'prop/guard/index' + location.search,
                    add_url: 'prop/guard/add',
                    edit_url: 'prop/guard/edit',
                    del_url: 'prop/guard/del',
                    multi_url: 'prop/guard/multi',
                    import_url: 'prop/guard/import',
                    table: 'guard',
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
                        /*{checkbox: true},
                        {field: 'id', title: __('Id')},*/
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'medal_image', title: __('Medal_image'), operate: false, formatter: Controller.api.formatter.svga},
                        {field: 'explain', title: __('Explain'), operate: false},
                        {field: 'pay_need_vital', title: __('Pay_need_vital')},
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
                    var suffix = value.substring(value.lastIndexOf(".")+1);
                    if (suffix == 'svga') {
                        return '<a class="svga_click" data-params="'+value+'">播放</a>';
                    }
                    return value;
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
