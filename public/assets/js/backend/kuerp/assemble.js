define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.config({
                base: '/assets/addons/kuerp/layui/module/'
            }).extend({
                tableMerge: 'tableMerge'
            });

            layui.use(['table','upload','form','tableMerge'], function(){
                var table = layui.table;
                upload = layui.upload;
                tableMerge = layui.tableMerge;

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
                    ,url:'kuerp/assemble/index'
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
                        {checkbox: true,merge:['purchase_code']},
                        {field: 'purchase_time', title: __('Purchase_time'),merge:['purchase_code'],width:120},
                        {field: 'produce_date', title: __('Produce_date'),merge:['purchase_code'],width:120,templet: function (d) {
                            if (d.produce_date) { return d.produce_date; }
                            return '<span style="color:#ccc">—</span>';
                        }},
                        {field: 'createtime', title: __('Create_date'),merge:['purchase_code'],width:160},
                        {field: 'purchase_code', title: __('Purchase_code'),merge:true,width:150},
                        {field: 'sales_order_id', title: __('Sales_order_id'),merge:['purchase_code'],width:160,templet: function (d) {
                            if (d.sales_order_id) { return '<span style="color:#1890ff">'+d.sales_order_id+'</span>'; }
                            return '<span style="color:#ccc">—</span>';
                        }},
                        {field: 'expected_delivery_date', title: __('Expected_delivery_date'),merge:['purchase_code'],width:150,templet: function (d) {
                            if (d.expected_delivery_date) { return '<span style="color:#ff5722">'+d.expected_delivery_date+'</span>'; }
                            return '<span style="color:#ccc">—</span>';
                        }},
                        {field: 'pname', title: __('组合件'),merge:['purchase_code'],width:140},
                        {field: 'pnumber', title: __('组合件数量'),merge:['purchase_code']},
                        {field: 'unit_name', title: __('单位'),merge:['purchase_code']},
                        // {field: 'money', title: __('组装费用'),merge:['purchase_code']},
                        // {field: 'punit_cost', title: __('组合件单位成本'),merge:['purchase_code'],width:140},
                        // {field: 'pcost_money', title: __('组合件成本'),merge:['purchase_code']},
                        {field: 'preparation', title: __('制单人'),merge:['purchase_code']},
                        {field: 'examine_status', title: __('Examine_status'),merge:['purchase_code'], templet: function(d){
                            if(d.examine_status == 0) return '<span style="color:red">' + __('Examine_status 0') + '</span>';
                            return '<span style="color:green">' + __('Examine_status 1') + '</span>';
                        }},
                        {field: 'inbound_status', title: __('Inbound_status'),merge:['purchase_code'], templet: function(d){
                            if(d.inbound_status == 0) return '<span style="color:#999">' + __('Inbound_status 0') + '</span>';
                            return '<span style="color:green">' + __('Inbound_status 1') + '</span>';
                        }},
                        {field: 'examine', title: __('审核人'),merge:['purchase_code'],hide:hideStatus},
                        {field: 'examine_time', title: __('审核时间'),merge:['purchase_code'],hide:hideStatus,width:120},
                        {field: 'remark', title: __('备注'),merge:['purchase_code']},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemoAssemble', width:200,merge:['purchase_code']}
                    ]],
                    done: function(){
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
                        tableMerge.render(this)
                    }
                });

                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    var data = checkStatus.data;
                    var ids = '';
                    $(data).each(function (k,v) {
                        ids+=v.id+',';
                    });
                    // 去掉末尾逗号
                    if(ids.length > 0){
                        ids = ids.substring(0, ids.length - 1);
                    }
                    console.log('toolbar event:', obj.event, 'ids:', ids);
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'add':
                            Fast.api.open("kuerp/assemble/add", "添加", {
                                callback:function(value){
                                    Controller.api.bindevent();
                                },
                                area:['90%','75%'],
                            });
                            break;
                        case 'delAll':
                            Fast.api.ajax("kuerp/assemble/del?ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'examine':
                            if(data.length<=0){
                                Toastr.error('请选择要子件出库审核的数据');return;
                            }
                            Fast.api.ajax("kuerp/assemble/examine?examine=1&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'examine_f':
                            if(data.length<=0){
                                Toastr.error('请选择要子件出库反审核的数据');return;
                            }
                            Fast.api.ajax("kuerp/assemble/examine?examine=2&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'examineInbound':
                            if(data.length<=0){
                                Toastr.error('请选择要组合件入库审核的数据');return;
                            }
                            Fast.api.ajax("kuerp/assemble/examineInbound?examine=1&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'examineInbound_f':
                            if(data.length<=0){
                                Toastr.error('请选择要组合件入库反审核的数据');return;
                            }
                            Fast.api.ajax("kuerp/assemble/examineInbound?examine=2&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'outboundEdit':
                            if(data.length != 1){
                                Toastr.error('请选择一条记录');return;
                            }
                            if(data[0].examine_status != 1){
                                Toastr.error('请选择已审核的数据');return;
                            }
                            Fast.api.open("kuerp/assemble/outboundEdit?ids=" + data[0].id, "实际出库/入库", {
                                callback: function () {
                                    $('.btn-refresh').click();
                                },
                                area: ['95%', '80%'],
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
                                    url: "kuerp/assemble/del",
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
                        Fast.api.open("kuerp/assemble/edit?ids="+data.id, "编辑",{area:['90%','75%'],});
                    } else if(obj.event === 'print'){
                        parent.Fast.api.open("kuerp/assemble/printpage?ids=" + data.id, "打印", {
                            // 打印页为 A4 横向（内容宽 297mm），弹层放宽以便预览不被压缩
                            area: ['95%', '90%'],
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
                    var purchase_code = $('#purchase_code').val();
                    if(purchase_code!=''){
                        search.purchase_code = purchase_code;
                    }
                    var purchase_time = $('#purchase_time').val();
                    if(purchase_time!=''){
                        search.purchase_time = purchase_time;
                    }
                    var produce_date = $('#produce_date').val();
                    if(produce_date!=''){
                        search.produce_date = produce_date;
                    }
                    var createtime = $('#createtime').val();
                    if(createtime!=''){
                        search.createtime = createtime;
                    }
                    var examine_status = $('#examine_status').val();
                    if(examine_status!=''){
                        search.examine_status = examine_status;
                    }
                    var inbound_status = $('#inbound_status').val();
                    if(inbound_status!=''){
                        search.inbound_status = inbound_status;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.purchase_code = 'LIKE';
                    op.purchase_time = 'RANGE';
                    op.produce_date = 'RANGE';
                    op.createtime = 'RANGE';
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
        outboundedit: function () {
            Controller.api.bindeventOutboundEdit();
        },
        api: {
            bindevent: function (reloadStatus=true) {
                layui.use('laydate', function(){
                    var laydate = layui.laydate;
                    //执行一个laydate实例

                    laydate.render({
                        elem: '#c-purchase_time',
                    });
                    laydate.render({
                        elem: '#c-produce_date',
                    });
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
                        elem: '#purchase_time',
                        type: 'datetime',
                        range: true
                    });
                    laydate.render({
                        elem: '#produce_date',
                        type: 'date',
                        range: true
                    });
                    laydate.render({
                        elem: '#createtime',
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
                    //一键生成采购预订单(计划)在已审核单据上仍可使用
                    $('.shortage_purchase').removeAttr('disabled');
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
                    var number_p = 2;
                    if ($(this).hasClass('number_p')){
                        //组合件
                        number_p = 1;
                    }
                    var goods_code = obj.find('.goods_code').val();
                    var store = obj.find('.stores_id').val();
                    var attr = obj.find('.goods_attr').val();
                    period = obj;
                    if(goods_code!=''){
                        $.ajax( {
                            url: "kuerp/goods_purchase/getUnit",
                            type: "POST",
                            data:{
                                'goods_code':goods_code,'store':store,'attr':attr
                            },
                            success: function(res){
                                res = JSON.parse(res);
                                if(res.goods.serial==1){
                                    obj.find(".number_ipt").attr("readOnly","true");
                                    showperiod(obj,number_p)
                                }else{
                                    obj.find(".number_ipt").attr("readOnly",false);
                                }
                            }
                        });
                    }
                });
                $(document).on("change",'.serial_ipt',function () {
                    check_serial()
                });
                function showperiod(obj,number_p){
                    period_ser = obj.find('.period_period');
                    period_period = obj.find('.period_period').val();
                    if(number_p==1){
                        //组合件
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
                        period_period_init(number_p);
                    }else{
                        //子件
                        var goods_code = obj.find('.goods_code').val();
                        var store = obj.find('.stores_id').val();
                        var attr = obj.find('.goods_attr').val();

                        $.ajax( {
                            url: "kuerp/goods/getSerial",
                            type: "POST",
                            data:{
                                'goods_code':goods_code,'stores_id':store,'attribute_id':attr
                            },
                            success: function(res) {
                                res = JSON.parse(res);
                                list = res.list;

                                period_ser = obj.find('.period_period');
                                period_period = obj.find('.period_period').val();
                                var html = '<form id="ser_form" class="form-horizontal" role="form" data-toggle="validator" method="POST" action="">\n' +
                                    '        <div class="list_form chose_list_form">\n' +
                                    '            <table id="serial_table" class="chose_serial_table">\n' +
                                    '                <tr>\n' +
                                    '                    <td></td><td><span class="must">*</span>序列号</td><td>备注</td>\n' +
                                    '                </tr>\n' +
                                    '                <tbody id="serial_tbody">\n';
                                for (var a=1;a<=list.length;a++){
                                    html += '                <tr>\n' +
                                        '                    <td><div><input type="checkbox" name="stock[serial][]" value="'+list[a-1].serial+'"></div></td>\n' +
                                        '                    <td><div>'+list[a-1].serial+'</div></td>\n' +
                                        '                    <td><div>'+list[a-1].remark+'</div></td>\n' +
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
                                    title: '选择序列号',
                                    shadeClose: true,
                                    shade: false,
                                    maxmin: true, //开启最大化最小化按钮
                                    area: ['780px', '550px'],
                                    content: html
                                });
                                period_period_init(number_p);
                            }
                        });
                    }
                }
                function period_period_init(number_p) {
                    $.ajax( {
                        url: "kuerp/goods/period",
                        type: "POST",
                        data:{'period_period':period_period},
                        success: function(res){
                            if(res.code==1 && res.msg.ser!=false){
                                var arr = res.msg.ser;
                                if(number_p==2){
                                    var boxes = document.getElementsByName("stock[serial][]");
                                    for (a=0;a<arr.length;a++){
                                        for(i=0;i<boxes.length;i++){
                                            if(boxes[i].value == arr[a].serial){
                                                boxes[i].checked = true;
                                                break
                                            }
                                        }
                                    }
                                }else{
                                    for (a=0;a<arr.length;a++){
                                        $("#serial_table tr:nth-child("+(a-(-1))+") td:nth-child(2)").find('input').val(arr[a].serial);
                                        $("#serial_table tr:nth-child("+(a-(-1))+") td:nth-child(3)").find('input').val(arr[a].remark);
                                    }
                                    check_serial();
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
                        cha = startlen-arr[a].toString().length;
                        for (var b=1;b<=cha-((a-(-1)).toString().length-1);b++){
                            str = str+'0';
                        }
                        $("#serial_table tr:nth-child("+(a-(-1))+") td:nth-child(2)").find('input').val(''+prefix+str+arr[a]);
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
                                }else{
                                    $("#serial_table tr:nth-child("+a+") td:nth-child(3)").find('input').val('');
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
                    $.ajax( {
                        url: "kuerp/goods/period",
                        type: "POST",
                        data:$('#ser_form').serialize(),
                        success: function(res){
                            if(res.code==1){
                                period.find('.number').val(res.msg.num);
                                period_ser.val(res.msg.ser);
                                changeGoodsInfo(period,'number');
                                layer.closeAll();
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
                        check_serial()
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
                                console.log(res);
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

                var switchs = 0;
                var switchss = 0;
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
                $(document).on("change", "#c-switchs", function(){
                    switchss = $(this).val();
                    if (switchss==1){
                        $('.goods_code_spans').hide()
                        var objp = $(this).parents('table')[0].id;
                        var inputs = $("#"+objp+" tr").find(".goods_names");
                        inputs[0].focus();
                    }else{
                        $('.goods_code_spans').show()
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
                                    obj.find('.goods_names').val(goods.goods_code+' '+goods.goods_name);
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
                //组合件
                $(document).on("click", ".goods_code_spans", function(){
                    if(switchss==1){
                        return false;
                    }
                    var obj = $(this).parents('tr');
                    parent.Fast.api.open("kuerp/Goods/chosegoods", "选择商品",{area:['90%','75%'],callback:function(goodsData){
                            if(goodsData!=undefined){
                                $.ajax( {
                                    url: "kuerp/goods/getUnit",
                                    type: "POST",
                                    data:{'where':arrayToJsonString(goodsData)},
                                    success: function(res){
                                        res = JSON.parse(res);
                                        goods=res;
                                        $(goods).each(function (kp,vp) {
                                            var thisobj = obj;
                                            thisobj.find('.goods_code').val(vp.goods.goods_code);
                                            thisobj.find('.goods_specification_id').val(vp.goods.goods_specification_id || '');
                                            thisobj.find('.vendor_name').val(vp.goods.vendor_name || '');
                                            thisobj.find('.packaging').val(vp.goods.packaging || '');
                                            thisobj.find('.goods_names').val(vp.goods.goods_code+' '+vp.goods.goods_name);
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
                //子件选择商品
                $(document).on("click", ".goods_code_span", function(){
                    if(switchs==1){
                        return false;
                    }
                    var obj = $(this).parents('tr');

                    var lawtable = document.getElementById("assemble_table_c");
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

                $(document).on("change", ".goods_names", function(){
                    changeWidths()
                });
                function changeWidths() {
                    allwidth = 165;
                    var goods_code = document.getElementsByClassName("goods_names");
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
                var types;
                $(document).on("input", ".goods_names", function(){
                    if(switchss!=1){
                        types = 1;
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
                $(document).on("input", ".goods_name", function(){
                    if(switchs!=1){
                        types = 2;
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
                    if(types==1){
                        obj.find('.goods_names').val(goods_code+' '+goods_name);
                    }else{
                        obj.find('.goods_name').val(goods_code+' '+goods_name);
                    }
                    obj.find('.goods_note').val($(this).data('note'));
                    obj.find('.stores_id').val($(this).data('store'));
                    changeGoods(goods_code,'','',obj,'',1);
                    changeWidth();changeWidths()
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
                        url: "kuerp/goods_purchase/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':stores_id,'attr':attr,'customers_id':customers_id
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
                        url: "kuerp/goods_purchase/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':stores_id,'attr':attr,'customers_id':customers_id
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
                    syncStoresName(obj);
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
                // 单价
                $(document).on("change", ".unit_price", function(){
                    var obj = $(this).parents('tr');
                    changeGoodsInfo(obj,'unit_price')
                });
                // 数量
                $(document).on("change", ".number", function(){
                    var obj = $(this).parents('tr');
                    // 手动修改子件数量 → 解除该行BOM联动（后续改组合件数量不再覆盖此行）
                    obj.removeAttr('data-bom-ratio');
                    changeGoodsInfo(obj,'number');
                    // BOM联动: 修改组合件(父件)数量 → 子件按BOM比例同步
                    if (obj.attr('data-bom-base') !== undefined) {
                        var set = Config.set;
                        var number_decimal = parseInt(set.number_decimal.value);
                        if(isNaN(number_decimal)){
                            number_decimal = 1;
                        }
                        var parentNumber = parseFloat($(this).val()) || 0;
                        $('#assemble_table_c tr[data-bom-ratio]').each(function () {
                            var ratio = parseFloat($(this).attr('data-bom-ratio'));
                            if (isNaN(ratio)) { return; }
                            $(this).find('.number').val((ratio * parentNumber).toFixed(number_decimal));
                        });
                    }
                });

                //变更商品价格信息
                function changeGoodsInfo(obj,now_field){
                    var goods_code = obj.find('.goods_code').val();
                    var goods_unit = obj.find('.goods_unit').val();
                    var attr = obj.find('.goods_attr').val();
                    var store = obj.find('.stores_id').val();
                    var number = obj.find('.number').val();
                    var unit_price = obj.find('.unit_price').val();
                    var all_money = obj.find('.all_money').val();

                    $.ajax( {
                        url: "kuerp/goods_sales/formatInfo2",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,
                            'attr':attr,
                            'goods_unit':goods_unit,
                            'store':store,
                            'number':number,
                            'unit_price':unit_price,
                            'all_money':all_money,
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
                            'default_unit':name,
                            'default_unit_id':id,
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
                // 关联销货订单明细：按商品编号取「品号与功率 / 生产备注」（同商品多行去重后拼接）
                function getSalesOrderItem(goods_code) {
                    var result = {remark: '', production_remark: ''};
                    var info = Config.salesOrderInfo;
                    if (!info || !info.items) {
                        return result;
                    }
                    var remarks = [], production_remarks = [];
                    $(info.items).each(function (i, item) {
                        if (item.goods_code !== goods_code) {
                            return;
                        }
                        var r = $.trim(item.remark || '');
                        var p = $.trim(item.production_remark || '');
                        if (r && $.inArray(r, remarks) < 0) { remarks.push(r); }
                        if (p && $.inArray(p, production_remarks) < 0) { production_remarks.push(p); }
                    });
                    result.remark = remarks.join('；');
                    result.production_remark = production_remarks.join('；');
                    return result;
                }

                // 组装单新增页：仓库一律取商品档案的默认仓库，不需要手动选择
                function isAssembleAddForm(){
                    return $('#add-form').length > 0;
                }
                // 同步仓库列的只读显示文本（新增页仓库列只展示默认仓库名称）
                function syncStoresName(obj){
                    var sel = obj.find('.stores_id');
                    if(sel.length === 0){
                        return;
                    }
                    var val = sel.val();
                    obj.find('.stores_name').val(val ? (sel.find('option:selected').text() || '') : '');
                }

                function changeGoods(goods_code,store,attr,obj,goods_unit,number) {
                    var set = Config.set;
                    var number_decimal = parseInt(set.number_decimal.value);
                    if(isNaN(number_decimal)){
                        number_decimal = 1;
                    }
                    var customers_id = $('#customers_id').val();
                    var sales_order_id = $('.sales_order_id').val() || '';
                    // 组装单新增：仓库固定取商品默认仓库，忽略外部传入的仓库
                    var isAddForm = isAssembleAddForm();
                    if(isAddForm){
                        store = '';
                    }

                    $.ajax( {
                        url: "kuerp/goods_purchase/getUnit",
                        type: "POST",
                        data:{
                            'goods_code':goods_code,'store':store,'attr':attr,'customers_id':customers_id,
                            'sales_order_id': sales_order_id
                        },
                        success: function(res){
                            res = JSON.parse(res);
                            goods=res;
                            obj.find('.goods_specification_id').val(goods.goods.goods_specification_id || '');
                            obj.find('.vendor_name').val(goods.goods.vendor_name || '');
                            obj.find('.packaging').val(goods.goods.packaging || '');
                            if(isAddForm){
                                // 仓库自动取商品默认仓库；商品未维护默认仓库时回退为手动选择，避免无法保存
                                var defaultStore = (res.goods && res.goods.manager_warehouse_id) ? String(res.goods.manager_warehouse_id) : '';
                                obj.find('.stores_id').val(defaultStore);
                                obj.find('.stores_select_wrap').toggle(!defaultStore);
                                obj.find('.stores_name').toggle(!!defaultStore);
                                syncStoresName(obj);
                                if(!defaultStore){
                                    Toastr.warning('商品'+goods_code+'未设置默认仓库，请选择仓库（或在商品档案中设置首选仓库）');
                                }
                            }
                            // 关联销货订单：带出品号与功率 / 生产备注（只读列，不参与提交）
                            var soItem = getSalesOrderItem(goods_code);
                            obj.find('.so_remark').val(soItem.remark);
                            obj.find('.so_production_remark').val(soItem.production_remark);
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
                            }else if(attrarr && attrarr.length > 0){
                                // 没有指定属性时，自动选中第一个辅助属性（避免辅助属性商品提交时报"请选择商品属性"）
                                // 无辅助属性商品的attr_name为空(纯单位行)：不自动选中，否则单位行ID会被当作attribute_id过滤库存，审核出库时查不到库存
                                var firstAttrRow = null;
                                $(attrarr).each(function (k, v) {
                                    if (firstAttrRow === null && v.attr_name) {
                                        firstAttrRow = v;
                                    }
                                });
                                if (firstAttrRow !== null) {
                                    obj.find('.goods_attr').val(firstAttrRow.id);
                                }
                            }
                            if(store!=''){
                                obj.find('.stores_id').val(store);
                            }

                            unit = res.unit;
                            var unit_html = '';
                            $(unit).each(function (k,v) {
                                unit_html += '<option value="'+v.id+'">'+v.name+'</option>';
                                var number = obj.find('.number').val();
                                var discount = 0;
                                if(v.is_default==1 && goods_unit==''){
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
                //修改显示合计信息
                function changeInfo(){
                    var set = Config.set;
                    var price_decimal = parseInt(set.price_decimal.value);
                    if(isNaN(price_decimal)){
                        price_decimal = 1;
                    }
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

                //存为模板
                var mb_preservation = 1;
                $(document).on("click", ".mb_preservation", function(){
                    // if(mb_preservation==1){
                        mb_preservation = 2;
                        layer.prompt({title: '请输入模板名称，并确认', formType: 0}, function(text, index){
                            if(text==''){
                                layer.close(index);
                                layer.msg('请输入模板名称！')
                            }else{
                                $('#temp_name').val(text);
                                $.ajax( {
                                    url: "kuerp/Assemble/savetemp",
                                    type: "POST",
                                    data:$('.assemble_form').serialize(),
                                    success: function(data){
                                        mb_preservation = 1;
                                        if(data.code==1){
                                            layer.close(index);
                                            layer.msg('模板保存成功！')
                                        }else{
                                            layer.close(index);
                                            layer.msg(data.msg)
                                        }
                                    },error:function () {
                                        mb_preservation = 1;
                                        layer.msg('网络错误！')
                                    }
                                });
                            }
                        });
                    // }
                });
                //选择模板
                $(document).on("click", ".mb_list", function(){
                    parent.Fast.api.open("kuerp/assemble_temp/index?type=1", "选择模板",{area:['90%','75%'],callback:function(goodsData){
                            if(goodsData!=undefined){
                                var temp_id = goodsData[0].id;
                                $.ajax( {
                                    url: "kuerp/assemble_temp/gettemp",
                                    type: "POST",
                                    data:{'id':temp_id},
                                    success: function(res){
                                        if(res.code==1){
                                            $('#c-note').val(res.msg.temp.remark);
                                            $('#c-costs').val(res.msg.temp.money);

                                            var data = res.msg.info;
                                            var lawtable = document.getElementById("assemble_table_c");
                                            var rows = lawtable.rows;//获取所有行
                                            var htmlp = rows[1].innerHTML;
                                            for (var a=0;a<rows.length;a++){
                                                lawtable.deleteRow(2);
                                            }
                                            var lawtable2 = document.getElementById("assemble_table_c");
                                            var rows2 = lawtable2.rows;//获取所有行
                                            if(rows2.length>2){
                                                lawtable.deleteRow(2);
                                            }

                                            $(data).each(function (k,vp) {
                                                if(vp.type==1){//组件
                                                    var thisobj = $('#assemble_table').find('.goods_code').parents('tr');
                                                    thisobj.find('.goods_code').val(vp.goods_code);
                                                    thisobj.find('.goods_names').val(vp.goods_code+' '+vp.goods_name);
                                                    changeGoods(vp.goods_code,vp.stores_id,vp.goods_attr,thisobj,vp.goods_unit,vp.number);
                                                }else{
                                                    html = '<tr class="goods_id'+vp.id+'">'+htmlp+'</tr>';
                                                    var obj = rows[1];
                                                    $(obj).after(html);

                                                    var thisobj = $('.goods_id'+vp.id);
                                                    thisobj.find('.goods_code').val(vp.goods_code);
                                                    thisobj.find('.goods_name').val(vp.goods_code+' '+vp.goods_name);
                                                    changeGoods(vp.goods_code,vp.stores_id,vp.goods_attr,thisobj,vp.goods_unit,vp.number);
                                                }
                                            })
                                        }else{
                                            Toastr.error(res.msg);
                                        }
                                    }
                                })
                            }
                        }});
                });

                //一键生成采购预订单(计划)：子件可用库存不足时，按缺口数量（需求数量-可用库存）预填采购预订单(计划)
                $(document).on("click", ".shortage_purchase", function(){
                    var set = Config.set;
                    var number_decimal = parseInt(set.number_decimal.value);
                    if(isNaN(number_decimal)){
                        number_decimal = 1;
                    }
                    var salesOrderId = '';
                    if($('.sales_order_id').length > 0 && $('.sales_order_id').val()){
                        salesOrderId = $('.sales_order_id').val();
                    }else if(Config.assembleSalesOrderId){
                        salesOrderId = Config.assembleSalesOrderId;
                    }
                    var shortageMap = {};
                    var shortageArr = [];
                    $('#assemble_table_c tr').each(function () {
                        var tr = $(this);
                        if(tr.find('.goods_code').length == 0 || !tr.is(':visible')){
                            return;//表头行/隐藏模板行
                        }
                        var goods_code = $.trim(tr.find('.goods_code').val() || '');
                        if(goods_code == ''){
                            return;
                        }
                        var number = parseFloat(tr.find('.number').val());
                        var allow_stock = parseFloat(tr.find('.allow_stock').val());
                        if(isNaN(number)){
                            number = 0;
                        }
                        if(isNaN(allow_stock)){
                            allow_stock = 0;
                        }
                        var shortage = parseFloat((number - allow_stock).toFixed(number_decimal));
                        if(shortage <= 0){
                            return;//库存足够，无需采购
                        }
                        var attr = tr.find('.goods_attr').val() || '';
                        var stores_id = tr.find('.stores_id').val() || '';
                        //同一商品+属性+仓库的多行缺口合并
                        var key = goods_code + '_' + attr + '_' + stores_id;
                        if(shortageMap[key]){
                            shortageMap[key].now_buy_number = parseFloat((shortageMap[key].now_buy_number + shortage).toFixed(number_decimal));
                        }else{
                            shortageMap[key] = {
                                'goods_code': goods_code,
                                'goods_attr': attr,
                                'goods_unit': '',
                                'stores_id': stores_id,
                                'now_buy_number': shortage,
                                'buy_price': '',
                                'purchase_code': salesOrderId
                            };
                            shortageArr.push(shortageMap[key]);
                        }
                    });
                    if(shortageArr.length == 0){
                        Toastr.error('子件库存充足，无需生成采购预订单');
                        return false;
                    }
                    var arrstr = arrayToJsonString(shortageArr);
                    $.ajax({
                        url: "kuerp/assemble/setdata",
                        type: "POST",
                        data: {'row': arrstr},
                        success: function(res){
                            if(res.code==1){
                                //计划部门生成采购预订单（计划版页面，不展示价格）
                                parent.Fast.api.open("kuerp/goods_purchase_order_plan/add?type=1", "生成采购预订单(计划)", {area:['90%','75%']});
                            }else{
                                Toastr.error(res.msg);
                            }
                        },
                        error: function(){
                            Toastr.error('网络错误！');
                        }
                    });
                });

                //使用BOM模版
                $(document).on("click", ".mb_bom", function(){
                    parent.Fast.api.open("kuerp/goods_bom/chosebom", "选择BOM模板", {area:['90%','75%'], callback: function(goodsData){
                        if(goodsData != undefined && goodsData.length > 0){
                            var bom_id = goodsData[0].id;
                            $.ajax({
                                url: "kuerp/goods_bom/getbomdetail",
                                type: "POST",
                                data: {'id': bom_id},
                                success: function(res){
                                    if(res.code == 1){
                                        var parent = res.data.parent;
                                        var children = res.data.children;

                                        // BOM基准数量(父件数量): 子件number为1个父件所需量, 子件数=子件number×父件数量
                                        var bomBase = parseFloat(parent.number) || 1;
                                        // 组合件（父件）
                                        var parentRow = $('#assemble_table').find('.goods_code').parents('tr');
                                        parentRow.attr('data-bom-base', bomBase);
                                        parentRow.find('.goods_code').val(parent.goods_code);
                                        parentRow.find('.goods_names').val(parent.goods_code + ' ' + parent.goods_name);
                                        changeGoods(parent.goods_code, parent.manager_warehouse_id > 0 ? parent.manager_warehouse_id : '', '', parentRow, parent.unit_id > 0 ? parent.unit_id : '', bomBase);

                                        // 子件
                                        var childTable = $('#assemble_table_c');
                                        var templateRow = childTable.find('tr').eq(1);
                                        var rows = childTable.find('tr');
                                        for (var i = rows.length - 1; i >= 2; i--) {
                                            $(rows[i]).remove();
                                        }
                                        $(children).each(function (k, v) {
                                            var newRow = $(templateRow).clone();
                                            newRow.show();
                                            newRow.find('.goods_code').val(v.goods_code);
                                            newRow.find('.goods_name').val(v.goods_code + ' ' + v.goods_name);
                                            childTable.append(newRow);
                                            var ratio = parseFloat(v.number) || 0;
                                            newRow.attr('data-bom-ratio', ratio);
                                            changeGoods(v.goods_code, v.manager_warehouse_id > 0 ? v.manager_warehouse_id : '', '', newRow, v.unit_id > 0 ? v.unit_id : '', ratio * bomBase);
                                        });

                                        // 重新绑定日期选择器
                                        layui.use('laydate', function () {
                                            var laydate = layui.laydate;
                                            lay('.manufacture').each(function () {
                                                laydate.render({ elem: this, trigger: "click", done: function (value) { manufacture(); } });
                                            });
                                            lay('.validity').each(function () {
                                                laydate.render({ elem: this, trigger: "click", done: function (value) { validity(); } });
                                            });
                                        });
                                    } else {
                                        Toastr.error(res.msg);
                                    }
                                }
                            });
                        }
                    }});
                });

                // 删除行
                $(document).on("click", ".del", function(){
                    var lawtable = document.getElementById("assemble_table_c");
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
                    var lawtable = document.getElementById("assemble_table_c");
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

                // 从销货排期生成组装单：预填组合件和BOM子件
                (function initScheduleBom() {
                    var salesOrderId = Config.scheduleSalesOrderId;
                    var goodsCode = Config.scheduleGoodsCode;
                    var bomChildren = Config.scheduleBomChildren;

                    if (!salesOrderId && !goodsCode) return;

                    // 设置销货订单号
                    $('.sales_order_id').val(salesOrderId);

                    // 排期「排产」时填写的生产日期 → 组装单生产日期（add 页才有该输入框）
                    if (Config.scheduleProduceDate) {
                        $('#c-produce_date').val(Config.scheduleProduceDate);
                    }

                    if (!goodsCode) return;

                    // 填充组合件（父级）
                    var parentRow = $('#assemble_table').find('tr').last();
                    var storesId = Config.scheduleStoresId || '';
                    var number = Config.scheduleNumber || 1;
                    var goodsUnit = Config.scheduleGoodsUnit || '';
                    var goodsAttr = Config.scheduleGoodsAttr || '';

                    parentRow.find('.goods_code').val(goodsCode);
                    parentRow.find('.goods_names').val(goodsCode + ' ' + (Config.scheduleGoodsName || ''));
                    // BOM基准数量(父件数量): 子件number为1个父件所需量(服务端已按bom_number×排期数量合计)，改组合件数量时按比例联动
                    var scheduleNumber = parseFloat(number) || 1;
                    parentRow.attr('data-bom-base', scheduleNumber);
                    changeGoods(goodsCode, storesId, goodsAttr, parentRow, goodsUnit, number);

                    // 填充子件（BOM零件）
                    if (bomChildren && bomChildren.length > 0) {
                        var childTable = $('#assemble_table_c');
                        var templateRow = childTable.find('tr').eq(1);
                        var rows = childTable.find('tr');
                        for (var i = rows.length - 1; i >= 2; i--) {
                            $(rows[i]).remove();
                        }

                        $(bomChildren).each(function (k, v) {
                            var newRow = $(templateRow).clone();
                            newRow.show();
                            newRow.find('.goods_code').val(v.goods_code);
                            newRow.find('.goods_name').val(v.goods_code + ' ' + v.goods_name);
                            childTable.append(newRow);

                            var childStoresId = v.stores_id || storesId;
                            var childNumber = parseFloat(v.number) || 0;
                            newRow.attr('data-bom-ratio', childNumber / scheduleNumber);
                            changeGoods(v.goods_code, childStoresId, '', newRow, '', v.number);
                        });

                        // 重新绑定日期选择器
                        layui.use('laydate', function () {
                            var laydate = layui.laydate;
                            lay('.manufacture').each(function () {
                                laydate.render({ elem: this, trigger: "click", done: function (value) { manufacture(); } });
                            });
                            lay('.validity').each(function () {
                                laydate.render({ elem: this, trigger: "click", done: function (value) { validity(); } });
                            });
                        });
                    }
                })();

                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    if(reloadStatus){
                        parent.location.reload();
                    }else{
                        $('.btn-refresh').click();
                    }
                });
            },

            // 实际出库/入库页面事件绑定
            bindeventOutboundEdit: function () {
                // 数量格式化
                $(document).on("change", ".number_decimal", function () {
                    var set = Config.set;
                    var number_decimal = parseInt(set.number_decimal.value);
                    if (isNaN(number_decimal)) { number_decimal = 1; }
                    var val = parseFloat($(this).val());
                    if (!isNaN(val)) {
                        $(this).val(val.toFixed(number_decimal));
                    } else {
                        $(this).val('');
                    }
                });

                // 表单提交
                Form.api.bindevent($("form[role=form]"), function (data, ret) {
                    parent.Layer.closeAll();
                    parent.$('.btn-refresh').click();
                }, null, function () {
                    // 实际出库/入库数量默认均为 0：一项都没填时不允许提交，避免误点直接出库/入库
                    var total = 0;
                    $('#outbound-form .out_number_ipt, #outbound-form .in_number_ipt').each(function () {
                        var val = parseFloat($(this).val());
                        if (!isNaN(val) && val > 0) {
                            total += val;
                        }
                    });
                    if (total <= 0) {
                        Toastr.error('请至少填写一项实际出库/入库数量');
                        return false;
                    }
                });
            }
        }
    };
    return Controller;
});