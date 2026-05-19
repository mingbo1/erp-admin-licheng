define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                var set = Config.set;
                var examine = parseInt(set.examine.is_checked);
                if(isNaN(examine)){
                    examine = 0;
                }
                if(examine!=1){
                    hideStatus = true;
                }else{
                    hideStatus = false;
                }
                var taxes = parseInt(set.taxes.is_checked);
                if(isNaN(taxes)){
                    taxes = 0;
                }
                if(taxes!=1){
                    taxesStatus = true;
                }else{
                    taxesStatus = false;
                }
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
                    ,url:'kuerp/goods_sales_info/index'
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
                        {field: 'purchase_time', title: __('Purchase_time'),width:120,templet: function (d) {
                                return layui.util.toDateString(d.purchase_time*1000,'yyyy-MM-dd');
                            }},
                        {field: 'purchase_code', title: __('Purchase_code'), width:170},
                        {field: 'purchase_type', title: __('Purchase_type'),templet: function (d) {
                            if(d.purchase_type==1){
                                return __('Purchase_type 1');
                            }
                            if(d.purchase_type==2){
                                return __('Purchase_type 2');
                            }
                        }},
                        {field: 'staff_member.name', title: __('Sale_user'),templet: function (d) {
                                if(d.staff_member.name!=null){
                                    return d.staff_member.name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'staff_member.name', title: __('Customers_id'),templet: function (d) {
                                if(d.customers.customer_name!=null){
                                    return d.customers.customer_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'goods_code', title: __('Goods_code'), operate: 'LIKE'},
                        {field: 'goods.goods_name', title: __('Goods_name'),templet: function (d) {
                                return d.goods.goods_name;
                            }},
                        {field: 'goods.barcode', title: __('Goods_barcode'),width:100,templet: function (d) {
                                return d.goods.goods_barcode;
                            }},
                        {field: 'goods.goods_specification_id', title: __('Goods_specification_id'),width:100,templet: function (d) {
                                return d.goods.goods_specification_id;
                            }},
                        {field: 'unit.name', title: __('Unitname'),width:100,templet: function (d) {
                                return d.unit.name;
                            }},
                        {field: 'stores.name', title: __('Storesname'),width:100,templet: function (d) {
                                return d.stores.name;
                            }},

                        {field: 'number', title: __('Number'),templet: function (d) {
                                if(d.purchase_type==1){
                                    return (d.number).toFixed(number_decimal);
                                }
                                if(d.purchase_type==2){
                                    return '-'+(d.number).toFixed(number_decimal);
                                }
                            }},
                        {field: 'unit_price', title: __('Unit_price'),templet: function (d) {
                                return (d.unit_price).toFixed(price_decimal);
                            }},
                        {field: 'tax_included', title: __('含税单价'),templet: function (d) {
                                return (d.tax_included).toFixed(price_decimal);
                            }},
                        {field: 'all_money', title: __('All_money'),templet: function (d) {
                                if(d.purchase_type==1){
                                    return (d.all_money).toFixed(price_decimal);
                                }
                                if(d.purchase_type==2){
                                    return '-'+(d.all_money).toFixed(price_decimal);
                                }
                            }},
                        {field: 'purchase_money', title: __('Purchase_money'), operate:'BETWEEN'},

                        {field: 'tax_money', title: __('税额'),hide:taxesStatus,templet: function (d) {
                                if(d.purchase_type==1){
                                    return (d.tax_money).toFixed(price_decimal);
                                }
                                if(d.purchase_type==2){
                                    return '-'+(d.tax_money).toFixed(price_decimal);
                                }
                            }},
                        {field: 'money_tax_price', title: __('价税合计'),hide:taxesStatus,templet: function (d) {
                                if(d.purchase_type==1){
                                    return (d.money_tax_price).toFixed(price_decimal);
                                }
                                if(d.purchase_type==2){
                                    return '-'+(d.money_tax_price).toFixed(price_decimal);
                                }
                            }},

                        {field: 'purchase_order_id', title: __('Purchase_order_id'), operate: 'LIKE'},
                        {field: 'remark', title: __('Remark')},
                        {field: 'note', title: __('Note'),templet: function (d) {
                                if(d.note!=null){
                                    return d.note;
                                }else{
                                    return '';
                                }
                            }},
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/goods_sales_info/import?file="+res.data.url, "",'',true);
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
                            Fast.api.open("kuerp/goods_sales_info/add", "添加", {
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
                            Fast.api.ajax("kuerp/goods_sales_info/del?ids="+ids, function () {
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
                            url: "kuerp/goods_sales_info/del",
                            type: "POST",
                            data:{'ids':data.id},
                            success: function(res){
                                if(res.code==1){
                                    Toastr.success('操作成功');
                                    setTimeout(function () {
                                        reloadTable();
                                    },1000)
                                }else{
                                    Toastr.error(res.msg);
                                }
                            }
                        });
                    } else if(obj.event === 'edit'){
                        Fast.api.open("kuerp/goods_sales_info/edit?ids="+data.id, "编辑");
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
                    var purchase_code = $('#purchase_code').val();
                    if(purchase_code!=''){
                        search.purchase_code = purchase_code;
                    }
                    var customers_id = $('#customers_id').val();
                    if(customers_id!=''){
                        search.customers_id = customers_id;
                    }
                    var goods_code = $('#goods_code').val();
                    if(goods_code!=''){
                        search.goods_code = goods_code;
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
                layui.use(['table','form'], function(){
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
                                    filter:JSON.stringify({"goods_name":val}),
                                    op:JSON.stringify({"goods_name":'like'}),
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