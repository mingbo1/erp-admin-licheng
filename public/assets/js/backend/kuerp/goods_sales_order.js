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
                    ,url:'kuerp/goods_sales_order/index'
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
                        {field: 'purchase_time', title: __('Purchase_time'),templet: function (d) {
                                return layui.util.toDateString(d.purchase_time*1000,"yyyy-MM-dd");
                            },width:110},
                        {field: 'purchase_code', title: __('Purchase_code'), operate: 'LIKE'},
                        {field: 'purchase_type', title: __('Purchase_type'),templet: function (d) {
                                if(d.purchase_type==1){
                                    return __('Purchase_type 1');
                                }
                                if(d.purchase_type==2){
                                    return __('Purchase_type 2');
                                }
                            }},
                        {field: 'customers', title: __('Customers_id'),templet: function (d) {
                                return d.customers.customer_name;
                            }},
                        {field: 'purchase_id', title: __('Purchase_id'), operate: 'LIKE'},
                        {field: 'number', title: __('Number'),templet: function (d) {
                                return (d.number).toFixed(number_decimal);
                            }},
                        {field: 'all_money', title: __('All_money'),templet: function (d) {
                                return (d.all_money-(-d.tax_money)).toFixed(price_decimal);
                            }},
                        {field: 'status', title: __('Status'),templet: function (d) {
                                if(d.status==0){
                                    return __('Status 0');
                                }
                                if(d.status==1){
                                    return __('Status 1');
                                }
                                if(d.status==2){
                                    return __('Status 2');
                                }
                                if(d.status==3){
                                    return __('Status 3');
                                }
                            }},
                        {field: 'preparation_id', title: __('Preparation_id'),templet: function (d) {
                                return d.preparation.username;
                            }},
                        {field: 'examine_id', title: __('Examine_id'),templet: function (d) {
                                if(d.examine.username!=null){
                                    return d.examine.username;
                                }else{
                                    return '';
                                }
                            },hide:hideStatus},
                        {field: 'second_examine_status', title: __('Second_examine_status'),templet: function (d) {
                                if(d.second_examine_status==1){
                                    return '<span style="color:green">'+__('Second_examine_status 1')+'</span>';
                                }else if(d.examine_status==1){
                                    return '<span style="color:orange">'+__('Second_examine_status 0')+'</span>';
                                }else{
                                    return '-';
                                }
                            },hide:hideStatus},
                        {field: 'second_examine_id', title: __('Second_examine_id'),templet: function (d) {
                                if(d.secondexamine && d.secondexamine.username!=null){
                                    return d.secondexamine.username;
                                }else{
                                    return '';
                                }
                            },hide:hideStatus},
                        {field: 'note', title: __('Note')},
                        {field: 'print_num', title: __('Print_num')},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemo', width:150}
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                        if(res.code==1){
                            Fast.api.ajax("kuerp/goods_sales_order/import?file="+res.data.url, "",'',true);
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
                            Fast.api.open("kuerp/goods_sales_order/add", "添加", {
                                callback:function(value){
                                    Controller.api.bindevent();
                                },
                                area:['90%','75%'],
                            });
                            break;
                        case 'delAll':
                            if(data.length<=0){
                                Toastr.error('请选择要删除的数据');return;
                            }
                            Layer.confirm(
                                __('Are you sure you want to delete the %s selected item?', data.length),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function (index) {
                                    Fast.api.ajax("kuerp/goods_sales_order/del?ids="+ids, function () {
                                        $('.btn-refresh').click();
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                            break;
                        case 'examine':
                            Fast.api.ajax("kuerp/goods_sales_order/examine?examine=1&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'examine_f':
                            Fast.api.ajax("kuerp/goods_sales_order/examine?examine=2&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'secondExamine':
                            if(data.length<=0){
                                Toastr.error('请选择要操作的数据');return;
                            }
                            Fast.api.ajax("kuerp/goods_sales_order/secondExamine?ids="+ids, function () {
                                $('.btn-refresh').click();
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
                        var that = this;
                        var top = $(that).offset().top - $(window).scrollTop();
                        var left = $(that).offset().left - $(window).scrollLeft() - 260;
                        if (top + 154 > $(window).height()) {
                            top = top - 154;
                        }
                        if ($(window).width() < 480) {
                            top = left = undefined;
                        }
                        Layer.confirm(
                            __('Are you sure you want to delete this item?'),
                            {icon: 3, title: __('Warning'),offset: [top, left], shadeClose: true},
                            function (index) {
                                Fast.api.ajax("kuerp/goods_sales_order/del?ids="+data.id, function () {
                                    $('.btn-refresh').click();
                                },'',true);
                                Layer.close(index);
                            }
                        );
                    } else if(obj.event === 'edit'){
                        Fast.api.open("kuerp/goods_sales_order/edit?ids="+data.id, "编辑",{ area:['90%','75%'],});
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
                    var status = $('#status').val();
                    if(status!=''){
                        search.status = status;
                    }
                    var examine_status = $('#examine_status').val();
                    if(examine_status!=''){
                        search.examine_status = examine_status;
                    }
                    var second_examine_status = $('#second_examine_status').val();
                    if(second_examine_status!=''){
                        search.second_examine_status = second_examine_status;
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
            Controller.api.bindevent(false);
        },
        api: {
            bindevent: function (reloadStatus=true) {
                layui.use('laydate', function(){
                    var laydate = layui.laydate;
                    //执行一个laydate实例
                    var set = Config.set;
                    var no_edittime = parseInt(set.no_edittime.is_checked);
                    if(isNaN(no_edittime)){
                        no_edittime = 0;
                    }
                    if(no_edittime==1){
                        $('#c-purchase_time').attr('readonly','true');
                    }else{
                        laydate.render({
                            elem: '#c-purchase_time',
                        });
                    }
                    laydate.render({
                        elem: '#purchase_time',
                        type: 'datetime',
                        range: true
                    });
                    laydate.render({
                        elem: '#c-delivery_time',
                    });
                    laydate.render({
                        elem: '#delivery_time',
                        type: 'datetime',
                        range: true
                    });
                });

                //选择原始单据
                $(document).on("click", "#choseEnclosure", function(){
                    parent.Fast.api.open("kuerp/enclosuresale/selectpage", "选择原始单据",{area:['80%','60%'],callback:function(listData){
                            if(listData!=undefined){
                                var count = listData.length;
                                var enclusure = listData.toString();
                                $('.enclosure_number').html(count);
                                $('#c-enclosure').val(enclusure);
                                if(count>0){
                                    $('.voucherAttachment').show();
                                }else{
                                    $('.voucherAttachment').hide();
                                }
                            }
                        }});
                });
                //查看原始单据
                $(document).on("click", ".voucherAttachment", function(){
                    var id = $('#c-enclosure').val();
                    $.ajax( {
                        url: "kuerp/enclosuresale/index",
                        type: "POST",
                        data:{
                            'searchIds':id,
                        },
                        success: function(res){
                            layer.photos({
                                photos: res.rows
                                ,anim: 5 //0-6的选择，指定弹出图片动画类型，默认随机（请注意，3.0之前的版本用shift参数）
                            });
                            console.log(res);
                        }
                    });
                });

                //设置审核的单据不可编辑
                var set = Config.set;
                var examine = parseInt(set.examine.is_checked);
                if(isNaN(examine)){
                    examine = 0;
                }
                if(examine==1 && Config.examine_status==1){
                    $('input').attr('disabled','true');
                    $('select').attr('disabled','true');
                    $('textarea').attr('disabled','true');
                    $('.btn').attr('disabled','true');
                }

                //数量格式化
                $(document).on("change", ".number_decimal", function(){
                    var set = Config.set;
                    var number_decimal = parseInt(set.number_decimal.value);
                    if(isNaN(number_decimal)){
                        number_decimal = 1;
                    }
                    var period = parseFloat($(this).val());
                    if(!isNaN(period)){
                        period = period.toFixed(number_decimal);
                        $(this).val(period);
                    }else{
                        $(this).val('');
                    }
                });
                //价格格式化
                $(document).on("change", ".price_decimal", function(){
                    var set = Config.set;
                    var price_decimal = parseInt(set.price_decimal.value);
                    if(isNaN(price_decimal)){
                        price_decimal = 1;
                    }
                    var period = parseFloat($(this).val());
                    if(!isNaN(period)){
                        period = period.toFixed(price_decimal);
                        $(this).val(period);
                    }else{
                        $(this).val('');
                    }
                });

                var goodsList = Config.goodsList;
                var unit;
                //选择客户
                $(document).on("change", "#customers_id", function(){
                    var customers_id = $(this).val();
                    $.ajax( {
                        url: "kuerp/goods_sales/getReceivingAddress",
                        type: "POST",
                        data:{
                            'customers_id':customers_id,
                        },
                        success: function(res){
                            $('#c-receiving_address').val(res);
                        }
                    });
                });

                //变更商品价格信息
                function changeGoodsInfo(obj,now_field){
                    var goods_code = obj.find('.goods_code').val();
                    var goods_unit = obj.find('.goods_unit').val();
                    var attr = obj.find('.goods_attr').val();
                    var customers_id = $('#customers_id').val();
                    var store = obj.find('.stores_id').val();
                    var number = obj.find('.number').val();
                    var unit_price = obj.find('.unit_price').val();
                    var tax_included = obj.find('.tax_included').val();
                    var discount = obj.find('.discount').val();
                    var dis_money = obj.find('.dis_money').val();
                    var all_money = obj.find('.all_money').val();
                    var tax_rate = obj.find('.tax_rate').val();
                    var tax_money = obj.find('.tax_money').val();
                    var money_tax_price = obj.find('.money_tax_price').val();

                    $.ajax( {
                        url: "kuerp/goods_sales/formatInfo2",
                        type: "POST",
                        data:{
                            'customers_id':customers_id,
                            'goods_code':goods_code,
                            'attr':attr,
                            'goods_unit':goods_unit,
                            'store':store,
                            'number':number,
                            'unit_price':unit_price,
                            'tax_included':tax_included,
                            'discount':discount,
                            'dis_money':dis_money,
                            'all_money':all_money,
                            'tax_rate':tax_rate,
                            'tax_money':tax_money,
                            'money_tax_price':money_tax_price,
                            'now_field':now_field,
                        },
                        success: function(data){
                            res = JSON.parse(data);
                            data = res.data;
                            $(data).each(function (k,v) {
                                if (v.value!==''){
                                    obj.find('.'+v.key).val(v.value);
                                }
                            });
                            changeInfo();
                        }
                    });
                }
                //初始获取商品信息
                function formatInfo(obj,number,stock,name,id,basic_nmber,purchase,tax_included,tax_rate,discount) {
                    $.ajax( {
                        url: "kuerp/goods_purchase/formatInfo",
                        type: "POST",
                        data:{
                            'number':number,
                            'allow_stock':stock,
                            'advance_rate':basic_nmber,
                            'basic_nmber':basic_nmber,
                            'unit_price':purchase,
                            'tax_included':tax_included,
                            'tax_rate':tax_rate,
                            'discount':discount,
                        },
                        success: function(data){
                            res = JSON.parse(data);
                            data = res.data;
                            $(data).each(function (k,v) {
                                if (v.value!==''){
                                    obj.find('.'+v.key).val(v.value);
                                }
                            });
                            changeInfo();
                        }
                    });
                }
                //选择商品,商品属性,商品单位修改商品信息

                function changeGoods(goods_code,store,attr,obj,goods_unit,number) {
                    var set = Config.set;
                    var number_decimal = parseInt(set.number_decimal.value);
                    if(isNaN(number_decimal)){
                        number_decimal = 1;
                    }
                    var customers_id = $('#customers_id').val();

                    $.ajax( {
                        url: "kuerp/goods_sales/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':store,'attr':attr,'customers_id':customers_id
                        },
                        success: function(res){
                            res = JSON.parse(res);
                            goods=res;
                            if(goods.goods.batch==1){
                                obj.find('.guarantee').val(goods.goods.shelf_life);
                            }
                            if(number==''){
                                number = 1;
                            }else{
                                number = parseFloat(number);
                            }

                            attrarr = res.attr;
                            var attr_html = '<option value=""></option>';
                            $(attrarr).each(function (k,v) {
                                attr_html += '<option value="'+v.id+'">'+v.attr_name+'</option>';
                            });
                            obj.find('.goods_attr').html(attr_html);
                            obj.find('.number').val((number).toFixed(number_decimal));

                            if(attr!=''){
                                obj.find('.goods_attr').val(attr);
                            }
                            if(store!=''){
                                obj.find('.stores_id').val(store);
                            }
                            if(goods.goods.warehouse!='' && goods_unit=='' && goods.goods.more_unit==1){
                                goods_unit = goods.goods.warehouse;
                            }
                            unit = res.unit;
                            var unit_html = '';
                            $(unit).each(function (k,v) {
                                unit_html += '<option value="'+v.id+'">'+v.name+'</option>';
                                var number = obj.find('.number').val();
                                var discount = 0;
                                if(v.is_default==1){
                                    obj.find('.default_unit').val(v.name);
                                    obj.find('.default_unit_id').val(v.id);
                                }
                                if(v.is_default==1 && goods_unit==''){
                                    obj.find('.default_unit').val(v.name);
                                    obj.find('.default_unit_id').val(v.id);
                                    formatInfo(obj,number,v.stock,v.name,v.id,v.basic_nmber,v.purchase,v.tax_included,v.tax_rate,discount);
                                }else if(goods_unit!='' && v.id==goods_unit){
                                    formatInfo(obj,number,v.stock,v.name,v.id,v.basic_nmber,v.purchase,v.tax_included,v.tax_rate,discount);
                                }
                            });
                            obj.find('.goods_unit').html(unit_html);

                            if(goods_unit!=''){
                                obj.find('.goods_unit').val(goods_unit);
                            }
                        }
                    });
                }
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

                var switchs = 0;
                $(document).on("change", "#c-switch", function(){
                    switchs = $(this).val();
                    if (switchs==1){
                        $('.goods_code_span').hide()
                        var objp = $(this).parents('table')[0].id;
                        var inputs = $("#"+objp+" tr").find(".goods_name");
                        inputs[1].focus();
                    }else{
                        $('.goods_code_span').show()
                    }
                });
                //扫码枪录入
                $(document).on("keydown", "input", function(e){
                    if(e.keyCode == 13){
                        var code = $(this).val();
                        $(this).val('');
                        var obj = $(this).parents('tr');
                        var objp = $(this).parents('table')[0].id;
                        var thisobj = this;

                        $.ajax( {
                            url: "kuerp/goods/getGoods",
                            type: "POST",
                            data:{
                                'code':code
                            },
                            success: function(res){
                                if (res.code==1){
                                    var goods = res.msg;
                                    obj.find('.goods_code').val(goods.goods_code);
                                    obj.find('.goods_name').val(goods.goods_code+' '+goods.goods_name);
                                    changeGoods(goods.goods_code,goods.stores_id,goods.goods_attr,obj,goods.goods_unit,1);
                                    var inputs = $("#"+objp+" tr").find(".goods_name");
                                    for(var i = 0;i<inputs.length;i++){
                                        if(i==(inputs.length-1)){
                                            var lawtable = document.getElementById(objp);
                                            var rows = lawtable.rows;//获取所有行
                                            var html = rows[1].innerHTML;
                                            html = '<tr>'+html+'</tr>';
                                            $(obj).after(html);
                                            inputs = $("#"+objp+" tr").find(".goods_name");
                                            inputs[i+1].focus();
                                            break;
                                        }else if(thisobj == inputs[i]){
                                            inputs[i+1].focus();
                                            break;
                                        }
                                    }
                                }else{
                                    Toastr.error(res.msg);return false;
                                }
                            }
                        });
                        return false;
                    }
                });

                $(document).on("click", ".goods_code_span", function(){
                    if(switchs==1){
                        return false;
                    }
                    var obj = $(this).parents('tr');

                    var lawtable = document.getElementById("sale_order_table");
                    var rows = lawtable.rows;//获取所有行
                    var htmlp = rows[1].innerHTML;
                    var html = '';
                    parent.Fast.api.open("kuerp/Goods/chosegoods", "选择商品",{area:['90%','75%'],callback:function(goodsData){
                            if(goodsData!=undefined){
                                $(goodsData).each(function (k,v) {
                                    if(k>0){
                                        html = '<tr class="goods_id'+v.goods_id+'_'+v.goods_attr+'">'+htmlp+'</tr>';
                                        $(obj).after(html);
                                    }
                                });
                                var customers_id = $('#customers_id').val();
                                $.ajax( {
                                    url: "kuerp/goods/getSaleUnit",
                                    type: "POST",
                                    data:{'where':arrayToJsonString(goodsData),'customers_id':customers_id},
                                    success: function(res){
                                        res = JSON.parse(res);
                                        goods=res;
                                        $(goods).each(function (kp,vp) {
                                            if(kp==0){
                                                var thisobj = obj;
                                            }else{
                                                var thisobj = $('.goods_id'+vp.goods_id+'_'+vp.goods_attr);
                                            }
                                            thisobj.find('.goods_code').val(vp.goods.goods_code);
                                            thisobj.find('.goods_name').val(vp.goods.goods_code+' '+vp.goods.goods_name);
                                            thisobj.find('.goods_note').val(vp.note);
                                            $(vp.unit).each(function (k,v) {
                                                if(v.is_default==1){
                                                    changeGoods(vp.goods.goods_code,vp.goods.manager_warehouse_id,vp.goods_attr,thisobj,'',vp.number);
                                                }
                                            });
                                        })
                                    }
                                })
                            }
                        }});
                });
                $(document).on("change", ".goods_name", function(){
                    changeWidth()
                });
                function changeWidth() {
                    allwidth = 165;
                    var goods_code = document.getElementsByClassName("goods_name");
                    $(goods_code).each(function (k,v) {
                        if(goods_code[k].value!=''){
                            iCount = goods_code[k].value.replace(/[^/u0000\-/u00ff]/g,"aa");
                            width = iCount.length*9;
                            if(width<165){
                                width = 165;
                            }else if(width>250){
                                width = iCount.length*8;
                            }
                            if(width>allwidth){
                                allwidth = width;
                            }
                        }
                    });
                    $(goods_code).each(function (k,v) {
                        goods_code[k].style.width = allwidth+'px';
                    });
                }
                var tableobj;
                $(document).on("input", ".goods_name", function(){
                    if(switchs!=1){
                        var obj = $(this);
                        var goods_name = obj.val();
                        var top = obj.offset().top-(-30);
                        var left = obj.offset().left-(-15);
                        tableobj = $(this).parents('tr');
                        $.ajax( {
                            url: "kuerp/goods/chosegoods",
                            type: "POST",
                            data:{
                                'goods_name':goods_name
                            },
                            success: function(res){
                                if(res.total>0){
                                    var html = '<div class="chose_goods_box" style="left:'+left+'px;top: '+top+'px;">\n' +
                                        '                            <table>\n' +
                                        '                                <tr>\n' +
                                        '                                    <th>商品编号</th><th>商品名称</th><th>规格型号</th><th>计量单位</th><th>商品类别</th><th>库存数量</th><th>备注</th>\n' +
                                        '                                </tr>\n';
                                    max = 5;
                                    if(res.total>5){
                                        max = 5;
                                    }else{
                                        max = res.total;
                                    }
                                    for (var a=0;a<max;a++){
                                        v = res.rows[a];
                                        html += '<tr class="chose_goods_tr" data-code="'+v.goods_code+'" data-name="'+v.goods_name+'" data-note="'+v.note+'" data-store="'+v.manager_warehouse_id+'"><td>'+v.goods_code+'</td><td>'+v.goods_name+'</td><td>'+v.goods_specification_id+'</td><td>'+v.unit.name+'</td><td>'+v.goods_type.name+'</td><td>'+v.stock+'</td><td>'+v.note+'</td></tr>';
                                    }
                                    html += '                            </table>\n' +
                                        '                        </div>';
                                    $('.chose_goods_boxs').html(html);
                                }
                            }
                        });
                    }
                });
                //选择赋值
                $(document).on("click", ".chose_goods_tr", function(){
                    var obj = tableobj;
                    var goods_code = $(this).data('code');
                    var goods_name = $(this).data('name');
                    $('.chose_goods_boxs').html('');
                    obj.find('.goods_code').val(goods_code);
                    obj.find('.goods_name').val(goods_code+' '+goods_name);
                    obj.find('.goods_note').val($(this).data('note'));
                    obj.find('.stores_id').val($(this).data('store'));
                    changeGoods(goods_code,'','',obj,'',1);
                    changeWidth()
                });
                $(document).on("click", ".is-dialog", function () {
                    $('.chose_goods_boxs').html('');
                });

                //选择商品
                $(document).on("change", ".goods_code", function(){
                    var obj = $(this).parents('tr');
                    var goods_code = $(this).val();
                    $(goodsList).each(function (k,v) {
                        if(goods_code==v.goods_code){
                            obj.find('.goods_note').val(v.note);
                            changeGoods(v.goods_code,'','',obj,'');
                            obj.find('.stores_id').val(v.manager_warehouse_id);
                        }
                    });
                });
                //商品属性
                $(document).on("change", ".goods_attr", function(){
                    var obj = $(this).parents('tr');
                    var goods_code = obj.find('.goods_code').val();
                    var attr = $(this).val();
                    var goods_unit = obj.find('.goods_unit').val();
                    var stores_id = obj.find('.stores_id').val();
                    var customers_id = $('#customers_id').val();
                    $.ajax( {
                        url: "kuerp/goods_sales/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':stores_id,'attr':attr,'customers_id':customers_id
                        },
                        success: function(res){
                            res = JSON.parse(res);
                            goods=res;
                            $(goods.unit).each(function (k,v) {
                                if(v.id==goods_unit){
                                    obj.find('.allow_stock').val(v.stock);
                                    var number = obj.find('.number').val();
                                    var discount = obj.find('.discount').val();
                                    formatInfo(obj,number,v.stock,v.name,v.id,v.basic_nmber,v.purchase,v.tax_included,v.tax_rate,discount);
                                }
                            });
                        }
                    })
                });
                // 商品单位
                $(document).on("change", ".goods_unit", function(){
                    var obj = $(this).parents('tr');
                    var goods_code = obj.find('.goods_code').val();
                    var attr = obj.find('.goods_attr').val();
                    var goods_unit = $(this).val();
                    var stores_id = obj.find('.stores_id').val();
                    var customers_id = $('#customers_id').val();
                    $.ajax( {
                        url: "kuerp/goods_sales/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':stores_id,'attr':attr,'customers_id':customers_id
                        },
                        success: function(res){
                            res = JSON.parse(res);
                            goods=res;
                            $(goods.unit).each(function (k,v) {
                                if(v.id==goods_unit){
                                    obj.find('.allow_stock').val(v.stock);
                                    var number = obj.find('.number').val();
                                    var discount = obj.find('.discount').val();
                                    formatInfo(obj,number,v.stock,v.name,v.id,v.basic_nmber,v.purchase,v.tax_included,v.tax_rate,discount);
                                }
                            });
                        }
                    })
                });
                // 仓库
                $(document).on("change", ".stores_id", function(){
                    var obj = $(this).parents('tr');
                    var goods_code = obj.find('.goods_code').val();
                    var attr = obj.find('.goods_attr').val();
                    var goods_unit = obj.find('.goods_unit').val();
                    var stores_id = $(this).val();
                    var customers_id = $('#customers_id').val();
                    $.ajax( {
                        url: "kuerp/goods_purchase/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':stores_id,'attr':attr,'customers_id':customers_id
                        },
                        success: function(res){
                            res = JSON.parse(res);
                            goods=res;
                            $(goods.unit).each(function (k,v) {
                                if(v.id==goods_unit){
                                    obj.find('.allow_stock').val(v.stock);
                                }
                            });
                        }
                    })
                });
                // 单价
                $(document).on("change", ".unit_price", function(){
                    var obj = $(this).parents('tr');
                    changeGoodsInfo(obj,'unit_price')
                });
                // 数量
                $(document).on("change", ".number", function(){
                    var obj = $(this).parents('tr');
                    changeGoodsInfo(obj,'number')
                });
                // 含税单价
                $(document).on("change", ".tax_included", function(){
                    var obj = $(this).parents('tr');
                    changeGoodsInfo(obj,'tax_included')
                });
                // 税率
                $(document).on("change", ".tax_rate", function(){
                    var obj = $(this).parents('tr');
                    changeGoodsInfo(obj,'tax_rate')
                });
                //税额
                $(document).on("change", ".tax_money", function(){
                    var obj = $(this).parents('tr');
                    changeGoodsInfo(obj,'tax_money')
                });
                // 折扣率
                $(document).on("change", ".discount", function(){
                    var obj = $(this).parents('tr');
                    changeGoodsInfo(obj,'discount')
                });
                // 折扣额
                $(document).on("change", ".dis_money", function(){
                    var obj = $(this).parents('tr');
                    changeGoodsInfo(obj,'dis_money')
                });
                //购货金额
                $(document).on("change", ".all_money", function(){
                    var obj = $(this).parents('tr');
                    changeGoodsInfo(obj,'all_money')
                });
                //价税合计
                $(document).on("change", ".money_tax_price", function(){
                    var obj = $(this).parents('tr');
                    changeGoodsInfo(obj,'money_tax_price')
                });


                // 优惠率
                $(document).on("change", "#c-preferential_rate", function(){
                    var set = Config.set;
                    var price_decimal = parseInt(set.price_decimal.value);
                    if(isNaN(price_decimal)){
                        price_decimal = 1;
                    }

                    var goods_code = document.getElementsByName("row[goods_code][]");
                    var all_money = document.getElementsByName("row[money_tax_price][]");
                    var money = 0;
                    $(goods_code).each(function (k,v) {
                        if(goods_code[k].value!=''){
                            nowmoney = parseFloat(all_money[k].value).toFixed(price_decimal);
                            if(!isNaN(nowmoney)){
                                money = money-(-nowmoney);
                            }
                        }
                    });
                    if(money>0){
                        var preferential_money = parseFloat($(this).val()).toFixed(price_decimal);
                        pmoney = (money*preferential_money/100).toFixed(price_decimal);
                        $('#c-preferential_money').val(pmoney);
                    }
                    changeInfo()
                });
                //优惠金额
                $(document).on("change", "#c-preferential_money", function(){
                    var set = Config.set;
                    var price_decimal = parseInt(set.price_decimal.value);
                    if(isNaN(price_decimal)){
                        price_decimal = 1;
                    }

                    var goods_code = document.getElementsByName("row[goods_code][]");
                    var all_money = document.getElementsByName("row[money_tax_price][]");
                    var money = 0;
                    $(goods_code).each(function (k,v) {
                        if(goods_code[k].value!=''){
                            nowmoney = parseFloat(all_money[k].value).toFixed(price_decimal);
                            if(!isNaN(nowmoney)){
                                money = money-(-nowmoney);
                            }
                        }
                    });
                    if(money>0){
                        var preferential_money = parseFloat($(this).val()).toFixed(price_decimal);
                        prate = ((preferential_money/money).toFixed(4)*100).toFixed(2);
                        $('#c-preferential_rate').val(prate);
                    }
                    changeInfo()
                });
                //本次付款
                $(document).on("change", "#c-now_pay_money", function(){
                    changeInfo()
                });


                //分摊采购费用
                $(document).on("click", "#sharebtn", function(){
                    var costs = parseFloat($('#c-costs').val()).toFixed(2);
                    if(!isNaN(costs)){
                        var goods_code = document.getElementsByName("row[goods_code][]");
                        var purchase_money = document.getElementsByName("row[purchase_money][]");
                        var real = 0;
                        $(goods_code).each(function (k,v) {
                            if(goods_code[k].value!=''){
                                real = real-(-1);
                            }
                        });
                        if(real>0){
                            money = (costs/real).toFixed(2);
                            $(goods_code).each(function (k,v) {
                                if(goods_code[k].value!=''){
                                    purchase_money[k].value = money;
                                }
                            });
                        }
                    }
                });
                //修改显示合计信息
                function changeInfo(){
                    var set = Config.set;
                    var price_decimal = parseInt(set.price_decimal.value);
                    if(isNaN(price_decimal)){
                        price_decimal = 1;
                    }

                    var goods_code = document.getElementsByName("row[goods_code][]");
                    var all_money = document.getElementsByName("row[money_tax_price][]");
                    var money = 0;
                    $(goods_code).each(function (k,v) {
                        if(goods_code[k].value!=''){
                            nowmoney = parseFloat(all_money[k].value).toFixed(price_decimal);
                            if(!isNaN(nowmoney)){
                                money = money-(-nowmoney);
                            }
                        }
                    });

                    //优惠额
                    var preferential_money = parseFloat($('#c-preferential_money').val()).toFixed(price_decimal);
                    prate = ((preferential_money/money).toFixed(4)*100).toFixed(2);
                    if(!isNaN(prate)){
                        $('#c-preferential_rate').val(prate);
                    }else{
                        preferential_money = 0;
                    }
                    money = money-preferential_money;

                    var now_pay_money = parseFloat($('#c-now_pay_money').val()).toFixed(2);
                    if(isNaN(now_pay_money)){
                        now_pay_money = 0;
                    }
                    $('#c-real_money').val(money);
                    $('#c-arrears').val(money-now_pay_money);
                }
                //检测是否可生成销货单
                $(document).on("click", ".purchase", function(){
                    var purchase = $(this).data('id');
                    $.ajax( {
                        url: "kuerp/goods_sales_order/checkPurchase",
                        type: "POST",
                        data:{
                            'order_id':purchase
                        },
                        success: function(res){
                            if(res.code==1){
                                parent.$("#createpurchase").val(purchase);
                                parent.$(".createpurchase").trigger("click");
                            }else{
                                Toastr.error(res.msg);
                            }
                        }
                    });
                });
                //生成销货单
                $(document).on("click", ".createpurchase", function(){
                    var purchase = $('#createpurchase').val();
                    Fast.api.open("kuerp/goods_sales/add?ids="+purchase, "销货单",{ area:['90%','75%'],});
                });

                //生成退货单
                $(document).on("click", ".exchange", function(){
                    var purchase = $(this).data('id');
                    $.ajax( {
                        url: "kuerp/goods_sales_order/checkExchange",
                        type: "POST",
                        data:{
                            'order_id':purchase
                        },
                        success: function(res){
                            if(res.code==1){
                                parent.$("#createpurchase").val(purchase);
                                parent.$(".createexchange").trigger("click");
                            }else{
                                Toastr.error(res.msg);
                            }
                        }
                    });
                });
                //生成退货单
                $(document).on("click", ".createexchange", function(){
                    var purchase = $('#createpurchase').val();
                    Fast.api.open("kuerp/goods_sales_exchange/add?ids="+purchase+'&type=1', "退货单",{ area:['90%','75%'],});
                });

                // 添加行
                $(document).on("click", ".add", function(){
                    var lawtable = document.getElementById("sale_order_table");
                    var rows = lawtable.rows;//获取所有行
                    var html = rows[1].innerHTML;
                    html = '<tr>'+html+'</tr>';
                    var obj = $(this).parents('tr');
                    $(obj).after(html);
                    layui.use('laydate', function(){
                        var laydate = layui.laydate;
                        lay('.manufacture').each(function () {
                            laydate.render({
                                elem:this,
                                trigger:"click",
                            })
                        });
                        lay('.validity').each(function () {
                            laydate.render({
                                elem:this,
                                trigger:"click",
                            })
                        })
                    })
                });
                // 删除行
                $(document).on("click", ".del", function(){
                    var lawtable = document.getElementById("sale_order_table");
                    var rows = lawtable.rows;//获取所有行
                    if(rows.length>3){
                        $(this).parents('tr').remove();
                    }else{
                        Toastr.error('至少保留一条数据！');
                    }
                    changeInfo()
                });

                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    if(reloadStatus){
                        parent.location.reload();
                    }else{
                        $('.btn-refresh').click();
                    }
                });
            }
        }
    };
    return Controller;
});