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
                    ,url:'kuerp/goods_purchase_order_info/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows, //解析数据列表
                            "field": res.field,
                            "order": res.order
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,cellMinWidth: 80 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
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
                        {field: 'purchase_code', title: __('Purchase_code'), width: 150},
                        {field: 's_name', title: __('S_name'),templet: function (d) {
                                if(d.s_code){
                                    return d.s_code+' '+d.s_name;
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
                                    return layui.util.toDateString (d.library_time*1000,"yyyy-MM-dd");
                                }else{
                                    return '';
                                }
                            }},
                    ]]
                    , done: function (res, curr, count) {//数据渲染完的回调。
                        res.list = res.data;

                        field = res.field;
                        order = res.order;
                        obj = $('.layui-table').find('th[data-field="'+field+'"]').find('.layui-table-cell');
                        text = obj.html();
                        if(order=='DESC'){
                            obj.html(text+' <span class="sortspan"><i class="layui-icon xia">&#xe61a;</i></span>');
                        }else{
                            obj.html(text+' <span class="sortspan"><i class="layui-icon shang">&#xe619;</i></span>');
                        }
                        if(field=='goods_code'){
                            obj = $('.layui-table').find('th[data-field="purchase_code"]').find('.layui-table-cell');
                            text = obj.html();
                            obj.html(text+' <span class="sortspan"><i class="layui-icon shang">&#xe619;</i></span>');
                        }else{
                            obj = $('.layui-table').find('th[data-field="goods_code"]').find('.layui-table-cell');
                            text = obj.html();
                            obj.html(text+' <span class="sortspan"><i class="layui-icon shang">&#xe619;</i></span>');
                        }


                        var that = this.elem.next();
                        res.list.forEach(function (item, index) {
                            if (index % 2 == 0) {
                                var tr = that.find(".layui-table-box tbody tr[data-index='" + index + "']").css("background-color", "#FFFFFF");
                            } else {
                                var tr = that.find(".layui-table-box tbody tr[data-index='" + index + "']").css("background-color", "#F4F6F9");
                            }
                        });
                    },
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/goods_purchase_order_info/import?file="+res.data.url, "",'',true);
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
                            Fast.api.open("kuerp/goods_purchase_order_info/add", "添加", {
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
                            Fast.api.ajax("kuerp/goods_purchase_order_info/del?ids="+ids, function () {
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
                            url: "kuerp/goods_purchase_order_info/del",
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
                        Fast.api.open("kuerp/goods_purchase_order_info/edit?ids="+data.id, "编辑");
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
                    var delivery_time = $('#delivery_time').val();
                    if(delivery_time!=''){
                        search.delivery_time = delivery_time;
                    }
                    var supplyer_code = $('#supplyer_code').val();
                    if(supplyer_code!=''){
                        search.supplyer_code = supplyer_code;
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
                    op.purchase_time = 'RANGE';
                    op.supplyer_code = 'LIKE';
                    op.delivery_time = 'RANGE';
                    op.goods_code = 'LIKE';
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
                $(document).on("click", ".shang", function(){
                    var field = $(this).parents('th').data('field');
                    $('.sortspan').find('i').removeClass('active');
                    $(this).addClass('active');
                    reloaddata(field,'DESC')
                });
                $(document).on("click", ".xia", function(){
                    var field = $(this).parents('th').data('field');
                    $('.sortspan').find('i').removeClass('active');
                    $(this).addClass('active');
                    reloaddata(field,'ASC')
                });
                function reloaddata(field,sort){
                    layui.use(['table','upload','form'], function(){
                        var table = layui.table;
                        table.reload('table', {
                            where: {
                                field:field,
                                sort:sort,
                                filter:JSON.stringify(getSearch()),
                                op:JSON.stringify(getOp()),
                            }
                        });
                    })
                }
                function getSearch() {
                    var search = {};
                    var purchase_code = $('#purchase_code').val();
                    if(purchase_code!=''){
                        search.purchase_code = purchase_code;
                    }
                    var delivery_time = $('#delivery_time').val();
                    if(delivery_time!=''){
                        search.delivery_time = delivery_time;
                    }
                    var supplyer_code = $('#supplyer_code').val();
                    if(supplyer_code!=''){
                        search.supplyer_code = supplyer_code;
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
                    op.purchase_time = 'RANGE';
                    op.supplyer_code = 'LIKE';
                    op.delivery_time = 'RANGE';
                    op.goods_code = 'LIKE';
                    return op;
                }
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});