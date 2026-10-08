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

                //表格固定高度：表头/合计行常驻，横向滚动条固定在可视区底部，页面不再整页滚动。
                //'full-xx' 会在窗口缩放时自动重算高度；xx = 表格顶部距页面顶端的高度 + 表格下方内边距(15+15+2)
                var tableGap = Math.ceil($('#table').offset().top) + 32;
                table.render({
                    elem: '#table'
                    ,height: 'full-' + tableGap
                    ,url:'kuerp/goods_batch_log/index'
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
                        {field: 'goods_code', title: __('商品编号'), fixed: 'left', templet: function (d) {
                                if(d.goods.goods_code!=null){
                                    return d.goods.goods_code;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'goods_name', title: __('商品名称'), fixed: 'left', templet: function (d) {
                                if(d.goods.goods_name!=null){
                                    return d.goods.goods_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'unit_price', title: __('Unit_price'),templet: function (d) {
                                return (d.unit_price).toFixed(price_decimal);
                            }},
                        {field: 'tax_included', title: __('Tax_included'),templet: function (d) {
                                return (d.tax_included).toFixed(price_decimal);
                            },hide:taxesStatus},
                        {field: 'all_money', title: __('All_money'),templet: function (d) {
                                return (d.all_money).toFixed(price_decimal);
                            }},
                        {field: 'tax_money', title: __('Tax_money'),templet: function (d) {
                                return (d.tax_money).toFixed(price_decimal);
                            },hide:taxesStatus},
                        {field: 'money_tax_price', title: __('Money_tax_price'),templet: function (d) {
                                return (d.money_tax_price).toFixed(price_decimal);
                            },hide:taxesStatus},

                        {field: 'address', title: __('产地'),templet: function (d) {
                                if(d.batch.address!=null){
                                    return d.batch.address;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'registration', title: __('注册证号'),templet: function (d) {
                                if(d.batch.registration!=null){
                                    return d.batch.registration;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'production', title: __('生产许可证'),templet: function (d) {
                                if(d.batch.production!=null){
                                    return d.batch.production;
                                }else{
                                    return '';
                                }
                            }},

                        {field: 'goods_unit', title: __('Goods_unit'),templet: function (d) {
                                if(d.unit.name!=null){
                                    return d.unit.name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'unitconversion', title: __('副单位'),templet: function (d) {
                                if(d.unitconversion!=null){
                                    return d.unitconversion;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'stores_id', title: __('仓库'),templet: function (d) {
                                if(d.stores.name!=null){
                                    return d.stores.name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'batch_name', title: __('批次'),templet: function (d) {
                                if(d.batch.batch_name!=null){
                                    return d.batch.batch_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'manufacture', title: __('生产日期'),templet: function (d) {
                                return layui.util.toDateString(d.batch.manufacture*1000,"yyyy-MM-dd");
                            },width:110},
                        {field: 'guarantee', title: __('保质期'),templet: function (d) {
                                if(d.batch.guarantee!=null){
                                    return d.batch.guarantee;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'validity', title: __('有效期'),templet: function (d) {
                                return layui.util.toDateString(d.batch.validity*1000,"yyyy-MM-dd");
                            },width:110},
                        {field: 'more_unit_num', title: __('Number'),templet: function (d) {
                                if(d.more_unit_num!=null){
                                    return d.more_unit_num;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'unit_name', title: __('副单位数'),templet: function (d) {
                                if(d.number!=null){
                                    return d.sub_unit;
                                }else{
                                    return '';
                                }
                            }},

                        {field: 'purchase_code', title: __('Purchase_code'),width:150},
                        {field: 'purchase_time', title: __('Purchase_time'),templet: function (d) {
                                return layui.util.toDateString(d.purchase_time*1000,"yyyy-MM-dd");
                            },width:120},
                        {field: 'business_type', title: __('Business_type'),templet: function (d) {
                            if(d.business_type==1){
                                return __('Business_type 1');
                            }
                            if(d.business_type==2){
                                return __('Business_type 2');
                            }
                            if(d.business_type==3){
                                return __('Business_type 3');
                            }
                            if(d.business_type==4){
                                return __('Business_type 4');
                            }
                            if(d.business_type==5){
                                return __('Business_type 5');
                            }
                            if(d.business_type==6){
                                return __('Business_type 6');
                            }
                                if(d.business_type==7){
                                    return __('Business_type 7');
                                }
                                if(d.business_type==8){
                                    return __('Business_type 8');
                                }
                                if(d.business_type==9){
                                    return __('Business_type 9');
                                }
                                if(d.business_type==10){
                                    return __('Business_type 10');
                                }
                        }},
                        {field: 'customer_id', title: __('Customer_id'),templet: function (d) {
                                if(d.customer_id!=null){
                                    return d.customer_id;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'sale_id', title: __('Sale_id')},
                        {field: 'remarks', title: __('Remarks'), operate: 'LIKE'},
                    ]]
                });

                //窗口尺寸变化时重算表格高度（兜底，layui 对 'full-' 高度本身也会自适应）
                $(window).on('resize', function () {
                    table.resize('table');
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/goods_batch_log/import?file="+res.data.url, "",'',true);
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
                        url: "kuerp/goods_batch_log/multi",
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
                            Fast.api.open("kuerp/goods_batch_log/add", "添加", {
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
                            Fast.api.ajax("kuerp/goods_batch_log/del?ids="+ids, function () {
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
                            url: "kuerp/goods_batch_log/del",
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
                        Fast.api.open("kuerp/goods_batch_log/edit?ids="+data.id, "编辑");
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
                    var goods_name = $('#goods_name').val();
                    if(goods_name!=''){
                        var search = {'goods.goods_name':goods_name};
                    }
                    var batch_name = $('#batch_name').val();
                    if(batch_name!=''){
                        if(goods_name!=''){
                            var search = {'goods.goods_name':goods_name,'batch.batch_name':batch_name};
                        }else{
                            var search = {'batch.batch_name':batch_name};
                        }
                    }
                    var purchase_code = $('#purchase_code').val();
                    if(purchase_code!=''){
                        search.purchase_code = purchase_code;
                    }
                    var purchase_time = $('#purchase_time').val();
                    if(purchase_time!=''){
                        search.purchase_time = purchase_time;
                    }
                    var business_type = $('#business_type').val();
                    if(business_type!=''){
                        search.business_type = business_type;
                    }
                    var sale_id = $('#sale_id').val();
                    if(sale_id!=''){
                        search.sale_id = sale_id;
                    }
                    return search;
                }
                function getOp() {
                    var op = {'goods.goods_name':'like','batch.batch_name':'like','purchase_code':'like','purchase_time':'BETWEEN','business_type':'=','sale_id':'='};
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
                });
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});