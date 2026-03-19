define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'echarts', 'echarts-theme', 'template'], function ($, undefined, Backend, Datatable, Table, Echarts, undefined, Template) {

    var Controller = {    
        index: function () {
            var countmChart = Echarts.init(document.getElementById('count-m-chart'), 'dark');
            var countmoption = {
                title: {
                    text: '每分钟请求次数',
                    left: 'left'
                },
                tooltip: {
                    trigger: 'axis',  
                },
                xAxis: {
                    type: 'category',
                    data: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
                },
                yAxis: {
                    type: 'value'
                },
                series: [{
                    data: [820, 932, 901, 934, 1290, 1330, 1320],
                    type: 'line',
                    smooth: true
                }]
            };
            countmChart.setOption(countmoption);



            var timemChart = Echarts.init(document.getElementById('time-m-chart'), 'dark');
            var timemoption = {
                title: {
                    text: '每分钟平均处理时间(ms)',
                    left: 'left'
                },
                tooltip: {
                    trigger: 'axis'                    
                },
                xAxis: {
                    type: 'category',
                    data: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
                },
                yAxis: {
                    type: 'value'
                },
                series: [{
                    data: [820, 932, 901, 934, 1290, 1330, 1320],
                    type: 'line',
                    smooth: true
                }]
            };
            timemChart.setOption(timemoption);


            var counthChart = Echarts.init(document.getElementById('count-h-chart'), 'dark');
            var counthoption = {
                title: {
                    text: '每小时请求次数',
                },
                tooltip: {
                    trigger: 'axis',                   
                },
                xAxis: {
                    type: 'category',
                    data: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
                },
                yAxis: {
                    type: 'value'
                },
                series: [{
                    data: [820, 932, 901, 934, 1290, 1330, 1320],
                    type: 'line',
                    smooth: true
                }]
            };
            counthChart.setOption(counthoption);

            var timehChart = Echarts.init(document.getElementById('time-h-chart'), 'dark');
            var timehoption = {
                title: {
                    text: '每小时平均处理时间(ms)',
                },
                tooltip: {
                    trigger: 'axis',                    
                },
                xAxis: {
                    type: 'category',
                    data: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
                },
                yAxis: {
                    type: 'value'
                },
                series: [{
                    data: [820, 932, 901, 934, 1290, 1330, 1320],
                    type: 'line',
                    smooth: true
                }]
            };
            timehChart.setOption(timehoption);


            var countdChart = Echarts.init(document.getElementById('count-d-chart'), 'dark');
            var countdoption = {
                title: {
                    text: '每天请求次数',
                },
                tooltip: {
                    trigger: 'axis',                    
                },
                xAxis: {
                    type: 'category',
                    data: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
                },
                yAxis: {
                    type: 'value'
                },
                series: [{
                    data: [120, 200, 150, 80, 70, 110, 130],
                    type: 'bar',
                    showBackground: true,
                    backgroundStyle: {
                        color: 'rgba(220, 220, 220, 0.8)'
                    }
                }]
            };
            countdChart.setOption(countdoption);



            var loadData = function () {
                $.post('apilog/data/qushi', null, function (res) {
                    countmoption.xAxis.data = res.count_m.x;
                    countmoption.series[0].data = res.count_m.y;
                    countmChart.setOption(countmoption);

                    timemoption.xAxis.data = res.time_m.x;
                    timemoption.series[0].data = res.time_m.y;
                    timemChart.setOption(timemoption);

                    counthoption.xAxis.data = res.count_h.x;
                    counthoption.series[0].data = res.count_h.y;
                    counthChart.setOption(counthoption);

                    timehoption.xAxis.data = res.time_h.x;
                    timehoption.series[0].data = res.time_h.y;
                    timehChart.setOption(timehoption);

                    countdoption.xAxis.data = res.count_d.x;
                    countdoption.series[0].data = res.count_d.y;
                    countdChart.setOption(countdoption);

                })
            };
            loadData();
        }
    };

    return Controller;
});