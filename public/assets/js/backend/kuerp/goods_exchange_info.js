define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_exchange_info/index'
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
                    ,cellMinWidth: 80 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'purchase_code', title: __('Purchase_code'), operate: 'LIKE'},
                        {field: 'purchase_type', title: __('Purchase_type'),templet: function (d) {
                            if(d.purchase_type==1){
                                return __('Purchase_type 1');
                            }
                            if(d.purchase_type==2){
                                return __('Purchase_type 2');
                            }
                        }},
                        {field: 'purchase_time', title: __('Purchase_time'),templet: function (d) {
                                return layui.util.toDateString(d.purchase_time*1000);
                            }},
                        {field: 'delivery_time', title: __('Delivery_time'),templet: function (d) {
                                return layui.util.toDateString(d.delivery_time*1000);
                            }},
                        {field: 'supplyer_code', title: __('Supplyer_code'), operate: 'LIKE'},
                        {field: 'goods_code', title: __('Goods_code'), operate: 'LIKE'},
                        {field: 'goods_note', title: __('Goods_note'), operate: 'LIKE'},
                        {field: 'goods_unit', title: __('Goods_unit')},
                        {field: 'stores_id', title: __('Stores_id')},
                        {field: 'allow_stock', title: __('Allow_stock'), operate:'BETWEEN'},
                        {field: 'number', title: __('Number'), operate:'BETWEEN'},
                        {field: 'default_unit', title: __('Default_unit')},
                        {field: 'basic_nmber', title: __('Basic_nmber')},
                        {field: 'unit_price', title: __('Unit_price'), operate:'BETWEEN'},
                        {field: 'discount', title: __('Discount'), operate:'BETWEEN'},
                        {field: 'dis_money', title: __('Dis_money'), operate:'BETWEEN'},
                        {field: 'all_money', title: __('All_money'), operate:'BETWEEN'},
                        {field: 'remark', title: __('Remark'), operate: 'LIKE'},
                        {field: 'purchase_order_id', title: __('Purchase_order_id'), operate: 'LIKE'},
                        {field: 'sales_order_id', title: __('Sales_order_id'), operate: 'LIKE'},
                        {field: 'returns_order_id', title: __('Returns_order_id'), operate: 'LIKE'},
                        {field: 'preferential_rate', title: __('Preferential_rate'), operate:'BETWEEN'},
                        {field: 'preferential_money', title: __('Preferential_money'), operate:'BETWEEN'},
                        {field: 'real_money', title: __('Real_money'), operate:'BETWEEN'},
                        {field: 'purchase_money', title: __('Purchase_money'), operate:'BETWEEN'},
                        {field: 'preparation_id', title: __('Preparation_id')},
                        {field: 'examine_id', title: __('Examine_id')},
                        {field: 'operation_id', title: __('Operation_id')},
                        {field: 'pay_status', title: __('Pay_status'),templet: function (d) {
                            if(d.pay_status==0){
                                return __('Pay_status 0');
                            }
                            if(d.pay_status==1){
                                return __('Pay_status 1');
                            }
                            if(d.pay_status==2){
                                return __('Pay_status 2');
                            }
                        }},
                        {field: 'check_status', title: __('Check_status'),templet: function (d) {
                            if(d.check_status==0){
                                return __('Check_status 0');
                            }
                            if(d.check_status==1){
                                return __('Check_status 1');
                            }
                        }},
                        {field: 'examine_status', title: __('Examine_status'),templet: function (d) {
                            if(d.examine_status==0){
                                return __('Examine_status 0');
                            }
                            if(d.examine_status==1){
                                return __('Examine_status 1');
                            }
                        }},
                        {field: 'examine_time', title: __('Examine_time'),templet: function (d) {
                                return layui.util.toDateString(d.examine_time*1000);
                            }},
                        {field: 'pay_money', title: __('Pay_money'), operate:'BETWEEN'},
                        {field: 'account', title: __('Account')},
                        {field: 'print_num', title: __('Print_num')},
                        {field: 'createtime', title: __('Createtime'),templet: function (d) {
                                return layui.util.toDateString(d.createtime*1000);
                            }},
                        {field: 'updatetime', title: __('Updatetime'),templet: function (d) {
                                return layui.util.toDateString(d.updatetime*1000);
                            }},
                        {field: 'goods.goods_name', title: __('Goods.goods_name'), operate: 'LIKE'},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemo', width:150}
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/goods_exchange_info/import?file="+res.data.url, "",'',true);
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
                            Fast.api.open("kuerp/goods_exchange_info/add", "添加", {
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
                            Fast.api.ajax("kuerp/goods_exchange_info/del?ids="+ids, function () {
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
                        ,page: {
                            curr: 1
                        }
                    });
                }
                //监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'del'){
                        $.ajax( {
                            url: "kuerp/goods_exchange_info/del",
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
                        Fast.api.open("kuerp/goods_exchange_info/edit?ids="+data.id, "编辑");
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    table.reload('table', {
                        where: {
                            searchdata:JSON.stringify(getSearch()),
                            op:JSON.stringify(getOp()),
                        }
                        ,page: {
                            curr: 1
                        }
                    });
                });
                function getSearch() {
                    var search = {};
                    var purchase_code = $('#purchase_code').val();
                    if(purchase_code!=''){
                        search.purchase_code = purchase_code;
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
                });
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});