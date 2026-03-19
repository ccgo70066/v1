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