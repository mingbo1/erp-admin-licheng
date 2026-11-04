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
                        //仓库/商品联动：选仓库后，商品下拉只保留该仓库下有库存记录的商品
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
                        url: 'kuerp/goods_stock/getgoods',
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
                var rowspanstr = Config.rowspan;
                var jsObject = JSON.parse(rowspanstr);    //转换为json对象

                var rowspanstr2 = Config.rowlist;
                var jsObjects = JSON.parse(rowspanstr2);    //转换为json对象

                var store_str = [jsObject,jsObjects];
                //表格固定高度：表头常驻，横向滚动条固定在可视区底部，页面不再整页滚动。
                //'full-xx' 会在窗口缩放时自动重算高度；xx = 表格顶部距页面顶端的高度 + 表格下方内边距(15+15+2)
                var tableGap = Math.ceil($('#table').offset().top) + 32;
                table.render({
                    elem: '#table'
                    ,height: 'full-' + tableGap
                    ,url:'kuerp/goods_stock/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //数据总条数（分页用）
                            "data": res.rows //当前页数据
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,page: true //分页加载：合计已改到顶部统计，翻页只请求当前页数据
                    ,limit: 20
                    ,limits: [20, 50, 100]
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: store_str
                });

                //当前搜索条件（顶部统计与列表共用，翻页时不变）
                var currentFilter = {};
                //加载顶部合计统计：只在页面加载/刷新/搜索时调用，翻页不调用
                var loadTotal = function () {
                    $.ajax({
                        url: 'kuerp/goods_stock/total',
                        type: 'GET',
                        dataType: 'json',
                        data: {filter: JSON.stringify(currentFilter)},
                        success: function (res) {
                            if (res.code != 1) {
                                Toastr.error(res.msg || '统计加载失败');
                                return;
                            }
                            var data = res.data || {};
                            $('#total_goods').text(data.goods_count);
                            $('#total_stock').text(data.stock);
                            $('#total_cost').text(data.cost_money);
                            $('#total_unit_price').text(data.unit_price);
                            var storeHtml = '';
                            $(data.stores || []).each(function (k, v) {
                                storeHtml += '<span class="stock-total-store">' + v.name + '：库存 <b>' + v.stock +
                                    '</b> / 成本 <b>' + v.cost_money + '</b></span>';
                            });
                            $('#total_stores').html(storeHtml);
                        },
                        error: function () {
                            Toastr.error('统计加载失败');
                        }
                    });
                };
                //按当前搜索条件重新加载列表（回到第一页）
                var reloadTable = function () {
                    table.reload('table', {
                        page: {curr: 1},
                        where: {
                            filter: JSON.stringify(currentFilter),
                            op: JSON.stringify({'goods_id': 'IN', 'stores_id': 'IN'})
                        }
                    });
                };
                loadTotal();

                //窗口尺寸变化时重算表格高度（兜底，layui 对 'full-' 高度本身也会自适应）
                $(window).on('resize', function () {
                    table.resize('table');
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/goods_stock/import?file="+res.data.url, "",'',true);
                         }else{
                             Toastr.error(res.msg);
                         }
                    }
                    ,accept: 'file' //允许上传的文件类型
                    ,size: 50 //最大允许上传的文件大小
                });

                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    var checkStatus = table.checkStatus(obj.config.id);
                    switch(obj.event){
                        case 'refresh':
                            reloadTable();
                            loadTotal();
                            break;
                        case 'add':
                            Fast.api.open("kuerp/goods_stock/add", "添加", {
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
                            Fast.api.ajax("kuerp/goods_stock/del?ids="+ids, "",'',true);
                        break;
                    }
                });

                //监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'del'){
                        $.ajax( {
                            url: "kuerp/goods_stock/del",
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
                        Fast.api.open("kuerp/goods_stock/edit?ids="+data.id, "编辑");
                    }
                });
                //搜索事件
                    $('#search').on('click', function () {
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
                        //搜索条件同时作用于列表与顶部统计（统计按条件全量算，只在搜索时更新）
                        currentFilter = search;
                        reloadTable();
                        loadTotal();
                     });
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
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});