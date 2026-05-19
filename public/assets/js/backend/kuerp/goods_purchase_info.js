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

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_purchase_info/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows, //解析数据列表
                            'countArr':res.count
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,page: true
                    ,totalRow: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {field: 'purchase_time', title: __('Purchase_time'),width:110,templet: function (d) {
                                return layui.util.toDateString(d.purchase_time*1000,"yyyy-MM-dd");
                            },'totalRow':'合计'},
                        {field: 'purchase_code', title: __('Purchase_code'),width:150},
                        {field: 'purchase_type', title: __('Purchase_type'),templet: function (d) {
                                if(d.purchase_type==1){
                                    return __('Purchase_type 1');
                                }
                                if(d.purchase_type==2){
                                    return __('Purchase_type 2');
                                }
                            }},
                        {field: 'suppliers', title: __('Suppliers'),width:200,templet: function (d) {
                                return d.suppliers.supplyer_code+' '+d.suppliers.supplyer_name;
                            }},
                        {field: 'goods.goods_code', title: __('Goods_code'),width:100,templet: function (d) {
                                return d.goods.goods_code;
                            }},
                        {field: 'goods.goods_name', title: __('Goods_name'),width:100,templet: function (d) {
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
                        {field: 'number', title: __('Number'),totalRow:true},
                        {field: 'unit_price', title: __('Unit_price'),totalRow:true},
                        {field: 'tax_included', title: __('含税单价'),hide:taxesStatus,totalRow:true},
                        {field: 'all_money', title: __('All_money'),totalRow:true},
                        {field: 'purchase_money', title: __('Purchase_money'),totalRow:true},

                        {field: 'tax_money', title: __('税额'),hide:taxesStatus,totalRow:true},
                        {field: 'money_tax_price', title: __('价税合计'),hide:taxesStatus,totalRow:true},

                        {field: 'purchase_order_id', title: __('Purchase_order_id'), width: 200},
                        {field: 'remark', title: __('Remark')},
                        {field: 'note', title: __('Note'),templet: function (d) {
                                return d.note;
                            }},
                    ]],
                    done: function(res, curr, count){
                        var obj = this.elem.next();
                        obj.find('.layui-table-total td[data-field="number"] .layui-table-cell').text(res.countArr.number);
                        obj.find('.layui-table-total td[data-field="unit_price"] .layui-table-cell').text(res.countArr.unit_price);
                        obj.find('.layui-table-total td[data-field="tax_included"] .layui-table-cell').text(res.countArr.tax_included);
                        obj.find('.layui-table-total td[data-field="all_money"] .layui-table-cell').text(res.countArr.all_money);
                        obj.find('.layui-table-total td[data-field="purchase_money"] .layui-table-cell').text(res.countArr.purchase_money);
                        obj.find('.layui-table-total td[data-field="tax_money"] .layui-table-cell').text(res.countArr.tax_money);
                        obj.find('.layui-table-total td[data-field="money_tax_price"] .layui-table-cell').text(res.countArr.money_tax_price);
                    }
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/goods_purchase_info/import?file="+res.data.url, "",'',true);
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
                            Fast.api.open("kuerp/goods_purchase_info/add", "添加", {
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
                            Fast.api.ajax("kuerp/goods_purchase_info/del?ids="+ids, function () {
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
                            url: "kuerp/goods_purchase_info/del",
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
                        Fast.api.open("kuerp/goods_purchase_info/edit?ids="+data.id, "编辑");
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
                    var purchase_time = $('#purchase_time').val();
                    if(purchase_time!=''){
                        search.purchase_time = purchase_time;
                    }
                    var supplyer_code = $('#supplyer_code').val();
                    if(supplyer_code!=''){
                        search.supplyer_code = supplyer_code;
                    }
                    var goods_code = $('#goods_code').val();
                    if(goods_code!=''){
                        search.goods_code = goods_code;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.purchase_code = 'LIKE';
                    op.supplyer_code = 'LIKE';
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