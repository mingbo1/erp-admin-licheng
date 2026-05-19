define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/payment_log/index'
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
                        {checkbox: true},
                        {field: 'suppliers_code', title: __('Suppliers_code'), operate: 'LIKE'},
                        {field: 'supplyer_name', title: __('Suppliers.supplyer_name'),templet: function (d) {
                                if(d.supplyer_name!=''){
                                    return d.supplyer_name;
                                }else if(d.supplyer_name==undefined){
                                    return '';
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'business_type', title: __('Business_type'),templet: function (d) {
                                if(d.business_type==1){
                                    return __('Business_type 1');
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
                        {field: 'pay_money', title: __('Pay_money')},
                        {field: 'give_money', title: __('Give_money')},
                        {field: 'payment_rate', title: __('付款率')},
                        {field: 'reamrk', title: __('Reamrk')},
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                        if(res.code==1){
                            Fast.api.ajax("kuerp/payment_log/import?file="+res.data.url, "",'',true);
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
                            Fast.api.open("kuerp/payment_log/add", "添加", {
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
                            Fast.api.ajax("kuerp/payment_log/del?ids="+ids, "",'',true);
                            break;
                    }
                });

                //监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'del'){
                        $.ajax( {
                            url: "kuerp/payment_log/del",
                            type: "POST",
                            data:{'ids':data.id},
                            success: function(res){
                                if(res.code==1){
                                    Toastr.success('操作成功');
                                    setTimeout(function () {
                                        parent.location.reload();
                                    },1500)
                                }else{
                                    Toastr.error(res.msg);
                                }
                            }
                        });
                    } else if(obj.event === 'edit'){
                        Fast.api.open("kuerp/payment_log/edit?ids="+data.id, "编辑");
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
                    var bill_codes = $('#bill_codes').val();
                    if(bill_codes!=''){
                        search.bill_codes = bill_codes;
                    }
                    var suppliers_code = $('#suppliers_code').val();
                    if(suppliers_code!=''){
                        search.suppliers_code = suppliers_code;
                    }
                    var bill_time = $('#bill_time').val();
                    if(bill_time!=''){
                        search.bill_time = bill_time;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.bill_codes = 'LIKE';
                    op.suppliers_code = 'LIKE';
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