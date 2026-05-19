define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_purchase_summarys/index'
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
                        {field: 'supplier_type_name', title: __('Supplier_type_name'),totalRowText: '合计',templet: function (d) {
                                if(d.supplier_type_name){
                                    return d.supplier_type_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'supplyer_name', title: __('Supplyer_name'),templet: function (d) {
                                if(d.supplyer_name){
                                    return d.supplyer_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'goods_code', title: __('Goods_code'),width: 100 },
                        {field: 'goods_name', title: __('Goods.goods_name')},
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
                        {field: 'basic_nmber', title: __('Basic_nmber'),templet: function (d) {
                                if(d.unit_conversion){
                                    return d.basic_nmber;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'u_name', title: __('Default_unit')},
                        {field: 'i_number', title: __('Basic_nmber')},
                        {field: 'unit_price', title: __('Unit_price')},
                        {field: 'all_money', title: __('All_money')},
                        {field: 'purchase_money', title: __('Purchase_money')},
                        {field: 'tax_money', title: __('税额')},
                        {field: 'money_tax_price', title: __('价税合计')},
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                        if(res.code==1){
                            Fast.api.ajax("kuerp/goods_purchase_summarys/import?file="+res.data.url, "",'',true);
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
                            Fast.api.open("kuerp/goods_purchase_summarys/add", "添加", {
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
                            Fast.api.ajax("kuerp/goods_purchase_summarys/del?ids="+ids, function () {
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
                        $.ajax( {
                            url: "kuerp/goods_purchase_summarys/del",
                            type: "POST",
                            data:{'ids':data.id},
                            success: function(res){
                                if(res.code==1){
                                    Toastr.success('操作成功');
                                    setTimeout(function () {
                                        reloadTable();
                                    },1500)
                                }else{
                                    Toastr.error(res.msg);
                                }
                            }
                        });
                    } else if(obj.event === 'edit'){
                        Fast.api.open("kuerp/goods_purchase_summarys/edit?ids="+data.id, "编辑");
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
                    var goods_code = $('#goods_code').val();
                    if(goods_code!=''){
                        search.goods_code = goods_code;
                    }
                    var supplyer_code = $('#supplyer_code').val();
                    if(supplyer_code!=''){
                        search.supplyer_code = supplyer_code;
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