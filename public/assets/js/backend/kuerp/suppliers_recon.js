define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/suppliers_recon/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows, //解析数据列表
                            "all_sale_money": res.all_sale_money,
                            "all_receivable": res.all_receivable,
                            "all_cost": res.all_cost,
                            "all_real_money": res.all_real_money,
                            "all_balance": res.all_balance,
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,totalRow: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true},
                        {field: 'purchase_time', title: __('Purchase_time'),width: 130},
                        {field: 'purchase_code', title: __('Purchase_code'),width: 160,'totalRow':'合计'},
                        {field: 'business_type', title: __('Business_type'),templet: function (d) {
                            if(d.business_type==1){
                                return __('Business_type 1');
                            }else if(d.business_type==2){
                                return __('Business_type 2');
                            }else if(d.business_type==3){
                                return __('Business_type 3');
                            }else if(d.business_type==4){
                                return __('Business_type 4');
                            }else if(d.business_type==5){
                                return __('Business_type 5');
                            }else if(d.business_type==6){
                                return __('Business_type 6');
                            }else if(d.business_type==7){
                                return __('Business_type 7');
                            }else if(d.business_type==8){
                                return __('Business_type 8');
                            }else if(d.business_type==9){
                                return __('Business_type 9');
                            }else{
                                return '';
                            }
                        }},
                        {field: 'sale_money', title: __('销售金额'),totalRow:true},
                        {field: 'receivable', title: __('应付金额'),totalRow:true},
                        {field: 'amount', title: __('优惠金额'),width:140},
                        {field: 'real_money', title: __('实际付款金额'),width:140,totalRow:true},
                        {field: 'balance', title: __('应付款余额'),totalRow:true},
                        {field: 'remark', title: __('Remark')},
                    ]],
                    done: function(res, curr, count){
                        var obj = this.elem.next();
                        obj.find('.layui-table-total td[data-field="sale_money"] .layui-table-cell').text(res.all_sale_money);
                        obj.find('.layui-table-total td[data-field="receivable"] .layui-table-cell').text(res.all_receivable);
                        obj.find('.layui-table-total td[data-field="cost"] .layui-table-cell').text(res.all_cost);
                        obj.find('.layui-table-total td[data-field="real_money"] .layui-table-cell').text(res.all_real_money);
                        obj.find('.layui-table-total td[data-field="balance"] .layui-table-cell').text(res.all_balance);
                    }
                });

                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
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
                    });
                });
                function getSearch() {
                    var search = {};
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
                    laydate.render({
                         elem: '#purchase_time',
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