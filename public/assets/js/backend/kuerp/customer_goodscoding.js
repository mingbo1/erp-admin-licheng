define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/customer_goodscoding/index'
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
                        {field: 'customers_id', title: __('Customers_id'),templet: function (d) {
                                return d.customers.customer_name;
                            }},
                        {field: 'goods_id', title: __('Goods_id'),templet: function (d) {
                                if(d.goods.goods_name!=null){
                                    return d.goods.goods_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'unit_id', title: __('Unit_id'),templet: function (d) {
                                return d.unit.name;
                            }},
                        {field: 'customer_code', title: __('Customer_code'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'),templet: function (d) {
                                return layui.util.toDateString(d.createtime*1000);
                            }},
                        {field: 'updatetime', title: __('Updatetime'),templet: function (d) {
                                return layui.util.toDateString(d.updatetime*1000);
                            }},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemo', width:150}
                    ]]
                });

                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'add':
                            Fast.api.open("kuerp/customer_goodscoding/add", "添加", {
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
                            if(data.length<=0){
                                Toastr.error('请选择要删除的数据');return;
                            }
                            Layer.confirm(
                                __('Are you sure you want to delete the %s selected item?', data.length),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function (index) {
                                    Fast.api.ajax("kuerp/customer_goodscoding/del?ids="+ids, function () {
                                        $('.btn-refresh').click();
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                        break;
                        case 'import':
                            Fast.api.open("kuerp/customer_goodscoding/import", "导入", {
                                callback:function(value){
                                    Controller.api.bindevent();
                                }
                            });
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
                                $.ajax( {
                                    url: "kuerp/customer_goodscoding/del",
                                    type: "POST",
                                    data:{'ids':data.id},
                                    success: function(res){
                                        if(res.code==1){
                                            Toastr.success('操作成功');
                                            setTimeout(function () {
                                                $('.btn-refresh').click();
                                            },1000)
                                        }else{
                                            Toastr.error(res.msg);
                                        }
                                    }
                                });
                                Layer.close(index);
                            }
                        );
                    } else if(obj.event === 'edit'){
                        Fast.api.open("kuerp/customer_goodscoding/edit?ids="+data.id, "编辑");
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
                    var customer_code = $('#customer_code').val();
                    if(customer_code!=''){
                        search.customer_code = customer_code;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.purchase_code = 'LIKE';
                    return op;
                }
            });
            Controller.api.bindevent();
        },
        import: function () {
            $(document).on("click", ".step-btn_btn", function(){
                var rel = $(this).data('rel');
                $('.ui_buttons').hide();
                $('.step_'+rel).show();
                $('.step-item').hide();
                $('#import-'+rel).show();

                $('.steps-ul').find('li').removeClass('step1-cur');
                $('.steps-ul').find('li').removeClass('step2-cur');
                $('.steps-ul').find('li').removeClass('step3-cur');
                if(!$('.step1-base').hasClass('step1-nol')){
                    $('.step1-base').addClass('step1-nol');
                }
                if(!$('.step2-base').hasClass('step2-nol')){
                    $('.step2-base').addClass('step2-nol');
                }
                if(!$('.step3-base').hasClass('step3-nol')){
                    $('.step3-base').addClass('step3-nol');
                }
                $('.'+rel+'-base').addClass(rel+'-cur');
            });
            $(document).on("click", ".btn-close", function(){
                parent.layer.closeAll()
            });
            $(document).on("click", ".finish_btn", function(){
                parent.layer.closeAll();
                parent.location.reload();
            });
            $(document).on("click", ".chk", function(){
                if(!$(this).hasClass('checked')){
                    $(this).addClass('checked');
                }else{
                    $(this).removeClass('checked');
                }
                if($('.quantity').hasClass('checked')){
                    $('#quantityStorage').show();
                }else{
                    $('#quantityStorage').hide();
                }
            });


            layui.use(['upload'], function(){
                upload = layui.upload;
                upload.render({
                    elem: '#upfile'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                        if(res.code==1){
                            var editstatus = $("input[name='editstatus']:checked").val();
                            $.ajax( {
                                url: "kuerp/customer_goodscoding/import",
                                type: "POST",
                                data:{
                                    'editstatus':editstatus,
                                    'file':res.data.url,
                                },
                                success: function(res){
                                    if(res.code==1){
                                        $('#addMsg').html(res.msg.add+'条');
                                        $('#updateMsg').html(res.msg.update+'条');
                                        Toastr.success('导入成功');
                                    }else{
                                        Toastr.error(res.msg);
                                    }
                                }
                            });
                        }else{
                            Toastr.error(res.msg);
                        }
                    }
                    ,accept: 'file' //允许上传的文件类型
                    ,size: 50 //最大允许上传的文件大小
                });
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
                        inputs[0].focus();
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

                    var lawtable = document.getElementById("purchase_table");
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
                                $.ajax( {
                                    url: "kuerp/goods/getUnit",
                                    type: "POST",
                                    data:{'where':arrayToJsonString(goodsData)},
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
                                            $(vp.unit).each(function (k,v) {
                                                if(v.is_default==1){
                                                    thisobj.find('.unit').val(v.name);
                                                    thisobj.find('.unit_id').val(v.id);
                                                }
                                            });
                                        })
                                    }
                                })
                            }
                        }});
                });
                $(document).on("change", ".goods_name", function(){
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
                });
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
                    changeGoods(goods_code,'','',obj,'',1);
                });
                $(document).on("click", ".is-dialog", function () {
                    $('.chose_goods_boxs').html('');
                });

                $(document).on("click", ".del", function(){
                    $(this).parents('tr').remove();
                });
                var goods = Config.goods;
                list = JSON.parse(goods);
                $(document).on("click", ".add", function(){
                    var obj = $(this).parents('tr');
                    var lawtable = document.getElementById("purchase_table");
                    var rows = lawtable.rows;//获取所有行
                    var html = rows[1].innerHTML;
                    html = '<tr>'+html+'</tr>';
                    $(obj).after(html);
                });

                $(document).on("change", ".goods_id", function(){
                    var obj = $(this);
                    var goods_id = obj.val();
                    $(list).each(function (k,v) {
                        if(v.id==goods_id){
                            obj.parents('tr').find('.unit').val(v.unit_name);
                            obj.parents('tr').find('.unit_id').val(v.unit_id);
                        }
                    });
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