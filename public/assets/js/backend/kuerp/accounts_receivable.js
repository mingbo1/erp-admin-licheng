define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                //表格固定高度：表头/合计行常驻，横向滚动条固定在可视区底部，页面不再整页滚动。
                //'full-xx' 会在窗口缩放时自动重算高度；xx = 表格顶部距页面顶端的高度 + 表格下方内边距(15+15+2)
                var tableGap = Math.ceil($('#table').offset().top) + 32;
                table.render({
                    elem: '#table'
                    ,height: 'full-' + tableGap
                    ,url:'kuerp/accounts_receivable/index'
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
                        {checkbox: true, fixed: 'left'},
                        {field: 'customer_name', title: __('Customers.customer_name'), fixed: 'left'},
                        {field: 'purchase_time', title: __('Purchase_time'),width: 130},
                        {field: 'purchase_code', title: __('Purchase_code'),width: 150,'totalRow':'合计'},
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
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'receivable', title: __('Receivable'),totalRow:true},
                        {field: 'advance', title: __('Advance'),totalRow:true},
                        {field: 'balance', title: __('应收款余额'),totalRow:true},
                        {field: 'staff_member', title: __('Staff_member_id')},
                        {field: 'remark', title: __('Remark')},
                    ]],
                    done: function(res, curr, count){
                        var obj = this.elem.next();
                        obj.find('.layui-table-total td[data-field="receivable"] .layui-table-cell').text(res.all_receivable);
                        obj.find('.layui-table-total td[data-field="advance"] .layui-table-cell').text(res.all_advance);
                        obj.find('.layui-table-total td[data-field="balance"] .layui-table-cell').text(res.all_balance);
                    }
                });

                //窗口尺寸变化时重算表格高度（兜底，layui 对 'full-' 高度本身也会自适应）
                $(window).on('resize', function () {
                    table.resize('table');
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