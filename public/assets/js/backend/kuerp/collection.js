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
                    ,url:'kuerp/collection/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows, //解析数据列表
                            "moneys": res.moneys,
                            "write_off": res.write_off
                        };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,page: true
                    ,totalRow: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true, merge:['purchase_code']},
                        {field: 'purchase_code', title: __('Purchase_code'), merge:true,width:140,'totalRow':'合计'},
                        {field: 'purchase_time', title: __('Purchase_time'),merge:['purchase_code'],width:120},
                        {field: 'customers_id', title: __('客户'), merge:['purchase_code']},
                        {field: 'accounts_id', title: __('结算账户')},
                        {field: 'money', title: __('收款金额'),totalRow:true},
                        {field: 'settlementtype', title: __('收款方式')},
                        {field: 'number', title: __('结算号'),width:120},
                        {field: 'remark', title: __('分录备注')},
                        {field: 'moneys', title: __('收款合计'), merge:['purchase_code'],totalRow:true},
                        {field: 'write_off', title: __('本次核销金额'), merge:['purchase_code'],width:120,totalRow:true},
                        {field: 'discount', title: __('整单折扣'), merge:['purchase_code']},
                        {field: 'advance', title: __('本次预收款'), merge:['purchase_code']},
                        {field: 'staff_member', title: __('收款人'), merge:['purchase_code']},
                        {field: 'examine', title: __('审核人'), merge:['purchase_code'],hide:hideStatus},
                        {field: 'preparation', title: __('制单人'), merge:['purchase_code']},
                        {field: 'remarks', title: __('备注'), merge:['purchase_code']},
                        {field: 'print_num', title: __('打印次数'), merge:['purchase_code']},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemo', width:150, merge:['purchase_code']}
                    ]],
                    done: function(res, curr, count){
                        tableMerge.render(this);
                        var obj = this.elem.next();
                        obj.find('.layui-table-total td[data-field="moneys"] .layui-table-cell').text(res.moneys);
                        obj.find('.layui-table-total td[data-field="write_off"] .layui-table-cell').text(res.write_off);
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
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'add':
                            Fast.api.open("kuerp/collection/add", "添加", {
                                callback:function(value){
                                    Controller.api.bindevent();
                                },
                                area:['80%','75%']
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
                                    Fast.api.ajax("kuerp/collection/del?ids="+ids, function () {
                                        $('.btn-refresh').click();
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                            break;
                        case 'examine':
                            Fast.api.ajax("kuerp/collection/examine?examine=1&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'examine_f':
                            Fast.api.ajax("kuerp/collection/examine?examine=2&ids="+ids, function () {
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
                                    url: "kuerp/collection/del",
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
                        Fast.api.open("kuerp/collection/edit?ids="+data.id, "编辑",{ area:['80%','75%']});
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
                    var staff_member_id = $('#staff_member_id').val();
                    if(staff_member_id!=''){
                        search.staff_member_id = staff_member_id;
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
                var edit = 1;
                layui.use(['table','form'], function(){
                    var supplyer_div = xmSelect.render({
                        el: '#customers_ids',
                        filterable: true,
                        paging: true,
                        tips: '请选择客户',
                        pageSize: 5,
                        // remoteSearch: true,
                        pageRemote: true,
                        remoteMethod: function (val, cb, show, pageIndex) {
                            $.ajax({
                                url: "kuerp/customers/index",
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
                                        row['name'] = v.customer_name;
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
                                $('#customers_id').val('');
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
                                    $('#customers_id').val(arr[0]['value']);

                                    var set = Config.set;
                                    var price_decimal = parseInt(set.price_decimal.value);
                                    if(isNaN(price_decimal)){
                                        price_decimal = 1;
                                    }

                                    var customers_id = arr[0]['value'];
                                    $.ajax( {
                                        url: "kuerp/goods_sales/getSuppliers",
                                        type: "POST",
                                        data:{
                                            'customers_id':customers_id
                                        },
                                        success: function(res){
                                            $('#c-arrears').val((res).toFixed(price_decimal));
                                        }
                                    });
                                }
                            }
                            changeInfo()
                            return Array.from(new Set(arr));
                        },
                    });

                    var kuerp_customers = Config.kuerp_customers;
                    if(kuerp_customers!=undefined){
                        var newArr = [];
                        newArr.push({name: kuerp_customers.customer_name, value: kuerp_customers.id,});
                        supplyer_div.setValue(newArr);
                        edit = 2;
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

                //客户变动
                $(document).on("change", "#customers_id", function(){
                    var set = Config.set;
                    var price_decimal = parseInt(set.price_decimal.value);
                    if(isNaN(price_decimal)){
                        price_decimal = 1;
                    }

                    var customers_id = $('#customers_id').val();
                    $.ajax( {
                        url: "kuerp/goods_sales/getSuppliers",
                        type: "POST",
                        data:{
                            'customers_id':customers_id
                        },
                        success: function(res){
                            $('#c-arrears').val((res).toFixed(price_decimal));
                        }
                    });
                });

                //选择源单
                var key=1;
                $(document).on("click", "#chose", function(){
                    var customers_id = $('#customers_id').val();
                    if(customers_id==''){
                        Toastr.error('请先选择客户！');return;
                    }
                    var lawtable = document.getElementById("sale_table");
                    var rows = lawtable.rows;//获取所有行
                    var add = true;
                    for(var k=0;k<rows.length;k++){
                        if(k>1 && rows[k].cells[1].childNodes[0].value==''){
                            add = false;
                            var obj = rows[k];
                            break;
                        }
                    }
                    if(add){
                        var obj = rows[rows.length-2];
                    }

                    var htmlp = rows[1].innerHTML;
                    var html = '';
                    parent.Fast.api.open("kuerp/writeoff/chosereceivable?customers_id="+customers_id, "选择源单",{area:['80%','75%'],callback:function(goodsData){
                            if(goodsData!=undefined){
                                $(goodsData).each(function (k,v) {
                                    var thisobj = obj;
                                    if(add){
                                        html = '<tr class="goods_id'+v.purchase_type_id+'">'+htmlp+'</tr>';
                                        $(obj).after(html);
                                        thisobj = $('.goods_id'+v.purchase_type_id);
                                        thisobj.find('.purchase_number').val(v.purchase_number);
                                        thisobj.find('.business_type').val(v.business_type);
                                        thisobj.find('.times').val(v.times);
                                        thisobj.find('.sale_money').val(v.sale_money);
                                        thisobj.find('.write_off').val(v.write_off);
                                        thisobj.find('.write_off_money').val(v.write_off_price);
                                        thisobj.find('.write_off_price').val(v.write_off_price);
                                        thisobj.find('.sale_remark').val(v.remark);
                                        thisobj.find('.purchase_type').val(v.purchase_type_id);
                                        thisobj.find('.purchase_numbers').val(v.purchase_numbers);
                                        key++;
                                    }else{
                                        thisobj.cells[1].childNodes[0].value=v.purchase_number;
                                        thisobj.cells[2].childNodes[0].value=v.business_type;
                                        thisobj.cells[3].childNodes[0].value=v.times;
                                        thisobj.cells[4].childNodes[0].value=v.sale_money;
                                        thisobj.cells[5].childNodes[0].value=v.write_off;
                                        thisobj.cells[6].childNodes[0].value=v.write_off_price;
                                        thisobj.cells[7].childNodes[0].value=v.write_off_price;
                                        thisobj.cells[8].childNodes[0].value=v.remark;
                                        thisobj.cells[9].childNodes[0].value=v.purchase_type_id;
                                        thisobj.cells[10].childNodes[0].value=v.purchase_numbers;
                                        add = true;
                                    }
                                });
                                changeSaleInfo();
                            }
                        }});
                });
                //核销金额变动
                $(document).on("change", ".write_off_price", function(){
                    changeSaleInfo()
                });
                //账户金额变动
                $(document).on("change", ".account_money", function(){
                    edit = 2;
                    changeAccountInfo()
                });
                //整单折扣
                $(document).on("change", "#c-discount", function(){
                    changeInfo()
                });

                //合计信息变动
                function changeInfo() {
                    var set = Config.set;
                    var price_decimal = parseInt(set.price_decimal.value);
                    if(isNaN(price_decimal)){
                        price_decimal = 1;
                    }

                    var sale_write_price = parseFloat($('.write_off_price_hj').val());
                    if(isNaN(sale_write_price)){
                        sale_write_price = 0;
                    }
                    var discount = parseFloat($('.discount').val());
                    if(isNaN(discount)){
                        discount = 0;
                    }
                    sale_write_price = sale_write_price-discount;
                    var number = parseFloat($('.number_hj').val());
                    if(isNaN(number)){
                        number = 0;
                    }

                    if(edit==1){
                        $("#account_table tr:nth-child(3) td:nth-child(3)").find('input').val(sale_write_price);
                        $('.number_hj').val(sale_write_price);
                        number = sale_write_price;
                    }

                    number = number-sale_write_price;
                    if(isNaN(number)){
                        number = 0;
                    }
                    number = number.toFixed(price_decimal);
                    $('.advance').val(number);
                }
                //账户信息变动
                function changeAccountInfo(){
                    var set = Config.set;
                    var price_decimal = parseInt(set.price_decimal.value);
                    if(isNaN(price_decimal)){
                        price_decimal = 1;
                    }

                    var lawtable = document.getElementById("account_table");
                    var rows = lawtable.rows;//获取所有行
                    var account_money = 0;
                    for(var k=0;k<rows.length;k++){
                        if(k>1 && rows[k].cells[2].childNodes[0].value!='' ){
                            if(rows[k].cells[2].childNodes[0].value!=undefined ){
                                rowaccount_money = parseFloat(rows[k].cells[2].childNodes[0].value);
                                if(!isNaN(rowaccount_money)){
                                    account_money = account_money-(-rowaccount_money);
                                }
                            }
                        }
                    }
                    account_money = account_money.toFixed(price_decimal);
                    $('.number_hj').val(account_money);

                    changeInfo()
                }
                //源单信息变动
                function changeSaleInfo(){
                    var set = Config.set;
                    var price_decimal = parseInt(set.price_decimal.value);
                    if(isNaN(price_decimal)){
                        price_decimal = 1;
                    }

                    var lawtable = document.getElementById("sale_table");
                    var rows = lawtable.rows;//获取所有行
                    var sale_money = 0;
                    var sale_write_off = 0;
                    var sale_write_off_money = 0;
                    var sale_write_price = 0;
                    for(var k=0;k<rows.length;k++){
                        if(k>1 && rows[k].cells[1].childNodes[0].value!='' ){
                            if(rows[k].cells[1].childNodes[0].value!=undefined ){
                                rowsalemoney = parseFloat(rows[k].cells[4].childNodes[0].value);
                                if(!isNaN(rowsalemoney)){
                                    sale_money = sale_money-(-rowsalemoney);
                                }

                                rowwrite_off = parseFloat(rows[k].cells[5].childNodes[0].value);
                                if(!isNaN(rowwrite_off)){
                                    sale_write_off = sale_write_off-(-rowwrite_off);
                                }

                                rowwrite_offmoney = parseFloat(rows[k].cells[6].childNodes[0].value);
                                if(!isNaN(rowwrite_offmoney)){
                                    sale_write_off_money = sale_write_off_money-(-rowwrite_offmoney);
                                }

                                rowwrite_pricemoney = parseFloat(rows[k].cells[7].childNodes[0].value);
                                if(!isNaN(rowwrite_pricemoney)){
                                    sale_write_price = sale_write_price-(-rowwrite_pricemoney);
                                }
                            }
                        }
                    }
                    sale_money = sale_money.toFixed(price_decimal);
                    sale_write_off = sale_write_off.toFixed(price_decimal);
                    sale_write_off_money = sale_write_off_money.toFixed(price_decimal);
                    sale_write_price = sale_write_price.toFixed(price_decimal);
                    $('.sale_money_hj').val(sale_money);
                    $('.write_off_hj').val(sale_write_off);
                    $('.write_off_money_hj').val(sale_write_off_money);
                    $('.write_off_price_hj').val(sale_write_price);

                    changeInfo()
                }
                // 账户添加行
                $(document).on("click", ".ac_add", function(){
                    var lawtable = document.getElementById("account_table");
                    var rows = lawtable.rows;//获取所有行
                    var html = rows[1].innerHTML;
                    html = '<tr>'+html+'</tr>';
                    var obj = $(this).parents('tr');
                    $(obj).after(html);
                });
                // 账户删除行
                $(document).on("click", ".ac_del", function(){
                    var lawtable = document.getElementById("account_table");
                    var rows = lawtable.rows;//获取所有行
                    if(rows.length>4){
                        $(this).parents('tr').remove();
                    }else{
                        Toastr.error('至少保留一条数据！');
                    }
                    changeAccountInfo()
                });
                // 单据添加行
                $(document).on("click", ".add", function(){
                    var lawtable = document.getElementById("sale_table");
                    var rows = lawtable.rows;//获取所有行
                    var html = rows[1].innerHTML;
                    html = '<tr>'+html+'</tr>';
                    var obj = $(this).parents('tr');
                    $(obj).after(html);
                });
                // 单据删除行
                $(document).on("click", ".del", function(){
                    var lawtable = document.getElementById("sale_table");
                    var rows = lawtable.rows;//获取所有行
                    if(rows.length>4){
                        $(this).parents('tr').remove();
                    }else{
                        Toastr.error('至少保留一条数据！');
                    }
                    changeSaleInfo()
                });

                //确定
                $(document).on("click", "#surebtn", function(){
                    var number_hj = $('#number_hj').val();
                    number_hj = parseFloat(number_hj);
                    if(isNaN(number_hj)){
                        number_hj = 0;
                    }
                    var write_off_price = $('#write_off_price_hj').val();
                    write_off_price = parseFloat(write_off_price);
                    if(isNaN(write_off_price)){
                        write_off_price = 0;
                    }
                    if(number_hj<write_off_price){
                        var msg = '付款金额小于本次折扣后核销金额，是否仍要修改?';
                        layer.confirm(msg, {
                            btn: ['确定','取消']
                        }, function(){
                            $('#surebtns').click();
                        });
                    }else{
                        $('#surebtns').click();
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