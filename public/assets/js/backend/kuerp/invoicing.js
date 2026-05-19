define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/invoicing/index'
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
                        {field: 'suppliers_code', title: __('Suppliers_code'), operate: 'LIKE'},
                        {field: 'suppliers.supplyer_name', title: __('Suppliers.supplyer_name'),templet: function (d) {
                                return d.suppliers.supplyer_name;
                            }},
                        {field: 'bill_codes', title: __('Bill_codes')},
                        {field: 'money', title: __('Money')},
                        {field: 'tax', title: __('Tax')},
                        {field: 'real_money', title: __('Real_money')},
                        {field: 'status', title: __('Status'),templet: function (d) {
                                if(d.status==0){
                                    return __('Status 0');
                                }
                                if(d.status==1){
                                    return __('Status 1');
                                }
                                if(d.status==2){
                                    return __('Status 2');
                                }
                                if(d.status==3){
                                    return __('Status 3');
                                }
                                if(d.status==4){
                                    return __('Status 4');
                                }
                                if(d.status==5){
                                    return __('Status 5');
                                }
                            }},
                        {field: 'times', title: __('开票日期'),templet: function (d) {
                                return layui.util.toDateString(d.times*1000,'yyyy-MM-dd');
                            }},
                        {field: 'pay_money', title: __('开票金额')},
                        {field: 'invoice', title: __('发票号')},
                        {field: 'invoice_header', title: __('发票抬头')},
                        {fixed: false, title:__('Operate'), toolbar: '#delDemo', width:100}
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
                            Fast.api.open("kuerp/invoicing/add", "开票", {
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
                                    Fast.api.ajax("kuerp/invoicing/del?ids="+ids, function () {
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
                                    url: "kuerp/invoicing/del",
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
                        Fast.api.open("kuerp/invoicing/edit?ids="+data.id, "编辑");
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
                    var suppliers_code = $('#suppliers_code').val();
                    if(suppliers_code!=''){
                        search.suppliers_code = suppliers_code;
                    }
                    var bill_codes = $('#bill_codes').val();
                    if(bill_codes!=''){
                        search.bill_codes = bill_codes;
                    }
                    var bill_time = $('#bill_time').val();
                    if(bill_time!=''){
                        search.bill_time = bill_time;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.bill_codes = 'LIKE';
                    op.suppliers_code = 'LIKE';
                    op.bill_time = 'RANGE';
                    return op;
                }
            });
            Controller.api.bindevent();
        },
        
        add: function () {
            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#invoicingTable'
                    ,url:'kuerp/invoicing/list'
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
                    ,defaultToolbar:[]
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true},
                        {field: 'bill_time', title: __('单据日期'),templet: function (d) {
                                return layui.util.toDateString(d.bill_time*1000,'yyyy-MM-dd');
                            }},
                        {field: 'bill_codes', title: __('单据编号'), width: '160'},
                        {field: 'suppliers.supplyer_name', title: __('供应商'),templet: function (d) {
                                return d.suppliers.supplyer_name;
                            }, width: '160'},
                        {field: 'money', title: __('金额')},
                        {field: 'tax', title: __('税额')},
                        {field: 'status', title: __('付款状态'),templet: function (d) {
                                if(d.status==0){
                                    return __('Status 0');
                                }
                                if(d.status==1){
                                    return __('Status 1');
                                }
                                if(d.status==2){
                                    return __('Status 2');
                                }
                                if(d.status==3){
                                    return __('Status 3');
                                }
                                if(d.status==4){
                                    return __('Status 4');
                                }
                                if(d.status==5){
                                    return __('Status 5');
                                }
                            }},
                        {field: 'real_money', title: __('价税合计')},
                        {field: 'pay_money', title: __('已开票金额')},
                        {field: 'give_money', title: __('未开票金额')},
                        {field: 'invoicing', title: __('本次开票金额'),edit:'number', width: '140'},
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
                            if(ids==''){
                                Toastr.error('请选择需要开票的数据');return false;
                            }
                            var invoice = $('#c-invoice').val();
                            if(invoice==''){
                                Toastr.error('请输入发票号');return false;
                            }
                            var invoice_header = $('#c-invoice_header').val();
                            if(invoice_header==''){
                                Toastr.error('请输入发票抬头');return false;
                            }
                            var arr = new Array();
                            $(data).each(function (k,v) {
                                if(v.invoicing==''){
                                    Toastr.error('请输入开票金额');return false;
                                }
                                var row = new Array();
                                row['id'] = v.id;
                                row['invoicing'] = v.invoicing;
                                arr.push(row);
                            });

                            $.ajax( {
                                url: "kuerp/invoicing/add",
                                type: "POST",
                                data:{'row':arrayToJsonString(arr),'invoice':invoice,'invoice_header':invoice_header},
                                success: function(res){
                                    if(res.code==0){
                                        Toastr.error(res.msg);
                                    }else{
                                        Toastr.success('操作成功');
                                        setTimeout(function () {
                                            parent.location.reload();
                                        },1000)
                                    }
                                }
                            });
                            break;
                    }
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

                //搜索事件
                $('#search').on('click', function () {
                    table.reload('invoicingTable', {
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
                    var suppliers_code = $('#suppliers_code').val();
                    if(suppliers_code!=''){
                        search.suppliers_code = suppliers_code;
                    }
                    var bill_codes = $('#bill_codes').val();
                    if(bill_codes!=''){
                        search.bill_codes = bill_codes;
                    }
                    var bill_time = $('#bill_time').val();
                    if(bill_time!=''){
                        search.bill_time = bill_time;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.bill_codes = 'LIKE';
                    op.suppliers_code = 'LIKE';
                    op.bill_time = 'RANGE';
                    return op;
                }
            });
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                layui.use('laydate', function(){
                    var laydate = layui.laydate;
                    //执行一个laydate实例
                    
                    laydate.render({
                        elem: '#c-bill_time',
                        type: 'datetime'
                    });
                    laydate.render({
                        elem: '#c-purchase_time',
                    });
                    laydate.render({
                         elem: '#bill_time',
                         type: 'datetime',
                         range: true
                    });
                });
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});