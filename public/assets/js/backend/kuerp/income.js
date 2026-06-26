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
                    ,url:'kuerp/income/index'
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
                        {field: 'purchase_code', title: __('Purchase_code'), width: 160},
                        {field: 'purchase_time', title: __('Purchase_time'),templet: function (d) {
                                return layui.util.toDateString(d.purchase_time*1000,'yyyy-MM-dd');
                            },width:130},
                        {field: 'customers_id', title: __('Customers_id'),templet:function (d) {
                                if(d.customers.customer_name!=null){
                                    return d.customers.customer_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'money', title: __('Money'), operate:'BETWEEN'},
                        {field: 'pay_money', title: __('Pay_money'), operate:'BETWEEN'},
                        {field: 'preparation_id', title: __('Preparation_id'),templet:function (d) {
                                if(d.admin.username!=null){
                                    return d.admin.username;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'examine_id', title: __('Examine_id'),templet:function (d) {
                                if(d.examine.username!=null){
                                    return d.examine.username;
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
                        {field: 'remark', title: __('Remark'), operate: 'LIKE'},
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
                            Fast.api.open("kuerp/income/add", "添加", {
                                callback:function(value){
                                    Controller.api.bindevent();
                                }
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
                                    Fast.api.ajax("kuerp/income/del?ids="+ids, function () {
                                        $('.btn-refresh').click();
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                            break;
                        case 'examine':
                            Fast.api.ajax("kuerp/income/examine?examine=1&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'examine_f':
                            Fast.api.ajax("kuerp/income/examine?examine=2&ids="+ids, function () {
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
                                    url: "kuerp/income/del",
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
                        Fast.api.open("kuerp/income/edit?ids="+data.id, "编辑");
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
                    var customers_id = $('#customers_id').val();
                    if(customers_id!=''){
                        search.customers_id = customers_id;
                    }
                    var examine_status = $('#examine_status').val();
                    if(examine_status!=''){
                        search.examine_status = examine_status;
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

                //核销金额变动
                $(document).on("change", ".account_money", function(){
                    var objp = $(this).parents('table')[0].id;
                    changeInfo(objp)
                });
                //付款金额变动
                $(document).on("change", "#c-pay_money", function(){

                    var accountsDefault = $('#accountsDefault').val();
                    var now_pay_money = $('#c-pay_money').val();
                    if(accountsDefault!='' && now_pay_money!=''){
                        $('#accounts_id').val(accountsDefault)
                    }

                    var set = Config.set;
                    var price_decimal = parseInt(set.price_decimal.value);
                    if(isNaN(price_decimal)){
                        price_decimal = 1;
                    }

                    var lawtable = document.getElementById('account_table');
                    var rows = lawtable.rows;//获取所有行
                    var money_hj = 0;
                    for(var k=0;k<rows.length;k++){
                        if(k>1 && rows[k].cells[2].childNodes[0].value!='' ){
                            if(rows[k].cells[2].childNodes[0].value!=undefined ){
                                rowsalemoney = parseFloat(rows[k].cells[2].childNodes[0].value);
                                if(!isNaN(rowsalemoney)){
                                    money_hj = money_hj-(-rowsalemoney);
                                }
                            }
                        }
                    }
                    money_hj = money_hj.toFixed(price_decimal);

                    var pay_money = parseFloat($('#c-pay_money').val());
                    if(!isNaN(money_hj) && !isNaN(pay_money) && money_hj>=pay_money){
                        var money = (money_hj-pay_money).toFixed(price_decimal);
                        $('#c-arrears').val(money);
                    }else{
                        $('#c-arrears').val('');
                    }
                });

                //更新合计
                function changeInfo(table){
                    var set = Config.set;
                    var price_decimal = parseInt(set.price_decimal.value);
                    if(isNaN(price_decimal)){
                        price_decimal = 1;
                    }

                    var lawtable = document.getElementById(table);
                    var rows = lawtable.rows;//获取所有行
                    var money = 0;
                    for(var k=0;k<rows.length;k++){
                        if(k>1 && rows[k].cells[2].childNodes[0].value!='' ){
                            if(rows[k].cells[2].childNodes[0].value!=undefined ){
                                rowsalemoney = parseFloat(rows[k].cells[2].childNodes[0].value);
                                if(!isNaN(rowsalemoney)){
                                    money = money-(-rowsalemoney);
                                }
                            }
                        }
                    }
                    money = money.toFixed(price_decimal);
                    $('#'+table).find('.money_hj').val(money);
                    $('#c-pay_money').val(money);
                }

                // 添加行
                $(document).on("click", ".add", function(){
                    var objp = $(this).parents('table')[0].id;
                    var lawtable = document.getElementById(objp);
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
                    var objp = $(this).parents('table')[0].id;
                    var lawtable = document.getElementById(objp);
                    var rows = lawtable.rows;//获取所有行
                    if(rows.length>4){
                        $(this).parents('tr').remove();
                    }else{
                        Toastr.error('至少保留一条数据！');
                    }
                    changeInfo(objp)
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