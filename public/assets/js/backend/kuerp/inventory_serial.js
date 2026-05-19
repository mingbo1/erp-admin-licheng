define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/inventory_serial/index'
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
                        {field: 'purchase_code', title: __('Purchase_code'), operate: 'LIKE'},
                        {field: 'purchase_time', title: __('Purchase_time'),templet: function (d) {
                                return layui.util.toDateString(d.purchase_time*1000);
                            }},
                        {field: 'store_type', title: __('Store_type'),templet: function (d) {
                                if(d.store_type==1){
                                    return __('Store_type 1');
                                }
                                if(d.store_type==2){
                                    return __('Store_type 2');
                                }
                            }},
                        {field: 'result', title: __('Result'), operate: 'LIKE',templet:function (d) {
                                return d.result;
                            }},
                        {fixed: 'right', title:__('Operate'), toolbar: '#thisBarDemo', width:150}
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/inventory_serial/import?file="+res.data.url, "",'',true);
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
                        url: "kuerp/inventory_serial/multi",
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
                            Fast.api.open("kuerp/inventory_serial/add", "添加", {
                               callback:function(value){
                                  Controller.api.bindevent();
                               },
                                area:['90%','75%'],
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
                                    Fast.api.ajax("kuerp/inventory_serial/del?ids="+ids, function () {
                                        $('.btn-refresh').click();
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
                                    url: "kuerp/inventory_serial/del",
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
                        Fast.api.open("kuerp/inventory_serial/edit?ids="+data.id, "编辑",{ area:['90%','75%'],});
                    }else if(obj.event === 'exchange'){
                        $.ajax( {
                            url: "kuerp/Warehousing/checkInventory",
                            type: "POST",
                            data:{
                                'purchase_code':data.purchase_code,
                                'type':2
                            },
                            success: function(res){
                                if(res.code==1){
                                    var data = res.msg;
                                    var html = '';
                                    $(data).each(function (k,v) {
                                        html+='<div class="rk_btn" data-purchase_code="'+v.purchase_code+'" data-status="'+v.status+'" data-type="'+v.type+'">'+v.title+'</div>';
                                    });
                                    layer.open({
                                        title:'盘点数据',
                                        type: 1,
                                        skin: 'layui-layer-rim', //加上边框
                                        area: ['420px', '240px'], //宽高
                                        content: html
                                    });
                                }else{
                                    Toastr.error(res.msg);
                                }
                            }
                        });
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
                layui.use(['table','form'], function(){
                    var supplyer_div = xmSelect.render({
                        el: '#stores_ids',
                        filterable: true,
                        tips: '请选择仓库',
                        // remoteSearch: true,
                        pageRemote: true,
                        remoteMethod: function (val, cb, show, pageIndex) {
                            $.ajax({
                                url: "kuerp/stores/index",
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
                                        row['name'] = v.name;
                                        row['value'] = v.id;
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
                                $('#stores_id').val('');
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
                                    $('#stores_id').val(arr[0]['value']);
                                }
                            }
                            return Array.from(new Set(arr));
                        },
                    });

                    var kuerp_stores = Config.kuerp_stores;
                    if(kuerp_stores!=undefined){
                        var newArr = [];
                        newArr.push({name: kuerp_stores.name, value: kuerp_stores.id,});
                        supplyer_div.setValue(newArr);
                    }

                    var set = Config.set;
                    var examine = parseInt(set.examine.is_checked);
                    if(isNaN(examine)){
                        examine = 0;
                    }
                    if(examine==1 && Config.examine_status==1){
                        supplyer_div.update({disabled:true})
                    }
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

                //盘点
                $(document).on("change", ".invertory_stock", function(){
                    var set = Config.set;
                    var number_decimal = parseInt(set.number_decimal.value);
                    if(isNaN(number_decimal)){
                        number_decimal = 1;
                    }

                    var obj = $(this).parents('tr');
                    var stock = obj.find('.stock').val();
                    var invertory = obj.find('.invertory_stock').val();
                    var profit = (invertory-stock).toFixed(number_decimal);
                    obj.find('.profit').val(profit);
                });

                var period;
                var period_ser;
                var period_period;
                var list;
                $(document).on("click", ".number", function(){
                    var obj = $(this).parents('tr');
                    var goods_code = obj.find('.goods_code').val();
                    var store = obj.find('.stores_id').val();
                    var attr = obj.find('.goods_attr').val();
                    period = obj;

                    $.ajax( {
                        url: "kuerp/inventory_serial/getSerial",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'stores_id':store,'attribute_id':attr
                        },
                        success: function(res){
                            res = JSON.parse(res);
                            list = res.list;

                            period_ser = obj.find('.period_period');
                            period_period = obj.find('.period_period').val();
                            var html = '<form id="ser_forms" style="width: 500px;margin-left: 10px;" class="form-horizontal" role="form" data-toggle="validator" method="POST" action="">\n' +
                                '\n' +
                                '        <div class="list list_form">\n' +
                                '            <table id="serial_table">\n' +
                                '                <tr>\n' +
                                '                    <th></th><th><span class="must">*</span>序列号</th>\n' +
                                '                </tr>\n' +
                                '                <tbody id="serial_tbody">\n';
                            for (var a=0;a<list.length;a++){
                                html += '                <tr>\n' +
                                    '                    <td><div class="btn"><span class="serial_add"><i class="fa fa-plus"></i></span><span class="serial_del"><i class="fa fa-trash"></i></span></div></td>\n' +
                                    '                    <td>' +
                                    '<input type="text" name="stock[serial][]" value="'+list[a].serial+'">' +
                                    '</td>\n' +
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
                                title: '序列号盘点',
                                shadeClose: true,
                                shade: false,
                                maxmin: true, //开启最大化最小化按钮
                                area: ['525px', '550px'],
                                content: html
                            });
                            period_period_init();
                            check_serial_option()
                        }
                    });
                });

                function check_serial_option() {
                    for (var b=0;b<list.length;b++){
                        var serial = list[b].serial;
                        $('.stock_serial_'+serial).show();
                    }
                    $('select[name="stock[serial][]"]').each(function(){
                        var serial = $(this).val();
                        $('.stock_serial_'+serial).hide();
                    });
                }
                function period_period_init() {
                    $.ajax( {
                        url: "kuerp/goods/period",
                        type: "POST",
                        data:{'period_period':period_period},
                        success: function(res){
                            if(res.code==1 && res.msg.ser!=false){
                                var arr = res.msg.ser;

                                var lawtable = document.getElementById("serial_table");
                                var rows = lawtable.rows;//获取所有行
                                var html = '';
                                for (a=0;a<arr.length;a++){
                                    if(a<(rows.length-1)){
                                        $("#serial_table tr:nth-child("+(a-(-1))+") td:nth-child(2)").find('input').val(arr[a].serial);
                                        $("#serial_table tr:nth-child("+(a-(-1))+") td:nth-child(3)").find('input').val(arr[a].remark);
                                    }else{
                                        html = '                <tr>\n' +
                                            '                    <td><div class="btn"><span class="serial_add"><i class="fa fa-plus"></i></span><span class="serial_del"><i class="fa fa-trash"></i></span></div></td>\n' +
                                            '                    <td>' +
                                            '<input type="text" name="stock[serial][]" value="'+arr[a].serial+'">' +
                                            '</td>\n' +
                                            '                </tr>\n';
                                        $('#serial_table').append(html);
                                    }
                                }
                            }
                        }
                    });
                }
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
                        for (var b=1;b<=cha-((a-(-1)).toString().length-1);b++){
                            str = str+'0';
                        }
                        $("#serial_table tr:nth-child("+(a-(-1))+") td:nth-child(2)").find('input').val(''+prefix+str+arr[a]);
                    }
                });
                $(document).on("click", "#sure_btn", function(){
                    layer.closeAll();
                    var els =document.getElementsByName("stock[serial][]");
                    var serial_pd = [];
                    for (var i = 0, j = els.length; i < j; i++){
                        if(els[i].value!==''){
                            serial_pd.push(els[i].value);
                        }
                    }
                    var serial_pds = JSON.stringify(serial_pd);

                    var obj = period;
                    var goods_code = obj.find('.goods_code').val();
                    var store = obj.find('.stores_id').val();
                    var attr = obj.find('.goods_attr').val();
                    $.ajax( {
                        url: "kuerp/inventory_serial/period",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'stores_id':store,'attribute_id':attr,'serial_pds':serial_pds
                        },
                        success: function(res){
                            if(res.code==1){
                                period.find('.number').val(res.msg.num);
                                var html = '';
                                html+='匹配序列号:'+res.msg.matching+'个<br>';
                                html+='盘盈序列号:'+res.msg.inventory+'个<br>';
                                html+='盘亏序列号:'+res.msg.loss+'个<br>';
                                period.find('.profit').html(html);
                                period.find('.matching').val(res.msg.matching);
                                period.find('.inventory').val(res.msg.inventory);
                                period.find('.loss').val(res.msg.loss);
                                period_ser.val(res.msg.ser);
                            }else{
                                Toastr.error('序列号设置错误，请重试！');
                            }
                        }
                    });
                });
                //删除序列号行
                $(document).on("click", ".serial_del", function(){
                    var lawtable = document.getElementById("serial_table");
                    var rows = lawtable.rows;//获取所有行
                    if(rows.length>2){
                        $(this).parents('tr').remove();
                    }else{
                        Toastr.error('至少保留一条序列号！');
                    }
                    check_serial_option()
                });
                //添加序列号行
                $(document).on("click", ".serial_add", function(){
                    html = '                <tr>\n' +
                        '                    <td><div class="btn"><span class="serial_add"><i class="fa fa-plus"></i></span><span class="serial_del"><i class="fa fa-trash"></i></span></div></td>\n' +
                        '                    <td>' +
                        '<input type="text" name="stock[serial][]" value="">' +
                        '</td>\n' +
                        '                </tr>\n';
                    var obj = $(this).parents('tr');
                    $(obj).after(html);
                    check_serial_option();
                });
                //序列号更改选择
                $(document).on("change", ".stock_serial", function(){
                    check_serial_option()
                });

                $(document).on("click", ".exchange", function(){
                    $.ajax( {
                        url: "kuerp/Warehousing/checkInventory",
                        type: "POST",
                        data:{
                            'purchase_code':$('#purchase_code').val(),
                            'type':2
                        },
                        success: function(res){
                            if(res.code==1){
                                var data = res.msg;
                                var html = '';
                                $(data).each(function (k,v) {
                                    html+='<div class="rk_btn" data-purchase_code="'+v.purchase_code+'" data-status="'+v.status+'" data-type="'+v.type+'">'+v.title+'</div>';
                                });
                                layer.open({
                                    title:'盘点数据',
                                    type: 1,
                                    skin: 'layui-layer-rim', //加上边框
                                    area: ['420px', '240px'], //宽高
                                    content: html
                                });
                            }else{
                                Toastr.error(res.msg);
                            }
                        }
                    });
                });
                // 入库单
                $(document).on("click", ".rk_btn", function(){
                    var purchase_code=$(this).data('purchase_code');
                    var status=$(this).data('status');
                    var type=$(this).data('type');
                    if(status==1){
                        //盘盈
                        parent.Fast.api.open("kuerp/Warehousing/add?purchase_code="+purchase_code+'&type='+type, "盘盈单",{area:['70%','75%'],});
                    }else if(status==2){
                        //盘亏
                        parent.Fast.api.open("kuerp/Warehouse/add?purchase_code="+purchase_code+'&type='+type, "盘亏单",{area:['70%','75%'],});
                    }
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