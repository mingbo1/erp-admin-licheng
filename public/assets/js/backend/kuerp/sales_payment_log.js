define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/sales_payment_log/index'
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
                        {field: 'customer_name', title: __('Customers.customer_name'),templet: function (d) {
                                if(d.customer_name!=''){
                                    return d.customer_name;
                                }else if(d.customer_name==undefined){
                                    return '';
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'business_type', title: __('Business_type'),templet: function (d) {
                                if(d.business_type==1){
                                    return __('普通销售');
                                }else if(d.business_type==2){
                                    return __('Business_type 2');
                                }else if(d.business_type==3){
                                    return __('Business_type 3');
                                }else if(d.business_type==4){
                                    return __('Business_type 4');
                                }else if(d.business_type==5){
                                    return __('期初余额');
                                }else if(d.business_type==6){
                                    return __('期初余额');
                                }else if(d.business_type==7){
                                    return __('核销');
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'sale_user', title: __('销售员'),templet: function (d) {
                                if(d.sale_user!=null){
                                    return d.sale_user;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'bill_time', title: __('Bill_time'),templet: function (d) {
                                if(d.bill_time!=''){
                                    return layui.util.toDateString(d.bill_time*1000,'yyyy-MM-dd');
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'bill_codes', title: __('Bill_codes')},
                        {field: 'money_tax_price', title: __('Purchase_money')},
                        {field: 'discount_money', title: __('Discount_money')},
                        {field: 'real_money', title: __('Real_money')},
                        {field: 'customers_money', title: __('Customers_money')},
                        {field: 'pay_money', title: __('Pay_money')},
                        {field: 'give_money', title: __('Give_money')},
                        {field: 'payment_rate', title: __('收款率')},
                        {field: 'reamrk', title: __('Reamrk')},
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                        if(res.code==1){
                            Fast.api.ajax("kuerp/sales_payment_log/import?file="+res.data.url, "",'',true);
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
                            Fast.api.open("kuerp/sales_payment_log/add", "添加", {
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
                            Fast.api.ajax("kuerp/sales_payment_log/del?ids="+ids, "",'',true);
                            break;
                    }
                });

                //监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'del'){
                        Fast.api.ajax("kuerp/sales_payment_log/del?ids="+data.id, "",'',true);
                    } else if(obj.event === 'edit'){
                        Fast.api.open("kuerp/sales_payment_log/edit?ids="+data.id, "编辑");
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
                    var customers_id = $('#customers_id').val();
                    if(customers_id!=''){
                        search.customers_id = customers_id;
                    }
                    var bill_time = $('#bill_time').val();
                    if(bill_time!=''){
                        search.bill_time = bill_time;
                    }
                    var bill_codes = $('#bill_codes').val();
                    if(bill_codes!=''){
                        search.bill_codes = bill_codes;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.bill_codes = 'LIKE';
                    op.bill_time = 'RANGE';
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
                        elem: '#c-bill_time',
                        type: 'datetime'
                    });
                    laydate.render({
                        elem: '#bill_time',
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