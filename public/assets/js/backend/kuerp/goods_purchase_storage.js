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

                var number_decimal = parseInt(set.number_decimal.value);
                if(isNaN(number_decimal)){
                    number_decimal = 1;
                }

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_purchase_storage/index'
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
                        {checkbox: true},
                        {field: 'purchase_time', title: __('Purchase_time'),templet: function (d) {
                                return layui.util.toDateString(d.purchase_time*1000,"yyyy-MM-dd");
                            },width:110},
                        {field: 'purchase_code', title: __('Purchase_code'),width:150, operate: 'LIKE'},
                        {field: 'supplyer_code', title: __('Supplyer_code'),templet: function (d) {
                                if(d.suppliers){
                                    return d.suppliers.supplyer_code+' '+d.suppliers.supplyer_name;
                                }
                                return d.supplyer_code || '';
                            }},
                        {field: 'purchase_order_id', title: __('Purchase_order_id'), operate: 'LIKE'},
                        {field: 'returns_order_id', title: __('Returns_order_id'), operate: 'LIKE'},
                        {field: 'number', title: __('Number'),templet: function (d) {
                                return (parseFloat(d.number) || 0).toFixed(number_decimal);
                            }},
                        {field: 'preparation_id', title: __('Preparation_id'),templet: function (d) {
                                if(d.preparation){
                                    return d.preparation.username;
                                }
                                return '';
                            }},
                        {field: 'examine_id', title: __('Examine_id'),templet: function (d) {
                                if(d.examine && d.examine.username!=null){
                                    return d.examine.username;
                                }else{
                                    return '';
                                }
                            },hide:hideStatus},
                        {field: 'examine_status', title: __('Examine_status'),templet: function (d) {
                            if(d.examine_status==0){
                                return '<span style="color:red">'+__('Examine_status 0')+'</span>';
                            }
                            return '<span style="color:green">'+__('Examine_status 1')+'</span>';
                        },hide:hideStatus},
                        {field: 'check_status', title: __('Check_status'),templet: function (d) {
                            if(d.check_status==0){
                                return __('Check_status 0');
                            }
                            if(d.check_status==1){
                                return __('Check_status 1');
                            }
                        }},
                        {field: 'note', title: __('Note')},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemoStorage', width:180}
                    ]]
                });

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
                        case 'examine':
                            Fast.api.ajax("kuerp/goods_purchase_storage/examine?examine=1&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'examine_f':
                            Fast.api.ajax("kuerp/goods_purchase_storage/examine?examine=2&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'counter':
                            Fast.api.ajax("kuerp/goods_purchase_storage/counter?counter=1&ids="+ids, function () {
                                $('.btn-refresh').click();
                            },'',true);
                            break;
                        case 'counter_f':
                            Fast.api.ajax("kuerp/goods_purchase_storage/counter?counter=2&ids="+ids, function () {
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
                    if(obj.event === 'detail'){
                        // 查看详情 - 不显示金额
                        Fast.api.open('kuerp/goods_purchase_storage/detail/ids/'+data.id, __('Storage_detail'), {
                            area: ['90%', '75%'],
                        });
                    }else if(obj.event === 'print'){
                        // 打印入库单 - 不显示金额
                        Fast.api.open('kuerp/goods_purchase_storage/printstorage/ids/'+data.id, __('Storage_print'), {
                            area: ['90%', '75%'],
                        });
                    }else if(obj.event === 'storage'){
                        // 入库 - 打开入库页面，可输入每次入库数量
                        Fast.api.open('kuerp/goods_purchase_storage/storage/ids/'+data.id, __('Storage_in'), {
                            area: ['90%', '75%'],
                            callback: function(){
                                // 入库成功后刷新列表
                                table.reload('table');
                            }
                        });
                    }
                });

                // 搜索
                $("#search").on("click", function () {
                    reloadTable();
                });

                // 获取搜索参数
                function getSearch() {
                    var obj = {};
                    $(".layui-search [name]").each(function () {
                        var value = $(this).val();
                        if (value != '') {
                            obj[$(this).attr("name")] = value;
                        }
                    });
                    return obj;
                }

                function getOp() {
                    var obj = {};
                    $(".layui-search [name]").each(function () {
                        var value = $(this).val();
                        if (value != '') {
                            var name = $(this).attr("name");
                            if (name == 'purchase_time') {
                                obj[name] = 'RANGE';
                            } else {
                                obj[name] = 'LIKE';
                            }
                        }
                    });
                    return obj;
                }
            });
        },
        add: function () { Controller.api.bindevent(); },
        edit: function () { Controller.api.bindevent(false); },
        api: {
            bindevent: function () {
                // 采购单入库主要用于查看/审核/对单，无需表单绑定逻辑
            }
        }
    };
    return Controller;
});
