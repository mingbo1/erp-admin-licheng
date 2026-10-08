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
                    ,url:'kuerp/replenishment/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,defaultToolbar:[]
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true, fixed: 'left',},
                        {field: 'goods_name', title: __('商品'),width:140, fixed: 'left'},
                        {field: 'attr_name', title: __('属性'), hide: window.KUERP_AUXILIARY_DISABLED === true},
                        {field: 'unit_name', title: __('单位')},
                        {field: 'number', title: __('销售在订量')},
                        {field: 'stock', title: __('库存余额')},
                        {field: 'buy_number', title: __('采购在订量')},
                        {field: 'min_stock', title: __('最低库存')},
                        {field: 'now_buy_number', title: __('建议采购量'),width:140},
                    ]]
                });

                //窗口尺寸变化时重算表格高度（兜底，layui 对 'full-' 高度本身也会自适应）
                $(window).on('resize', function () {
                    table.resize('table');
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
                                Toastr.error('请先选择单据');return false;
                            }
                            var arr = new Array();
                            var check_now_buy_number = false;
                            $(data).each(function (k,v) {
                                if(v.now_buy_number<=0){
                                    check_now_buy_number = true;
                                }
                                var row = new Array();
                                row['goods_code'] = v.goods_code;
                                row['purchase_code'] = '';
                                row['goods_attr'] = v.attr_id;
                                row['goods_unit'] = v.goods_unit;
                                row['stores_id'] = '';
                                row['now_buy_number'] = v.now_buy_number;
                                row['buy_price'] = v.buy_price;
                                arr.push(row);
                            });
                            if(check_now_buy_number){
                                Toastr.error('建议采购数量需大于0，否则无法生成购货订单！！');return false;
                            }
                            var arrstr = arrayToJsonString(arr);
                            $.ajax( {
                                url: "kuerp/replenishment/setdata",
                                type: "POST",
                                data:{'row':arrstr},
                                success: function(res){
                                    if(res.code==0){
                                        Toastr.error(res.msg);
                                    }else{
                                        Fast.api.open("kuerp/goods_purchase_order/add?type=1", "购货订单",{ area:['90%','75%'],});
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

                //监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'del'){
                        $.ajax( {
                            url: "kuerp/invoicing/del",
                            type: "POST",
                            data:{'ids':data.id},
                            success: function(res){
                                if(res.code==1){
                                    Toastr.success('操作成功');
                                    setTimeout(function () {
                                        parent.location.reload();
                                    },1500)
                                }else{
                                    Toastr.error(res.msg);
                                }
                            }
                        });
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
                    var goods_code = $('#goods_code').val();
                    if(goods_code!=''){
                        search.goods_code = goods_code;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.goods_code = 'LIKE';
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
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});