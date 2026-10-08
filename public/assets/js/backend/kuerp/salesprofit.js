define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/salesprofit/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows, //解析数据列表
                            "profit_rate": res.profit_rate_all,
                            "net_profit_rate": res.net_profit_rate_all
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,page: true
                    ,totalRow: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {field: 'purchase_time', title: __('Purchase_time'),templet: function (d) {
                                return layui.util.toDateString(d.purchase_time*1000,"yyyy-MM-dd");
                            },'totalRow':'合计',width:140},
                        {field: 'customers_id', title: __('Customers_id'),templet: function (d) {
                                if(d.customers.customer_name!=null){
                                    return d.customers.customer_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'sale_user', title: __('Sale_user'),templet: function (d) {
                                if(d.staffmember.name!=null){
                                    return d.staffmember.name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'preparation_id', title: __('Preparation_id'),templet: function (d) {
                                if(d.preparation.nickname!=null){
                                    return d.preparation.nickname;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'purchase_code', title: __('Purchase_code'), width: 160},
                        {field: 'purchase_type', title: __('Purchase_type'),templet: function (d) {
                                if(d.purchase_type==1){
                                    return __('Purchase_type 1');
                                }
                                if(d.purchase_type==2){
                                    return __('Purchase_type 2');
                                }
                            }},
                        {field: 'number', title: __('Number'),totalRow:true},
                        {field: 'amount', title: __('Amount'),totalRow:true},
                        {field: 'all_money', title: __('All_money'),totalRow:true},
                        {field: 'tax_money', title: __('Tax_money'),totalRow:true},
                        {field: 'money_tax_price', title: __('Money_tax_price'),totalRow:true},
                        {field: 'sales_cost', title: __('Sales_cost'),totalRow:true},
                        {field: 'profit', title: __('销售毛利'),totalRow:true},
                        {field: 'profit_rate', title: __('毛利率%')},
                        {field: 'preferential_money', title: __('Preferential_money'),totalRow:true},
                        {field: 'purchase_money', title: __('Purchase_money'),totalRow:true},

                        {field: 'net_profit', title: __('销售净利润'),totalRow:true},
                        {field: 'net_profit_rate', title: __('净利率%')},

                        {field: 'real_money', title: __('Real_money'),totalRow:true},
                        {field: 'customers_money', title: __('Customers_money'),width:150,totalRow:true},
                        {field: 'need_money', title: __('Need_money'),totalRow:true},
                        {field: 'pay_money', title: __('Pay_money'),totalRow:true},
                        {field: 'remark', title: __('备注')},
                    ]],
                    done: function(res, curr, count){
                        var obj = this.elem.next();
                        obj.find('.layui-table-total td[data-field="profit_rate"] .layui-table-cell').text(res.profit_rate);
                        obj.find('.layui-table-total td[data-field="net_profit_rate"] .layui-table-cell').text(res.net_profit_rate);
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
                    var sale_user = $('#sale_user').val();
                    if(sale_user!=''){
                        search.sale_user = sale_user;
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
        api: {
            bindevent: function () {
                layui.use('laydate', function(){
                    var laydate = layui.laydate;
                    //执行一个laydate实例
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