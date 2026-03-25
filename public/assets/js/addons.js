define([], function () {
    require(['fast', 'layer'], function (Fast, Layer) {
    var _fastOpen = Fast.api.open;
    Fast.api.open = function (url, title, options) {
        options = options || {};
        options.area = Config.betterform.area;
        options.offset = Config.betterform.offset;
        options.anim = Config.betterform.anim;
        options.shadeClose = Config.betterform.shadeClose;
        options.shade = Config.betterform.shade;
        return _fastOpen(url, title, options);
    };
    if (isNaN(Config.betterform.anim)) {
        var _layerOpen = Layer.open;
        Layer.open = function (options) {
            var classNameArr = {slideDown: "layer-anim-slide-down", slideLeft: "layer-anim-slide-left", slideUp: "layer-anim-slide-up", slideRight: "layer-anim-slide-right"};
            var animClass = "layer-anim " + classNameArr[options.anim] || "layer-anim-fadein";
            var index = _layerOpen(options);
            var layero = $('#layui-layer' + index);

            layero.addClass(classNameArr[options.anim] + "-custom");
            layero.addClass(animClass).one('webkitAnimationEnd mozAnimationEnd MSAnimationEnd oanimationend animationend', function () {
                $(this).removeClass(animClass);
            });
            return index;
        }
    }
});
require.config({
    paths: {
        'editable': '../libs/bootstrap-table/dist/extensions/editable/bootstrap-table-editable.min',
        'x-editable': '../addons/editable/js/bootstrap-editable.min',
    },
    shim: {
        'editable': {
            deps: ['x-editable', 'bootstrap-table']
        },
        "x-editable": {
            deps: ["css!../addons/editable/css/bootstrap-editable.css"],
        }
    }
});
if ($("table.table").length > 0) {
    require(['editable', 'table'], function (Editable, Table) {
        $.fn.bootstrapTable.defaults.onEditableSave = function (field, row, oldValue, $el) {
            var data = {};
            data["row[" + field + "]"] = row[field];
            Fast.api.ajax({
                url: this.extend.edit_url + "/ids/" + row[this.pk],
                data: data
            });
        };
    });
}

require.config({
    paths: {
        'summernote': '../addons/summernote/lang/summernote-zh-CN.min',
        'purify': '../addons/summernote/js/purify.min'
    },
    shim: {
        'summernote': ['../addons/summernote/js/summernote.min', 'css!../addons/summernote/css/summernote.min.css'],
    }
});
require(['form', 'upload'], function (Form, Upload) {
    var _bindevent = Form.events.bindevent;
    Form.events.bindevent = function (form) {
        _bindevent.apply(this, [form]);
        try {
            //绑定summernote事件
            if ($(Config.summernote.classname || '.editor', form).length > 0) {
                var selectUrl = typeof Config !== 'undefined' && Config.modulename === 'index' ? 'user/attachment' : 'general/attachment/select';
                require(['summernote', 'purify'], function (undefined, DOMPurify) {
                    var imageButton = function (context) {
                        var ui = $.summernote.ui;
                        var button = ui.button({
                            contents: '<i class="fa fa-file-image-o"/>',
                            tooltip: __('Choose'),
                            click: function () {
                                parent.Fast.api.open(selectUrl + "?element_id=&multiple=true&mimetype=image/", __('Choose'), {
                                    callback: function (data) {
                                        var urlArr = data.url.split(/\,/);
                                        $.each(urlArr, function () {
                                            var url = Fast.api.cdnurl(this, true);
                                            context.invoke('editor.insertImage', url);
                                        });
                                    }
                                });
                                return false;
                            }
                        });
                        return button.render();
                    };
                    var attachmentButton = function (context) {
                        var ui = $.summernote.ui;
                        var button = ui.button({
                            contents: '<i class="fa fa-file"/>',
                            tooltip: __('Choose'),
                            click: function () {
                                parent.Fast.api.open(selectUrl + "?element_id=&multiple=true&mimetype=*", __('Choose'), {
                                    callback: function (data) {
                                        var urlArr = data.url.split(/\,/);
                                        $.each(urlArr, function () {
                                            var url = Fast.api.cdnurl(this, true);
                                            var node = $("<a href='" + url + "'>" + url + "</a>");
                                            context.invoke('insertNode', node[0]);
                                        });
                                    }
                                });
                                return false;
                            }
                        });
                        return button.render();
                    };
                    if (Config.summernote.isdompurify) {
                        // 添加 hook 过滤 iframe 来源
                        DOMPurify.addHook('uponSanitizeElement', function (node, data, config) {
                            if (data.tagName === 'iframe') {
                                var allowedIframePrefixes = Config.summernote.allowiframeprefixs || [];
                                var src = node.getAttribute('src');

                                // 判断是否匹配允许的前缀
                                var isAllowed = false;
                                for (var i = 0; i < allowedIframePrefixes.length; i++) {
                                    if (src && src.indexOf(allowedIframePrefixes[i]) === 0) {
                                        isAllowed = true;
                                        break;
                                    }
                                }

                                if (!isAllowed) {
                                    // 不符合要求则移除该节点
                                    return node.parentNode.removeChild(node);
                                }

                                // 添加安全属性
                                node.setAttribute('allowfullscreen', '');
                                node.setAttribute('allow', 'fullscreen');
                            }
                        });

                        var purifyOptions = {
                            ADD_TAGS: ['iframe'],
                            FORCE_REJECT_IFRAME: false
                        };
                        $.extend($.summernote.plugins, {
                            'dompurify': function (context) {
                                // 重写代码过滤方法
                                const originalPurify = context.options.modules.codeview.prototype.purify;
                                context.options.modules.codeview.prototype.purify = function (html) {
                                    html = DOMPurify.sanitize(html, purifyOptions);
                                    return originalPurify.call(this, html);
                                };
                            }
                        });
                    }

                    $(Config.summernote.classname || '.editor', form).each(function () {
                        $(this).summernote($.extend(true, {}, {
                            height: isNaN(Config.summernote.height) ? null : parseInt(Config.summernote.height),
                            minHeight: parseInt(Config.summernote.minHeight || 250),
                            toolbar: Config.summernote.toolbar,
                            followingToolbar: parseInt(Config.summernote.followingToolbar),
                            placeholder: Config.summernote.placeholder || '',
                            airMode: parseInt(Config.summernote.airMode) || false,
                            lang: 'zh-CN',
                            fontNames: Config.summernote.fontNames || [],
                            fontNamesIgnoreCheck: ["Open Sans", "Microsoft YaHei", '微软雅黑', '宋体', '黑体', '仿宋', '楷体', '幼圆'],
                            buttons: {
                                image: imageButton,
                                attachment: attachmentButton,
                            },
                            plugins: {
                                'dompurify': true
                            },
                            dialogsInBody: true,
                            callbacks: {
                                onChange: function (contents) {
                                    if (Config.summernote.isdompurify) {
                                        contents = DOMPurify.sanitize(contents, purifyOptions);
                                    }
                                    $(this).val(contents);
                                    $(this).trigger('change');
                                },
                                onInit: function () {
                                },
                                onImageUpload: function (files) {
                                    var that = this;
                                    //依次上传图片
                                    for (var i = 0; i < files.length; i++) {
                                        Upload.api.send(files[i], function (data) {
                                            var url = Fast.api.cdnurl(data.url, true);
                                            $(that).summernote("insertImage", url, 'filename');
                                        });
                                    }
                                },
                                onPaste: function (e) {
                                    if (Config.summernote.pasteAsPlainText || false) {
                                        var bufferText = ((e.originalEvent || e).clipboardData || window.clipboardData).getData('Text');
                                        e.preventDefault();
                                        setTimeout(function () {
                                            document.execCommand('insertText', false, bufferText);
                                        }, 10);
                                    }
                                }
                            }
                        }, $(this).data("summernote-options") || {}));
                    });
                });
            }
        } catch (e) {

        }

    };
});

});