define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                var storesList = Config.storesList;
                var stores = xmSelect.render({
                    el: '#stores',
                    language: 'zn',
                    tips: '仓库',
                    filterable: true,
                    searchTips: '输入关键字搜索',
                    data: storesList,
                    on: function (data) {
                        //仓库/商品联动：选仓库后，商品下拉只保留该仓库下有收发记录的商品
                        var stores_id = [];
                        $(data.arr).each(function (k, v) {
                            stores_id.push(v.value);
                        });
                        loadGoods(stores_id.join(','));
                    }
                });

                var goodsList = Config.goodsList;
                var goods = xmSelect.render({
                    el: '#goods',
                    language: 'zn',
                    tips: '商品',
                    filterable: true,
                    searchTips: '输入关键字搜索',
                    data: goodsList
                });

                //按仓库重新加载商品下拉（stores_id 为空=全部仓库，即全部商品）
                var loadGoods = function (stores_id) {
                    $.ajax({
                        url: 'kuerp/goods_log_summary/getgoods',
                        type: 'GET',
                        dataType: 'json',
                        data: {stores_id: stores_id},
                        success: function (res) {
                            if (res.code == 1) {
                                goods.setValue([]);
                                goods.update({data: res.data || []});
                            } else {
                                Toastr.error(res.msg || '商品加载失败');
                            }
                        },
                        error: function () {
                            Toastr.error('商品加载失败');
                        }
                    });
                };

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_log_summary/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows, //解析数据列表
                            "totalRowData": res.total_row //合计行（服务端按全部数据算，非当前页）
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,page: true
                    ,limit: 20 //默认每页 20 条
                    ,totalRow: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {field: 'type_name', title: __('商品类别'), rowspan: 2,'totalRow':'合计'},
                        {field: 'goods_code', title: __('商品编号'), rowspan: 2},
                        {field: 'goods_name', title: __('商品名称'), rowspan: 2},
                        {field: 'goods_barcode', title: __('商品条码'), rowspan: 2},
                        {field: 'goods_spec', title: '型号', rowspan: 2},
                        {field: 'unit_name', title: __('单位'), rowspan: 2},
                        {field: 'store_code', title: __('仓库编号'), rowspan: 2},
                        {field: 'store_name', title: __('仓库'), rowspan: 2},

                        //本页给仓库人员使用，成本/金额类字段一律不展示（服务端也不再下发 *_cost）
                        {field: 'period', title: __('期初')},
                        {field: 'allocation_e', title: __('调拨入库')},
                        {field: 'buy_o', title: __('普通采购')},
                        {field: 'sale_e', title: __('销售退回')},
                        {field: 'enter', title: __('入库合计')},
                        {field: 'allocation_o', title: __('调拨出库')},
                        {field: 'sale_o', title: __('普通销售')},
                        {field: 'buy_e', title: __('采购退回')},
                        {field: 'out', title: __('出库合计')},
                        {field: 'balance', title: __('结存')},
                    ],[
                        {field: 'period_basics', title: __('数量'),totalRow:true},
                        {field: 'allocation_e_basics', title: __('数量'),totalRow:true},
                        {field: 'buy_o_basics', title: __('数量'),totalRow:true},
                        {field: 'sale_e_basics', title: __('数量'),totalRow:true},
                        {field: 'enter_basics', title: __('数量'),totalRow:true},

                        {field: 'allocation_o_basics', title: __('数量'),totalRow:true},
                        {field: 'sale_o_basics', title: __('数量'),totalRow:true},
                        {field: 'buy_e_basics', title: __('数量'),totalRow:true},
                        {field: 'out_basics', title: __('数量'),totalRow:true},
                        {field: 'balance_basics', title: __('数量'),totalRow:true},
                    ]],
                    done: function(res, curr, count){
                        //合计行用服务端返回的「全部数据」合计覆盖 layui 默认的「当前页」合计
                        var obj = this.elem.next();
                        var totalRow = res.totalRowData || {};
                        for (var field in totalRow) {
                            obj.find('.layui-table-total td[data-field="' + field + '"] .layui-table-cell').text(totalRow[field]);
                        }
                    }
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/goods_log_summary/import?file="+res.data.url, "",'',true);
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
                        url: "kuerp/goods_log_summary/multi",
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
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'add':
                            Fast.api.open("kuerp/goods_log_summary/add", "添加", {
                               callback:function(value){
                                  Controller.api.bindevent();
                               }
                            });
                        break;
                        case 'delAll':
                            var data = checkStatus.data;
                            var ids = '';
                            $(data).each(function (k,v) {
                                ids+=v.id+',';
                            });
                            Fast.api.ajax("kuerp/goods_log_summary/del?ids="+ids, function () {
                                reloadTable()
                            },'',true);
                        break;
                    }
                });
                function reloadTable(){
                    table.reload('table', {
                        //重新查询要回到第 1 页，否则筛选后页数变少会停在空页上
                        page: {curr: 1},
                        where: {
                            filter:JSON.stringify(getSearch()),
                            op:JSON.stringify(getOp()),
                            // 型号规格是商品档案字段，本表没有该列，单独传参
                            goods_specification_id: $.trim($('#goods_specification_id').val()) || ''
                        }
                    });
                }
                //监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'del'){
                        $.ajax( {
                            url: "kuerp/goods_log_summary/del",
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
                    } else if(obj.event === 'edit'){
                        Fast.api.open("kuerp/goods_log_summary/edit?ids="+data.id, "编辑");
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    table.reload('table', {
                        page: {curr: 1},
                        where: {
                            filter:JSON.stringify(getSearch()),
                            op:JSON.stringify(getOp()),
                            // 型号规格是商品档案字段，本表没有该列，单独传参
                            goods_specification_id: $.trim($('#goods_specification_id').val()) || ''
                        }
                    });
                });
                function getSearch() {
                    var storesArr = stores.getValue();
                    var stores_id = '';
                    if(storesArr.length>0){
                        $(storesArr).each(function (k,v) {
                            if(stores_id===''){
                                stores_id += v.value;
                            }else{
                                stores_id += ','+v.value;
                            }
                        })
                    }
                    var goodsArr = goods.getValue();
                    var goods_id = '';
                    if(goodsArr.length>0){
                        $(goodsArr).each(function (k,v) {
                            if(goods_id===''){
                                goods_id += v.value;
                            }else{
                                goods_id += ','+v.value;
                            }
                        })
                    }
                    var search = {};
                    if(stores_id!=''){
                        search.stores_id = stores_id;
                    }
                    if(goods_id!=''){
                        search.goods_id = goods_id;
                    }
                    var purchase_time = $('#purchase_time').val();
                    if(purchase_time!=''){
                        search.purchase_time = purchase_time;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.goods_id = 'IN';
                    op.stores_id = 'IN';
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
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});