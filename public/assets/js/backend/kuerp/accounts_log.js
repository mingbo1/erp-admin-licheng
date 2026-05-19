define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                var accountsLists = Config.accountsLists;
                var accounts = xmSelect.render({
                    el: '#accountsLists',
                    language: 'zn',
                    tips: '账户',
                    data: accountsLists
                });

                var suppliersLists = Config.suppliersLists;
                var suppliers = xmSelect.render({
                    el: '#suppliersLists',
                    language: 'zn',
                    tips: '供应商',
                    data: suppliersLists
                });

                var customersLists = Config.customersLists;
                var customers = xmSelect.render({
                    el: '#customersLists',
                    language: 'zn',
                    tips: '客户',
                    data: customersLists
                });

                var saleLists = Config.saleLists;
                var payment = xmSelect.render({
                    el: '#payment',
                    language: 'zn',
                    tips: '收/付款人',
                    data: saleLists
                });

                table.render({
                    elem: '#table'
                    ,url:'kuerp/accounts_log/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows, //解析数据列表
                            "all_balance": res.all_balance,
                            "all_receivable": res.all_receivable,
                            "all_advance": res.all_advance,
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,totalRow: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true},
                        {field: 'account_code', title: __('账户编号')},
                        {field: 'account_name', title: __('账户名称')},
                        {field: 'pay_time', title: __('日期'), width: 130},
                        {field: 'bill_code', title: __('单据编号'), width:160},
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
                            }else if(d.business_type==10){
                                return __('Business_type 10');
                            }else if(d.business_type==11){
                                return __('Business_type 11');
                            }else if(d.business_type==12){
                                return __('Business_type 12');
                            }else{
                                return d.business_type;
                            }
                        },'totalRow':'合计'},
                        {field: 'income', title: __('Income'),totalRow:true},
                        {field: 'expenditure', title: __('Expenditure'),totalRow:true},
                        {field: 'balance', title: __('账户余额'),totalRow:true},
                        {field: 'correspondent', title: __('往来单位')},
                        {field: 'payment', title: __('Payment')},
                        {field: 'remark', title: __('Remark')},
                    ]],
                    done: function(res, curr, count){
                        var obj = this.elem.next();
                        obj.find('.layui-table-total td[data-field="income"] .layui-table-cell').text(res.all_receivable);
                        obj.find('.layui-table-total td[data-field="expenditure"] .layui-table-cell').text(res.all_advance);
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

                    var accountsArr = accounts.getValue();
                    var accounts_id = '';
                    if(accountsArr.length>0){
                        $(accountsArr).each(function (k,v) {
                            if(accounts_id===''){
                                accounts_id += v.value;
                            }else{
                                accounts_id += ','+v.value;
                            }
                        })
                    }

                    var suppliersArr = suppliers.getValue();
                    var suppliers_id = '';
                    if(suppliersArr.length>0){
                        $(suppliersArr).each(function (k,v) {
                            if(suppliers_id===''){
                                suppliers_id += v.value;
                            }else{
                                suppliers_id += ','+v.value;
                            }
                        })
                    }

                    var customersArr = customers.getValue();
                    var customers_id = '';
                    if(customersArr.length>0){
                        $(customersArr).each(function (k,v) {
                            if(customers_id===''){
                                customers_id += v.value;
                            }else{
                                customers_id += ','+v.value;
                            }
                        })
                    }

                    var paymentArr = payment.getValue();
                    var payment_id = '';
                    if(paymentArr.length>0){
                        $(paymentArr).each(function (k,v) {
                            if(payment_id===''){
                                payment_id += v.value;
                            }else{
                                payment_id += ','+v.value;
                            }
                        })
                    }

                    var pay_time = $('#pay_time').val();
                    if(pay_time!=''){
                        search.pay_time = pay_time;
                    }
                    if(accounts_id!=''){
                        search.accounts_id = accounts_id;
                    }
                    if(suppliers_id!=''){
                        search.suppliers_id = suppliers_id;
                    }
                    if(customers_id!=''){
                        search.customers_id = customers_id;
                    }
                    if(payment_id!=''){
                        search.payment_id = payment_id;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.pay_time = 'RANGE';
                    op.accounts_id = 'IN';
                    op.suppliers_id = 'IN';
                    op.customers_id = 'IN';
                    op.payment = 'IN';
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
                        elem: '#c-pay_time',
                        type: 'datetime'
                    });
                    laydate.render({
                         elem: '#pay_time',
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