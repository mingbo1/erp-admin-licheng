define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;
                var form = layui.form;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_sales_profit/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,page: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {field: 'c_type_name', title: __('客户分类')},
                        {field: 'customer_code', title: __('客户编号'),sort:true},
                        {field: 'customer_name', title: __('客户名称')},
                        {field: 'count', title: __('销售笔数'),sort:true},
                        {field: 'basic_nmber', title: __('销售基本数量'),sort:true},
                        {field: 'all_money', title: __('销售金额'),sort:true},
                        {field: 'tax_money', title: __('税额'),sort:true},
                        {field: 'money_tax_price', title: __('价税合计'),sort:true},
                        {field: 'unit_cost', title: __('单位成本'),sort:true},
                        {field: 'cost_all', title: __('销售成本'),sort:true},
                        {field: 'profit', title: __('销售毛利'),sort:true},
                        {field: 'profit_rate', title: __('毛利率'),sort:true},
                    ]]
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
                    var purchase_type = $('#purchase_type').val();
                    if(purchase_type!=''){
                        search.purchase_type = purchase_type;
                    }
                    var customerType = $('#customerType').val();
                    if(customerType!=''){
                        search.customerType = customerType;
                    }
                    var customerList = $('#customerList').val();
                    if(customerList!=''){
                        search.customerList = customerList;
                    }
                    var goodsType = $('#goodsType').val();
                    if(goodsType!=''){
                        search.goodsType = goodsType;
                    }
                    var goods_code = $('#goods_code').val();
                    if(goods_code!=''){
                        search.goods_code = goods_code;
                    }
                    var purchase_time = $('#purchase_time').val();
                    if(purchase_time!=''){
                        search.purchase_time = purchase_time;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.goods_code = 'LIKE';
                    op.purchase_time = 'RANGE';
                    return op;
                }

                form.on('select(customerType)', function(data){
                    var type = data.value;
                    $('.search_type').hide();
                    $('.search_type'+type).show();
                    if(type==2){
                        $('.layui-tab-item1').removeClass('layui-show');
                        $('.layui-tab-item2').addClass('layui-show')
                    }else{
                        $('.layui-tab-item2').removeClass('layui-show');
                        $('.layui-tab-item1').addClass('layui-show')
                    }
                });
            });

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;
                var form = layui.form;

                table.render({
                    elem: '#table2'
                    ,url:'kuerp/goods_sales_profit/index2'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                        };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,page: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {field: 'c_type_name', title: __('商品分类')},
                        {field: 'customer_code', title: __('商品编号'),sort:true},
                        {field: 'customer_name', title: __('商品名称')},
                        {field: 'count', title: __('销售笔数'),sort:true},
                        {field: 'basic_nmber', title: __('销售基本数量'),sort:true},
                        {field: 'all_money', title: __('销售金额'),sort:true},
                        {field: 'tax_money', title: __('税额'),sort:true},
                        {field: 'money_tax_price', title: __('价税合计'),sort:true},
                        {field: 'unit_cost', title: __('单位成本'),sort:true},
                        {field: 'cost_all', title: __('销售成本'),sort:true},
                        {field: 'profit', title: __('销售毛利'),sort:true},
                        {field: 'profit_rate', title: __('毛利率'),sort:true},
                    ]]
                });

                //头工具栏事件
                table.on('toolbar(table2)', function(obj){
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table2', []);
                            break;
                    }
                });

                //搜索事件
                $('#search').on('click', function () {
                    table.reload('table2', {
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
                    var purchase_type = $('#purchase_type').val();
                    if(purchase_type!=''){
                        search.purchase_type = purchase_type;
                    }
                    var customerType = $('#customerType').val();
                    if(customerType!=''){
                        search.customerType = customerType;
                    }
                    var customerList = $('#customerList').val();
                    if(customerList!=''){
                        search.customerList = customerList;
                    }
                    var goodsType = $('#goodsType').val();
                    if(goodsType!=''){
                        search.goodsType = goodsType;
                    }
                    var goods_code = $('#goods_code').val();
                    if(goods_code!=''){
                        search.goods_code = goods_code;
                    }
                    var purchase_time = $('#purchase_time').val();
                    if(purchase_time!=''){
                        search.purchase_time = purchase_time;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.goods_code = 'LIKE';
                    op.purchase_time = 'RANGE';
                    return op;
                }

                form.on('select(customerType)', function(data){
                    var type = data.value;
                    $('.search_type').hide();
                    $('.search_type'+type).show();
                    if(type==2){
                        $('.layui-tab-item1').removeClass('layui-show');
                        $('.layui-tab-item2').addClass('layui-show')
                    }else{
                        $('.layui-tab-item2').removeClass('layui-show');
                        $('.layui-tab-item1').addClass('layui-show')
                    }
                });
            });
            $('.search_type2').hide();
            Controller.api.bindevent();
        },
        
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                layui.use('laydate', function(){
                    var laydate = layui.laydate;
                    laydate.render({
                         elem: '#purchase_time',
                         type: 'datetime',
                         range: true
                    });
                });
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});