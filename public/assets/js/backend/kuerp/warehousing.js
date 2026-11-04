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

                table.render({
                    elem: '#table'
                    ,url:'kuerp/warehousing/index'
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
                        {field: 'purchase_code', title: __('Purchase_code'),width:150},
                        {field: 'business_type', title: __('Business_type'),templet: function (d) {
                            if(d.business_type==1){
                                return __('Business_type 1');
                            }
                            if(d.business_type==2){
                                return __('Business_type 2');
                            }
                        }},
                        {field: 'number', title: __('Number'), operate:'BETWEEN'},
                        {field: 'price', title: __('Price'), operate:'BETWEEN'},
                        {field: 'suppliers_id', title: __('Suppliers_id'),templet: function (d) {
                                if(d.suppliers.supplyer_name!==null){
                                    return d.suppliers.supplyer_name;
                                }else{
                                    return ''
                                }
                            }},
                        {field: 'preparation_id', title: __('Preparation_id'),templet: function (d) {
                                if(d.admin.nickname!==null){
                                    return d.admin.nickname;
                                }else{
                                    return ''
                                }
                            }},
                        {field: 'examine_id', title: __('Examine_id'),templet: function (d) {
                                if(d.examine.nickname!==null){
                                    return d.examine.nickname;
                                }else{
                                    return ''
                                }
                            },hide:hideStatus},
                        {field: 'remarks', title: __('Remarks')},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemo', width:150}
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/warehousing/import?file="+res.data.url, "",'',true);
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
                        url: "kuerp/warehousing/multi",
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
                            Fast.api.open("kuerp/warehousing/add", "添加", {
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
                                    Fast.api.ajax("kuerp/warehousing/del?ids="+ids, function () {
                                        $('.btn-refresh').click();
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                        break;
                        case 'examine':
                            Fast.api.ajax("kuerp/warehousing/examine?examine=1&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'examine_f':
                            Fast.api.ajax("kuerp/warehousing/examine?examine=2&ids="+ids, function () {
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
                                $.ajax( {
                                    url: "kuerp/warehousing/del",
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
                        Fast.api.open("kuerp/warehousing/edit?ids="+data.id, "编辑",{ area:['90%','75%'],});
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
                    var business_type = $('#business_type').val();
                    if(business_type!=''){
                        search.business_type = business_type;
                    }
                    var suppliers_id = $('#suppliers_id').val();
                    if(suppliers_id!=''){
                        search.suppliers_id = suppliers_id;
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

                layui.use(['table','form'], function(){
                    var supplyer_div = xmSelect.render({
                        el: '#supplyer_codes',
                        filterable: true,
                        paging: true,
                        pageSize: 5,
                        // remoteSearch: true,
                        pageRemote: true,
                        remoteMethod: function (val, cb, show, pageIndex) {
                            $.ajax({
                                url: "kuerp/suppliers/index",
                                type: 'GET',
                                crossOrigin: true,
                                dataType: 'json',
                                cache: false,
                                async: true,
                                data: {
                                    keywords: val,
                                    filter:JSON.stringify({"supplyer_name":val}),
                                    op:JSON.stringify({"supplyer_name":'like'}),
                                    page: pageIndex,
                                    limit: 5
                                },
                                success: function (data) {
                                    var TotalCount = data.total;
                                    var Pages = Math.ceil(TotalCount/5);
                                    var list = [];
                                    $(data.rows).each(function (k,v) {
                                        var row = [];
                                        row['name'] = v.supplyer_name;
                                        row['value'] = v.supplyer_code;
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
                                $('#supplyer_code').val('');
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
                                    $('#supplyer_code').val(arr[0]['value']);
                                }
                            }
                            return Array.from(new Set(arr));
                        },
                    });

                    var kuerp_suppliers = Config.kuerp_suppliers;
                    if(kuerp_suppliers!=undefined){
                        var newArr = [];
                        newArr.push({name: kuerp_suppliers.supplyer_name, value: kuerp_suppliers.supplyer_code});
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
                    });
                    laydate.render({
                         elem: '#purchase_time',
                         type: 'datetime',
                         range: true
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


                var period;
                var period_ser;
                var period_period;
                // 数量
                $(document).on("click", ".number", function(){
                    var obj = $(this).parents('tr');
                    var goods_code = obj.find('.goods_code').val();
                    var store = obj.find('.stores_id').val();
                    var attr = obj.find('.goods_attr').val();
                    var supplyer_code = $('#supplyer_code').val();
                    period = obj;
                    if(goods_code!=''){
                        $.ajax( {
                            url: "kuerp/goods_purchase/getUnit",
                            type: "POST",
                            data:{
                                'goods_code':goods_code,'store':store,'attr':attr,'supplyer_code':supplyer_code
                            },
                            success: function(res){
                                res = JSON.parse(res);
                                if(res.goods.serial==1){
                                    obj.find(".number_ipt").attr("readOnly","true");
                                    showperiod(obj)
                                }else{
                                    obj.find(".number_ipt").attr("readOnly",false);
                                }
                            }
                        });
                    }
                });
                function showperiod(obj){
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
                                }
                                check_serial();
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

                    var lengths = 0;
                    for (var b=0;b<rows.length;b++){
                        xl2 = $("#serial_table tr:nth-child("+b+") td:nth-child(2)").find('input').val();
                        if(xl2=='' || xl2==undefined){}else{
                            lengths = lengths-(-1);
                        }
                    }

                    var body = rows[1].innerHTML;
                    var html='';
                    $(arr).each(function (k,v) {
                        html += '<tr>'+body+'</tr>';
                    });
                    $('#serial_tbody').append(html);
                    for (a=0;a<arr.length;a++){
                        str = '';
                        cha = startlen-arr[a].toString().length;
                        for (var b=1;b<=cha-((a-(-1)).toString().length-1);b++){
                            str = str+'0';
                        }
                        $("#serial_table tr:nth-child("+(a-(-lengths)-(-1))+") td:nth-child(2)").find('input').val(''+prefix+str+arr[a]);
                    }
                    check_serial();
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
                $(document).on("click", "#sure_btn", function(){
                    layer.closeAll();
                    $.ajax( {
                        url: "kuerp/goods/period",
                        type: "POST",
                        data:$('.layui-layer-content>#ser_form').serialize(),
                        success: function(res){
                            if(res.code==1){
                                period.find('.number').val(res.msg.num);
                                period_ser.val(res.msg.ser);
                                changeGoodsInfo(period)
                            }else{
                                Toastr.error('序列号设置错误，请重试！');
                            }
                        }
                    });
                });
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


                var goods;
                //出厂日期
                var batch_sec=0;
                var manufacture_obj;

                var batch;
                var batch_obj;
                //选择批次号
                $(document).on("click", ".batch_span", function(){
                    var obj = $(this).parents('tr');
                    batch_obj = obj;
                    var goods_code = obj.find('.goods_code').val();
                    var attr = obj.find('.goods_attr').val();
                    var store = obj.find('.stores_id').val();
                    if(batch_sec==0){
                        $.ajax({
                            url: "kuerp/goods_purchase/getBranch",
                            type: "POST",
                            data:{
                                'goods_code':goods_code,'attr':attr,'store':store
                            },
                            success: function(res){
                                res = JSON.parse(res);
                                batch = res.batch;
                                var html = '<form id="ser_form" class="form-horizontal" role="form" data-toggle="validator" method="POST" action="">\n' +
                                    '        <div class="list list_form">\n' +
                                    '            <table id="serial_table">\n' +
                                    '                <tr>\n' +
                                    '                    <th><span class="must">*</span>批次</th><th>生产日期</th><th>保质期</th><th>过期时间</th>\n' +
                                    '                </tr>\n' +
                                    '                <tbody id="serial_tbody">\n';
                                for (var a=0;a<res.batch.length;a++){
                                    html += '                <tr>\n' +
                                        '                    <td><div><input class="batch_radio" type="radio" name="batch_id" value="'+res.batch[a].id+'">'+res.batch[a].batch_name+'</td>\n' +
                                        '                    <td><div>'+res.batch[a].manufacture+'</td>\n' +
                                        '                    <td><div>'+res.batch[a].guarantee+'</td>\n' +
                                        '                    <td><div>'+res.batch[a].validity+'</td>\n' +
                                        '                </tr>\n';
                                }
                                html += '                </tbody>\n' +
                                    '            </table>\n' +
                                    '        </div>\n' +
                                    '\n' +
                                    '        <div class="form-group">\n' +
                                    '            <label class="control-label col-xs-12 col-sm-2"></label>\n' +
                                    '            <div class="col-xs-12 col-sm-8">\n' +
                                    '                <button type="button" id="batch_sure_btn" class="btn btn-success btn-embossed">确定</button>\n' +
                                    '                <button type="button" class="btn btn-default btn-embossed" onclick="layer.closeAll()">取消</button>\n' +
                                    '            </div>\n' +
                                    '        </div>\n' +
                                    '    </form>';
                                layer.open({
                                    type: 1,
                                    title: '选择批次',
                                    shadeClose: true,
                                    shade: false,
                                    maxmin: true, //开启最大化最小化按钮
                                    area: ['780px', '550px'],
                                    content: html
                                });
                            }
                        })
                    }else{
                        batch_sec=0;
                    }
                });
                $(document).on("click", "#batch_sure_btn", function(){
                    var batch_id = $("input[name='batch_id']:checked").val();
                    $(batch).each(function (k,v) {
                        if(v.id==batch_id){
                            batch_obj.find('.batch_id').val(v.id);
                            batch_obj.find('.batch_sec').val(v.batch_name);
                            batch_obj.find('.manufacture').val(v.manufacture);
                            batch_obj.find('.guarantee').val(v.guarantee);
                            batch_obj.find('.validity').val(v.validity);
                            layer.closeAll();
                        }
                    })
                });

                //批次
                $(document).on("change", ".batch_sec", function(){
                    obj = $(this).parents('tr');
                    obj.find('.batch_id').val('');
                });
                $(document).on("click", ".manufacture", function(){
                    manufacture_obj = $(this).parents('tr');
                });
                $(document).on("click", ".validity", function(){
                    manufacture_obj = $(this).parents('tr');
                });

                //初始获取商品信息
                function formatInfo(obj,number,stock,name,id,basic_nmber,purchase) {
                    $.ajax( {
                        url: "kuerp/Warehousing/formatInfo",
                        type: "POST",
                        data:{
                            'number':number,
                            'allow_stock':stock,
                            'default_unit':name,
                            'default_unit_id':id,
                            'advance_rate':basic_nmber,
                            'basic_nmber':basic_nmber,
                            'unit_price':purchase,
                        },
                        success: function(data){
                            res = JSON.parse(data);
                            data = res.data;
                            $(data).each(function (k,v) {
                                if (v.value!==''){
                                    obj.find('.'+v.key).val(v.value);
                                }
                            });
                            var afterChange = obj.data('warehouseAfterChange');
                            if(typeof afterChange === 'function'){
                                obj.removeData('warehouseAfterChange');
                                afterChange(obj);
                            }
                        }
                    });
                }

                //变更商品价格信息
                function changeGoodsInfo(obj){
                    var set = Config.set;
                    var price_decimal = parseInt(set.price_decimal.value);
                    if(isNaN(price_decimal)){
                        price_decimal = 1;
                    }
                    var number = obj.find('.number').val();
                    if(isNaN(number)){
                        number = 0;
                    }
                    var unit_price = obj.find('.unit_price').val();
                    if(isNaN(unit_price)){
                        unit_price = 0;
                    }
                    var all_money = (number*unit_price).toFixed(price_decimal);
                    obj.find('.all_money').val(all_money);
                }

                function changeGoods(goods_code,store,attr,obj,goods_unit,number,callback) {
                    var set = Config.set;
                    var number_decimal = parseInt(set.number_decimal.value);
                    if(isNaN(number_decimal)){
                        number_decimal = 1;
                    }
                    var supplyer_code = $('#supplyer_code').val();

                    $.ajax( {
                        url: "kuerp/goods_purchase/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':store,'attr':attr,'supplyer_code':supplyer_code
                        },
                        success: function(res){
                            res = JSON.parse(res);
                            goods=res;
                            obj.find('.goods_specification_id').val(goods.goods.goods_specification_id || '');
                            obj.find('.vendor_name').val(goods.goods.vendor_name || '');
                            obj.find('.packaging').val(goods.goods.packaging || '');
                            if(goods.goods.batch==1){
                                obj.find('input[name="row[batch][]"]').attr('placeholder','SN');
                            }
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
                            if(goods.goods.warehousing!='' && goods_unit=='' && goods.goods.more_unit==1){
                                goods_unit = goods.goods.warehousing;
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
                                    formatInfo(obj,number,v.stock,v.name,v.id,v.basic_nmber,v.tax_included);
                                }else if(goods_unit!='' && v.id==goods_unit){
                                    formatInfo(obj,number,v.stock,v.name,v.id,v.basic_nmber,v.tax_included);
                                }
                            });
                            obj.find('.goods_unit').html(unit_html);

                            if(goods_unit!=''){
                                obj.find('.goods_unit').val(goods_unit);
                            }
                            if(typeof callback === 'function'){ obj.data('warehouseAfterChange', callback); }
                        }
                    });
                }

                // ===== Excel：下载模板 / 导入明细（模板由后端生成，导入只解析不写库） =====
                $(document).on("click", ".btn_export_template", function(){
                    window.location.href = $(this).data('url');
                });
                $(document).on("click", ".btn_import_excel", function(){
                    $('#import_excel_file')[0].click();
                });
                $(document).on("change", "#import_excel_file", function(){
                    var file = this.files && this.files[0];
                    if(!file){ return; }
                    var btn = $('.btn_import_excel');
                    var fd = new FormData();
                    fd.append('file', file);
                    btn.attr('disabled', true);
                    $.ajax({
                        url: "kuerp/warehousing/parseimport",
                        type: "POST",
                        data: fd,
                        processData: false,
                        contentType: false,
                        success: function(res){
                            btn.removeAttr('disabled');
                            $('#import_excel_file').val('');
                            if(!res || res.code != 1){
                                Toastr.error((res && res.msg) ? res.msg : '导入解析失败');
                                return;
                            }
                            var rows = (res.data && res.data.rows) ? res.data.rows : [];
                            if(!rows.length){ Toastr.error('未读取到有效的明细行'); return; }
                            var doApply = function(){
                                applyImportedRows(rows);
                                if(res.data.warnings && res.data.warnings.length){
                                    Toastr.warning(res.data.warnings.join('；'), '导入提示');
                                }else{
                                    Toastr.success('已导入 ' + rows.length + ' 行明细');
                                }
                            };
                            var hasData = false;
                            var tableRows = document.getElementById('purchase_table').rows;
                            for(var i = 2; i < tableRows.length; i++){
                                if($(tableRows[i]).find('.goods_code').val()){ hasData = true; break; }
                            }
                            if(hasData){
                                Layer.confirm('导入会替换当前全部明细行（包括手工录入的商品），是否继续？', {icon: 3, title: '导入确认'}, function(index){
                                    Layer.close(index);
                                    doApply();
                                });
                            }else{
                                doApply();
                            }
                        },
                        error: function(){
                            btn.removeAttr('disabled');
                            $('#import_excel_file').val('');
                            Toastr.error('导入失败，请检查文件或网络后重试');
                        }
                    });
                });

                // 把导入解析出的行填进明细表格（替换除表头、模板行外的全部行）
                function applyImportedRows(rows){
                    var lawtable = document.getElementById('purchase_table');
                    var tableRows = lawtable.rows;
                    for(var i = tableRows.length - 1; i >= 2; i--){ $(tableRows[i]).remove(); }
                    var templateHtml = tableRows[1].innerHTML;
                    $(rows).each(function(k, v){
                        var tr = $('<tr>' + templateHtml + '</tr>');
                        $('#purchase_table').append(tr);
                        tr.find('.goods_code').val(v.goods_code);
                        tr.find('.goods_name').val(v.goods_code + ' ' + v.goods_name);
                        tr.find('.remark').val(v.remark || '');
                        changeGoods(v.goods_code, v.stores_id ? v.stores_id : '', '', tr, v.goods_unit ? v.goods_unit : '', v.number, function(row){
                            if(v.unit_price === undefined || v.unit_price === '' || v.unit_price === null){ return; }
                            var price = parseFloat(v.unit_price);
                            if(isNaN(price)){ return; }
                            var price_decimal = parseInt(Config.set.price_decimal.value);
                            if(isNaN(price_decimal)){ price_decimal = 2; }
                            row.find('.unit_price').val(price.toFixed(price_decimal));
                            var number = parseFloat(row.find('.number').val()) || 0;
                            row.find('.all_money').val((price * number).toFixed(price_decimal));
                        });
                    });
                    layui.use('laydate', function(){
                        var laydate = layui.laydate;
                        lay('.manufacture').each(function () {
                            laydate.render({ elem:this, trigger:"click" });
                        });
                        lay('.validity').each(function () {
                            laydate.render({ elem:this, trigger:"click" });
                        });
                    });
                }

                // ===== 调用BOM：选BOM模板 → 子商品即明细商品；父商品数量自动计算子商品数量 =====
                $(document).on("click", ".mb_bom_call", function(){
                    parent.Fast.api.open("kuerp/goods_bom/chosebom", "选择BOM模板", { area: ['90%', '75%'], callback: function(goodsData){
                        if(goodsData == undefined || goodsData.length < 1){ return; }
                        $.ajax({
                            url: "kuerp/goods_bom/getbomdetail",
                            type: "POST",
                            data: { 'id': goodsData[0].id },
                            success: function(res){
                                if(res.code != 1){ Toastr.error(res.msg); return; }
                                var parentInfo = res.data.parent || {};
                                var children = res.data.children || [];
                                if(children.length < 1){ Toastr.error('该BOM模板没有子商品'); return; }

                                var set = Config.set;
                                var number_decimal = parseInt(set.number_decimal.value);
                                if(isNaN(number_decimal)){ number_decimal = 1; }

                                var parentNumber = parseFloat($('.bom_parent_number').val());
                                if(isNaN(parentNumber) || parentNumber <= 0){
                                    parentNumber = 1;
                                }

                                // 先清理空行与上一次BOM生成的行（已录入的其它商品行保留）
                                var lawtable = document.getElementById("purchase_table");
                                var rows = lawtable.rows;
                                for(var i = rows.length - 1; i >= 2; i--){
                                    var oldRow = $(rows[i]);
                                    if(oldRow.attr('data-bom-ratio') !== undefined || !oldRow.find('.goods_code').val()){
                                        oldRow.remove();
                                    }
                                }

                                // 以隐藏模板行克隆生成子商品行
                                var templateHtml = rows[1].innerHTML;
                                var parentStore = parentInfo.manager_warehouse_id > 0 ? parentInfo.manager_warehouse_id : '';
                                $(children).each(function(k, v){
                                    var newRow = $('<tr>' + templateHtml + '</tr>');
                                    $('#purchase_table').append(newRow);
                                    var ratio = parseFloat(v.number) || 0;
                                    newRow.find('.goods_code').val(v.goods_code);
                                    newRow.find('.goods_name').val(v.goods_code + ' ' + v.goods_name);
                                    newRow.attr('data-bom-ratio', ratio);
                                    var childStore = v.manager_warehouse_id > 0 ? v.manager_warehouse_id : parentStore;
                                    changeGoods(v.goods_code, childStore, '', newRow, v.unit_id > 0 ? v.unit_id : '', ratio * parentNumber);
                                });

                                $('.bom_title').text(res.data.bom ? res.data.bom.bom_name : '');
                                $('.bom_parent').text((parentInfo.goods_code || '') + ' ' + (parentInfo.goods_name || ''));
                                $('.bom_parent_number').val(parentNumber);
                                $('.bom_bar').show();

                                layui.use('laydate', function(){
                                    var laydate = layui.laydate;
                                    lay('.manufacture').each(function () {
                                        laydate.render({ elem:this, trigger:"click" });
                                    });
                                    lay('.validity').each(function () {
                                        laydate.render({ elem:this, trigger:"click" });
                                    });
                                });
                            }
                        });
                    }});
                });

                // 父商品数量变化 → 子商品数量按BOM比例自动重算
                $(document).on("input change", ".bom_parent_number", function(){
                    var set = Config.set;
                    var number_decimal = parseInt(set.number_decimal.value);
                    if(isNaN(number_decimal)){ number_decimal = 1; }
                    var parentNumber = parseFloat($(this).val());
                    if(isNaN(parentNumber) || parentNumber < 0){ parentNumber = 0; }
                    $('#purchase_table tr[data-bom-ratio]').each(function(){
                        var ratio = parseFloat($(this).attr('data-bom-ratio'));
                        if(isNaN(ratio)){ return; }
                        $(this).find('.number').val((ratio * parentNumber).toFixed(number_decimal));
                        changeGoodsInfo($(this));
                    });
                });

                // 清除BOM生成的行
                $(document).on("click", ".bom_clear", function(){
                    $('#purchase_table tr[data-bom-ratio]').remove();
                    $('.bom_bar').hide();
                    $('.bom_title, .bom_parent').text('');
                    $('.bom_parent_number').val(1);
                });

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
                                                lay('.validity').each(function () {
                                                    laydate.render({
                                                        elem:this,
                                                        trigger:"click",
                                                        done: function(value){
                                                            manufacture();
                                                        }
                                                    })
                                                })
                                            });
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
                                    lay('.validity').each(function () {
                                        laydate.render({
                                            elem:this,
                                            trigger:"click",
                                            done: function(value){
                                                manufacture();
                                            }
                                        })
                                    })
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
                                            thisobj.find('.goods_specification_id').val(vp.goods.goods_specification_id || '');
                                            thisobj.find('.vendor_name').val(vp.goods.vendor_name || '');
                                            thisobj.find('.packaging').val(vp.goods.packaging || '');
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
                                        '                                    <th>商品编号</th><th>商品名称</th><th>型号</th><th>规格2/厂商</th><th>规格3/封装</th><th>计量单位</th><th>商品类别</th><th>库存数量</th><th>备注</th>\n' +
                                        '                                </tr>\n';
                                    max = 5;
                                    if(res.total>5){
                                        max = 5;
                                    }else{
                                        max = res.total;
                                    }
                                    for (var a=0;a<max;a++){
                                        v = res.rows[a];
                                        html += '<tr class="chose_goods_tr" data-code="'+v.goods_code+'" data-name="'+v.goods_name+'" data-note="'+v.note+'" data-store="'+v.manager_warehouse_id+'"><td>'+v.goods_code+'</td><td>'+v.goods_name+'</td><td>'+v.goods_specification_id+'</td><td>'+(v.vendor_name||'')+'</td><td>'+(v.packaging||'')+'</td><td>'+v.unit.name+'</td><td>'+v.goods_type.name+'</td><td>'+v.stock+'</td><td>'+v.note+'</td></tr>';
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

                var goodsList = Config.goodsList;
                var unit;
                //选择商品
                $(document).on("change", ".goods_code", function(){
                    var obj = $(this).parents('tr');
                    obj.find('.batch_id').val('');
                    var goods_code = $(this).val();
                    $(goodsList).each(function (k,v) {
                        if(goods_code==v.goods_code){
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
                    var supplyer_code = $('#supplyer_code').val();
                    $.ajax( {
                        url: "kuerp/goods_purchase/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':stores_id,'attr':attr,'supplyer_code':supplyer_code
                        },
                        success: function(res){
                            res = JSON.parse(res);
                            goods=res;
                            obj.find('.goods_specification_id').val(goods.goods.goods_specification_id || '');
                            obj.find('.vendor_name').val(goods.goods.vendor_name || '');
                            obj.find('.packaging').val(goods.goods.packaging || '');
                            $(goods.unit).each(function (k,v) {
                                if(v.id==goods_unit){
                                    obj.find('.allow_stock').val(v.stock);
                                    var number = obj.find('.number').val();
                                    formatInfo(obj,number,v.stock,v.name,v.id,v.basic_nmber,v.tax_included);
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
                    var supplyer_code = $('#supplyer_code').val();
                    $.ajax( {
                        url: "kuerp/goods_purchase/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':stores_id,'attr':attr,'supplyer_code':supplyer_code
                        },
                        success: function(res){
                            res = JSON.parse(res);
                            goods=res;
                            obj.find('.goods_specification_id').val(goods.goods.goods_specification_id || '');
                            obj.find('.vendor_name').val(goods.goods.vendor_name || '');
                            obj.find('.packaging').val(goods.goods.packaging || '');
                            $(goods.unit).each(function (k,v) {
                                if(v.id==goods_unit){
                                    obj.find('.allow_stock').val(v.stock);
                                    var number = obj.find('.number').val();
                                    formatInfo(obj,number,v.stock,v.name,v.id,v.basic_nmber,v.tax_included);
                                }
                            });
                        }
                    })
                });
                // 数量
                $(document).on("change", ".number", function(){
                    var obj = $(this).parents('tr');
                    // 手动修改过数量的行解除BOM联动（再改父商品数量不会覆盖）
                    obj.removeAttr('data-bom-ratio');
                    changeGoodsInfo(obj)
                });
                $(document).on("change", ".unit_price", function(){
                    var obj = $(this).parents('tr');
                    changeGoodsInfo(obj)
                });
                // 仓库
                $(document).on("change", ".stores_id", function(){
                    var obj = $(this).parents('tr');
                    var goods_code = obj.find('.goods_code').val();
                    var attr = obj.find('.goods_attr').val();
                    var goods_unit = obj.find('.goods_unit').val();
                    var stores_id = $(this).val();
                    var supplyer_code = $('#supplyer_code').val();
                    $.ajax( {
                        url: "kuerp/goods_purchase/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':stores_id,'attr':attr,'supplyer_code':supplyer_code
                        },
                        success: function(res){
                            res = JSON.parse(res);
                            goods=res;
                            obj.find('.goods_specification_id').val(goods.goods.goods_specification_id || '');
                            obj.find('.vendor_name').val(goods.goods.vendor_name || '');
                            obj.find('.packaging').val(goods.goods.packaging || '');
                            $(goods.unit).each(function (k,v) {
                                if(v.id==goods_unit){
                                    obj.find('.allow_stock').val(v.stock);
                                }
                            });
                        }
                    })
                });

                // 添加行
                $(document).on("click", ".add", function(){
                    var lawtable = document.getElementById("purchase_table");
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
                        lay('.validity').each(function () {
                            laydate.render({
                                elem:this,
                                trigger:"click",
                                done: function(value){
                                    manufacture();
                                }
                            })
                        })
                    })
                });
                // 删除行
                $(document).on("click", ".del", function(){
                    var lawtable = document.getElementById("purchase_table");
                    var rows = lawtable.rows;//获取所有行
                    if(rows.length>2){
                        $(this).parents('tr').remove();
                    }else{
                        Toastr.error('至少保留一条商品数据！');
                    }
                });

                // 入库单
                $(document).on("click", ".createexchange", function(){
                    var exchange = $("#createexchange").val();
                    Fast.api.open("kuerp/goods_exchange/add?ids="+exchange, "盘盈",{ area:['90%','75%'],});
                });

                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    $.ajax( {
                        url: "kuerp/Warehousing/getMenu",
                        type: "POST",
                        data:{
                            'url':'kuerp/warehousing',
                        },
                        success: function(res){
                            if(res.code==1){
                                if(reloadStatus){
                                    Fast.api.addtabs('kuerp/warehousing',res.msg.title,res.msg.icon);
                                    parent.location.reload();
                                }else{
                                    $('.btn-refresh').click();
                                }
                            }
                        }
                    });
                });
            }
        }
    };
    return Controller;
});