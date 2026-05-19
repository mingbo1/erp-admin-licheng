define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                var batchList = Config.batchList;
                var batch = xmSelect.render({
                    el: '#batch',
                    language: 'zn',
                    tips: '批次',
                    data: batchList
                });

                var goodsList = Config.goodsList;
                var goods = xmSelect.render({
                    el: '#goods',
                    language: 'zn',
                    tips: '商品',
                    data: goodsList
                });

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_batch_stock/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows, //解析数据列表
                            "stock_all": res.stock_all
                        };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,totalRow: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {field: 'goods_code', title: __('商品编号')},
                        {field: 'goods_name', title: __('商品名称')},
                        {field: 'goods_spec', title: __('规格型号')},
                        {field: 'unit_name', title: __('单位')},
                        {field: 'unitconversion', title: __('副单位')},
                        {field: 'store_name', title: __('仓库'),totalRow:'合计'},
                        {field: 'stock', title: __('数量')},
                        {field: 'more_unit_num', title: __('副单位数')},
                        {field: 'batch_name', title: __('批次')},
                        {field: 'manufacture', title: __('生产日期'),width:120},
                        {field: 'guarantee', title: __('保质期')},
                        {field: 'validity', title: __('有效期至'),width:120},
                        {field: 'times', title: __('剩余天数')},
                    ]],
                    done: function(res, curr, count){
                        this.elem.next().find('.layui-table-total td[data-field="stock"] .layui-table-cell').text(res.stock_all);
                    }
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                        if(res.code==1){
                            Fast.api.ajax("kuerp/goods_batch_stock/import?file="+res.data.url, "",'',true);
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
                        url: "kuerp/goods_batch_stock/multi",
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
                            Fast.api.open("kuerp/goods_batch_stock/add", "添加", {
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
                            Fast.api.ajax("kuerp/goods_batch_stock/del?ids="+ids, function () {
                                reloadTable()
                            },'',true);
                            break;
                    }
                });
                function reloadTable(){
                    table.reload('table', {
                        where: {
                            filter:JSON.stringify(getSearch()),
                            op:JSON.stringify({'goods_id':'IN','batch_id':'IN'}),
                        }
                        ,page: {
                            curr: 1
                        }
                    });
                }
                //监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'del'){
                        $.ajax( {
                            url: "kuerp/goods_batch_stock/del",
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
                        Fast.api.open("kuerp/goods_batch_stock/edit?ids="+data.id, "编辑");
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    table.reload('table', {
                        where: {
                            filter:JSON.stringify(getSearch()),
                            op:JSON.stringify({'goods_id':'IN','batch_id':'IN'}),
                        }
                        ,page: {
                            curr: 1
                        }
                    });
                });
                function getSearch() {
                    var batchArr = batch.getValue();
                    var batch_id = '';
                    if(batchArr.length>0){
                        $(batchArr).each(function (k,v) {
                            if(batch_id===''){
                                batch_id += v.value;
                            }else{
                                batch_id += ','+v.value;
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
                    if(batch_id!=''){
                        search.batch_id = batch_id;
                    }
                    if(goods_id!=''){
                        search.goods_id = goods_id;
                    }
                    return search;
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
                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});