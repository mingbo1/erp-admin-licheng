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
                    ,url:'kuerp/writeoff/index'
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
                        {field: 'purchase_code', title: __('Purchase_code'), width: 150},
                        {field: 'purchase_times', title: __('Purchase_time'),width:130},
                        {field: 'business_type', title: __('Business_type'),templet: function (d) {
                                if(d.business_type==1){
                                    return __('Business_type 1');
                                }
                                if(d.business_type==2){
                                    return __('Business_type 2');
                                }
                                if(d.business_type==3){
                                    return __('Business_type 3');
                                }
                                if(d.business_type==4){
                                    return __('Business_type 4');
                                }
                                if(d.business_type==5){
                                    return __('Business_type 5');
                                }
                            }},
                        {field: 'customers_id', title: __('客户名称')},
                        {field: 'suppliers_id', title: __('供应商名称')},
                        {field: 'write_off', title: __('Write_off'), operate:'BETWEEN'},
                        {field: 'remarks', title: __('Remarks'), operate: 'LIKE'},
                        {field: 'preparation_id', title: __('Preparation_id')},
                        {field: 'examine_id', title: __('Examine_id'),hide:hideStatus},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemo', width:150}
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                        if(res.code==1){
                            Fast.api.ajax("kuerp/writeoff/import?file="+res.data.url, "",'',true);
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
                        url: "kuerp/writeoff/multi",
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
                            Fast.api.open("kuerp/writeoff/add", "添加", {
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
                                    Fast.api.ajax("kuerp/writeoff/del?ids="+ids, function () {
                                        $('.btn-refresh').click();
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                            break;
                        case 'examine':
                            Fast.api.ajax("kuerp/writeoff/examine?examine=1&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'examine_f':
                            Fast.api.ajax("kuerp/writeoff/examine?examine=2&ids="+ids, function () {
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
                                    url: "kuerp/writeoff/del",
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
                        Fast.api.open("kuerp/writeoff/edit?ids="+data.id, "编辑",{area:['80%','75%']});
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
                    var business_type = $('#business_type').val();
                    if(business_type!=''){
                        search.business_type = business_type;
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
        choselist: function () {
            layui.use(['table','upload'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#choseTable'
                    ,url:'kuerp/writeoff/choselist?customers_id='+Config.customers_id
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                        };
                    }
                    ,toolbar: '#btnToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true},
                        {field: 'purchase_code', title: __('源单编号'),width:150},
                        {field: 'purchase_type', title: __('业务类别')},
                        {field: 'purchase_time', title: __('单据日期'),width:130},
                        {field: 'money_tax_price', title: __('单据金额')},
                        {field: 'pay_money', title: __('已核销金额')},
                        {field: 'money', title: __('未核销金额')},
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
                                arr['business_type'] = v.purchase_type;
                                arr['times'] = v.purchase_time;
                                arr['sale_money'] = v.money_tax_price;
                                arr['write_off'] = v.pay_money;
                                arr['write_off_price'] = v.money;
                                arr['purchase_type_id'] = v.purchase_type_id;
                                arr['purchase_numbers'] = v.purchase_codes;
                                arr['remark'] = v.remark;
                                arr['type'] = v.type;
                                ids.push(arr)
                            });
                            Fast.api.close(ids);//这里是重点，将这个ids 传至父页面
                            break;
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    var search = {};
                    var purchase_code = $('#purchase_code').val();
                    if(purchase_code!=''){
                        search.purchase_code = purchase_code;
                    }
                    var purchase_time = $('#purchase_time').val();
                    if(purchase_time!=''){
                        search.purchase_time = purchase_time;
                    }
                    table.reload('choseTable', {
                        where: {
                            filter:JSON.stringify(search),
                            op:JSON.stringify({'purchase_code':'LIKE','purchase_time':'BETWEEN'}),
                        }
                        ,page: {
                            curr: 1
                        }
                    });
                });
                //头工具栏事件
                $(document).on("click", "#cancel", function(){
                    Fast.api.close();
                });
            });
            Controller.api.bindevent();
        },
        choseadvancelist: function () {
            layui.use(['table','upload'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#choseTable'
                    ,url:'kuerp/writeoff/choseadvancelist?suppliers_id='+Config.suppliers_id
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                        };
                    }
                    ,toolbar: '#btnToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true},
                        {field: 'purchase_code', title: __('源单编号'),width:150},
                        {field: 'purchase_type', title: __('业务类别')},
                        {field: 'purchase_time', title: __('单据日期'),width:130},
                        {field: 'money_tax_price', title: __('单据金额')},
                        {field: 'pay_money', title: __('已核销金额')},
                        {field: 'money', title: __('未核销金额')},
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
                                arr['business_type'] = v.purchase_type;
                                arr['times'] = v.purchase_time;
                                arr['sale_money'] = v.money_tax_price;
                                arr['write_off'] = v.pay_money;
                                arr['write_off_price'] = v.money;
                                arr['purchase_type_id'] = v.purchase_type_id;
                                arr['purchase_numbers'] = v.purchase_codes;
                                arr['remark'] = v.remark;
                                arr['type'] = v.type;
                                ids.push(arr)
                            });
                            Fast.api.close(ids);//这里是重点，将这个ids 传至父页面
                            break;
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    var search = {};
                    var purchase_code = $('#purchase_code').val();
                    if(purchase_code!=''){
                        search.purchase_code = purchase_code;
                    }
                    var purchase_time = $('#purchase_time').val();
                    if(purchase_time!=''){
                        search.purchase_time = purchase_time;
                    }
                    table.reload('choseTable', {
                        where: {
                            filter:JSON.stringify(search),
                            op:JSON.stringify({'purchase_code':'LIKE','purchase_time':'BETWEEN'}),
                        }
                        ,page: {
                            curr: 1
                        }
                    });
                });
                //头工具栏事件
                $(document).on("click", "#cancel", function(){
                    Fast.api.close();
                });
            });
            Controller.api.bindevent();
        },
        chosereceivable: function () {
            layui.use(['table','upload'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#choseTable'
                    ,url:'kuerp/writeoff/chosereceivable?customers_id='+Config.customers_id
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                        };
                    }
                    ,toolbar: '#btnToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true},
                        {field: 'purchase_code', title: __('源单编号'),width:150},
                        {field: 'purchase_type', title: __('业务类别')},
                        {field: 'purchase_time', title: __('单据日期'),width:130},
                        {field: 'money_tax_price', title: __('单据金额')},
                        {field: 'pay_money', title: __('已核销金额')},
                        {field: 'money', title: __('未核销金额')},
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
                                arr['purchase_numbers'] = v.purchase_codes;
                                arr['remark'] = v.remark;
                                arr['type'] = v.type;
                                ids.push(arr)
                            });
                            Fast.api.close(ids);//这里是重点，将这个ids 传至父页面
                            break;
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    var search = {};
                    var purchase_code = $('#purchase_code').val();
                    if(purchase_code!=''){
                        search.purchase_code = purchase_code;
                    }
                    var purchase_time = $('#purchase_time').val();
                    if(purchase_time!=''){
                        search.purchase_time = purchase_time;
                    }
                    table.reload('choseTable', {
                        where: {
                            filter:JSON.stringify(search),
                            op:JSON.stringify({'purchase_code':'LIKE','purchase_time':'BETWEEN'}),
                        }
                        ,page: {
                            curr: 1
                        }
                    });
                });
                //头工具栏事件
                $(document).on("click", "#cancel", function(){
                    Fast.api.close();
                });
            });
            Controller.api.bindevent();
        },
        chosecopewith: function () {
            layui.use(['table','upload'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#choseTable'
                    ,url:'kuerp/writeoff/chosecopewith?suppliers_id='+Config.suppliers_id
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                        };
                    }
                    ,toolbar: '#btnToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true},
                        {field: 'purchase_code', title: __('源单编号'),width:150},
                        {field: 'purchase_type', title: __('业务类别')},
                        {field: 'purchase_time', title: __('单据日期'),width:130},
                        {field: 'money_tax_price', title: __('单据金额')},
                        {field: 'pay_money', title: __('已核销金额')},
                        {field: 'money', title: __('未核销金额')},
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
                                arr['business_type'] = v.purchase_type;
                                arr['purchase_type_id'] = v.purchase_type_id;
                                arr['times'] = v.purchase_time;
                                arr['sale_money'] = v.money_tax_price;
                                arr['write_off'] = v.pay_money;
                                arr['write_off_price'] = v.money;
                                arr['purchase_numbers'] = v.purchase_codes;
                                arr['remark'] = v.remark;
                                arr['type'] = v.type;
                                ids.push(arr)
                            });
                            Fast.api.close(ids);//这里是重点，将这个ids 传至父页面
                            break;
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    var search = {};
                    var purchase_code = $('#purchase_code').val();
                    if(purchase_code!=''){
                        search.purchase_code = purchase_code;
                    }
                    var purchase_time = $('#purchase_time').val();
                    if(purchase_time!=''){
                        search.purchase_time = purchase_time;
                    }
                    table.reload('choseTable', {
                        where: {
                            filter:JSON.stringify(search),
                            op:JSON.stringify({'purchase_code':'LIKE','purchase_time':'BETWEEN'}),
                        }
                        ,page: {
                            curr: 1
                        }
                    });
                });
                //头工具栏事件
                $(document).on("click", "#cancel", function(){
                    Fast.api.close();
                });
            });
            Controller.api.bindevent();
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

                //类型变动
                $(document).on("change", "#c-business_type", function(){
                    var business_type = $('#c-business_type').val();
                    business_type = parseInt(business_type);
                    $('.chose_btn').hide();
                    $('.chose_u').hide();
                    switch (business_type) {
                        case 1:
                            $('.chose_customers_id').show();
                            $('.choses').show();
                            $('#chose_advance').show();
                            $('#choses_receivable').show();
                            break;
                        case 2:
                            $('.chose_suppliers_id').show();
                            $('.choses').show();
                            $('#chose_pay').show();
                            $('#choses_cope').show();
                            break;
                        case 3:
                            $('.chose_customers_id').show();
                            $('.chose_suppliers_id').show();
                            $('.choses').show();
                            $('#chose_receivable').show();
                            $('#choses_cope').show();
                            break;
                        case 4:
                            $('.chose_customers_out_id').show();
                            $('.chose_customers_enter_id').show();
                            $('.choses').hide();
                            $('#chose_receivable').show();
                            break;
                        case 5:
                            $('.chose_suppliers_out_id').show();
                            $('.chose_suppliers_enter_id').show();
                            $('.choses').hide();
                            $('#chose_cope').show();
                            break;
                    }

                    var lawtable = document.getElementById('account_table');
                    var rows = lawtable.rows;//获取所有行
                    for(var k=0;k<rows.length;k++){
                        if(k>1 && k<=rows.length-2){
                            var thisobj = rows[k];
                            for(var a=1;a<9;a++){
                                thisobj.cells[a].childNodes[0].value='';
                            }
                        }
                    }
                    changeInfo('account_table');

                    var lawtable = document.getElementById('sale_table');
                    var rows = lawtable.rows;//获取所有行
                    for(var k=0;k<rows.length;k++){
                        if(k>1 && k<=rows.length-2){
                            var thisobj = rows[k];
                            for(var a=1;a<9;a++){
                                thisobj.cells[a].childNodes[0].value='';
                            }
                        }
                    }
                    changeInfo('sale_table');
                });

                //选择预收单据
                var key=1;
                $(document).on("click", ".chose_advance", function(){
                    key=1;
                    var table_id = $(this).data('id');
                    var customers_id = $('#customers_id').val();
                    if(customers_id==''){
                        Toastr.error('请先选择客户！');return;
                    }
                    var lawtable = document.getElementById(table_id);
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
                    parent.Fast.api.open("kuerp/writeoff/choselist?customers_id="+customers_id, "选择预收单据",{area:['80%','75%'],callback:function(goodsData){
                            if(goodsData!=undefined){
                                $(goodsData).each(function (k,v) {
                                    var thisobj = obj;
                                    if(add){
                                        html = '<tr class="keys'+v.purchase_type_id+'">'+htmlp+'</tr>';
                                        $(obj).after(html);
                                        thisobj = $('.keys'+v.purchase_type_id);
                                        thisobj.find('.purchase_number').val(v.purchase_number);
                                        thisobj.find('.business_type').val(v.business_type);
                                        thisobj.find('.times').val(v.times);
                                        thisobj.find('.sale_money').val(v.sale_money);
                                        thisobj.find('.write_off').val(v.write_off);
                                        thisobj.find('.write_off_money').val(v.write_off_price);
                                        thisobj.find('.write_off_price').val(v.write_off_price);
                                        thisobj.find('.purchase_type').val(v.purchase_type_id);
                                        thisobj.find('.purchase_numbers').val(v.purchase_numbers);
                                    }else{
                                        thisobj.cells[1].childNodes[0].value=v.purchase_number;
                                        thisobj.cells[2].childNodes[0].value=v.business_type;
                                        thisobj.cells[3].childNodes[0].value=v.times;
                                        thisobj.cells[4].childNodes[0].value=v.sale_money;
                                        thisobj.cells[5].childNodes[0].value=v.write_off;
                                        thisobj.cells[6].childNodes[0].value=v.write_off_price;
                                        thisobj.cells[7].childNodes[0].value=v.write_off_price;
                                        thisobj.cells[8].childNodes[0].value=v.purchase_type_id;
                                        thisobj.cells[9].childNodes[0].value=v.purchase_numbers;
                                        add = true
                                    }
                                });
                                changeInfo(table_id)
                            }
                        }});
                });

                //选择预付单据
                $(document).on("click", ".chose_pay", function(){
                    key=1;
                    var table_id = $(this).data('id');
                    var suppliers_id = $('#suppliers_id').val();
                    if(suppliers_id==''){
                        Toastr.error('请先选择供应商！');return;
                    }
                    var lawtable = document.getElementById(table_id);
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
                    parent.Fast.api.open("kuerp/writeoff/choseadvancelist?suppliers_id="+suppliers_id, "选择预付单据",{area:['80%','75%'],callback:function(goodsData){
                            if(goodsData!=undefined){
                                $(goodsData).each(function (k,v) {
                                    var thisobj = obj;
                                    if(add){
                                        html = '<tr class="keys'+v.purchase_type_id+'">'+htmlp+'</tr>';
                                        $(obj).after(html);
                                        thisobj = $('.keys'+v.purchase_type_id);
                                        thisobj.find('.purchase_number').val(v.purchase_number);
                                        thisobj.find('.business_type').val(v.business_type);
                                        thisobj.find('.times').val(v.times);
                                        thisobj.find('.sale_money').val(v.sale_money);
                                        thisobj.find('.write_off').val(v.write_off);
                                        thisobj.find('.write_off_money').val(v.write_off_price);
                                        thisobj.find('.write_off_price').val(v.write_off_price);
                                        thisobj.find('.purchase_type').val(v.purchase_type_id);
                                        thisobj.find('.purchase_numbers').val(v.purchase_numbers);
                                    }else{
                                        thisobj.cells[1].childNodes[0].value=v.purchase_number;
                                        thisobj.cells[2].childNodes[0].value=v.business_type;
                                        thisobj.cells[3].childNodes[0].value=v.times;
                                        thisobj.cells[4].childNodes[0].value=v.sale_money;
                                        thisobj.cells[5].childNodes[0].value=v.write_off;
                                        thisobj.cells[6].childNodes[0].value=v.write_off_price;
                                        thisobj.cells[7].childNodes[0].value=v.write_off_price;
                                        thisobj.cells[8].childNodes[0].value=v.purchase_type_id;
                                        thisobj.cells[9].childNodes[0].value=v.purchase_numbers;
                                        add = true
                                    }
                                });
                                changeInfo(table_id)
                            }
                        }});
                });

                //选择应收单据
                $(document).on("click", ".chose_receivable", function(){
                    key=1;
                    var table_id = $(this).data('id');
                    var business_type = $('#c-business_type').val();
                    if(business_type=='4'){
                        var customers_id = $('#customers_out_id').val();
                        if(customers_id==''){
                            Toastr.error('请先选择转出客户！');return;
                        }
                    }else{
                        var customers_id = $('#customers_id').val();
                        if(customers_id==''){
                            Toastr.error('请先选择客户！');return;
                        }
                    }
                    var lawtable = document.getElementById(table_id);
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
                    parent.Fast.api.open("kuerp/writeoff/chosereceivable?customers_id="+customers_id, "选择应收单据",{area:['80%','75%'],callback:function(goodsData){
                            if(goodsData!=undefined){
                                $(goodsData).each(function (k,v) {
                                    var thisobj = obj;
                                    if(add){
                                        html = '<tr class="keys'+v.purchase_type_id+'">'+htmlp+'</tr>';
                                        $(obj).after(html);
                                        thisobj = $('.keys'+v.purchase_type_id);
                                        thisobj.find('.purchase_number').val(v.purchase_number);
                                        thisobj.find('.business_type').val(v.business_type);
                                        thisobj.find('.times').val(v.times);
                                        thisobj.find('.sale_money').val(v.sale_money);
                                        thisobj.find('.write_off').val(v.write_off);
                                        thisobj.find('.write_off_money').val(v.write_off_price);
                                        thisobj.find('.write_off_price').val(v.write_off_price);
                                        thisobj.find('.purchase_type').val(v.purchase_type_id);
                                        thisobj.find('.purchase_numbers').val(v.purchase_numbers);
                                    }else{
                                        thisobj.cells[1].childNodes[0].value=v.purchase_number;
                                        thisobj.cells[2].childNodes[0].value=v.business_type;
                                        thisobj.cells[3].childNodes[0].value=v.times;
                                        thisobj.cells[4].childNodes[0].value=v.sale_money;
                                        thisobj.cells[5].childNodes[0].value=v.write_off;
                                        thisobj.cells[6].childNodes[0].value=v.write_off_price;
                                        thisobj.cells[7].childNodes[0].value=v.write_off_price;
                                        thisobj.cells[8].childNodes[0].value=v.purchase_type_id;
                                        thisobj.cells[9].childNodes[0].value=v.purchase_numbers;
                                        add = true
                                    }
                                });
                                changeInfo(table_id)
                            }
                        }});
                });

                //选择应付单据
                $(document).on("click", ".chose_cope", function(){
                    key=1;
                    var table_id = $(this).data('id');
                    var business_type = $('#c-business_type').val();
                    if(business_type=='5'){
                        var suppliers_id = $('#suppliers_out_id').val();
                        if(suppliers_id==''){
                            Toastr.error('请先选择转出供应商！');return;
                        }
                    }else{
                        var suppliers_id = $('#suppliers_id').val();
                        if(suppliers_id==''){
                            Toastr.error('请先选择供应商！');return;
                        }
                    }
                    var lawtable = document.getElementById(table_id);
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
                    parent.Fast.api.open("kuerp/writeoff/chosecopewith?suppliers_id="+suppliers_id, "选择应付单据",{area:['80%','75%'],callback:function(goodsData){
                            if(goodsData!=undefined){
                                $(goodsData).each(function (k,v) {
                                    var thisobj = obj;
                                    if(add){
                                        html = '<tr class="keys'+v.purchase_type_id+'">'+htmlp+'</tr>';
                                        $(obj).after(html);
                                        thisobj = $('.keys'+v.purchase_type_id);
                                        thisobj.find('.purchase_number').val(v.purchase_number);
                                        thisobj.find('.business_type').val(v.business_type);
                                        thisobj.find('.times').val(v.times);
                                        thisobj.find('.sale_money').val(v.sale_money);
                                        thisobj.find('.write_off').val(v.write_off);
                                        thisobj.find('.write_off_money').val(v.write_off_price);
                                        thisobj.find('.write_off_price').val(v.write_off_price);
                                        thisobj.find('.purchase_type').val(v.purchase_type_id);
                                        thisobj.find('.purchase_numbers').val(v.purchase_number);
                                    }else{
                                        thisobj.cells[1].childNodes[0].value=v.purchase_number;
                                        thisobj.cells[2].childNodes[0].value=v.business_type;
                                        thisobj.cells[3].childNodes[0].value=v.times;
                                        thisobj.cells[4].childNodes[0].value=v.sale_money;
                                        thisobj.cells[5].childNodes[0].value=v.write_off;
                                        thisobj.cells[6].childNodes[0].value=v.write_off_price;
                                        thisobj.cells[7].childNodes[0].value=v.write_off_price;
                                        thisobj.cells[8].childNodes[0].value=v.purchase_type_id;
                                        thisobj.cells[9].childNodes[0].value=v.purchase_numbers;
                                        add = true
                                    }
                                });
                                changeInfo(table_id)
                            }
                        }});
                });

                //核销金额变动
                $(document).on("change", ".write_off_price", function(){
                    var objp = $(this).parents('table')[0].id;
                    changeInfo(objp)
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
                    $('#'+table).find('.sale_money_hj').val(sale_money);
                    $('#'+table).find('.write_off_hj').val(sale_write_off);
                    $('#'+table).find('.write_off_money_hj').val(sale_write_off_money);
                    $('#'+table).find('.write_off_price_hj').val(sale_write_price);
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