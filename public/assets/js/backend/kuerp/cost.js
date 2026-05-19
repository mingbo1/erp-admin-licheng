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

                table.render({
                    elem: '#table'
                    ,url:'kuerp/cost/index'
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
                        {checkbox: true, merge:['purchase_code']},
                        {field: 'purchase_code', title: __('单据编号'), merge:true,width:160},
                        {field: 'suppliers', title: __('供应商'), merge:['purchase_code']},
                        {field: 'business_type', title: __('Business_type'),templet: function (d) {
                                if(d.business_type==1){
                                    return __('Business_type 1');
                                }else if(d.business_type==2){
                                    return __('Business_type 2');
                                }else if(d.business_type==3){
                                    return __('Business_type 3');
                                }else if(d.business_type==4){
                                    return __('Business_type 4');
                                }
                            }, merge:['purchase_code']},
                        {field: 'money', title: __('金额'), merge:['purchase_code']},
                        {field: 'pay_money', title: __('未付金额'), merge:['purchase_code']},
                        {field: 'purchase_number', title: __('源单号'), merge:['purchase_code'],width:160},
                        {field: 'suppliers_name', title: __('源单往来单位'), merge:['purchase_code'],width:160},
                        {field: 'expenditure', title: __('其他支出单号'),width:160},
                        {field: 'purchase_times', title: __('源单日期'), merge:['purchase_code'],width:140},
                        {field: 'remark', title: __('备注'), merge:['purchase_code']},
                        {field: 'pay_status', title: __('付款状态'),templet: function (d) {
                                if(d.pay_status==0){
                                    return __('Pay_status 0');
                                }else if(d.pay_status==1){
                                    return __('Pay_status 1');
                                }else if(d.pay_status==2){
                                    return __('Pay_status 2');
                                }
                            }, merge:['purchase_code']},
                    ]]
                });
                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'examine':
                            var data = checkStatus.data;
                            var ids = '';
                            var sup = '';
                            var status = true;
                            $(data).each(function (k,v) {
                                ids+=v.id+',';
                                if(sup=='' && v.suppliers_id!=''){
                                    sup = v.suppliers_id
                                }else if(sup!='' && v.suppliers_id!='' && sup!=v.suppliers_id){
                                    status = false;
                                }
                            });
                            if(ids==''){
                                Toastr.error('请先选择单据！');return
                            }
                            if(status){
                                Fast.api.open("kuerp/expenditure/add?ids="+ids, "其他支出单");
                            }else{
                                Toastr.error('请选择相同供应商！');
                            }
                            break;
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
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
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

                $(document).on("click", ".surebtn", function(){
                    $.ajax( {
                        url: "kuerp/cost/assemble",
                        type: "POST",
                        data:$('#add-form').serialize(),
                        success: function(res){
                            console.log(res);
                        }
                    });
                });

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
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});