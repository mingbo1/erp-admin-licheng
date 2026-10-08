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
                    data: storesList
                });

                var goodsList = Config.goodsList;
                var goods = xmSelect.render({
                    el: '#goods',
                    language: 'zn',
                    tips: '商品',
                    data: goodsList
                });
                var rowspanstr = Config.rowspan;
                var jsObject = JSON.parse(rowspanstr);    //转换为json对象

                var rowspanstr2 = Config.rowlist;
                var jsObjects = JSON.parse(rowspanstr2);    //转换为json对象

                var store_str = [jsObject,jsObjects];
                //表格固定高度：表头/合计行常驻，横向滚动条固定在可视区底部，页面不再整页滚动。
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
                            "count": res.total, //解析数据长度
                            "data": res.rows, //解析数据列表
                            "countArr": res.countArr //解析数据列表
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,totalRow: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: store_str,
                    done: function(res, curr, count){
                        var countArr = res.countArr;
                        var obj = this.elem.next();
                        $(countArr).each(function (k,v) {
                            obj.find('.layui-table-total td[data-field="unit_price'+v.id+'"] .layui-table-cell').text(v.unit_price_count);
                        })
                    }
                });

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
                            table.reload('table', []);
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
                        var createtime = $('#createtime').val();
                        if(createtime!=''){
                            search.createtime = createtime;
                        }
                        table.reload('table', {
                            where: {
                                filter:JSON.stringify(search),
                                op:JSON.stringify({'goods_id':'IN','stores_id':'IN','createtime':'BETWEEN'}),
                            }
                        });
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
                layui.use('laydate', function(){
                    var laydate = layui.laydate;
                    laydate.render({
                        elem: '#createtime',
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