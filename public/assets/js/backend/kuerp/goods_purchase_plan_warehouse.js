define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','form'], function(){
                var table = layui.table;

                var set = Config.set;
                var number_decimal = parseInt(set.number_decimal.value);
                if(isNaN(number_decimal)){
                    number_decimal = 1;
                }

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_purchase_plan_warehouse/index'
                    ,parseData: function(res){
                        return {
                            "code": 0,
                            "msg": '',
                            "count": res.total,
                            "data": res.rows
                         };
                    }
                    ,toolbar: '#thisToolbar'
                    ,page: true
                    ,cellMinWidth: 100
                    ,cols: [[
                        {field: 'purchase_time', title: __('Purchase_time'),templet: function (d) {
                                return layui.util.toDateString(d.purchase_time*1000,"yyyy-MM-dd");
                            },width:110},
                        {field: 'purchase_code', title: __('Purchase_code'),width:150},
                        {field: 'supplyer_code', title: __('Supplyer_code'),templet: function (d) {
                                return d.suppliers.supplyer_code+' '+d.suppliers.supplyer_name;
                            }},
                        {field: 'number', title: __('Number'),templet: function (d) {
                                return (d.number).toFixed(number_decimal);
                            }},
                        {field: 'preparation_id', title: __('Preparation_id'),templet: function (d) {
                                return d.preparation.nickname;
                            }},
                        {field: 'storage_status', title: __('Storage_status'),templet: function (d) {
                            if(d.storage_status===''||d.storage_status===null||d.storage_status===undefined){
                                return '<span style="color:#ccc">—</span>';
                            }
                            if(d.storage_status==2){
                                return '<span style="color:#5fb878">'+__('Storage_status 2')+'</span>';
                            }
                            if(d.storage_status==1){
                                return '<span style="color:#ffb800">'+__('Storage_status 1')+'</span>';
                            }
                            return '<span style="color:#999">'+__('Storage_status 0')+'</span>';
                        }},
                        {field: 'note', title: __('Note')},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemoPlanWarehouse', width:100}
                    ]]
                });

                //头工具栏事件（只读页面仅刷新）
                table.on('toolbar(table)', function(obj){
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                    }
                });
                //监听行工具事件（只读页面仅打印）
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'print'){
                        parent.Fast.api.open("kuerp/goods_purchase_plan_warehouse/printpage?ids="+data.id, __('Print'), {
                            area: ['90%', '75%'],
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
                    var supplyer_code = $('#supplyer_code').val();
                    if(supplyer_code!=''){
                        search.supplyer_code = supplyer_code;
                    }
                    var preparation_id = $('#preparation_id').val();
                    if(preparation_id!=''){
                        search.preparation_id = preparation_id;
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
                    laydate.render({
                         elem: '#purchase_time',
                         type: 'datetime',
                         range: true
                    });
                });
            }
        }
    };
    return Controller;
});
