define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                var set = Config.set;
                var price_decimal = parseInt(set.price_decimal.value);
                if(isNaN(price_decimal)){
                    price_decimal = 1;
                }
                var number_decimal = parseInt(set.number_decimal.value);
                if(isNaN(number_decimal)){
                    number_decimal = 1;
                }

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_sales_order_info/index'
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
                        {field: 'goods_code', title: __('Goods_code'),width: 120,totalRowText: '合计' },
                        {field: 'goods_name', title: __('Goods.goods_name')},
                        {field: 'goods_specification_id', title: __('Goods_specification_id')},
                        {field: 'unitname', title: __('Unitname')},
                        {field: 'purchase_time', title: __('Purchase_time'),width:120,templet: function (d) {
                                if(d.purchase_time){
                                    return layui.util.toDateString(d.purchase_time*1000,"yyyy-MM-dd");
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'purchase_code', title: __('Purchase_code'), width: 170},
                        {field: 's_name', title: __('S_name'),templet: function (d) {
                                if(d.s_name){
                                    return d.s_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'r_name', title: __('R_name')},
                        {field: 'status', title: __('Status'),templet: function (d) {
                                if(d.status==0){
                                    return __('Status 0');
                                }else if(d.status==1){
                                    return __('Status 1');
                                }else if(d.status==2){
                                    return __('Status 2');
                                }else if(d.status==3){
                                    return __('Status 3');
                                }else{
                                    return '<span style="font-weight: bold">'+d.purchase_type+'</span>';
                                }
                            }},
                        {field: 'number', title: __('Number'),templet: function (d) {
                                return (d.number).toFixed(number_decimal);
                            }},
                        {field: 'tax_included', title: __('Unit_price'),templet: function (d) {
                                if(d.tax_included!=undefined){
                                    return (d.tax_included).toFixed(price_decimal);
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'money_tax_price', title: __('All_money'),templet: function (d) {
                                return (d.money_tax_price).toFixed(price_decimal);
                            }},
                        {field: 'library_number', title: __('Library_number'),templet: function (d) {
                                return (d.library_number).toFixed(number_decimal);
                            }},
                        {field: 'library_money', title: __('Library_money'),templet: function (d) {
                                return (d.library_money).toFixed(price_decimal);
                            }},
                        {field: 'delivery_time', title: __('Delivery_time'),width:120,templet: function (d) {
                                if(d.delivery_time){
                                    return layui.util.toDateString(d.delivery_time*1000,"yyyy-MM-dd");
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'library_time', title: __('Library_time'),width:120,templet: function (d) {
                                if(d.library_time && (d.status==1 || d.status==2)){
                                    return layui.util.toDateString(d.library_time*1000,"yyyy-MM-dd");
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'remark', title: __('Remark')},
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/goods_sales_order_info/import?file="+res.data.url, "",'',true);
                         }else{
                             Toastr.error(res.msg);
                         }
                    }
                    ,accept: 'file' //允许上传的文件类型
                    ,size: 50 //最大允许上传的文件大小
                });

                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'add':
                            Fast.api.open("kuerp/goods_sales_order_info/add", "添加", {
                               callback:function(value){
                                  Controller.api.bindevent();
                               }
                            });
                        break;
                        case 'delAll':
                            var data = checkStatus.data;
                            var ids = '';
                            $(data).each(function (k,v) {
                                ids+=v.id+',';
                            });
                            Fast.api.ajax("kuerp/goods_sales_order_info/del?ids="+ids, function () {
                                reloadTable()
                            },'',true);
                        break;
                    }
                });
                function reloadTable(){
                    table.reload('table', {
                        where: {
                            filter:JSON.stringify(getSearch()),
                            op:JSON.stringify(getOp()),
                        }
                    });
                }
                //监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'del'){
                        Fast.api.ajax("goods_sales_order_info/del?ids="+data.id, function () {
                            reloadTable()
                        },'',true);
                    } else if(obj.event === 'edit'){
                        Fast.api.open("kuerp/goods_sales_order_info/edit?ids="+data.id, "编辑");
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
                    var status = $('#status').val();
                    if(status!=''){
                        search.status = status;
                    }
                    var examine_status = $('#examine_status').val();
                    if(examine_status!=''){
                        search.examine_status = examine_status;
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
                    //执行一个laydate实例
                    
                    laydate.render({
                        elem: '#c-purchase_time',
                        type: 'datetime'
                    });
                    laydate.render({
                         elem: '#purchase_time',
                         type: 'datetime',
                         range: true
                    });
                    laydate.render({
                        elem: '#c-delivery_time',
                        type: 'datetime'
                    });
                    laydate.render({
                         elem: '#delivery_time',
                         type: 'datetime',
                         range: true
                    });
                    laydate.render({
                        elem: '#c-library_time',
                        type: 'datetime'
                    });
                    laydate.render({
                         elem: '#library_time',
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