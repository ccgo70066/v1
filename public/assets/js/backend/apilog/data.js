define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'echarts', 'echarts-theme', 'template'], function ($, undefined, Backend, Datatable, Table, Echarts, undefined, Template) {

    var Controller = {
        index: function () {
            $(".datetimerange").data('callback', function (start, end) {
                loadData(start / 1000, end / 1000);
            });
            if ($(".datetimerange").size() > 0) {
                require(['bootstrap-daterangepicker'], function () {
                    var ranges = {};
                    ranges[__('Today')] = [Moment().startOf('day'), Moment().endOf('day')];
                    ranges[__('Yesterday')] = [Moment().subtract(1, 'days').startOf('day'), Moment().subtract(1, 'days').endOf('day')];
                    ranges[__('Last 7 Days')] = [Moment().subtract(6, 'days').startOf('day'), Moment().endOf('day')];
                    ranges[__('Last 30 Days')] = [Moment().subtract(29, 'days').startOf('day'), Moment().endOf('day')];
                    ranges[__('This Month')] = [Moment().startOf('month'), Moment().endOf('month')];
                    ranges[__('Last Month')] = [Moment().subtract(1, 'month').startOf('month'), Moment().subtract(1, 'month').endOf('month')];
                    var options = {
                        dateLimit: { months: 2 },
                        timePicker: true,
                        autoUpdateInput: false,
                        timePickerSeconds: true,
                        timePicker24Hour: true,
                        autoApply: true,
                        locale: {
                            format: 'YYYY/MM/DD HH:mm:ss',
                            customRangeLabel: __("Custom Range"),
                            applyLabel: __("Apply"),
                            cancelLabel: __("Clear"),
                        },
                        ranges: ranges,
                    };
                    var origincallback = function (start, end) {
                        $(this.element).val(start.format(this.locale.format) + " - " + end.format(this.locale.format));
                        $(this.element).trigger('blur');
                    };

                    $(".datetimerange").each(function () {
                        var callback = typeof $(this).data('callback') == 'function' ? $(this).data('callback') : origincallback;
                        $(this).on('apply.daterangepicker', function (ev, picker) {                           
                            origincallback.call(picker, picker.startDate, picker.endDate);
                        });
                        $(this).on('cancel.daterangepicker', function (ev, picker) {
                            $(this).val('').trigger('blur');
                        });
                        $(this).daterangepicker($.extend(true, options, $(this).data()), callback);
                    });
                });
            }
            //datafilter
            $(document).on('click', '.datefilter .btn', function (e) {
                var type = $(this).data('type');
                var endDate = Moment().unix();
                if (type == 1) {
                    var startDate = Moment().subtract(15, 'minutes').unix();
                }
                if (type == 2) {
                    var startDate = Moment().subtract(30, 'minutes').unix();
                }
                if (type == 3) {
                    var startDate = Moment().subtract(1, 'hours').unix();
                }
                if (type == 4) {
                    var startDate = Moment().subtract(4, 'hours').unix();
                }
                if (type == 5) {
                    var startDate = Moment().subtract(12, 'hours').unix();
                }
                if (type == 6) {
                    var startDate = Moment().subtract(1, 'days').unix();
                }
               
              
                loadData(startDate, endDate);
            });

            var codeChart = Echarts.init(document.getElementById('code-chart'), 'dark');
            var codeoption = {
                title: {
                    text: '请求状态码',
                    left: 'left'
                },
                tooltip: {
                    trigger: 'item',
                    formatter: '{a} <br/>{b} : {c} ({d}%)'
                },
                legend: {
                    type: 'scroll',
                    orient: 'vertical',
                    right: 10,
                    top: 20,
                    bottom: 20,
                    data: ['a', 'b', 'c'],
                },
                series: [
                    {
                        name: '请求状态码',
                        type: 'pie',
                        radius: '55%',
                        center: ['40%', '50%'],
                        data: [{ 'name': 'a', value: 1 }, { 'name': 'b', value: 2 }, { 'name': 'c', value: 3 }],
                        emphasis: {
                            itemStyle: {
                                shadowBlur: 10,
                                shadowOffsetX: 0,
                                shadowColor: 'rgba(0, 0, 0, 0.5)'
                            }
                        }
                    }
                ]
            };
            codeChart.setOption(codeoption);



            var timeChart = Echarts.init(document.getElementById('time-chart'), 'dark');
            var timeoption = {
                title: {
                    text: '请求处理时间(ms)',
                    left: 'left'
                },
                tooltip: {
                    trigger: 'item',
                    formatter: '{a} <br/>{b} : {c} ({d}%)'
                },
                legend: {
                    type: 'scroll',
                    orient: 'vertical',
                    right: 10,
                    top: 20,
                    bottom: 20,
                    data: ['a', 'b', 'c'],
                },
                series: [
                    {
                        name: '请求处理时间(ms)',
                        type: 'pie',
                        radius: '55%',
                        center: ['40%', '50%'],
                        data: [{ 'name': 'a', value: 1 }, { 'name': 'b', value: 2 }, { 'name': 'c', value: 3 }],
                        emphasis: {
                            itemStyle: {
                                shadowBlur: 10,
                                shadowOffsetX: 0,
                                shadowColor: 'rgba(0, 0, 0, 0.5)'
                            }
                        }
                    }
                ]
            };
            timeChart.setOption(timeoption);


            var requestChart = Echarts.init(document.getElementById('request-chart'), 'dark');
            var requestoption = {
                title: {
                    text: '最多请求 TOP15',
                },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        type: 'shadow'
                    }
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                xAxis: {
                    type: 'value',
                    boundaryGap: [0, 0.01]
                },
                yAxis: {
                    inverse: true,
                    type: 'category',
                    data: ['巴西', '印尼', '美国', '印度', '中国', '世界人口(万)']
                },
                series: [
                    {
                        name: '次数',
                        type: 'bar',
                        data: [18203, 23489, 29034, 104970, 131744, 630230]
                    }

                ]
            };
            requestChart.setOption(requestoption);

            var errorChart = Echarts.init(document.getElementById('error-chart'), 'dark');
            var erroroption = {
                title: {
                    text: '请求错误 TOP15',
                },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        type: 'shadow'
                    }
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                xAxis: {
                    type: 'value',
                    boundaryGap: [0, 0.01]
                },
                yAxis: {
                    inverse: true,
                    type: 'category',
                    data: ['巴西', '印尼', '美国', '印度', '中国', '世界人口(万)']
                },
                series: [
                    {
                        name: '次数',
                        type: 'bar',
                        data: [18203, 23489, 29034, 104970, 131744, 630230]
                    }

                ]
            };
            errorChart.setOption(erroroption);


            var fastChart = Echarts.init(document.getElementById('fast-chart'), 'dark');
            var fastoption = {
                title: {
                    text: '平均处理时间最快 TOP15',
                },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        type: 'shadow'
                    }
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                xAxis: {
                    type: 'value',
                    boundaryGap: [0, 0.01]
                },
                yAxis: {
                    inverse: true,
                    type: 'category',
                    data: ['巴西', '印尼', '美国', '印度', '中国', '世界人口(万)']
                },
                series: [
                    {
                        name: '耗时',
                        type: 'bar',
                        data: [18203, 23489, 29034, 104970, 131744, 630230]
                    }

                ]
            };
            fastChart.setOption(fastoption);

            var slowChart = Echarts.init(document.getElementById('slow-chart'), 'dark');
            var slowoption = {
                title: {
                    text: '平均处理时间最慢 TOP15',
                },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {
                        type: 'shadow'
                    }
                },
                grid: {
                    left: '3%',
                    right: '4%',
                    bottom: '3%',
                    containLabel: true
                },
                xAxis: {
                    type: 'value',
                    boundaryGap: [0, 0.01]
                },
                yAxis: {
                    inverse: true,
                    type: 'category',
                    data: ['巴西', '印尼', '美国', '印度', '中国', '世界人口(万)']
                },
                series: [
                    {
                        name: '耗时',
                        type: 'bar',
                        data: [18203, 23489, 29034, 104970, 131744, 630230]
                    }

                ]
            };
            slowChart.setOption(slowoption);

            var loadData = function (start, end) {
                $('#createtime').val( Moment(start*1000).format('YYYY/MM/DD HH:mm:ss') + ' - ' + Moment(end*1000).format('YYYY/MM/DD HH:mm:ss'));
                $.post('apilog/data/index', { start: start, end: end }, function (res) {

                    $('#bs_request').text(res.base.count_request);
                    $('#bs_time').text(res.base.avg_time.toFixed(2));
                    $('#bs_404').text(res.base.count_404);
                    $('#bs_500').text(res.base.count_500);
                    $('#bs_error').text((res.base.error_rank * 100).toFixed(2) + '%');
                    $('#bs_api').text(res.base.count_api);
                    codeoption.legend.data = res.code.x;
                    codeoption.series[0].data = res.code.kv;
                    codeChart.setOption(codeoption);

                    timeoption.legend.data = res.time.x;
                    timeoption.series[0].data = res.time.kv;
                    timeChart.setOption(timeoption);

                    requestoption.yAxis.data = res.requesttop.x;
                    requestoption.series[0].data = res.requesttop.y;
                    requestChart.setOption(requestoption);

                    erroroption.yAxis.data = res.errortop.x;
                    erroroption.series[0].data = res.errortop.y;
                    errorChart.setOption(erroroption);

                    fastoption.yAxis.data = res.fasttop.x;
                    fastoption.series[0].data = res.fasttop.y;
                    fastChart.setOption(fastoption);

                    slowoption.yAxis.data = res.slowtop.x;
                    slowoption.series[0].data = res.slowtop.y;
                    slowChart.setOption(slowoption);
                })
            };
            loadData(Moment().startOf('day').unix(), Moment().endOf('day').unix());
        }
    };

    return Controller;
});