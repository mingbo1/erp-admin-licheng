define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form','laydate'], function(){
                var table = layui.table;
                upload = layui.upload;
                laydate = layui.laydate;

                var set = Config.set;
                var number_decimal = parseInt(set.number_decimal.value);
                if(isNaN(number_decimal)){
                    number_decimal = 1;
                }

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_sales_schedule/index'
                    ,parseData: function(res){
                        return {
                            "code": 0,
                            "msg": '',
                            "count": res.total,
                            "data": res.rows
                         };
                    }
                    ,toolbar: '#thisToolbar'
                    ,page: true
                    ,cellMinWidth: 100
                    ,cols: [[
                        {checkbox: true},
                        {field: 'purchase_time', title: __('Purchase_time'),templet: function (d) {
                                return layui.util.toDateString(d.purchase_time*1000,"yyyy-MM-dd");
                            },width:110},
                        {field: 'purchase_code', title: __('Purchase_code'),width:150},
                        {field: 'customers_id', title: __('Customers_id'),templet: function (d) {
                                return d.customers ? d.customers.customer_name : '';
                            }},
                        {field: 'sale_user', title: __('Sale_user'),templet: function (d) {
                                return d.staffmember ? d.staffmember.name : '';
                            }},
                        {field: 'delivery_mode', title: __('Delivery_mode'), width: 90, templet: function (d) {
                                if(d.delivery_mode=='dropship'){
                                    return '<span style="color:#ff5722">'+__('Delivery_mode dropship')+'</span>';
                                }
                                return __('Delivery_mode direct');
                            }},
                        {field: 'number', title: __('Number'),templet: function (d) {
                                return (parseFloat(d.number) || 0).toFixed(number_decimal);
                            }},
                        {field: 'preparation_id', title: __('Preparation_id'),templet: function (d) {
                                return d.preparation.nickname;
                            }},
                        {field: 'check_status', title: __('Check_status'),templet: function (d) {
                                if(d.check_status==0){
                                    return __('Check_status 0');
                                }
                                if(d.check_status==1){
                                    return __('Check_status 1');
                                }
                            }},
                        {field: 'production_status', title: __('Production_status'),width:100,templet: function (d) {
                                if(d.production_status==1){
                                    return '<span style="color:#5FB878;">' + __('Production_status 1') + '</span>';
                                }
                                return '<span style="color:#999;">' + __('Production_status 0') + '</span>';
                            }},
                        {field: 'production_date', title: __('Production_date'),width:110,templet: function (d) {
                                return d.production_date ? d.production_date : '<span style="color:#ccc">—</span>';
                            }},
                        {field: 'delivery_time', title: __('Delivery_time'),templet: function (d) {
                                return layui.util.toDateString(d.delivery_time*1000,"yyyy-MM-dd");
                            },width:110},
                        {field: 'expected_delivery_date', title: __('Expected_delivery_date'), edit: 'text', width: 130},
                        {field: 'note', title: __('Note')},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemo', width:150}
                    ]]
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
                            Fast.api.open("kuerp/goods_sales_schedule/add", "添加计划排期", {
                               callback:function(value){
                                  Controller.api.bindevent();
                               },
                                area:['90%','75%'],
                            });
                        break;
                        case 'production':
                            if(data.length<=0){
                                Toastr.error('请选择要排产的数据');return;
                            }
                            // 弹窗填写生产日期（批量选中的排期统一写入同一日期）
                            var todayProdStr = layui.util.toDateString(new Date(), 'yyyy-MM-dd');
                            layer.open({
                                type: 1,
                                title: '排产 - 填写生产日期',
                                shadeClose: false,
                                area: ['520px', '250px'],
                                content: '<div class="form-horizontal" role="form" style="padding:24px 10px 0;overflow:hidden;">' +
                                    '<div class="form-group">' +
                                    '<label class="control-label col-xs-4">生产日期:</label>' +
                                    '<div class="col-xs-7"><input type="text" class="form-control" id="production-date" value="' + todayProdStr + '" readonly placeholder="请选择日期"></div>' +
                                    '</div>' +
                                    '<div class="form-group" style="margin-top:-6px;">' +
                                    '<div class="col-xs-offset-4 col-xs-7"><span class="help-block" style="margin:0;">已选 ' + data.length + ' 条，排产后统一写入该生产日期（生成组装单时自动带入）</span></div>' +
                                    '</div>' +
                                    '</div>',
                                btn: ['确定', '取消'],
                                yes: function(index){
                                    var productionDate = $('#production-date').val();
                                    if(!/^\d{4}-\d{2}-\d{2}$/.test(productionDate)){
                                        Toastr.error('请选择生产日期');return;
                                    }
                                    Fast.api.ajax("kuerp/goods_sales_schedule/setproduction?status=1&ids="+ids+"&production_date="+productionDate, function () {
                                        $('.btn-refresh').click();
                                    },'',true);
                                    layer.close(index);
                                }
                            });
                            laydate.render({ elem: '#production-date', trigger: 'click' });
                            break;
                        case 'production_f':
                            if(data.length<=0){
                                Toastr.error('请选择要取消排产的数据');return;
                            }
                            Fast.api.ajax("kuerp/goods_sales_schedule/setproduction?status=0&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'export':
                            if(data.length<=0){
                                Toastr.error('请选择要导出的数据');return;
                            }
                            // form提交才能触发文件下载；action需fixurl补全模块前缀
                            var $form = $('<form method="post" action="' + Fast.api.fixurl("kuerp/goods_sales_schedule/export") + '" style="display:none;"></form>');
                            $form.append($('<input type="hidden" name="ids">').val(ids));
                            $form.appendTo('body').submit().remove();
                            break;
                        case 'delAll':
                            if(data.length<=0){
                                Toastr.error('请选择要删除的数据');return;
                            }
                            Layer.confirm(
                                __('Are you sure you want to delete the %s selected item?', data.length),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function (index) {
                                    Fast.api.ajax("kuerp/goods_sales_schedule/del?ids="+ids, function () {
                                        $('.btn-refresh').click();
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                        break;
                        case 'expected':
                            if(data.length<=0){
                                Toastr.error('请选择要设置预计生产完成时间的数据');return;
                            }
                            // 弹窗填写预计生产完成时间（批量选中的排期统一写入同一日期）
                            var todayExpectedStr = layui.util.toDateString(new Date(), 'yyyy-MM-dd');
                            layer.open({
                                type: 1,
                                title: '填写预计生产完成时间',
                                shadeClose: false,
                                area: ['520px', '250px'],
                                content: '<div class="form-horizontal" role="form" style="padding:24px 10px 0;overflow:hidden;">' +
                                    '<div class="form-group">' +
                                    '<label class="control-label col-xs-4">预计生产完成时间:</label>' +
                                    '<div class="col-xs-7"><input type="text" class="form-control" id="expected-date" value="' + todayExpectedStr + '" readonly placeholder="请选择日期"></div>' +
                                    '</div>' +
                                    '<div class="form-group" style="margin-top:-6px;">' +
                                    '<div class="col-xs-offset-4 col-xs-7"><span class="help-block" style="margin:0;">已选 ' + data.length + ' 条，统一写入该日期（列表中可再修改）</span></div>' +
                                    '</div>' +
                                    '</div>',
                                btn: ['确定', '取消'],
                                yes: function(index){
                                    var expectedDate = $('#expected-date').val();
                                    if(!/^\d{4}-\d{2}-\d{2}$/.test(expectedDate)){
                                        Toastr.error('请选择预计生产完成时间');return;
                                    }
                                    // setExpectedDelivery 只接受 POST（ids 支持逗号分隔的多个 id）
                                    $.ajax({
                                        url: "kuerp/goods_sales_schedule/setExpectedDelivery",
                                        type: "POST",
                                        data: {'ids': ids, 'expected_delivery_date': expectedDate},
                                        success: function(res){
                                            if(res.code==1){
                                                Toastr.success(res.msg || '预计生产完成时间已更新');
                                                $('.btn-refresh').click();
                                                layer.close(index);
                                            }else{
                                                Toastr.error(res.msg || '更新失败');
                                            }
                                        },
                                        error: function(){
                                            Toastr.error('更新失败');
                                        }
                                    });
                                }
                            });
                            laydate.render({ elem: '#expected-date', trigger: 'click' });
                            break;
                        case 'counter':
                            Fast.api.ajax("kuerp/goods_sales_schedule/counter?counter=1&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'counter_f':
                            Fast.api.ajax("kuerp/goods_sales_schedule/counter?counter=2&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                    }
                });
                //预计生产完成日期 行内编辑（点击单元格直接修改，二次改期）
                table.on('edit(table)', function(obj){
                    if(obj.field !== 'expected_delivery_date'){ return; }
                    var val = (obj.value || '').trim();
                    if(val !== '' && !/^\d{4}-\d{2}-\d{2}$/.test(val)){
                        Toastr.error('日期格式须为 YYYY-MM-DD，已还原');
                        table.reload('table', []);
                        return;
                    }
                    $.ajax({
                        url: "kuerp/goods_sales_schedule/setExpectedDelivery",
                        type: "POST",
                        data: {'ids': obj.data.id, 'expected_delivery_date': val},
                        success: function(res){
                            if(res.code==1){
                                Toastr.success('预计生产完成日期已更新');
                            }else{
                                Toastr.error(res.msg || '更新失败');
                                table.reload('table', []);
                            }
                        },
                        error: function(){
                            Toastr.error('更新失败，已还原');
                            table.reload('table', []);
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
                                    url: "kuerp/goods_sales_schedule/del",
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
                        Fast.api.open("kuerp/goods_sales_schedule/edit?ids="+data.id, "编辑计划排期",{ area:['90%','75%'],});
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
                    var customers_id = $('#customers_id').val();
                    if(customers_id!=''){
                        search.customers_id = customers_id;
                    }
                    var preparation_id = $('#preparation_id').val();
                    if(preparation_id!=''){
                        search.preparation_id = preparation_id;
                    }
                    var check_status = $('#check_status').val();
                    if(check_status!=''){
                        search.check_status = check_status;
                    }
                    var production_status = $('#production_status').val();
                    if(production_status!=''){
                        search.production_status = production_status;
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
                });

                //选择客户
                $(document).on("change", "#customers_id", function(){
                    var customers_id = $(this).val();
                    // 自动填充客户绑定的销售员
                    $.ajax( {
                        url: "kuerp/goods_sales_schedule/getCustomerSaleUser",
                        type: "POST",
                        data:{
                            'customers_id':customers_id,
                        },
                        success: function(res){
                            var data = JSON.parse(res);
                            if (data.staff_member_id && data.staff_member_id > 0) {
                                $('#sale_user').val(data.staff_member_id);
                            }
                        }
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

                var period;
                var period_ser;
                var period_period;
                // 数量（序列号）
                $(document).on("click", ".number", function(){
                    var obj = $(this).parents('tr');
                    var goods_code = obj.find('.goods_code').val();
                    var store = obj.find('.stores_id').val();
                    var attr = obj.find('.goods_attr').val();
                    var number = obj.find('.number').val();
                    period = obj;
                    if(goods_code!=''){
                        $.ajax( {
                            url: "kuerp/goods_sales_schedule/getUnit",
                            type: "POST",
                            data:{
                                'goods_code':goods_code,'store':store,'attr':attr
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
                        maxmin: true,
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
                            }else{
                                Toastr.error('序列号设置错误，请重试！');
                            }
                        }
                    });
                });
                $(document).on("click", ".serial_del", function(){
                    var lawtable = document.getElementById("serial_table");
                    var rows = lawtable.rows;
                    if(rows.length>2){
                        $(this).parents('tr').remove();
                    }else{
                        Toastr.error('至少保留一条序列号！');
                    }
                });
                $(document).on("click", ".serial_add", function(){
                    var lawtable = document.getElementById("serial_table");
                    var rows = lawtable.rows;
                    var html = rows[1].innerHTML;
                    html = '<tr>'+html+'</tr>';
                    var obj = $(this).parents('tr');
                    $(obj).after(html);
                });


                var goods;
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
                            url: "kuerp/goods_sales_schedule/getBranch",
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
                                    maxmin: true,
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
                        url: "kuerp/goods_sales_schedule/getTime",
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
                            url: "kuerp/goods_sales_schedule/getTime",
                            type: "POST",
                            data:{'shelf_life':shelf_life,'manufacture':manufacture},
                            success: function(res){
                                res = JSON.parse(res);
                                manufacture_obj.find('.validity').val(res.validity);
                            }
                        });
                    }else if(validity!=''){
                        $.ajax( {
                            url: "kuerp/goods_sales_schedule/getTime",
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
                        url: "kuerp/goods_sales_schedule/getTime",
                        type: "POST",
                        data:{'shelf_life':shelf_life,'validity':validity},
                        success: function(res){
                            res = JSON.parse(res);
                            manufacture_obj.find('.manufacture').val(res.manufacture);
                        }
                    });
                }

                //初始获取商品信息
                function formatInfo(obj,number,stock,name,id,basic_nmber) {
                    $.ajax( {
                        url: "kuerp/goods_sales_schedule/formatInfo",
                        type: "POST",
                        data:{
                            'number':number,
                            'allow_stock':stock,
                            'advance_rate':basic_nmber,
                            'basic_nmber':basic_nmber,
                        },
                        success: function(data){
                            res = JSON.parse(data);
                            data = res.data;
                            $(data).each(function (k,v) {
                                if (v.value!==''){
                                    obj.find('.'+v.key).val(v.value);
                                }
                            });
                        }
                    });
                }

                function changeGoods(goods_code,store,attr,obj,goods_unit,number) {
                    var set = Config.set;
                    var number_decimal = parseInt(set.number_decimal.value);
                    if(isNaN(number_decimal)){
                        number_decimal = 1;
                    }

                    $.ajax( {
                        url: "kuerp/goods_sales_schedule/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':store,'attr':attr
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
                                if(v.is_default==1){
                                    obj.find('.default_unit').val(v.name);
                                    obj.find('.default_unit_id').val(v.id);
                                }
                                if(v.is_default==1 && goods_unit==''){
                                    obj.find('.default_unit').val(v.name);
                                    obj.find('.default_unit_id').val(v.id);
                                    formatInfo(obj,number,v.stock,v.name,v.id,v.basic_nmber);
                                    obj.data('basic_rate', v.basic_nmber);
                                }else if(goods_unit!='' && v.id==goods_unit){
                                    formatInfo(obj,number,v.stock,v.name,v.id,v.basic_nmber);
                                    obj.data('basic_rate', v.basic_nmber);
                                }
                            });
                            obj.find('.goods_unit').html(unit_html);

                            if(goods_unit!=''){
                                obj.find('.goods_unit').val(goods_unit);
                            }
                        }
                    });
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
                                            var rows = lawtable.rows;
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
                    var rows = lawtable.rows;
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
                                        '                                    <th>商品编号</th><th>商品名称</th><th>型号</th><th>品号</th><th>功率</th><th>计量单位</th><th>商品类别</th><th>库存数量</th><th>备注</th>\n' +
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
                    $.ajax( {
                        url: "kuerp/goods_sales_schedule/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':stores_id,'attr':attr
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
                                    formatInfo(obj,number,v.stock,v.name,v.id,v.basic_nmber);
                                    obj.data('basic_rate', v.basic_nmber);
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
                    $.ajax( {
                        url: "kuerp/goods_sales_schedule/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':stores_id,'attr':attr
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
                                    formatInfo(obj,number,v.stock,v.name,v.id,v.basic_nmber);
                                    obj.data('basic_rate', v.basic_nmber);
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
                    $.ajax( {
                        url: "kuerp/goods_sales_schedule/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':stores_id,'attr':attr
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

                // 删除行
                $(document).on("click", ".del", function(){
                    var lawtable = document.getElementById("purchase_table");
                    var rows = lawtable.rows;
                    if(rows.length>3){
                        $(this).parents('tr').remove();
                    }else{
                        Toastr.error('至少保留一条商品数据！');
                    }
                });
                // 添加行
                $(document).on("click", ".add", function(){
                    var lawtable = document.getElementById("purchase_table");
                    var rows = lawtable.rows;
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

                // 「生成采购订单」按钮 — 弹出购货订单添加页，带BOM零件预填
                $(document).on("click", ".generate-purchase-order", function(){
                    var schedule_id = $(this).data('id');
                    parent.Fast.api.open("kuerp/goods_purchase_order_plan/add?schedule_id="+schedule_id, "购货订单",{ area:['95%','80%'],});
                });

                // 「生成组装单」按钮 — 弹出组装单添加页，将该行商品作为组合件、BOM零件作为子件
                $(document).on("click", ".generate-assemble", function(){
                    var scheduleId = $(this).data('schedule-id');
                    var goodsCode = $(this).data('goods-code');
                    parent.Fast.api.open("kuerp/assemble/add?schedule_id="+scheduleId+"&goods_code="+goodsCode, "生成组装单",{ area:['90%','75%'],});
                });

                // ============ BOM零件需求计算 ============
                // 从URL获取排期ID
                var scheduleId = null;
                (function(){
                    var url = window.location.href;
                    var match = url.match(/[?&]ids=(\d+)/);
                    if (match) {
                        scheduleId = match[1];
                    }
                })();

                // 重新计算一行商品的基本单位数量 = 数量 × 单位换算率
                // 换算率优先使用行上缓存的 basic_rate（选择商品/切换单位时写入）；
                // 未缓存时根据现有 basic_nmber/数量 反推（兼容编辑页已保存且一致的数据）。
                // 数量被修改后 hidden .basic_nmber 不会自动更新，BOM需求计算必须用它，否则数量改了需求不变。
                function recalcBasicNmber($row) {
                    var number = parseFloat($row.find('.number').val()) || 0;
                    if (number <= 0) {
                        $row.find('.basic_nmber').val(0);
                        return 0;
                    }
                    var rate = parseFloat($row.data('basic_rate'));
                    if (!rate || isNaN(rate) || rate <= 0) {
                        var curBasic = parseFloat($row.find('.basic_nmber').val()) || 0;
                        rate = curBasic > 0 ? (curBasic / number) : 1;
                        if (!rate || isNaN(rate) || rate <= 0) rate = 1;
                        $row.data('basic_rate', rate);
                    }
                    var basicNmber = number * rate;
                    // 按系统小数位取整，避免浮点尾差
                    var nd = 2;
                    try {
                        var set = Config.set;
                        nd = parseInt(set.number_decimal.value) || 2;
                    } catch(e) {}
                    basicNmber = parseFloat(basicNmber.toFixed(nd));
                    // 写回隐藏域，保证保存时落库的也是重算后的正确值
                    $row.find('.basic_nmber').val(basicNmber);
                    return basicNmber;
                }

                // 加载BOM需求数据
                function loadBomRequirement() {
                    if (!scheduleId) return;
                    var $tbody = $('#bom_tbody');
                    $tbody.html('<tr><td colspan="7" class="text-center text-muted">计算中...</td></tr>');

                    // 收集当前页面的商品数据
                    var goodsData = [];
                    $('#purchase_table tr').each(function(){
                        var $row = $(this);
                        var goodsCode = $row.find('.goods_code').val();
                        if (goodsCode) {
                            var number = parseFloat($row.find('.number').val()) || 0;
                            var defaultUnit = parseFloat($row.find('.default_unit_id').val()) || 0;
                            // 数量修改后重新计算基本单位数量（同时写回隐藏域）
                            var basicNmber = recalcBasicNmber($row);
                            var storesId = $row.find('.stores_id').val() || 0;
                            goodsData.push({
                                goods_code: goodsCode,
                                number: number,
                                default_unit: defaultUnit,
                                basic_nmber: basicNmber,
                                stores_id: storesId
                            });
                        }
                    });

                    $.ajax({
                        url: "kuerp/goods_sales_schedule/getBomRequirement",
                        type: "POST",
                        data: {
                            schedule_id: scheduleId,
                            goods_data: goodsData
                        },
                        success: function(res){
                            if (res.code === 1 && res.msg && res.msg.length > 0) {
                                renderBomTable(res.msg);
                            } else {
                                $tbody.html('<tr><td colspan="7" class="text-center text-muted">' + (res.msg || '未找到BOM零件数据') + '</td></tr>');
                            }
                        },
                        error: function(){
                            $tbody.html('<tr><td colspan="7" class="text-center text-danger">加载失败，请重试</td></tr>');
                        }
                    });
                }

                // 渲染BOM表格
                function renderBomTable(data) {
                    var $tbody = $('#bom_tbody');
                    var html = '';
                    var scheduleIdForLock = scheduleId;
                    $(data).each(function(k, v){
                        var diffClass = v.total_diff >= 0 ? 'text-success' : 'text-danger';
                        var diffIcon = v.total_diff >= 0 ? '✓' : '✗';

                        // 构建仓库明细文本
                        var storeLines = [];
                        $(v.stock_details).each(function(i, sd){
                            var sdiffClass = sd.diff >= 0 ? 'text-success' : 'text-danger';
                            storeLines.push(
                                '<span style="white-space:nowrap;">' +
                                sd.stores_name + ': 库存=' + sd.stock +
                                ' / 需求=' + sd.required +
                                ' / <span class="' + sdiffClass + '">差值=' + sd.diff + '</span>' +
                                '</span>'
                            );
                        });
                        var storeDetail = storeLines.join('<br>');

                        // 锁定状态
                        var lockHtml = '';
                        // 构建仓库选项（用于锁定弹窗）
                        var storesJson = [];
                        $(v.stock_details).each(function(i, sd){
                            storesJson.push({id: sd.stores_id, name: sd.stores_name, stock: sd.stock});
                        });
                        var storesJsonStr = JSON.stringify(storesJson).replace(/'/g, "&#39;").replace(/"/g, '&quot;');
                        // 零件锁定/解锁按钮按权限显示（后端 lockGoods/unlockGoods 已并入权限表）
                        var canLock = (Config.canLockGoods == 1 || Config.canLockGoods === true);
                        var canUnlock = (Config.canUnlockGoods == 1 || Config.canUnlockGoods === true);

                        if (v.has_lock && v.lock_info) {
                            // 显示解锁按钮 + 锁定信息（按仓库）
                            var lockInfoHtml = [];
                            $.each(v.lock_info, function(sid, li){
                                // 查找仓库名
                                var sname = '仓库' + sid;
                                $.each(storesJson, function(i, sj){ if (sj.id == sid) { sname = sj.name; } });
                                lockInfoHtml.push(
                                    '<span style="white-space:nowrap;color:#1890ff;">' +
                                    sname + ': 锁定' + li.lock_quantity +
                                    (li.used_quantity > 0 ? ' / 已用' + li.used_quantity : '') +
                                    ' / 剩余' + li.remaining +
                                    '</span>'
                                );
                            });
                            lockHtml = '<div style="margin-bottom:4px;">' + lockInfoHtml.join('<br>') + '</div>' +
                                (canUnlock ? '<button type="button" class="btn btn-xs btn-warning unlock-goods"' +
                                ' data-goods-id="' + v.goods_id + '"' +
                                ' data-goods-code="' + v.goods_code + '"' +
                                ' data-stores="' + storesJsonStr + '"' +
                                ' data-schedule-id="' + scheduleIdForLock + '">解锁</button>' : '');
                        } else {
                            // 显示锁定按钮（传入所有仓库信息）
                            lockHtml = canLock ? '<button type="button" class="btn btn-xs btn-success lock-goods"' +
                                ' data-goods-id="' + v.goods_id + '"' +
                                ' data-goods-code="' + v.goods_code + '"' +
                                ' data-goods-name="' + v.goods_name + '"' +
                                ' data-required="' + v.required_total + '"' +
                                ' data-stores="' + storesJsonStr + '"' +
                                ' data-schedule-id="' + scheduleIdForLock + '"' +
                                ' data-available="' + v.total_stock + '">锁定</button>' : '';
                        }

                        // 半成品且库存不足：提供「组装单」按钮，跳转组装单添加页预填组装该半成品
                        if (v.product_type === 'semi_finished' && parseFloat(v.total_diff) < 0) {
                            var bestStoreId = (v.stock_details && v.stock_details.length > 0) ? v.stock_details[0].stores_id : '';
                            lockHtml += ' <button type="button" class="btn btn-xs btn-primary bom-assemble"' +
                                ' data-goods-code="' + v.goods_code + '"' +
                                ' data-goods-name="' + v.goods_name + '"' +
                                ' data-number="' + v.required_total + '"' +
                                ' data-stores="' + bestStoreId + '">组装单</button>';
                        }

                        html += '<tr>';
                        html += '<td>' + v.goods_code + '</td>';
                        html += '<td>' + v.goods_name + '</td>';
                        html += '<td>' + v.required_total + '</td>';
                        html += '<td>' + v.total_stock + '</td>';
                        html += '<td class="' + diffClass + '"><strong>' + diffIcon + ' ' + v.total_diff + '</strong></td>';
                        html += '<td style="font-size:12px;">' + storeDetail + '</td>';
                        html += '<td style="text-align:center;">' + lockHtml + '</td>';
                        html += '</tr>';
                    });
                    $tbody.html(html);

                    // 汇总行
                    var totalRequired = 0, totalStock = 0, totalDiff = 0;
                    $(data).each(function(k, v){
                        totalRequired += parseFloat(v.required_total);
                        totalStock += parseFloat(v.total_stock);
                        totalDiff += parseFloat(v.total_diff);
                    });
                    var summaryClass = totalDiff >= 0 ? 'text-success' : 'text-danger';
                    var summaryHtml = '<tr style="font-weight:bold;background:#f5f5f5;">' +
                        '<td colspan="2">合计</td>' +
                        '<td>' + totalRequired.toFixed(2) + '</td>' +
                        '<td>' + totalStock.toFixed(2) + '</td>' +
                        '<td class="' + summaryClass + '">' + totalDiff.toFixed(2) + '</td>' +
                        '<td></td><td></td>' +
                        '</tr>';
                    $tbody.append(summaryHtml);
                }

                // 页面加载时自动计算BOM（编辑页）
                if (scheduleId) {
                    loadBomRequirement();
                }

                // 编辑页已有行：初始化时按"数量×换算率"的初值缓存换算率，
                // 保证后续修改数量时 basic_nmber 能按正确的换算率重算
                $('#purchase_table tr').each(function(){
                    var $row = $(this);
                    var n = parseFloat($row.find('.number').val()) || 0;
                    var b = parseFloat($row.find('.basic_nmber').val()) || 0;
                    if (n > 0 && b > 0 && !$row.data('basic_rate')) {
                        $row.data('basic_rate', b / n);
                    }
                });

                // 数量变更（失焦）时立即重算基本单位数量并写回隐藏域，
                // 确保点击保存时落库的是重算后的正确值（不等500ms防抖）
                $(document).on("change", ".number", function(){
                    recalcBasicNmber($(this).parents('tr'));
                });

                // BOM刷新按钮
                $(document).on("click", "#bom-refresh", function(){
                    loadBomRequirement();
                });

                // ============ 锁定/解锁事件 ============
                // 锁定按钮
                $(document).on("click", ".lock-goods", function(){
                    var $btn = $(this);
                    var goodsId = $btn.data('goods-id');
                    var goodsCode = $btn.data('goods-code');
                    var goodsName = $btn.data('goods-name');
                    var requiredQty = parseFloat($btn.data('required'));
                    var scheduleId = $btn.data('schedule-id');
                    var stores = $btn.data('stores');
                    // 解析仓库JSON
                    if (typeof stores === 'string') {
                        stores = stores.replace(/&quot;/g, '"').replace(/&#39;/g, "'");
                        try { stores = JSON.parse(stores); } catch(e) { stores = []; }
                    }

                    // 构建仓库选项
                    var storeOptions = '';
                    $.each(stores, function(i, s){
                        storeOptions += '<option value="' + s.id + '" data-stock="' + s.stock + '">' + s.name + ' (库存:' + s.stock + ')</option>';
                    });

                    var defaultStoreId = stores.length > 0 ? stores[0].id : '';
                    var defaultStock = stores.length > 0 ? stores[0].stock : 0;

                    // 弹窗输入锁定数量
                    var html = '<form id="lock-form" class="form-horizontal" role="form">' +
                        '<div class="form-group">' +
                        '<label class="control-label col-xs-3">商品:</label>' +
                        '<div class="col-xs-9"><p class="form-control-static">' + goodsCode + ' ' + goodsName + '</p></div>' +
                        '</div>' +
                        '<div class="form-group">' +
                        '<label class="control-label col-xs-3">需求数量:</label>' +
                        '<div class="col-xs-9"><p class="form-control-static">' + requiredQty.toFixed(2) + '</p></div>' +
                        '</div>' +
                        '<div class="form-group">' +
                        '<label class="control-label col-xs-3"><span class="must">*</span>锁定仓库:</label>' +
                        '<div class="col-xs-9"><select class="form-control" id="lock-store">' + storeOptions + '</select></div>' +
                        '</div>' +
                        '<div class="form-group">' +
                        '<label class="control-label col-xs-3"><span class="must">*</span>锁定数量:</label>' +
                        '<div class="col-xs-9"><input type="number" class="form-control" id="lock-quantity" value="' + requiredQty.toFixed(2) + '" step="0.01" min="0.01">' +
                        '<span class="help-block" id="lock-available-tip">该仓库可用库存: ' + defaultStock.toFixed(2) + '</span></div>' +
                        '</div>' +
                        '</form>';

                    layer.open({
                        type: 1,
                        title: '锁定库存 - ' + goodsName,
                        shadeClose: true,
                        area: ['480px', '360px'],
                        content: html,
                        btn: ['确定', '取消'],
                        yes: function(index, layero){
                            var lockQty = parseFloat($('#lock-quantity').val());
                            var lockStore = $('#lock-store').val();
                            var available = parseFloat($('#lock-store').find('option:selected').data('stock'));
                            if (isNaN(lockQty) || lockQty <= 0) {
                                Toastr.error('请输入有效的锁定数量');
                                return;
                            }
                            if (!lockStore) {
                                Toastr.error('请选择仓库');
                                return;
                            }
                            if (lockQty > available) {
                                Toastr.error('锁定数量不能超过该仓库库存(' + available.toFixed(2) + ')');
                                return;
                            }
                            $.ajax({
                                url: 'kuerp/goods_sales_schedule/lockGoods',
                                type: 'POST',
                                data: {
                                    schedule_id: scheduleId,
                                    goods_code: goodsCode,
                                    goods_id: goodsId,
                                    stores_id: lockStore,
                                    lock_quantity: lockQty
                                },
                                success: function(res){
                                    if (res.code === 1) {
                                        Toastr.success('锁定成功');
                                        layer.close(index);
                                        loadBomRequirement();
                                    } else {
                                        Toastr.error(res.msg || '锁定失败');
                                    }
                                },
                                error: function(){
                                    Toastr.error('锁定失败，请重试');
                                }
                            });
                        }
                    });

                    // 仓库切换时更新可用库存提示
                    $(document).on('change', '#lock-store', function(){
                        var stock = $(this).find('option:selected').data('stock');
                        $('#lock-available-tip').text('该仓库可用库存: ' + (parseFloat(stock) || 0).toFixed(2));
                    });
                });

                // 解锁按钮
                $(document).on("click", ".unlock-goods", function(){
                    var $btn = $(this);
                    var goodsId = $btn.data('goods-id');
                    var goodsCode = $btn.data('goods-code');
                    var scheduleId = $btn.data('schedule-id');
                    var stores = $btn.data('stores');
                    // 解析仓库JSON
                    if (typeof stores === 'string') {
                        stores = stores.replace(/&quot;/g, '"').replace(/&#39;/g, "'");
                        try { stores = JSON.parse(stores); } catch(e) { stores = []; }
                    }

                    // 如果只有一个仓库，直接确认解锁
                    if (stores.length <= 1) {
                        var storesId = stores.length === 1 ? stores[0].id : 0;
                        Layer.confirm(
                            '确认解锁该商品的库存锁定？',
                            {icon: 3, title: '解锁确认', shadeClose: true},
                            function(index){
                                $.ajax({
                                    url: 'kuerp/goods_sales_schedule/unlockGoods',
                                    type: 'POST',
                                    data: {
                                        schedule_id: scheduleId,
                                        goods_code: goodsCode,
                                        goods_id: goodsId,
                                        stores_id: storesId
                                    },
                                    success: function(res){
                                        if (res.code === 1) {
                                            Toastr.success('解锁成功');
                                            Layer.close(index);
                                            loadBomRequirement();
                                        } else {
                                            Toastr.error(res.msg || '解锁失败');
                                        }
                                    },
                                    error: function(){
                                        Toastr.error('解锁失败，请重试');
                                    }
                                });
                            }
                        );
                        return;
                    }

                    // 多仓库：弹窗选择
                    var storeOptions = '';
                    $.each(stores, function(i, s){
                        storeOptions += '<option value="' + s.id + '">' + s.name + ' (库存:' + s.stock + ')</option>';
                    });
                    var html = '<form id="unlock-form" class="form-horizontal" role="form">' +
                        '<div class="form-group">' +
                        '<label class="control-label col-xs-3">选择仓库:</label>' +
                        '<div class="col-xs-9"><select class="form-control" id="unlock-store">' + storeOptions + '</select></div>' +
                        '</div>' +
                        '</form>';

                    layer.open({
                        type: 1,
                        title: '解锁库存 - 选择仓库',
                        shadeClose: true,
                        area: ['400px', '180px'],
                        content: html,
                        btn: ['确定', '取消'],
                        yes: function(index, layero){
                            var storesId = $('#unlock-store').val();
                            $.ajax({
                                url: 'kuerp/goods_sales_schedule/unlockGoods',
                                type: 'POST',
                                data: {
                                    schedule_id: scheduleId,
                                    goods_code: goodsCode,
                                    goods_id: goodsId,
                                    stores_id: storesId
                                },
                                success: function(res){
                                    if (res.code === 1) {
                                        Toastr.success('解锁成功');
                                        layer.close(index);
                                        loadBomRequirement();
                                    } else {
                                        Toastr.error(res.msg || '解锁失败');
                                    }
                                },
                                error: function(){
                                    Toastr.error('解锁失败，请重试');
                                }
                            });
                        }
                    });
                });

                // BOM半成品「组装单」按钮：组装库存不足的半成品
                // 打开组装单添加页，组合件=半成品（数量=BOM需求量），子件=该半成品BOM零件（服务端预计算）
                $(document).on("click", ".bom-assemble", function(){
                    var goodsCode = $(this).data('goods-code');
                    var goodsName = $(this).data('goods-name');
                    var number = $(this).data('number');
                    var storesId = $(this).data('stores');
                    parent.Fast.api.open(
                        "kuerp/assemble/add?schedule_id=" + scheduleId +
                        "&goods_code=" + encodeURIComponent(goodsCode) +
                        "&number=" + encodeURIComponent(number) +
                        "&stores_id=" + encodeURIComponent(storesId),
                        "生成组装单 - " + goodsCode + " " + goodsName,
                        { area:['90%','75%'], }
                    );
                });

                // 当商品数量/单位/仓库改变时自动刷新BOM（防抖500ms）
                var bomRefreshTimer = null;
                $(document).on("change input", ".number_ipt, .number, .goods_unit, .stores_id", function(){
                    if (bomRefreshTimer) clearTimeout(bomRefreshTimer);
                    bomRefreshTimer = setTimeout(function(){
                        loadBomRequirement();
                    }, 500);
                });

                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    $.ajax( {
                        url: "kuerp/Goods/getMenu",
                        type: "POST",
                        data:{
                            'url':'kuerp/goods_sales_schedule',
                        },
                        success: function(res){
                            if(res.code==1){
                                if(reloadStatus){
                                    Fast.api.addtabs('kuerp/goods_sales_schedule',res.msg.title,res.msg.icon);
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
