define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'echarts', 'echarts-theme', 'template'], function ($, undefined, Backend, Datatable, Table, Echarts, undefined, Template) {

    var Controller = {
        index: function () {
            // 基于准备好的dom，初始化echarts实例
            var myChart = Echarts.init(document.getElementById('echart'), 'walden');


            // 指定图表的配置项和数据
            var option = {
                title: {
                    text: '',
                    subtext: ''
                },
                color: [
                    "#18d1b1",
                    "#3fb1e3",
                    "#626c91",
                    "#a0a7e6",
                    "#c4ebad",
                    "#96dee8"
                ],
                tooltip: {
                    trigger: 'axis'
                },
                legend: {
                    // data: [__('Register user')]
                },
                toolbox: {
                    show: false,
                    feature: {
                        magicType: {show: true, type: ['stack', 'tiled']},
                        saveAsImage: {show: true}
                    }
                },
                xAxis: {
                    type: 'category',
                    boundaryGap: false,
                    data: Config.chart.column
                },
                yAxis: {},
                grid: [{
                    left: 'left',
                    top: 'top',
                    right: '10',
                    bottom: 30
                }],
                series: [
                    {
                        name: __('Register user'),
                        type: 'line',
                        smooth: true,
                        areaStyle: {normal: {}},
                        lineStyle: {normal: {width: 1.5}},
                        data: Config.chart.user_reg
                    },
                    {
                        name: __('登陆用户数'),
                        type: 'line',
                        smooth: true,
                        areaStyle: {normal: {}},
                        lineStyle: {normal: {width: 1.5}},
                        data: Config.chart.user_login
                    },
                    {
                        name: __('美金充值'),
                        type: 'line',
                        smooth: true,
                        areaStyle: {normal: {}},
                        lineStyle: {normal: {width: 1.5}},
                        data: Config.chart.recharge_usd
                    },
                    {
                        name: __('台币充值'),
                        type: 'line',
                        smooth: true,
                        areaStyle: {normal: {}},
                        lineStyle: {normal: {width: 1.5}},
                        data: Config.chart.recharge_twd
                    },
                    {
                        name: __('提现'),
                        type: 'line',
                        smooth: true,
                        areaStyle: {normal: {}},
                        lineStyle: {normal: {width: 1.5}},
                        data: Config.chart.withdraw
                    },
                ]
            };

            // 使用刚指定的配置项和数据显示图表。
            myChart.setOption(option);

            $(window).resize(function () {
                myChart.resize();
            });

            $(document).on("click", ".btn-refresh", function () {
                setTimeout(function () {
                    myChart.resize();
                }, 0);
            });

        }
    };

    return Controller;
});
