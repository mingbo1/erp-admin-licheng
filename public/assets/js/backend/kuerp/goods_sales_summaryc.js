define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;

                var set = Config.set;
                var taxes = parseInt(set.taxes.is_checked);
                if(isNaN(taxes)){
                    taxes = 0;
                }
                if(taxes!=1){
                    taxesStatus = true;
                }else{
                    taxesStatus = false;
                }

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_sales_summaryc/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,cellMinWidth: 80 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {field: 'supplier_type_name', title: __('Supplier_type_name'),totalRowText: '合计',templet: function (d) {
                                if(d.supplier_type_name){
                                    return d.supplier_type_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'supplyer_name', title: __('Customers_id'),templet: function (d) {
                                if(d.supplyer_name){
                                    return d.supplyer_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'goods_code', title: __('Goods_code'),width: 100 },
                        {field: 'goods_name', title: __('Goods.goods_name')},
                        {field: 'goods_barcode', title: __('Goods_barcode'),width: 100 },
                        {field: 'goods_specification_id', title: __('Goods_specification_id')},
                        {field: 's_name', title: __('Stores_id'),templet: function (d) {
                                if(d.s_name){
                                    return d.s_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'unit_conversion', title: __('Unit_conversion'),templet: function (d) {
                                if(d.unit_conversion){
                                    return d.unit_conversion;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'basic_nmber', title: __('副单位数'),templet: function (d) {
                                if(d.unit_conversion){
                                    return d.basic_nmber;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'u_name', title: __('Default_unit')},
                        {field: 'i_number', title: __('Basic_nmber')},
                        {field: 'unit_price', title: __('Unit_price')},
                        {field: 'tax_included', title: __('含税单价'),templet: function (d) {
                                return d.tax_included;
                            },hide:taxesStatus},
                        {field: 'all_money', title: __('All_money')},
                        {field: 'tax_money', title: __('税额'),templet: function (d) {
                                return d.tax_money;
                            },hide:taxesStatus},
                        {field: 'money_tax_price', title: __('价税合计'),templet: function (d) {
                                return d.money_tax_price;
                            },hide:taxesStatus},
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
                    });
                });
                function getSearch() {
                    var search = {};
                    var purchase_code = $('#purchase_code').val();
                    if(purchase_code!=''){
                        search.purchase_code = purchase_code;
                    }
                    var customers_id = $('#customers_id').val();
                    if(customers_id!=''){
                        search.customers_id = customers_id;
                    }
                    var sale_user = $('#sale_user').val();
                    if(sale_user!=''){
                        search.sale_user = sale_user;
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
                    op.purchase_code = 'LIKE';
                    op.goods_code = 'LIKE';
                    op.purchase_time = 'RANGE';
                    return op;
                }
            });
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