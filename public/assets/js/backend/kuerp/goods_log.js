define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                var storesList = Config.storesList;
                var stores = xmSelect.render({
                    el: '#stores',
                    language: 'zn',
                    tips: '仓库',
                    filterable: true,
                    searchTips: '输入关键字搜索',
                    data: storesList
                });

                var goodsList = Config.goodsList;
                var goods = xmSelect.render({
                    el: '#goods',
                    language: 'zn',
                    tips: '商品',
                    filterable: true,
                    searchTips: '输入关键字搜索',
                    data: goodsList
                });

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_log/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows, //解析数据列表
                            "countsum": res.count,
                            "balance": res.balance
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,page: true
                    ,totalRow: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {field: 'type_name', title: __('商品类别'), rowspan: 2,'totalRow':'合计'},
                        {field: 'goods_code', title: __('商品编号'), rowspan: 2},
                        {field: 'goods_name', title: __('商品名称'), rowspan: 2,width:120},
                        {field: 'goods_barcode', title: __('商品条码'), rowspan: 2},
                        {field: 'goods_spec', title: '型号', rowspan: 2},
                        {field: 'note', title: __('商品备注'), rowspan: 2},
                        {field: 'purchase_time', title: __('Purchase_time'),templet: function (d) {
                                if(d.purchase_time!=null){
                                    return layui.util.toDateString(d.purchase_time*1000,"yyyy-MM-dd");
                                }else{
                                    return '';
                                }
                            }, rowspan: 2,width:120},
                        {field: 'purchase_code', title: __('Purchase_code'), rowspan: 2,width:150},
                        {field: 'business_type', title: __('Business_type'),templet: function (d) {
                           return __('Business_type '+d.business_type);
                            // if(d.business_type==1){
                            //     return __('Business_type 1');
                            // }
                            // if(d.business_type==2){
                            //     return __('Business_type 2');
                            // }
                            // if(d.business_type==3){
                            //     return __('Business_type 3');
                            // }
                            // if(d.business_type==4){
                            //     return __('Business_type 4');
                            // }
                            // if(d.business_type==5){
                            //     return __('Business_type 5');
                            // }
                            // if(d.business_type==6){
                            //     return __('Business_type 6');
                            // }
                            // if(d.business_type==7){
                            //     return __('Business_type 7');
                            // }
                            //     if(d.business_type==8){
                            //         return __('Business_type 8');
                            //     }
                            //     if(d.business_type==9){
                            //         return __('Business_type 9');
                            //     }
                            //     if(d.business_type==10){
                            //         return __('Business_type 10');
                            //     }
                            //     if(d.business_type==11){
                            //         return __('Business_type 11');
                            //     }
                        }, rowspan: 2},
                        {field: 'customer_id', title: __('往来单位'), rowspan: 2},
                        {field: 'unit_name', title: __('单位'), rowspan: 2},
                        {field: 'remark', title: __('备注'), rowspan: 2},
                        {field: 'enter_store', title: __('入库'),colspan:3},
                        {field: 'out_store', title: __('出库'),colspan:3},
                        {field: 'balance', title: __('结存'),colspan:3},
                    ],[
                        {field: 'enter_number', title: __('入库数量')},
                        {field: 'enter_unit', title: __('单位成本')},
                        {field: 'enter_cost', title: __('成本'),totalRow:true},
                        {field: 'out_number', title: __('出库数量')},
                        {field: 'out_unit', title: __('单位成本')},
                        {field: 'out_cost', title: __('成本'),totalRow:true},
                        {field: 'balance_basics', title: __('库存数量')},
                        {field: 'balance_unit', title: __('单位成本')},
                        {field: 'balance_cost', title: __('成本')},
                    ]],
                    done: function(res, curr, count){
                        var obj = this.elem.next();
                        obj.find('.layui-table-total td[data-field="balance_basics"] .layui-table-cell').text(res.countsum);
                        obj.find('.layui-table-total td[data-field="balance_cost"] .layui-table-cell').text(res.balance);
                    }
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/goods_log/import?file="+res.data.url, "",'',true);
                         }else{
                             Toastr.error(res.msg);
                         }
                    }
                    ,accept: 'file' //允许上传的文件类型
                    ,size: 50 //最大允许上传的文件大小
                });

                layui.form.on('switch(switchList)', function (obj) {
                    var onoff = this.checked ? '1' : '0';
                    var id = $(this).data('id');
                    $.ajax( {
                        url: "kuerp/goods_log/multi",
                        type: "POST",
                        data:{
                            'ids':id,
                            'params':"status="+onoff,
                        },
                        success: function(res){
                            if(res.code==0){
                                Toastr.error(res.msg);
                            }else{
                                Toastr.success('操作成功');
                            }
                        }
                    });
                });

                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'add':
                            Fast.api.open("kuerp/goods_log/add", "添加", {
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
                            Fast.api.ajax("kuerp/goods_log/del?ids="+ids, function () {
                                reloadTable()
                            },'',true);
                        break;
                    }
                });
                function reloadTable(){
                    table.reload('table', {
                        where: {
                            filter:JSON.stringify(getSearch()),
                            op:JSON.stringify({'goods_id':'IN','stores_id':'IN','purchase_time':'BETWEEN'}),
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
                            url: "kuerp/goods_log/del",
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
                        Fast.api.open("kuerp/goods_log/edit?ids="+data.id, "编辑");
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    table.reload('table', {
                        where: {
                            filter:JSON.stringify(getSearch()),
                            op:JSON.stringify({'goods_id':'IN','stores_id':'IN','purchase_time':'BETWEEN'}),
                        }
                        ,page: {
                            curr: 1
                        }
                    });
                });
                function getSearch() {
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
                    var goodsArr = goods.getValue();
                    var goods_id = '';
                    if(goodsArr.length>0){
                        $(goodsArr).each(function (k,v) {
                            if(goods_id===''){
                                goods_id += v.value;
                            }else{
                                goods_id += ','+v.value;
                            }
                        })
                    }
                    var search = {};
                    if(stores_id!=''){
                        search.stores_id = stores_id;
                    }
                    if(goods_id!=''){
                        search.goods_id = goods_id;
                    }
                    var purchase_time = $('#purchase_time').val();
                    if(purchase_time!=''){
                        search.purchase_time = purchase_time;
                    }
                    return search;
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
                });
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});