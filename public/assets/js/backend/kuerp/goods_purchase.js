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

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_purchase/index'
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
                        // {field: 'id', title: __('Id')},
                        {field: 'purchase_time', title: __('Purchase_time'),templet: function (d) {
                                return layui.util.toDateString(d.purchase_time*1000,"yyyy-MM-dd");
                            },width:110},
                        {field: 'purchase_code', title: __('Purchase_code'),width:150},
                        {field: 'supplyer_code', title: __('Supplyer_code'),templet: function (d) {
                                return d.suppliers.supplyer_code+' '+d.suppliers.supplyer_name;
                            }},
                        {field: 'purchase_order_id', title: __('Purchase_order_id'), operate: 'LIKE'},
                        {field: 'returns_order_id', title: __('Returns_order_id'), operate: 'LIKE'},
                        {field: 'number', title: __('Number'),templet: function (d) {
                                return (d.number).toFixed(number_decimal);
                            }},
                        {field: 'all_money', title: __('All_money'),templet: function (d) {
                                return (d.real_money-(-d.preferential_money)).toFixed(price_decimal);
                            }},
                        {field: 'real_money', title: __('Real_money'),templet: function (d) {
                                return (d.real_money).toFixed(price_decimal);
                            }},
                        {field: 'preparation_id', title: __('Preparation_id'),templet: function (d) {
                                return d.preparation.nickname;
                            }},
                        {field: 'examine_id', title: __('Examine_id'),templet: function (d) {
                                if(d.examine.nickname!=null){
                                    return d.examine.nickname;
                                }else{
                                    return '';
                                }
                            },hide:hideStatus},
                        {field: 'pay_status', title: __('Pay_status'),templet: function (d) {
                            if(d.pay_status==0){
                                return __('Pay_status 0');
                            }
                            if(d.pay_status==1){
                                return __('Pay_status 1');
                            }
                            if(d.pay_status==2){
                                return __('Pay_status 2');
                            }
                        }},
                        {field: 'check_status', title: __('Check_status'),templet: function (d) {
                            if(d.check_status==0){
                                return __('Check_status 0');
                            }
                            if(d.check_status==1){
                                return __('Check_status 1');
                            }
                        }},
                        {field: 'storage_status', title: __('Storage_status'),templet: function (d) {
                            if(d.storage_status===''||d.storage_status===null||d.storage_status===undefined){
                                return '<span style="color:#ccc">—</span>';
                            }
                            if(d.storage_status==2){
                                return '<span style="color:#5fb878">'+__('Storage_status 2')+'</span>';
                            }
                            if(d.storage_status==1){
                                return '<span style="color:#ffb800">'+__('Storage_status 1')+'</span>';
                            }
                            return '<span style="color:#999">'+__('Storage_status 0')+'</span>';
                        }},
                        {field: 'pay_money', title: __('Pay_money'),templet: function (d) {
                                return (d.pay_money).toFixed(price_decimal);
                            }},
                        {field: 'note', title: __('Note')},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemoPurchase', width:180}
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/goods_purchase/import?file="+res.data.url, "",'',true);
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
                            Fast.api.open("kuerp/goods_purchase/add", "添加", {
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
                                    Fast.api.ajax("kuerp/goods_purchase/del?ids="+ids, function () {
                                        $('.btn-refresh').click();
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                        break;
                        case 'recyclebin':
                            Fast.api.open("kuerp/goods_purchase/recyclebin", "回收站");
                        break;
                        case 'examine':
                            Fast.api.ajax("kuerp/goods_purchase/examine?examine=1&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'examine_f':
                            Fast.api.ajax("kuerp/goods_purchase/examine?examine=2&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'counter':
                            Fast.api.ajax("kuerp/goods_purchase/counter?counter=1&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'counter_f':
                            Fast.api.ajax("kuerp/goods_purchase/counter?counter=2&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'summary':
                            if(data.length<=0){
                                Toastr.error('请选择要汇总的数据');return;
                            }
                            Fast.api.open("kuerp/goods_purchase/summary?ids="+ids, "采购汇总", {
                                area: ['90%', '75%'],
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
                                    url: "kuerp/goods_purchase/del",
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
                        Fast.api.open("kuerp/goods_purchase/edit?ids="+data.id, "编辑",{ area:['90%','75%'],});
                    } else if(obj.event === 'print'){
                        // 打印采购单：按打印模板（采购单打印模板.xlsx）渲染，printpage 在 $noNeedRight
                        Fast.api.open("kuerp/goods_purchase/printpage?ids="+data.id, "打印", {area:['90%','85%']});
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
                    var preparation_id = $('#preparation_id').val();
                    if(preparation_id!=''){
                        search.preparation_id = preparation_id;
                    }
                    var examine_id = $('#examine_id').val();
                    if(examine_id!=''){
                        search.examine_id = examine_id;
                    }
                    var operation_id = $('#operation_id').val();
                    if(operation_id!=''){
                        search.operation_id = operation_id;
                    }
                    var pay_status = $('#pay_status').val();
                    if(pay_status!=''){
                        search.pay_status = pay_status;
                    }
                    var check_status = $('#check_status').val();
                    if(check_status!=''){
                        search.check_status = check_status;
                    }
                    var examine_status = $('#examine_status').val();
                    if(examine_status!=''){
                        search.examine_status = examine_status;
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
                    laydate.render({
                        elem: '.manufacture',
                        done: function(value){
                            manufacture();
                        }
                    });
                    laydate.render({
                        elem: '.validity',
                        done: function(value){
                            validity();
                        }
                    });
                    laydate.render({
                        elem: '#c-purchase_time',
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

                //选择原始单据
                $(document).on("click", "#choseEnclosure", function(){
                    parent.Fast.api.open("kuerp/enclosure/selectpage", "选择原始单据",{area:['80%','60%'],callback:function(listData){
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
                //查看原始单据：图片用相册预览，PDF/Excel/Word 等文件用链接打开
                $(document).on("click", ".voucherAttachment", function(){
                    var id = $('#c-enclosure').val();
                    $.ajax( {
                        url: "kuerp/Enclosure/index",
                        type: "POST",
                        data:{
                            'searchIds':id,
                        },
                        success: function(res){
                            var rows = (res.rows && res.rows.data) ? res.rows.data : [];
                            var images = [], files = [];
                            $(rows).each(function (k, v) {
                                if (v.is_image == 1) {
                                    images.push({src: v.src});
                                } else {
                                    files.push(v);
                                }
                            });
                            if (images.length > 0) {
                                layer.photos({
                                    photos: {data: images}
                                    ,anim: 5 //0-6的选择，指定弹出图片动画类型，默认随机（请注意，3.0之前的版本用shift参数）
                                });
                            }
                            if (files.length > 0) {
                                var html = '<div style="padding:12px 16px;">';
                                $(files).each(function (k, v) {
                                    html += '<p style="margin-bottom:8px;"><a href="' + v.url + '" target="_blank"><i class="fa fa-file-o"></i> ' + v.name + '</a></p>';
                                });
                                html += '</div>';
                                layer.open({
                                    type: 1,
                                    title: '原始单据文件',
                                    area: ['420px', 'auto'],
                                    shadeClose: true,
                                    content: html
                                });
                            }
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
                    var number = obj.find('.number').val();
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
                                    showperiod(obj,number)
                                }else{
                                    obj.find(".number_ipt").attr("readOnly",false);
                                }
                            }
                        });
                    }
                });
                function showperiod(obj,number){
                    period_ser = obj.find('.period_period');
                    period_period = obj.find('.period_period').val();
                    if(number==''){
                        number = 8;
                    }
                    if(number<1){
                        number = 1;
                    }
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

                    for (var a=1;a<=number;a++){
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
                        for (var b=1;b<=cha;b++){
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
                                changeGoodsInfo(period,'number')
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


                // 供应商
                $(document).on("change", "#supplyer_code", function(){
                    changeInfo()
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
                    var obj = $(this).parents('tr');
                    obj.find('.batch_id').val('');
                });
                $(document).on("click", ".manufacture", function(){
                    manufacture_obj = $(this).parents('tr');
                });
                $(document).on("click", ".validity", function(){
                    manufacture_obj = $(this).parents('tr');
                });
                function manufacture() {
                    var manufacture = manufacture_obj.find('.manufacture').val();
                    var shelf_life = parseInt(manufacture_obj.find('.guarantee').val());
                    $.ajax( {
                        url: "kuerp/goods_purchase/getTime",
                        type: "POST",
                        data:{'shelf_life':shelf_life,'manufacture':manufacture},
                        success: function(res){
                            res = JSON.parse(res);
                            manufacture_obj.find('.validity').val(res.validity);
                        }
                    });
                }
                $(document).on("change", ".guarantee", function(){
                    manufacture_obj = $(this).parents('tr');
                    var manufacture = manufacture_obj.find('.manufacture').val();
                    var shelf_life = parseInt(manufacture_obj.find('.guarantee').val());
                    var validity = manufacture_obj.find('.validity').val();
                    if(manufacture!=''){
                        $.ajax( {
                            url: "kuerp/goods_purchase/getTime",
                            type: "POST",
                            data:{'shelf_life':shelf_life,'manufacture':manufacture},
                            success: function(res){
                                res = JSON.parse(res);
                                manufacture_obj.find('.validity').val(res.validity);
                            }
                        });
                    }else if(validity!=''){
                        $.ajax( {
                            url: "kuerp/goods_purchase/getTime",
                            type: "POST",
                            data:{'shelf_life':shelf_life,'validity':validity},
                            success: function(res){
                                res = JSON.parse(res);
                                manufacture_obj.find('.manufacture').val(res.manufacture);
                            }
                        });
                    }
                });
                function validity() {
                    var shelf_life = parseInt(manufacture_obj.find('.guarantee').val());
                    var validity = manufacture_obj.find('.validity').val();
                    $.ajax( {
                        url: "kuerp/goods_purchase/getTime",
                        type: "POST",
                        data:{'shelf_life':shelf_life,'validity':validity},
                        success: function(res){
                            res = JSON.parse(res);
                            manufacture_obj.find('.manufacture').val(res.manufacture);
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


                //变更商品价格信息
                function changeGoodsInfo(obj,now_field){
                    var goods_code = obj.find('.goods_code').val();
                    var goods_unit = obj.find('.goods_unit').val();
                    var attr = obj.find('.goods_attr').val();
                    var supplyer_code = $('#supplyer_code').val();
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
                        url: "kuerp/goods_purchase/formatInfo2",
                        type: "POST",
                        data:{
                            'supplyer_code':supplyer_code,
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

                function changeGoods(goods_code,store,attr,obj,goods_unit,number) {
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
                            renderSpecText(obj);
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
                                            renderSpecText(thisobj);
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
                // 型号 / 规格2·厂商 / 规格3·封装：把只读输入框的值同步到可换行的文本节点
                function renderSpecText(obj) {
                    $(obj).find('input.goods_specification_id,input.vendor_name,input.packaging').each(function () {
                        var $input = $(this);
                        $input.closest('td').find('.spec_text').text($input.val() || '');
                    });
                }
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
                            renderSpecText(obj);
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
                            renderSpecText(obj);
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
                            renderSpecText(obj);
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
                            money = money-(-parseFloat(all_money[k].value).toFixed(price_decimal))
                        }
                    });
                    var preferential_money = parseFloat($(this).val()).toFixed(price_decimal);
                    pmoney = (money*preferential_money/100).toFixed(price_decimal);
                    if(!isNaN(pmoney)){
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
                            money = money-(-parseFloat(all_money[k].value).toFixed(price_decimal))
                        }
                    });
                    var preferential_money = parseFloat($(this).val()).toFixed(price_decimal);
                    prate = ((preferential_money/money).toFixed(4)*100).toFixed(2);
                    if(!isNaN(prate)){
                        $('#c-preferential_rate').val(prate);
                    }
                    changeInfo()
                });

                //本次付款
                $(document).on("change", "#c-now_pay_money", function(){
                    var accountsDefault = $('#accountsDefault').val();
                    var now_pay_money = $('#c-now_pay_money').val();
                    if(accountsDefault!='' && now_pay_money!=''){
                        $('#account').val(accountsDefault)
                    }
                    changeInfo()
                });
                //分摊采购费用
                $(document).on("click", "#sharebtn", function(){
                    var costs = parseFloat($('#c-costs').val()).toFixed(2);
                    if(!isNaN(costs)){
                        var goods_code = document.getElementsByName("row[goods_code][]");
                        var all_money = document.getElementsByName("row[money_tax_price][]");
                        var purchase_money = document.getElementsByName("row[purchase_money][]");
                        var real_money = parseFloat($('#c-real_money').val()).toFixed(2);
                        if(isNaN(real_money)){
                            real_money = 0;
                        }
                        var preferential_money = parseFloat($('#c-preferential_money').val()).toFixed(2);
                        real_money = real_money-(-preferential_money);
                        if(real_money>0){
                            $(all_money).each(function (k,v) {
                                if(goods_code[k].value!=''){
                                    var dis = parseFloat(v.value).toFixed(2);
                                    money = (costs*dis/real_money).toFixed(2);
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
                            money = money-(-parseFloat(all_money[k].value).toFixed(price_decimal))
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

                    var now_pay_money = parseFloat($('#c-now_pay_money').val()).toFixed(price_decimal);
                    if(isNaN(now_pay_money)){
                        now_pay_money = 0;
                    }
                    $('#c-real_money').val(money);
                    $('#c-arrears').val((money-now_pay_money).toFixed(price_decimal));

                    var supplyer_code = $('#supplyer_code').val();
                    $.ajax( {
                        url: "kuerp/goods_purchase/getSuppliers",
                        type: "POST",
                        data:{
                            'supplyer_code':supplyer_code
                        },
                        success: function(res){
                            give_account = res;
                            if(Config.examine_status==1){
                                $('#c-add_arrears').val( (give_account).toFixed(price_decimal) );
                            }else{
                                $('#c-add_arrears').val( (money-now_pay_money-(-give_account)).toFixed(price_decimal) );
                            }
                        }
                    });
                }
                // 删除行
                $(document).on("click", ".del", function(){
                    var lawtable = document.getElementById("purchase_table");
                    var rows = lawtable.rows;//获取所有行
                    if(rows.length>3){
                        $(this).parents('tr').remove();
                    }else{
                        Toastr.error('至少保留一条商品数据！');
                    }
                    changeInfo()
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
                // 打印
                $(document).on("click", ".print", function(){
                    var strHTML=document.getElementsByTagName("html")[0].innerHTML;
                    LODOP.PRINT_INITA(1,1,770,660,"预览");
                    LODOP.ADD_PRINT_HTM(30,5,"100%","80%",strHTML);
                    LODOP.PREVIEW();
                });


                var suppliersList = Config.suppliersList;
                var expendTypeList = Config.expendTypeList;
                //采购费用
                $(document).on("click", "#add_costs", function(){
                    var html = '<form id="costs_form" class="form-horizontal" role="form" data-toggle="validator" method="POST" action="">\n' +
                        '        <div class="list list_form" style="width: 700px">\n' +
                        '            <table id="costs_table">\n' +
                        '                <tr>\n' +
                        '                    <th></th><th>供应商</th><th>支出类别</th><th>金额</th><th>备注</th>\n' +
                        '                </tr>\n' +
                        '                <tbody id="serial_tbody">\n';
                    for (var a=1;a<=5;a++){
                        if(a==1){
                            html += '<tr style="display: none">';
                        }else{
                            html += '<tr>';
                        }
                        html +=  '                    <td><div class="btn"><span class="costs_add"><i class="fa fa-plus"></i></span><span class="costs_del"><i class="fa fa-trash"></i></span></div></td>\n' +
                            '                    <td><div><select name="costs[suppliers_id][]"><option value=""></option>';
                        $(suppliersList).each(function (k,v) {
                            html += '<option value="'+v.id+'">'+v.supplyer_name+'</option>';
                        });
                        html +=  '</select></div></td>\n';
                        html +=  '<td><div><select name="costs[expend_type_id][]"><option value=""></option>';
                        $(expendTypeList).each(function (k,v) {
                            html += '<option value="'+v.id+'">'+v.name+'</option>';
                        });
                        html +=  '</select></div></td>\n';

                        html += '<td><div><input type="text" class="ipt" name="costs[money][]"></div></td>\n' +
                            '<td><div><input type="text" class="ipt" name="costs[remark][]"></div></td>\n' +
                            '</tr>\n';
                    }
                    html += '                </tbody>\n' +
                        '            </table>\n' +
                        '        </div>\n' +
                        '\n' +
                        '        <div class="form-group" style="margin: 0">\n' +
                        '            <label class="control-label col-xs-12 col-sm-2"></label>\n' +
                        '            <div class="col-xs-12 col-sm-8">\n' +
                        '                <button type="button" id="costs_sure_btn" class="btn btn-success btn-embossed">确定</button>\n' +
                        '                <button type="button" class="btn btn-default btn-embossed" onclick="layer.closeAll()">取消</button>\n' +
                        '            </div>\n' +
                        '        </div>\n' +
                        '    </form>';
                    layer.open({
                        type: 1,
                        title: '采购费用',
                        shadeClose: true,
                        shade: false,
                        maxmin: true, //开启最大化最小化按钮
                        area: ['780px', '550px'],
                        content: html
                    });
                    cost_data_init()
                });
                //初始化采购费用数据
                function cost_data_init() {
                    var cost_data = $('#cost_data').val();
                    $.ajax( {
                        url: "kuerp/cost/checkdata",
                        type: "POST",
                        data:{'cost_data':cost_data},
                        success: function(res){
                            if(res.code==1 && res.msg.ser!=false){
                                var arr = res.msg.ser;
                                for (a=0;a<arr.length;a++){
                                    $("#costs_table tr:nth-child("+(a-(-2))+") td:nth-child(2)").find('select').val(arr[a].suppliers_id);
                                    $("#costs_table tr:nth-child("+(a-(-2))+") td:nth-child(3)").find('select').val(arr[a].expend_type_id);
                                    $("#costs_table tr:nth-child("+(a-(-2))+") td:nth-child(4)").find('input').val(arr[a].money);
                                    $("#costs_table tr:nth-child("+(a-(-2))+") td:nth-child(5)").find('input').val(arr[a].remark);
                                }
                            }
                        }
                    });
                }
                //统计采购费用
                $(document).on("click", "#costs_sure_btn", function(){
                    $.ajax( {
                        url: "kuerp/cost/checkdata",
                        type: "POST",
                        data:$('#costs_form').serialize(),
                        success: function(res){
                            if(res.code==1){
                                $('#c-costs').val(res.msg.count);
                                $('#cost_data').val(res.msg.data);
                                layer.closeAll();
                            }else{
                                layer.msg(res.msg);
                            }
                        }
                    });
                });
                // 采购费用添加行
                $(document).on("click", ".costs_add", function(){
                    var objp = $(this).parents('table')[0].id;
                    var lawtable = document.getElementById(objp);
                    var rows = lawtable.rows;//获取所有行
                    var html = rows[1].innerHTML;
                    html = '<tr>'+html+'</tr>';
                    var obj = $(this).parents('tr');
                    $(obj).after(html);
                });
                // 采购费用删除行
                $(document).on("click", ".costs_del", function(){
                    var objp = $(this).parents('table')[0].id;
                    var lawtable = document.getElementById(objp);
                    var rows = lawtable.rows;//获取所有行
                    if(rows.length>3){
                        $(this).parents('tr').remove();
                    }else{
                        layer.msg('至少保留一条数据！')
                    }
                    changeAccountInfo()
                });


                //生成退货单
                $(document).on("click", ".exchange", function(){
                    var exchange = $(this).data('id');
                    $.ajax( {
                        url: "kuerp/goods_purchase/checkPurchase",
                        type: "POST",
                        data:{
                            'exchange':exchange
                        },
                        success: function(res){
                            if(res.code==1){
                                parent.$("#createexchange").val(exchange);
                                parent.$(".createexchange").trigger("click");
                            }else{
                                Toastr.error(res.msg);
                            }
                        }
                    });
                });
                //生成退货单
                $(document).on("click", ".createexchange", function(){
                    var exchange = $("#createexchange").val();
                    Fast.api.open("kuerp/goods_exchange/add?ids="+exchange, "退货单",{ area:['90%','75%'],});
                });
                // 编辑页已有明细：按商品编号回填型号 / 规格2·厂商 / 规格3·封装
                // （服务端模板这三列只输出空的只读输入框，值由前端从商品档案带出）
                (function initSpecText() {
                    var goodsMap = {};
                    var gl = Config.goodsList || [];
                    for (var i = 0; i < gl.length; i++) {
                        if (gl[i] && gl[i].goods_code) { goodsMap[gl[i].goods_code] = gl[i]; }
                    }
                    $('#goods_table tr, #purchase_table tr').each(function () {
                        var tr = $(this);
                        var code = tr.find('.goods_code').val();
                        if (!code || !goodsMap[code]) { return; }
                        var g = goodsMap[code];
                        tr.find('.goods_specification_id').val(g.goods_specification_id || '');
                        tr.find('.vendor_name').val(g.vendor_name || '');
                        tr.find('.packaging').val(g.packaging || '');
                        renderSpecText(tr);
                    });
                })();

                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    $.ajax( {
                        url: "kuerp/Goods/getMenu",
                        type: "POST",
                        data:{
                            'url':'kuerp/goods_purchase',
                        },
                        success: function(res){
                            if(res.code==1){
                                if(reloadStatus){
                                    Fast.api.addtabs('kuerp/goods_purchase',res.msg.title,res.msg.icon);
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