define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/purchaseby/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                        };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,defaultToolbar:[]
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true,rowspan:2,fixed: 'left'},
                        {field: 'goods_name', title: __('商品'),width:140,rowspan:2,fixed: 'left'},
                        {field: 'goods_code', title: __('销货订单信息'),colspan:8},
                        {field: 'sales_code', title: __('实际出入库数量'),colspan:8},
                        {field: 'sales_code', title: __('本次采购信息'),colspan:2},
                    ],[
                        {field: 'attr_name', title: __('属性')},
                        {field: 'unit_name', title: __('单位')},
                        {field: 'number', title: __('数量')},
                        {field: 'stores_name', title: __('仓库')},
                        {field: 'customers_name', title: __('客户名称')},
                        {field: 'customer_code', title: __('客户编码')},
                        {field: 'purchase_code', title: __('订单号'),width:160},
                        {field: 'purchase_time', title: __('订单日期'),width:140},

                        {field: 'sales_time', title: __('购货/销货日期'),width:140},
                        {field: 'sales_purchase_code', title: __('购货订单号'),width:160},
                        {field: 'sales_number', title: __('以销定购数量'),width:140},
                        {field: 'sure_number', title: __('实际入库数量'),width:140},
                        {field: 'sales_code', title: __('销货单号'),width:160},
                        {field: 'sales_number', title: __('销货出库数量'),width:140},
                        {field: 'warehouse', title: __('未出库数量')},
                        {field: 'buy_number', title: __('待采购数量')},

                        {field: 'now_buy_number', title: __('本次采购数量'),width:140,edit:'number'},
                        {field: 'buy_price', title: __('本次采购价格'),width:140,edit:'number'},
                    ]]
                });

                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    var data = checkStatus.data;
                    var ids = '';
                    $(data).each(function (k,v) {
                        ids+=v.id+',';
                    });
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'add':
                            if(ids==''){
                                Toastr.error('请先选择单据');return false;
                            }
                            var arr = new Array();
                            var check_now_buy_number = false;
                            var check_buy_number = false;
                            var check_buy_price = false;
                            $(data).each(function (k,v) {
                                if(v.now_buy_number==''){
                                    check_now_buy_number = true;
                                }
                                if(v.buy_number==''){
                                    check_buy_number = true;
                                }
                                if(v.buy_price==''){
                                    check_buy_price = true;
                                }
                                var row = new Array();
                                row['goods_code'] = v.goods_code;
                                row['purchase_code'] = v.purchase_code;
                                row['goods_attr'] = v.attr_id;
                                row['goods_unit'] = v.goods_unit;
                                row['stores_id'] = v.stores_id;
                                row['now_buy_number'] = v.now_buy_number;
                                row['buy_price'] = v.buy_price;
                                arr.push(row);
                            });
                            if(check_buy_number){
                                Toastr.error('待采购数量需大于0，否则无法生成购货订单！');return false;
                            }
                            if(check_now_buy_number){
                                Toastr.error('请输入本次采购数量！');return false;
                            }
                            if(check_buy_price){
                                Toastr.error('请输入本次采购金额！');return false;
                            }
                            var arrstr = arrayToJsonString(arr);
                            $.ajax( {
                                url: "kuerp/purchaseby/setdata",
                                type: "POST",
                                data:{'row':arrstr},
                                success: function(res){
                                    if(res.code==0){
                                        Toastr.error(res.msg);
                                    }else{
                                        Fast.api.open("kuerp/goods_purchase_order/add?type=1", "购货订单",{ area:['90%','75%'],});
                                    }
                                }
                            });
                            break;
                    }
                });

                function arrayToJsonString(o) {
                    var len = o.length;
                    var new_arr = new Array();
                    var str = '',strone='',strs='',jsonstr='';
                    for(var i = 0;i<len;i++){
                        new_arr = o[i];
                        for(var k in new_arr){
                            strone += '"'+k+'"'+':'+'"'+new_arr[k]+'"'+',';
                        }
                        str = '{'+strone.substring(0,strone.length-1)+'}';
                        strone='';
                        strs += str+',';
                        new_arr=[];
                    }
                    strs = '['+strs.substring(0,strs.length-1)+']';
                    return strs;
                }
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
                    var purchase_code = $('#purchase_code').val();
                    if(purchase_code!=''){
                        search.purchase_code = purchase_code;
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
                    op.goods_code = '=';
                    op.purchase_time = 'RANGE';
                    return op;
                }
            });
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                layui.use(['table','form'], function(){
                    //商品
                    xmSelect.render({
                        el: '#goods_codes',
                        filterable: true,
                        tips: '请选择商品',
                        paging: true,
                        pageSize: 5,
                        pageRemote: true,
                        remoteMethod: function (val, cb, show, pageIndex) {
                            $.ajax({
                                url: "kuerp/goods/index",
                                type: 'GET',
                                crossOrigin: true,
                                dataType: 'json',
                                cache: false,
                                async: true,
                                data: {
                                    keywords: val,
                                    page: pageIndex,
                                    limit: 5
                                },
                                success: function (data) {
                                    var TotalCount = data.total;
                                    var Pages = Math.ceil(TotalCount/5);
                                    var list = [];
                                    $(data.rows).each(function (k,v) {
                                        var row = [];
                                        row['name'] = v.goods_name;
                                        row['value'] = v.goods_code;
                                        list.push(row)
                                    });
                                    setTimeout(function () {
                                        cb(list, Pages)
                                    }, 100)
                                },
                                error: function (data) {
                                    cb([], 0);
                                }
                            });
                        },
                        on: function(data){
                            var arr = data.arr;
                            var change = data.change;
                            var isAdd = data.isAdd;
                            if(!isAdd){
                                $('#goods_code').val('');
                            }
                            if(change.length>0){
                                for (let i in arr){
                                    if(change[0].parentId==arr[i].parentId){
                                        arr.splice(i,1,change[0]);
                                    }
                                }
                            }
                            if(arr.length>0){
                                if(arr[0]['value']!=undefined){
                                    $('#goods_code').val(arr[0]['value']);
                                }
                            }
                            return Array.from(new Set(arr));
                        },
                    });
                });
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