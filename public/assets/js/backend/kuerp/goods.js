define(['jquery', 'bootstrap', 'backend', 'table', 'form','/assets/addons/kuerp/layui/layui.js','jstree'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods/index'
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
                        {field: 'goods_code', title: __('Goods_code'), operate: 'LIKE'},
                        {field: 'goods_name', title: __('Goods_name'), operate: 'LIKE', width: 200},
                        {field: 'goods_specification_id', title: __('Goods_specification_id'), width: 180},
                        {field: 'vendor_name', title: '规格2/厂商'},
                        {field: 'packaging', title: '规格3/封装', width: 150},
                        {field: 'goods_type_id', title: __('Goods_type_id'),templet: function (d) {
                                if(d.goodstype.supplier_type_name!=null){
                                    return d.goodstype.supplier_type_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'product_type', title: __('Product_type'),templet: function (d) {
                                if(d.product_type=='finished'){
                                    return __('Product_type finished');
                                }
                                if(d.product_type=='semi_finished'){
                                    return __('Product_type semi_finished');
                                }
                                if(d.product_type=='part'){
                                    return __('Product_type part');
                                }
                                return '';
                            }},
                        {field: 'location_id', title: __('Location_id'),templet: function (d) {
                                if(d.goodslocation && d.goodslocation.location_name){
                                    return d.goodslocation.location_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'unit', title: __('Unit'),templet: function (d) {
                                if(d.more_unit==1){
                                    return d.unitconversion;
                                }
                                if(d.more_unit==2){
                                    return d.unit.name;
                                }
                            }},
                        {field: 'market_sell_price', title: __('Market_sell_price'), operate:'BETWEEN'},
                        // {field: 'vip_price', title: __('Vip_price'), operate:'BETWEEN'},
                        {field: 'purchase', title: __('Purchase'), operate:'BETWEEN'},
                        {field: 'goods_image', title: __('Goods_image'), operate: false,templet: function (d) {
                                return "<a href='javascript:;'><img src="+d.goods_image+" style='width:50px;height:50px;'></a>";
                            }},
                        {field: 'inventory', title: __('Inventory')},
                        {field: 'status', title: __('Status'),templet: function (d) {
                                if(d.status==1){
                                    return '<input type="checkbox" data-id="'+d.id+'" checked="" name="open" lay-skin="switch" lay-filter="switchList" lay-text="上架|下架">'
                                }else{
                                    return '<input type="checkbox" data-id="'+d.id+'" name="open" lay-skin="switch" lay-filter="switchList" lay-text="上架|下架">'
                                }
                            }},
                        {field: 'is_show', title: __('Is_show'),templet: function (d) {
                                if(d.is_show==1){
                                   return '<input type="checkbox" data-id="'+d.id+'" checked="" name="open" lay-skin="switch" lay-filter="switchShow" lay-text="显示|隐藏">'
                                }else{
                                    return '<input type="checkbox" data-id="'+d.id+'" name="open" lay-skin="switch" lay-filter="switchShow" lay-text="显示|隐藏">'
                                }
                            }},
                        {field: 'frequent_customers_text', title: __('Frequent_customers'), minWidth: 150},
                        {field: 'updatetime', title: __('Updatetime'),templet: function (d) {
                                return layui.util.toDateString(d.updatetime*1000);
                            }},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemo', width:150}
                    ]], done: function () {
                        $(".layui-table-main tr").each(function (index, val) {
                            $(".layui-table-fixed").each(function () {
                                $($(this).find(".layui-table-body tbody tr")[index]).height($(val).height());
                            });
                        });
                        $(".layui-table-header tr").each(function (index, val) {
                            $(".layui-table-fixed").each(function () {
                                $($(this).find(".layui-table-header thead tr")[index]).height($(val).height());
                            });
                        });
                    }
                });

                upload.render({
                    elem: '#upfile'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                        if(res.code==1){
                            Fast.api.ajax("kuerp/goods/import?file="+res.data.url, "",'',true);
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
                            Fast.api.open("kuerp/goods/add", "添加", {
                                callback:function(value){
                                    Controller.api.bindevent();
                                },
                                area:['900px','700px'],
                            });
                            break;
                        case 'import':
                            Fast.api.open("kuerp/goods/import", "导入", {
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
                                    Fast.api.ajax("kuerp/goods/del?ids="+ids, function () {
                                        reloadTable()
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
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
                layui.form.on('switch(switchList)', function (obj) {
                    var onoff = this.checked ? '1' : '0';
                    var id = $(this).data('id');
                    $.ajax( {
                        url: "kuerp/goods/multi",
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
                layui.form.on('switch(switchShow)', function (obj) {
                    var onoff = this.checked ? '1' : '0';
                    var id = $(this).data('id');
                    $.ajax( {
                        url: "kuerp/goods/multi",
                        type: "POST",
                        data:{
                            'ids':id,
                            'params':"is_show="+onoff,
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
                                    url: "kuerp/goods/del",
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
                                Layer.close(index);
                            }
                        );
                    } else if(obj.event === 'edit'){
                        Fast.api.open("kuerp/goods/edit?ids="+data.id, "编辑",{ area:['900px','700px'],});
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
                    var goods_code = $('#goods_code').val();
                    if(goods_code!=''){
                        search.goods_code = goods_code;
                    }
                    var goods_name = $('#goods_name').val();
                    if(goods_name!=''){
                        search.goods_name = goods_name;
                    }
                    var goods_specification_id = $('#goods_specification_id').val();
                    if(goods_specification_id!=''){
                        search.goods_specification_id = goods_specification_id;
                    }
                    var packaging = $('#packaging').val();
                    if(packaging!=''){
                        search.packaging = packaging;
                    }
                    var vendor_name = $('#vendor_name').val();
                    if(vendor_name!=''){
                        search.vendor_name = vendor_name;
                    }
                    var status = $('#status').val();
                    if(status!=''){
                        search.status = status;
                    }
                    var product_type = $('#product_type').val();
                    if(product_type!=''){
                        search.product_type = product_type;
                    }
                    var location_id = $('#location_id').val();
                    if(location_id!=''){
                        search.location_id = location_id;
                    }
                    var supplier_id = $('#supplier_id').val();
                    if(supplier_id!=''){
                        search.supplier_id = supplier_id;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.goods_code = 'LIKE';
                    op.goods_name = 'LIKE';
                    op.goods_specification_id = 'LIKE';
                    op.packaging = 'LIKE';
                    op.vendor_name = 'LIKE';
                    op.product_type = '=';
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
        period: function () {
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

            var skulist = new Array();
            $(document).on("click", ".chk", function(){
                var thisid = $(this).data('id');
                if(!$(this).hasClass('checked')){
                    $(this).addClass('checked');
                    if(thisid!=undefined){
                        skulist.push(thisid);
                    }
                }else{
                    $(this).removeClass('checked');
                    if(thisid!=undefined){
                        var index = skulist.indexOf(thisid);
                        if (index > -1) {
                            skulist.splice(index, 1);
                        }
                    }
                }
                if($('.quantity').hasClass('checked')){
                    $('#quantityStorage').show();
                }else{
                    $('#quantityStorage').hide();
                }
                if($('.sku').hasClass('checked')){
                    $('#skuTypeWrap').show();
                }else{
                    skulist = [];
                    $('.skuList').removeClass('checked');
                    $('#skuTypeWrap').hide();
                }
            });

            layui.use(['upload'], function(){
                upload = layui.upload;
                upload.render({
                    elem: '#upfile'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                        if(res.code==1){
                            more_unit = 0;quantity = 0;
                            if($('.more_unit').hasClass('checked')){
                                more_unit = 1;
                            }
                            if($('.quantity').hasClass('checked')){
                                quantity = 1;
                            }
                            var stores = $('#stores').val();
                            var editstatus = $("input[name='editstatus']:checked").val();
                            $.ajax( {
                                url: "kuerp/goods/import",
                                type: "POST",
                                data:{
                                    'more_unit':more_unit,
                                    'quantity':quantity,
                                    'stores':stores,
                                    'editstatus':editstatus,
                                    'skulist':skulist,
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
        chosegoods: function () {
            layui.use(['table','upload', 'form'], function(){
                var table = layui.table;
                upload = layui.upload;
                form = layui.form;

                //从弹窗URL取客户ID（销货订单选货时常用客户商品优先，仿chosesale的URL传参方式）
                var customersId = '';
                var cm = window.location.search.match(/[?&]customers_id=([^&]*)/);
                if (cm) { customersId = cm[1]; }

                // 设置全局变量以保存选中行信息(仅需要id的话在你的业务位置调用ids即可，数据格式是int数组)
                let ids = new Array();
                // 存储所有选中的数据(需要行内全量数据在你的业务位置调用lists即可，数据格式是对象集合)
                var lists = new Array();
                let listsArr = new Array();
                // 保存当前页全部数据id，点击全选时使用
                let tableIds = new Array();
                let tableArr = new Array();
                var a=0;

                table.render({
                    elem: '#choseTable'
                    ,url:'kuerp/goods/choseGoods' + (customersId ? '?customers_id=' + customersId : '')
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                        };
                    }
                    , id: 'layuiReload'
                    ,toolbar: '#btnToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,page: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true},
                        {field: 'id', title: 'ID', minWidth: 80},
                        {field: 'goods_code', title: __('Goods_code'), minWidth: 130, templet: function (d) {
                                // 商品编号完整展示，过长换行
                                return '<div style="white-space:normal;word-break:break-all;line-height:18px;padding:2px 0;" title="' + (d.goods_code || '') + '">' + (d.goods_code || '') + '</div>';
                            }},
                        {field: 'goods_name', title: __('Goods_name'), minWidth: 220, templet: function (d) {
                                // 商品名称完整展示，过长换行；带客户参数时常用客户商品加"常用"标识
                                var name = d.goods_name;
                                if (customersId && d.is_frequent == 1) {
                                    name = '<span class="text-danger">[常用]</span> ' + name;
                                }
                                return '<div style="white-space:normal;word-break:break-all;line-height:18px;padding:2px 0;" title="' + d.goods_name + '">' + name + '</div>';
                            }},
                        {field: 'goods_specification_id', title: '型号', minWidth: 110},
                        {field: 'vendor_name', title: '规格2/厂商', minWidth: 120},
                        {field: 'packaging', title: '规格3/封装', minWidth: 120},
                        {field: 'goods_type_id', title: __('Goods_type_id'),templet: function (d) {
                                if(d.goods_type==null){
                                    return '';
                                }else{
                                    return d.goods_type.name;
                                }
                            }},
                        {field: 'product_type', title: __('Product_type'),templet: function (d) {
                                if(d.product_type=='finished') return __('Product_type finished');
                                if(d.product_type=='semi_finished') return __('Product_type semi_finished');
                                if(d.product_type=='part') return __('Product_type part');
                                return '';
                            }},
                        {field: 'location_id', title: __('Location_id'),templet: function (d) {
                                if(d.goods_location && d.goods_location.name){
                                    return d.goods_location.name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'attr_name', title: __('商品属性'), hide: true},
                        {field: 'unit', title: __('Unit'),templet: function (d) {
                                if(d.unit==null){
                                    return '';
                                }else{
                                    return d.unit.name;
                                }
                            }},
                        {field: 'stock', title: __('库存数量')},
                        {field: 'locked_quantity', title: __('已锁定'), templet: function (d) {
                                if (d.locked_quantity === '' || d.locked_quantity === null || parseFloat(d.locked_quantity) <= 0) {
                                    return '<span class="text-gray">0</span>';
                                }
                                return '<span class="text-warning">' + d.locked_quantity + '</span>';
                            }},
                        {field: 'available_stock', title: __('可用库存'), templet: function (d) {
                                if (d.available_stock === '' || d.available_stock === null) return '';
                                var cls = parseFloat(d.available_stock) <= 0 ? 'text-danger' : 'text-success';
                                return '<span class="' + cls + '">' + d.available_stock + '</span>';
                            }},
                        {field: 'number', title: __('录入数量'),edit:'text',templet: function (d) {
                                return '';
                            }},
                        {field: 'note', title: __('备注')},
                        {field: 'is_offten', title: __('Is_offten'),templet: function (d) {
                                if(d.is_offten==0){
                                    return '';
                                }
                                if(d.is_offten==1){
                                    return __('Is_offten 1');
                                }
                            }},
                    ]]
                    , done: function (res, curr, count) {
                        // 设置当前页全部数据id到全局变量
                        tableIds = [];tableArr=[];
                        $.each(res.data, function (k, v) {
                            if(tableIds.indexOf(v.id+'-'+v.goods_attr)===-1){
                                tableIds.push(v.id+'-'+v.goods_attr);
                                listsArr.push(v);
                                tableArr.push(v);
                            }
                        });
                        // 设置当前页选中项
                        $.each(res.data, function (idx, val) {
                            if (ids.indexOf(val.id+'-'+val.goods_attr) > -1) {
                                val["LAY_CHECKED"] = 'true';
                                //找到对应数据改变勾选样式，呈现出选中效果
                                let index = val['LAY_TABLE_INDEX'];
                                $('tr[data-index=' + index + '] input[type="checkbox"]').click();
                                form.render('checkbox'); //刷新checkbox选择框渲染
                            }
                        });
                        // 获取表格勾选状态，全选中时设置全选框选中
                        var allStatus = true;
                        $.each(tableIds,function (k,v) {
                            var exitsStatus = false;
                            $.each(lists,function (k2,v2) {
                                if(v === v2['id']+'-'+v2['goods_attr']){
                                    exitsStatus = true;
                                }
                            });
                            if(!exitsStatus){
                                allStatus = false;
                            }
                        });
                        if (allStatus) {
                            $('.layui-table-header th[data-field="0"] input[type="checkbox"]').prop('checked', true);
                            form.render('checkbox'); //刷新checkbox选择框渲染
                        }
                    }
                });

                //使用on监听checkbox选中状态并进行处理(tableFilter为table的lay-filter值)
                table.on('checkbox(table)', function (obj) {
                    if (obj.checked == true) {
                        if (obj.type == 'one') {
                            ids.push(obj.data.id+'-'+obj.data.goods_attr);
                            lists.push(obj.data);
                        } else {
                            for (let i = 0; i < tableIds.length; i++) {
                                //当全选之前选中了部分行进行判断，避免重复
                                if (ids.indexOf(tableIds[i]) == -1) {
                                    ids.push(tableIds[i]);
                                    lists.push(tableArr[i]);
                                }
                            }
                        }
                    } else {
                        if (obj.type == 'one') {
                            let i = ids.length;
                            while (i--) {
                                if (ids[i] == obj.data.id+'-'+obj.data.goods_attr) {
                                    ids.splice(i, 1);
                                    lists.splice(i, 1);
                                }
                            }
                        } else {
                            let i = ids.length;
                            while (i--) {
                                if (tableIds.indexOf(ids[i]) != -1) {
                                    ids.splice(i, 1);
                                    lists.splice(i, 1);
                                }
                            }
                        }
                    }

                    var allStatus = true;
                    $.each(tableIds,function (k,v) {
                        var exitsStatus = false;
                        $.each(lists,function (k2,v2) {
                            if(v === v2['id']+'-'+v2['goods_attr']){
                                exitsStatus = true;
                            }
                        });
                        if(!exitsStatus){
                            allStatus = false;
                        }
                    });
                    if (allStatus) {
                        $('.layui-table-header th[data-field="0"] input[type="checkbox"]').prop('checked', true);
                        form.render('checkbox'); //刷新checkbox选择框渲染
                    }
                });

                table.on('toolbar(table)', function(obj){
                    switch(obj.event){
                        case 'sure':
                            var data = lists;
                            if(data.length<1){
                                Toastr.error('请至少选择一个商品！');return;
                            }
                            var ids = [];
                            $(data).each(function (k,v) {
                                var arr = [];
                                arr['goods_id'] = v.id;
                                arr['goods_attr'] = v.goods_attr;
                                arr['number'] = v.number;
                                arr['note'] = v.note;
                                ids.push(arr)
                            });
                            Fast.api.close(ids);//这里是重点，将这个ids 传至父页面
                            break;
                    }
                });

                var set = Config.set;
                var number_decimal = parseInt(set.number_decimal.value);
                if(isNaN(number_decimal)){
                    number_decimal = 1;
                }

                table.on('edit(table)', function (obj) {
                    var value = obj.value //得到修改后的值
                        , data = obj.data //得到所在行所有键值
                        , field = obj.field; //得到字段
                    if ((field == 'number')) {
                        value = parseFloat(value);
                        if(!isNaN(value)){
                            value = value.toFixed(number_decimal);
                            $(this).parent().html(value);
                        }
                    }
                });
                var rdata;
                //搜索事件
                $('#search').on('click', function () {
                    table.reload('layuiReload', {
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
                    var keyword = $('#keyword').val();
                    if(keyword!=''){
                        search.keyword = keyword;
                    }
                    //型号单独查询
                    var goods_specification_id = $('#goods_specification_id').val();
                    if(goods_specification_id!=''){
                        search.goods_specification_id = goods_specification_id;
                    }
                    var store = $('#store').val();
                    if(store!=''){
                        search.store = store;
                    }
                    if(rdata!=''){
                        search.type = rdata;
                    }
                    var product_type = $('#product_types').val();
                    if(product_type!=''){
                        search.product_type = product_type;
                    }
                    var location_id = $('#location_ids').val();
                    if(location_id!=''){
                        search.location_id = location_id;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.store = 'LIKE';
                    op.product_type = '=';
                    return op;
                }
                $.ajax({
                    url: "kuerp/goods/gettype",
                    type: 'post',
                    dataType: 'json',
                    data: '',
                    success: function (ret) {
                        if (ret.hasOwnProperty("code")) {
                            var data = ret.hasOwnProperty("data") && ret.data != "" ? ret.data : "";
                            if (ret.code === 1) {
                                //销毁已有的节点树
                                $("#treeview").jstree("destroy");

                                $("#treeview").on('redraw.jstree', function (e) {
                                    $(".layer-footer").attr("domrefresh", Math.random());
                                }).jstree({
                                    "themes": {"stripes": true},
                                    "checkbox": {
                                        "keep_selected_style": false,
                                    },
                                    "types": {
                                        "root": {
                                            "icon": "fa fa-folder-open",
                                        },
                                        "menu": {
                                            "icon": "fa fa-folder-open",
                                        },
                                        "file": {
                                            "icon": "fa fa-file-o",
                                        }
                                    },
                                    "plugins": ["checkbox", "types"],
                                    "core": {
                                        'check_callback': true,
                                        // 数据直接传入时 jstree 默认用 Worker 解析，压缩版在 Worker 内会抛
                                        // ReferenceError，导致分类树渲染失败，这里改为主线程解析
                                        "worker": false,
                                        "data": data
                                    }
                                });
                                $("#treeview").on("changed.jstree", function (e, data) {
                                    var i, j, r = [];
                                    for(i = 0, j = data.selected.length; i < j; i++) {
                                        r.push(data.instance.get_node(data.selected[i]).id);
                                    }
                                    rdata = r;
                                    goodssearch(r);
                                }).jstree({
                                    "plugins" : [ "changed" ]
                                });

                            } else {
                                Backend.api.toastr.error(ret.msg);
                            }
                        }
                    }, error: function (e) {
                        Backend.api.toastr.error(e.message);
                    }
                });
                function getSearchs() {
                    var search = {};
                    var keyword = $('#keyword').val();
                    if(keyword!=''){
                        search.keyword = keyword;
                    }
                    var store = $('#store').val();
                    if(store!=''){
                        search.store = store;
                    }
                    if(r!=''){
                        search.type = r;
                    }
                    var product_type = $('#product_types').val();
                    if(product_type!=''){
                        search.product_type = product_type;
                    }
                    var location_id = $('#location_ids').val();
                    if(location_id!=''){
                        search.location_id = location_id;
                    }
                    return search;
                }
                function goodssearch(r){
                    table.reload('layuiReload', {
                        where: {
                            filter:JSON.stringify(getSearch()),
                            op:JSON.stringify(getOp()),
                        }
                        ,page: {
                            curr: 1
                        }
                    });
                }
                //头工具栏事件
                $(document).on("click", "#cancel", function(){
                    Fast.api.close();
                });
            });
            Controller.api.bindevent();
        },
        chosesale: function () {
            layui.use(['table','upload'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#choseTable'
                    ,url:'kuerp/goods/chosesale?customers_id='+Config.customers_id+'&supplyer_id='+Config.supplyer_id
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                        };
                    }
                    ,toolbar: '#btnToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,page: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true},
                        {field: 'purchase_code', title: __('源单编号'),width:150},
                        {field: 'purchase_type', title: __('业务类别')},
                        {field: 'purchase_time', title: __('单据日期'),width:130},
                        {field: 'money_tax_price', title: __('单据金额')},
                        {field: 'pay_money', title: __('已核销金额')},
                        {field: 'money', title: __('未核销金额')},
                        {field: 'note', title: __('备注')},
                    ]]
                });
                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    switch(obj.event){
                        case 'sure':
                            var data = checkStatus.data;
                            if(data.length<1){
                                Toastr.error('请至少选择一个源单！');return;
                            }
                            var ids = [];
                            $(data).each(function (k,v) {
                                var arr = [];
                                arr['purchase_number'] = v.purchase_code;
                                arr['purchase_type_id'] = v.purchase_type_id;
                                arr['business_type'] = v.purchase_type;
                                arr['times'] = v.purchase_time;
                                arr['sale_money'] = v.money_tax_price;
                                arr['write_off'] = v.pay_money;
                                arr['write_off_price'] = v.money;
                                arr['sale_remark'] = v.note;
                                ids.push(arr)
                            });
                            Fast.api.close(ids);//这里是重点，将这个ids 传至父页面
                            break;
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    table.reload('choseTable', {
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
                    var purchase_time = $('#purchase_time').val();
                    if(purchase_time!=''){
                        search.purchase_time = purchase_time;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.purchase_time = 'RANGE';
                    return op;
                }
                //头工具栏事件
                $(document).on("click", "#cancel", function(){
                    Fast.api.close();
                });
            });
            Controller.api.bindevent();
        },
        api: {
            bindevent: function (reloadStatus=true) {
                //属性分类选择开关
                layui.use('laydate', function(){
                    var laydate = layui.laydate;
                    //执行一个laydate实例
                    laydate.render({
                        elem: '.manufacture',
                        done: function(value){
                            manufacture();
                        }
                    });
                    laydate.render({
                        elem: '#purchase_time',
                        type: 'datetime',
                        range: true
                    });
                });

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

                //属性分类选择开关
                $(document).on("click", "#auxiliary", function(){
                    var obj = document.getElementsByName('row[auxiliary]');
                    check_val = '';
                    for(k in obj){
                        if(obj[k].checked)
                            check_val=obj[k].value;
                    }
                    if(check_val==1){
                        $('.auxiliary_div').show();
                        autocreat();
                        $('#price_strategy').hide();
                    }else{
                        var attr_open = '<option value=""></option>';
                        $('.stock_attr_id').html(attr_open);
                        $('.auxiliary_div').hide();
                        $('#price_strategy').show();
                    }
                });
                //属性一级分类选择
                $(document).on("click", ".auxiliary_p", function(){
                    checkAux()
                });
                //属性二级分类选择
                $(document).on("click", ".auxiliary_type_c", function(){
                    checkAux()
                });
                //属性分类选择切换显示
                function checkAux(){
                    var obj = document.getElementsByName('row[auxiliary_type][]');
                    $('.aux_tab').hide();
                    for (var i = 0; i < obj.length; i++) {
                        if (obj[i].checked) {
                            $('.aux_tab'+obj[i].value).show();
                            $('.auxiliary_p_s'+obj[i].value).val('');
                        }
                    }

                    obj = document.getElementsByName('row[auxiliary_type_c][]');
                    $('.aux_c').hide();
                    var check_val = [];
                    for (var i = 0; i < obj.length; i++) {
                        if (obj[i].checked) {
                            $('.aux_c'+obj[i].value).show();
                            check_val.push(obj[i].value);
                        }
                    }
                }
                //添加行
                $(document).on("click", ".auxiliary_add", function(){
                    var lawtable = document.getElementById("auxiliary_table");
                    var rows = lawtable.rows;//获取所有行
                    var html = rows[1].innerHTML;
                    html = '<tr>'+html+'</tr>';
                    var obj = $(this).parents('tr');
                    $(obj).after(html);
                });
                $(document).on("click", ".auxiliary_del", function(){
                    var lawtable = document.getElementById("auxiliary_table");
                    var rows = lawtable.rows;//获取所有行
                    if(rows.length>2){
                        $(this).parents('tr').remove();
                    }else{
                        Toastr.error('至少保留一条分录！');
                    }
                });
                //快速生成属性组合
                $(document).on("click", "#btn-autocreat", function(){
                    var obj = document.getElementsByName('row[auxiliary_type][]');
                    var typearr = [];
                    for (var i = 0; i < obj.length; i++) {
                        if (obj[i].checked) {
                            typearr.push(obj[i].value);
                        }
                    }

                    obj = document.getElementsByName('row[auxiliary_type_c][]');
                    var typesarr = [];
                    for (var i = 0; i < obj.length; i++) {
                        if (obj[i].checked) {
                            typesarr.push(obj[i].value);
                        }
                    }
                    if(typearr.length<1 || typesarr.length<1){
                        Toastr.error('请选择辅助属性');return;
                    }
                    var code =$('#c-goods_code').val();
                    $.ajax( {
                        url: "kuerp/goods/auxiliary",
                        type: "POST",
                        data:{
                            'typearr':typearr,'typesarr':typesarr,'code':code
                        },
                        success: function(res){
                            if(res.code===1){
                                var data = res.msg;
                                var body = $('#example').html();
                                html = '<tr id="example" class="auxiliary_example">'+body+'</tr>';
                                for (var a=0;a<data.lists.length-1;a++){
                                    html += '<tr>'+body+'</tr>';
                                }

                                var attr_open = '<option value=""></option>';
                                for (a=0;a<data.lists.length;a++){
                                    attr_open += '<option value="'+data.lists[a].id+'">'+data.lists[a].spec_name+'</option>';
                                }
                                $('.stock_attr_id').html(attr_open);

                                $('#auxiliary_table_tbody').html(html);
                                for (a=1;a<=data.lists.length;a++){
                                    for (var b=0;b<data.lists[a-1].spec_id.length;b++){
                                        $("#auxiliary_table_tbody tr:nth-child("+a+")").find('.aux_tab'+typearr[b]).find('select').val(data.lists[a-1].spec_id[b]);
                                    }
                                    $("#auxiliary_table_tbody tr:nth-child("+a+")").find('.auxiliary_code').val(data.lists[a-1].code);
                                }
                            }
                        }
                    });
                });
                //建立属性组合
                function autocreat(){
                    var obj = document.getElementsByName('row[auxiliary_type][]');
                    var typearr = [];
                    for (var i = 0; i < obj.length; i++) {
                        if (obj[i].checked) {
                            typearr.push(obj[i].value);
                        }
                    }
                    var auxiliary = JSON.parse(Config.auxiliary);

                    var lawtable = document.getElementById("auxiliary_table");
                    var rows = lawtable.rows;//获取所有行
                    var data = [];
                    for(var a=0;a<rows.length;a++){
                        if(a>0){
                            arr_row = '';
                            ids_row = '';
                            $(typearr).each(function (k,v) {
                                name_str = $("#auxiliary_table tr:nth-child("+a+")").find('.auxiliary_p_s'+v).val();
                                if(name_str!=''){
                                    arr_row = arr_row+auxiliary[name_str]+'/';
                                    ids_row = ids_row+name_str+'&';
                                }
                            });
                            if(arr_row!=''){
                                arr_row = arr_row.substr(0, arr_row.length - 1);
                                ids_row = ids_row.substr(0, ids_row.length - 1);
                                data.push({'id':ids_row,'spec_name':arr_row});
                            }
                        }
                    }
                    var attr_open = '<option value=""></option>';
                    for (a=0;a<data.length;a++){
                        attr_open += '<option value="'+data[a].id+'">'+data[a].spec_name+'</option>';
                    }
                    $('.stock_attr_id').html(attr_open);
                }
                $(document).on("change", ".auxiliary_p_s", function(){
                    autocreat()
                });

                //启用批次保质期管理
                $(document).on("click", "#batch", function(){
                    obj = document.getElementsByName('row[serial]');
                    check_val2 = '';
                    for(k in obj){
                        if(obj[k].checked)
                            check_val2=obj[k].value;
                    }

                    var obj = document.getElementsByName('row[batch]');
                    check_val = '';
                    for(k in obj){
                        if(obj[k].checked)
                            check_val=obj[k].value;
                    }

                    var lawtable = document.getElementById("stock_table");
                    var rows = lawtable.rows;//获取所有行
                    for (a=1;a<=rows.length;a++){
                        if(a>1){
                            var dates =  $("#stock_table tr:nth-child("+a+") td:nth-child(5)").find('input').val();
                            if(dates!='' && check_val==''){
                                for (var n =0;n<obj.length;n++) {
                                    obj[n].checked = true;
                                }
                                Toastr.error('期初中设置了保质期的分录，不能修改该属性！');return;
                            }
                        }
                    }

                    if(check_val2==check_val && check_val==1 && check_val2==1){
                        for (var n =0;n<obj.length;n++) {
                            obj[n].checked = false;
                        }
                        Toastr.error('商品不能同时启用序列号和批次号');return;
                    }

                    if(check_val==1){
                        $('.shelf_life_box').show();
                        $('.batch_set').show();
                    }else{
                        $('.shelf_life_box').hide();
                        $('.batch_set').hide();
                    }
                });
                //启用序列号管理
                $(document).on("click", "#serial", function(){
                    var obj = document.getElementsByName('row[batch]');
                    check_val = '';
                    for(k in obj){
                        if(obj[k].checked)
                            check_val=obj[k].value;
                    }

                    obj = document.getElementsByName('row[serial]');
                    check_val2 = '';
                    for(k in obj){
                        if(obj[k].checked)
                            check_val2=obj[k].value;
                    }

                    if(check_val2!=1){
                        $(".period_ipt").attr("readOnly",false);
                    }
                    if(check_val2==check_val && check_val==1 && check_val2==1){
                        for (var n =0;n<obj.length;n++) {
                            obj[n].checked = false;
                        }
                        $(".period_ipt").attr("readOnly",false);
                        Toastr.error('商品不能同时启用序列号和批次号');return;
                    }
                    if(check_val2==1){
                        $(".period_ipt").attr("readOnly","true");
                    }
                });

                //自动添加序列号
                $(document).on("click", "#addAuto", function(){
                    var prefix = $('#c-prefix').val();
                    var start = $('#c-start').val();
                    var startlen = start.length;
                    start = parseFloat(start);
                    var numlen = start.toString().length;
                    var cha = startlen-numlen;
                    var str = '';
                    for (var a=1;a<=cha;a++){
                        str = str+'0';
                    }
                    var incremental = parseFloat($('#c-incremental').val());
                    var number = parseFloat($('#c-number').val());
                    if(start=='' || isNaN(start)){
                        Toastr.error('请输入正确的起始号！');return false;
                    }
                    if(incremental=='' || isNaN(incremental)){
                        Toastr.error('请输入正确的递增量！');return false;
                    }
                    if(number=='' || isNaN(number)){
                        Toastr.error('请输入正确的个数！');return false;
                    }
                    if(number>500){
                        Toastr.error('不支持自动生成大于500的序列号！');return false;
                    }
                    var arr = [];var xl;
                    for (a=0;a<number;a++){
                        xl = start-(-(incremental*a));
                        if(!isNaN(xl)){
                            arr.push(xl);
                        }
                    }

                    var lawtable = document.getElementById("serial_table");
                    var rows = lawtable.rows;
                    var body = rows[1].innerHTML;
                    var html='';
                    $(arr).each(function (k,v) {
                        html += '<tr>'+body+'</tr>';
                    });
                    $('#serial_tbody').html(html);
                    for (a=0;a<arr.length;a++){
                        str = '';
                        cha = startlen-arr[a].toString().length;
                        for (var b=1;b<=cha-((a-(-1)).toString().length-1);b++){
                            str = str+'0';
                        }
                        console.log(str);
                        $("#serial_table tr:nth-child("+(a-(-1))+") td:nth-child(2)").find('input').val(''+prefix+str+arr[a]);
                    }
                    check_serial();
                });
                $(document).on("change", ".serial_ipt", function(){
                    check_serial()
                });
                //检测序列号是否重复
                function check_serial() {
                    var lawtable = document.getElementById("serial_table");
                    var rows = lawtable.rows;
                    for (a=0;a<rows.length;a++){
                        if(a>0){
                            var check = true;
                            for (var b=0;b<rows.length;b++){
                                xl1 = $("#serial_table tr:nth-child("+a+") td:nth-child(2)").find('input').val();
                                xl2 = $("#serial_table tr:nth-child("+b+") td:nth-child(2)").find('input').val();
                                if(a!=b && xl1==xl2 && xl1!='' && xl2!=''){
                                    check = false;
                                    $("#serial_table tr:nth-child("+a+") td:nth-child(3)").find('input').val('序列号与第'+b+'行相同');
                                }
                            }
                            if(check){
                                xl3 = $("#serial_table tr:nth-child("+a+") td:nth-child(3)").find('input').val();
                                if(xl3.indexOf("序列号与第") != -1){
                                    $("#serial_table tr:nth-child("+a+") td:nth-child(3)").find('input').val('');
                                }
                            }
                        }
                    }
                }
                $(document).on("click", ".serial_del", function(){
                    var lawtable = document.getElementById("serial_table");
                    var rows = lawtable.rows;//获取所有行
                    if(rows.length>2){
                        $(this).parents('tr').remove();
                    }else{
                        Toastr.error('至少保留一条序列号！');
                    }
                });
                $(document).on("click", ".serial_add", function(){
                    var lawtable = document.getElementById("serial_table");
                    var rows = lawtable.rows;//获取所有行
                    var html = rows[1].innerHTML;
                    html = '<tr>'+html+'</tr>';
                    var obj = $(this).parents('tr');
                    $(obj).after(html);
                });

                var period;
                var period_ser;
                var period_period;
                $(document).on("click", ".period", function(){
                    obj = document.getElementsByName('row[serial]');
                    check_val = '';
                    for(k in obj){
                        if(obj[k].checked)
                            check_val=obj[k].value;
                    }
                    if(check_val==1){
                        period = $(this);
                        var obj = $(this).parents('tr');
                        period_ser = obj.find('.period_period');
                        period_period = obj.find('.period_period').val();
                        var html = '<form id="ser_form" class="form-horizontal" role="form" data-toggle="validator" method="POST" action="">\n' +
                            '        <div class="layui-tab">\n' +
                            '            <ul class="layui-tab-title">\n' +
                            '                <li class="layui-this">基本录入序列号</li>\n' +
                            '                <li>批量录入序列号</li>\n' +
                            '            </ul>\n' +
                            '            <div class="layui-tab-content">\n' +
                            '                <div class="layui-tab-item layui-show">\n' +
                            '                </div>\n' +
                            '                <div class="layui-tab-item more_period row">\n' +
                            '                    <div class="col-xs-6 col-sm-3 col-md-3 col-lg-3">\n' +
                            '                        <div class="form-group">\n' +
                            '                            <label class="control-label col-xs-12 col-sm-3">前缀:</label>\n' +
                            '                            <div class="col-xs-12 col-sm-9">\n' +
                            '                                <input id="c-prefix" class="form-control" type="text" name="prefix" value="" placeholder="前缀，可以为空">\n' +
                            '                            </div>\n' +
                            '                        </div>\n' +
                            '                    </div>\n' +
                            '                    <div class="col-xs-6 col-sm-3 col-md-3 col-lg-3">\n' +
                            '                        <div class="form-group">\n' +
                            '                            <label class="control-label col-xs-12 col-sm-3">起始号:</label>\n' +
                            '                            <div class="col-xs-12 col-sm-9">\n' +
                            '                                <input id="c-start" class="form-control" name="start" type="text" value="001" placeholder="起始号">\n' +
                            '                            </div>\n' +
                            '                        </div>\n' +
                            '                    </div>\n' +
                            '                    <div class="col-xs-6 col-sm-3 col-md-3 col-lg-3">\n' +
                            '                        <div class="form-group">\n' +
                            '                            <label class="control-label col-xs-12 col-sm-3">递增量:</label>\n' +
                            '                            <div class="col-xs-12 col-sm-9">\n' +
                            '                                <input id="c-incremental" class="form-control" name="incremental" type="number" value="1" placeholder="递增量">\n' +
                            '                            </div>\n' +
                            '                        </div>\n' +
                            '                    </div>\n' +
                            '                    <div class="col-xs-6 col-sm-2 col-md-2 col-lg-2">\n' +
                            '                        <div class="form-group">\n' +
                            '                            <label class="control-label col-xs-12 col-sm-3">个数:</label>\n' +
                            '                            <div class="col-xs-12 col-sm-9">\n' +
                            '                                <input id="c-number" class="form-control" name="number" type="number" value="1" placeholder="个数">\n' +
                            '                            </div>\n' +
                            '                        </div>\n' +
                            '                    </div>\n' +
                            '                    <div class="col-xs-6 col-sm-1 col-md-1 col-lg-1">\n' +
                            '                        <div class="form-group" style="padding-top: 5px;">\n' +
                            '                            <a class="ui-btn ui-btn-sp" id="addAuto">添加</a>\n' +
                            '                        </div>\n' +
                            '                    </div>\n' +
                            '                </div>\n' +
                            '            </div>\n' +
                            '        </div>\n' +
                            '\n' +
                            '        <div class="list list_form">\n' +
                            '            <table id="serial_table">\n' +
                            '                <tr>\n' +
                            '                    <th></th><th><span class="must">*</span>序列号</th><th>备注</th>\n' +
                            '                </tr>\n' +
                            '                <tbody id="serial_tbody">\n';
                        for (var a=1;a<=8;a++){
                            html += '                <tr>\n' +
                                '                    <td><div class="btn"><span class="serial_add"><i class="fa fa-plus"></i></span><span class="serial_del"><i class="fa fa-trash"></i></span></div></td>\n' +
                                '                    <td><div><input type="text" class="ipt serial_ipt" name="stock[serial][]"></div></td>\n' +
                                '                    <td><div><input type="text" class="ipt" name="stock[remark][]"></div></td>\n' +
                                '                </tr>\n';
                        }
                        html += '                </tbody>\n' +
                            '            </table>\n' +
                            '        </div>\n' +
                            '\n' +
                            '        <div class="form-group">\n' +
                            '            <label class="control-label col-xs-12 col-sm-2"></label>\n' +
                            '            <div class="col-xs-12 col-sm-8">\n' +
                            '                <button type="button" id="sure_btn" class="btn btn-success btn-embossed">确定</button>\n' +
                            '                <button type="button" class="btn btn-default btn-embossed" onclick="layer.closeAll()">取消</button>\n' +
                            '            </div>\n' +
                            '        </div>\n' +
                            '    </form>';
                        layer.open({
                            type: 1,
                            title: '序列号录入',
                            shadeClose: true,
                            shade: false,
                            maxmin: true, //开启最大化最小化按钮
                            area: ['780px', '550px'],
                            content: html
                        });
                        period_period_init();
                    }
                });
                $(document).on("click", "#sure_btn", function(){
                    layer.closeAll();
                    $.ajax( {
                        url: "kuerp/goods/period",
                        type: "POST",
                        data:$('.layui-layer-content>#ser_form').serialize(),
                        success: function(res){
                            if(res.code==1){
                                period.val(res.msg.num);
                                period_ser.val(res.msg.ser);
                                layer.closeAll();
                            }else{
                                Toastr.error('序列号设置错误，请重试！');
                            }
                        }
                    });
                });
                function period_period_init() {
                    $.ajax( {
                        url: "kuerp/goods/period",
                        type: "POST",
                        data:{'period_period':period_period},
                        success: function(res){
                            if(res.code==1 && res.msg.ser!=false){
                                var arr = res.msg.ser;
                                for (a=0;a<arr.length;a++){
                                    $("#serial_table tr:nth-child("+(a-(-1))+") td:nth-child(2)").find('input').val(arr[a].serial);
                                    $("#serial_table tr:nth-child("+(a-(-1))+") td:nth-child(3)").find('input').val(arr[a].remark);
                                    if(a>7){
                                        html = '<tr>' +
                                            '<td><div class="btn"><span class="serial_add"><i class="fa fa-plus"></i></span><span class="serial_del"><i class="fa fa-trash"></i></span></div></td>' +
                                            '<td><div><input type="text" class="ipt serial_ipt" name="stock[serial][]" value="'+arr[a].serial+'"></div></td>' +
                                            '<td><div><input type="text" class="ipt" name="stock[remark][]" value="'+arr[a].remark+'"></div></td>' +
                                            '</tr>';
                                        $('#serial_table').append(html);
                                    }
                                }
                                check_serial();
                            }
                        }
                    });
                }

                //有效期计算
                function addDate(date,days){
                    var d=new Date(date);
                    d.setDate(d.getDate()+days);
                    var m=d.getMonth()+1;
                    if(m<10){
                        m = '0'+m;
                    }
                    var day = d.getDate();
                    if(day<10){
                        day = '0'+day;
                    }
                    return d.getFullYear()+'-'+m+'-'+day;
                }

                function manufacture(value=''){
                    setTimeout(function () {
                        var lawtable = document.getElementById("stock_table");
                        var rows = lawtable.rows;//获取所有行
                        var shelf_life = parseInt($('#shelf_life').val());
                        for (a=1;a<=rows.length;a++){
                            if(a>1){
                                if(value==''){
                                    var dates = $("#stock_table tr:nth-child("+a+") td:nth-child(5)").find('input').val();
                                }else{
                                    var dates = value;
                                }
                                if(dates!='' && !isNaN(shelf_life)){
                                    var validity=addDate(dates,shelf_life);
                                    $("#stock_table tr:nth-child("+a+") td:nth-child(6)").find('input').val(shelf_life);
                                    $("#stock_table tr:nth-child("+a+") td:nth-child(7)").find('input').val(validity);
                                }
                            }
                        }
                    },300);
                }
                $(document).on("change", "#shelf_life", function(){
                    manufacture()
                });

                //多单位开关
                $(document).on("click", "#more_unit", function(){
                    obj = document.getElementsByName("row[more_unit]");
                    check_val = '';
                    for(k in obj){
                        if(obj[k].checked)
                            check_val=obj[k].value;
                    }

                    var obj = document.getElementsByName('row[auxiliary]');
                    check_val2 = '';
                    for(k in obj){
                        if(obj[k].checked)
                            check_val2=obj[k].value;
                    }
                    if(check_val==1){
                        $('.price_strategy').hide();
                        $('.more_unit').show();
                        $(".aux_price_set").attr("readOnly","true");
                    }else{
                        $('.price_strategy').show();
                        $('.more_unit').hide();
                        $(".aux_price_set").attr("readOnly",false);
                    }
                });

                //多单位设置
                var unit_conversion = Config.unit_conversion;
                conversion = JSON.parse(unit_conversion);
                $(document).on("change", "#c-more_unit_id", function(){
                    var more_unit = $('#c-more_unit_id').val();
                    if(more_unit>0){
                        var html = '';
                        var tbd = '';
                        $('.more_unitselect').html('');
                        $(conversion).each(function (k,v) {
                            if(v.id==more_unit){
                                html+='<option value="'+v.id+'">'+v.unit_name+'</option>';
                                // 主单位
                                $('#more_unit_div1').html(v.unit_name);
                                $('#more_unit_id1').val(v.id);
                                // 副单位
                                $(v.unit_list).each(function (k2,v2) {
                                    html+='<option value="'+v2.id+'">'+v2.name+'</option>';
                                    tbd += '<tr>\n' +
                                        '<td style="white-space:nowrap">副单位'+(k2-(-1))+'</td>\n' +
                                        '<td><div id="more_unit_div2">'+v2.name+'</div><input type="hidden" name="more_unit[id][]" value="'+v2.id+'"></td>\n' +
                                        '<td><div><input type="number" class="price_decimal" name="more_unit[market_sell_price][]"></div></td>\n' +
                                        '<td><div><input type="number" class="price_decimal" name="more_unit[vip_price][]"></div></td>\n' +
                                        '<td><div><input type="number" name="more_unit[discount][]"></div></td>\n' +
                                        '<td><div><input type="number" name="more_unit[discount2][]"></div></td>\n' +
                                        '<td><div><input type="number" class="price_decimal" name="more_unit[purchase][]"></div></td>\n' +
                                        '</tr>';
                                });
                            }
                        });
                        $('#more_unit_tbd').html(tbd);
                        $('.more_unitselect').append(html);
                    }
                });

                // 期初设置开关
                $(document).on("click", "#setqc", function(){
                    obj = document.getElementsByName("setqc");
                    check_val = '';
                    for(k in obj){
                        if(obj[k].checked)
                            check_val=obj[k].value;
                    }
                    if(check_val==1){
                        $('.list_form').show();
                    }else{
                        $('.list_form').hide();
                    }
                });

                // 库存预警
                $(document).on("click", "#stock_warning", function(){
                    var boxes = document.getElementsByName("row[branch]");
                    for(i=0;i<boxes.length;i++){
                        boxes[i].checked = false;
                    }
                    obj = document.getElementsByName("row[stock_warning]");
                    check_val = '';
                    for(k in obj){
                        if(obj[k].checked)
                            check_val=obj[k].value;
                    }
                    $('.branchs').hide();
                    if(check_val==1){
                        $('.branch').show();
                        $('.branch_btn').show();
                    }else{
                        $('.branch').hide();
                        $('.branch_btn').hide();
                    }
                });

                // 分仓预警
                $(document).on("click", "#branch", function(){
                    obj = document.getElementsByName("row[branch]");
                    check_val = '';
                    for(k in obj){
                        if(obj[k].checked)
                            check_val=obj[k].value;
                    }
                    if(check_val==1){
                        $('.branch').hide();
                        $('.branchs').show();
                    }else{
                        $('.branch').show();
                        $('.branchs').hide();
                    }
                });

                // 期初设置添加行
                $(document).on("click", ".del", function(){
                    var lawtable = document.getElementById("stock_table");
                    var rows = lawtable.rows;//获取所有行
                    if(rows.length>2){
                        $(this).parents('tr').remove();
                    }else{
                        Toastr.error('至少保留一条分录！');
                    }
                });
                $(document).on("click", ".add", function(){
                    var lawtable = document.getElementById("stock_table");
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
                                done: function(value){
                                    manufacture();
                                }
                            })
                        });
                    })
                });

                // 自动计算
                $(document).on("change", ".ipt", function(){
                    var set = Config.set;
                    var number_decimal = parseInt(set.number_decimal.value);
                    if(isNaN(number_decimal)){
                        number_decimal = 1;
                    }

                    var price_decimal = parseInt(set.price_decimal.value);
                    if(isNaN(price_decimal)){
                        price_decimal = 1;
                    }

                    var period = parseFloat($(this).parents('tr').find('.period').val());
                    if(!isNaN(period)){
                        period = period.toFixed(number_decimal);
                    }

                    var unit_cost = parseFloat($(this).parents('tr').find('.unit_cost').val());
                    if(!isNaN(unit_cost)){
                        unit_cost = unit_cost.toFixed(price_decimal);
                    }

                    var ipts = parseFloat(period*unit_cost);
                    if(!isNaN(ipts)){
                        ipts = ipts.toFixed(price_decimal);
                        $(this).parents('tr').find('.ipts').val(ipts);
                    }
                });

                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    if(reloadStatus){
                        parent.location.reload();
                    }else{
                        $('.btn-refresh').click();
                    }
                });
            },
            rendertree: function (content) {
                $("#treeview").on('redraw.jstree', function (e) {
                    $(".layer-footer").attr("domrefresh", Math.random());
                }).jstree({
                    "themes": {"stripes": true},
                    "checkbox": {
                        "keep_selected_style": false,
                    },
                    "types": {
                        "root": {
                            "icon": "fa fa-folder-open",
                        },
                        "menu": {
                            "icon": "fa fa-folder-open",
                        },
                        "file": {
                            "icon": "fa fa-file-o",
                        }
                    },
                    "plugins": ["checkbox", "types"],
                    "core": {
                        'check_callback': true,
                        "worker": false,
                        "data": content
                    }
                });
                $("#treeview").on("changed.jstree", function (e, data) {
                    var i, j, r = [];
                    for(i = 0, j = data.selected.length; i < j; i++) {
                        r.push(data.instance.get_node(data.selected[i]).id);
                    }
                    goodssearch(r);
                }).jstree({
                    "plugins" : [ "changed" ]
                });
            }
        }
    };
    return Controller;
});