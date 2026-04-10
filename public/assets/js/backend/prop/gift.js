define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'prop/gift/index' + location.search,
                    add_url: 'prop/gift/add',
                    edit_url: 'prop/gift/edit',
                    del_url: 'prop/gift/del',
                    multi_url: 'prop/gift/multi',
                    import_url: 'prop/gift/import',
                    table: 'gift',
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
                hideColumn: 'type1',
                columns: [
                    [
                        {checkbox: true},
                        /*{field: 'id', title: __('Id')},*/
                        {
                            field: 'type',
                            title: __('Type'),
                            operate: false,
                            searchList: {
                                "1": __('Type 1'),
                                "2": __('Type 2'),
                                "3": __('Type 3'),
                                "4": __('Type 4'),
                                // "5": __('Type 5'),
                                "6": __('Type 6'),
                            },
                            formatter: Table.api.formatter.normal
                        },
                        {
                            field: 'cate',
                            title: __('Cate'),
                            operate: false,
                            searchList: {
                                "10": __('热门'),
                                "11": __('专场'),
                                "12": __('特权'),
                                "13": __('土豪'),
                                "20": __('穹翼银龛'),
                                "21": __('炎凰金匣'),
                                "22": __('紫霄龍龕')
                            },
                            formatter: Table.api.formatter.normal
                        },
                        {
                            field: 'type1',visible:false, title: __('Type'), searchList: function () {
                                return Template('categorytpl', {});
                            },
                        },
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {
                            field: 'image',
                            title: __('Image'),
                            operate: false,
                            events: Table.api.events.image,
                            formatter: Table.api.formatter.image
                        },
                        //{field: 'animate', title: __('Animate'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {
                            field: 'animate',
                            title: __('Animate'),
                            operate: false,
                            formatter: Controller.api.formatter.svga
                        },
                        {field: 'price', title: __('Price'), operate: false},
                        {field: 'note', title: __('Note'), operate: false},
                        {field: 'weigh', title: __('Weigh'), operate: false, sortable: true},
                        {
                            field: 'screen_show',
                            title: __('Screen_show'),
                            searchList: {"0": __('Screen_show 0'), "1": __('Screen_show 1'), "2": __('Screen_show 2')},
                            formatter: Table.api.formatter.normal
                        },
                        // {
                        //     field: 'notice',
                        //     title: __('Notice'),
                        //     searchList: {"1": __('Notice 1'), "0": __('Notice 0')},
                        //     formatter: Table.api.formatter.normal
                        // },
                        {
                            field: 'status',
                            title: __('Status'),
                            searchList: {"1": __('Status 1'), "0": __('Status 0')},
                            formatter: Table.api.formatter.status
                        },
                        // { field: 'create_time', title: __('Create_time'), operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, sortable: true },
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            $(document).on('click', '.svga_click', function () {
                Controller.api.events.svga($(this).data('params'));
            });

            $(document).on('click', '.btn-push', function (e) {
                e.preventDefault();
                var ids = Table.api.selectedids(table);
                Layer.confirm('确认要推送所选中的礼物吗?', {
                    title: '提示',
                    btn: ['确认', '取消'] //按钮
                }, function (index) {
                    Fast.api.ajax({url: 'gift/gift/push', data: {ids: ids},}, function (r) {
                        Layer.close(index);
                    }, function (r) {
                        Layer.close(index);
                    });
                }, function () {
                });

            });
            Form.events.cxselect(table);
        },
        add: function () {
            $('#c-type').change(function () {
                var type = $(this).val();
                $.ajax({
                    url: 'prop/gift/select_type',
                    type: 'get',
                    data: {type: type},
                    success: function (res) {
                        var searchLists = res.data;
                        $('#c-cate option').remove();
                        $.each(searchLists, function (key, value) {
                            if (value.constructor === Object) {
                                key = value.value;
                                value = value.name;
                            } else {
                                key = isArray ? value : key;
                            }
                            $('#c-cate').append("<option value='" + key + "' >" + value + "</option>");
                        });
                    }
                });
            });

            $('#c-cate').change(function () {
                var cate = $(this).val();

                if (cate === 12 || cate === '12') {
                    $('#noble_limit_div').show();
                } else {

                    $('#noble_limit_div').hide();

                }
            });

            Controller.api.bindevent();
        },
        edit: function () {
            var type = $("#c-type").val();
            var cate = $("#c-cate").val();
            $.ajax({
                url: 'prop/gift/select_type',
                type: 'get',
                data: {type: type},
                success: function (res) {
                    var searchLists = res.data;
                    $('#c-cate option').remove();
                    $.each(searchLists, function (key, value) {
                        if (value.constructor === Object) {
                            key = value.value;
                            value = value.name;
                        } else {
                            key = isArray ? value : key;
                        }
                        var selected = '';
                        if (key == cate) {
                            selected = 'selected';
                        }
                        $('#c-cate').append("<option value='" + key + "' " + selected + ">" + value + "</option>");
                    });
                }
            });

            $('#c-type').change(function () {
                var type = $(this).val();
                $.ajax({
                    url: 'prop/gift/select_type',
                    type: 'get',
                    data: {type: type},
                    success: function (res) {
                        var searchLists = res.data;
                        $('#c-cate option').remove();
                        $.each(searchLists, function (key, value) {
                            if (value.constructor === Object) {
                                key = value.value;
                                value = value.name;
                            } else {
                                key = isArray ? value : key;
                            }
                            $('#c-cate').append("<option value='" + key + "' >" + value + "</option>");
                        });
                    }
                });
            });

            $('#c-cate').change(function () {
                var cate = $(this).val();
                if (cate === 12 || cate === '12') {
                    $('#noble_limit_div').show();
                } else {

                    $('#noble_limit_div').hide();

                }
            });


            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
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
                Form.api.bindevent($("form[role=form]"), function () {
                }, function () {
                }, function () {
                    Layer.confirm('敏感操作,请仔细核对和确认,确认进行吗?', {
                        title: '提示',
                        btn: ['确认无误', '取消'] //按钮
                    }, function (index) {
                        Form.api.submit($("form[role=form]"), function () {
                            // parent.Toastr.success('提交成功');
                            parent.$(".btn-refresh").trigger("click");
                            var index_top = parent.Layer.getFrameIndex(window.name);
                            parent.Layer.close(index_top);
                        }, function () {
                        }, function () {
                            Layer.close(index);
                            // var index_top = parent.Layer.getFrameIndex(window.name);
                            // parent.Layer.close(index_top);
                        });
                    }, function () {
                    });
                    return false;
                });
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
