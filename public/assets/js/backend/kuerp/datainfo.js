define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'echarts', 'echarts-theme', 'template','/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Datatable, Table, Echarts, undefined, Template) {

    var Controller = {
        index: function () {
            $(document).on("click", ".btn-refresh", function () {
                setTimeout(function () {
                    var myChart = Echarts.init(document.getElementById('echart'), 'walden');
                    myChart.resize();

                    var myCharts = Echarts.init(document.getElementById('echart2'), 'walden');
                    myCharts.resize();
                }, 0);
            });

            $(document).on("click", ".btn_chart", function () {
                var id = $(this).data('id');
                $('.btn_chart').removeClass('active');
                $(this).addClass('active');
                getChart(id)
            });
            getChart(1);
            function getChart(types) {
                $.ajax({
                    type : 'POST',
                    url : 'kuerp/datainfo/index',
                    data:{
                        'types':types
                    },
                    success : function(res){
                        if(types<3){
                            $('#echart2').hide();$('#echart').show();
                            var myChart = Echarts.init(document.getElementById('echart'), 'walden');
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
                                    data: [res.legend]
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
                                    data: res.column
                                },
                                yAxis: {},
                                grid: [{
                                    left: 'left',
                                    top: 'top',
                                    right: '10',
                                    bottom: 30
                                }],
                                series: [{
                                    name: res.legend,
                                    type: 'line',
                                    smooth: true,
                                    areaStyle: {
                                        normal: {}
                                    },
                                    lineStyle: {
                                        normal: {
                                            width: 1.5
                                        }
                                    },
                                    data: res.data
                                }]
                            };
                            // 使用刚指定的配置项和数据显示图表。
                            myChart.setOption(option);
                            window.addEventListener("resize", function () {
                                myChart.resize();
                            });
                        }else{
                            $('#echart').hide();$('#echart2').show();
                            var myChart = Echarts.init(document.getElementById('echart2'), 'walden');
                            option = {
                                title: {
                                    text: res.title,
                                    left: 'center'
                                },
                                tooltip: {
                                    trigger: 'item'
                                },
                                legend: {
                                    orient: 'vertical',
                                    left: 'left'
                                },
                                series: [
                                    {
                                        name: res.legend,
                                        type: 'pie',
                                        radius: '50%',
                                        data: res.data,
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
                            // 使用刚指定的配置项和数据显示图表。
                            myChart.setOption(option);
                            window.addEventListener("resize", function () {
                                myChart.resize();
                            });
                        }
                    }
                });
            }
        },
        stockworing: function () {

            layui.use(['table','upload'], function(){
                var table = layui.table;

                var storesList = Config.storesList;
                var stores = xmSelect.render({
                    el: '#stores',
                    language: 'zn',
                    tips: '仓库',
                    data: storesList
                });

                var goodsList = Config.goodsList;
                var goodstype = xmSelect.render({
                    el: '#goods',
                    language: 'zn',
                    tips: '商品分类',
                    data: goodsList
                });

                table.render({
                    elem: '#table'
                    ,url:'kuerp/datainfo/stockworing'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                        };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {field: 'goods_type_id', title: __('商品分类'),templet: function (d) {
                                if(d.goodstype.supplier_type_name!=undefined){
                                    return d.goodstype.supplier_type_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'goods_code', title: __('商品编号')},
                        {field: 'goods_name', title: __('商品名称')},
                        {field: 'goods_barcode', title: __('商品条码')},
                        {field: 'goods_image', title: __('商品图片'), operate: false,templet: function (d) {
                                return "<a href='javascript:;'><img src="+d.goods_image+"></a>";
                            }},
                        {field: 'unit', title: __('单位'),templet: function (d) {
                                if(d.more_unit==1){
                                    return d.unitconversion;
                                }
                                if(d.more_unit==2){
                                    return d.unit.name;
                                }
                            }},
                        {field: 'stores', title: __('仓库'),templet: function (d) {
                                if(d.stores.name!=undefined){
                                    return d.stores.name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'min_stock', title: __('最低库存')},
                        {field: 'max_stock', title: __('最高库存')},
                        {field: 'stock', title: __('当前库存')},
                    ]]
                    ,done: function(res, curr, count){
                        // 根据库存预警上下限设置当前库存列的背景色
                        var tableElem = $(this.elem).next();
                        res.data.forEach(function(item, index){
                            var tr = tableElem.find('.layui-table-body tbody tr[data-index="' + index + '"]');
                            if(parseFloat(item.stock) < parseFloat(item.min_stock)){
                                // 低于库存预警下限 - 红色背景
                                tr.find('td[data-field="stock"]').css({'background-color': '#ffcccc', 'color': '#a00'});
                            }else if(parseFloat(item.stock) > parseFloat(item.max_stock)){
                                // 高于库存预警上限 - 黄色背景
                                tr.find('td[data-field="stock"]').css({'background-color': '#ffffcc', 'color': '#660'});
                            }
                        });
                    }
                });

                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    table.reload('table', {
                        where: {
                            filter:JSON.stringify(getSearch()),
                            op:JSON.stringify(getOp()),
                        }
                        ,page: {
                            curr: 1
                        }
                    });
                });
                function getSearch() {
                    var search = {};

                    var storesArr = stores.getValue();
                    var stores_id = '';
                    if(storesArr.length>0){
                        $(storesArr).each(function (k,v) {
                            if(stores_id===''){
                                stores_id += v.value;
                            }else{
                                stores_id += ','+v.value;
                            }
                        })
                    }
                    search.stores_id = stores_id;

                    var typeArr = goodstype.getValue();
                    var type_id = '';
                    if(typeArr.length>0){
                        $(typeArr).each(function (k,v) {
                            if(type_id===''){
                                type_id += v.value;
                            }else{
                                type_id += ','+v.value;
                            }
                        })
                    }
                    search.type_id = type_id;

                    var goods_code = $('#goods_code').val();
                    if(goods_code!=''){
                        search.goods_code = goods_code;
                    }
                    var goods_name = $('#goods_name').val();
                    if(goods_name!=''){
                        search.goods_name = goods_name;
                    }
                    var warn_type = $('#warn_type').val();
                    if(warn_type!=''){
                        search.warn_type = warn_type;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.goods_code = 'LIKE';
                    op.goods_name = 'LIKE';
                    return op;
                }
            });
        },
    };

    return Controller;
});
