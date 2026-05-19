define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_serial/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows //解析数据列表
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,page: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'admin_id', title: __('Admin_id')},
                        {field: 'goods_id', title: __('Goods_id')},
                        {field: 'goods_code', title: __('Goods_code'), operate: 'LIKE'},
                        {field: 'stores_id', title: __('Stores_id')},
                        {field: 'attribute_id', title: __('Attribute_id'), operate: 'LIKE'},
                        {field: 'serial', title: __('Serial'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'),templet: function (d) {
                            if(d.status==1){
                                    return '<input type="checkbox" data-id="'+d.id+'" checked="" name="open" lay-skin="switch" lay-filter="switchList" lay-text="开启|关闭">'
                                }else{
                                    return '<input type="checkbox" data-id="'+d.id+'" name="open" lay-skin="switch" lay-filter="switchList" lay-text="开启|关闭">'
                                }
                            }},
                        {field: 'store_status', title: __('Store_status'),templet: function (d) {
                            if(d.store_status==0){
                                return __('Store_status 0');
                            }
                            if(d.store_status==1){
                                return __('Store_status 1');
                            }
                            if(d.store_status==2){
                                return __('Store_status 2');
                            }
                        }},
                        {field: 'remark', title: __('Remark'), operate: 'LIKE'},
                        {field: 'purchase_id', title: __('Purchase_id')},
                        {field: 'purchase_code', title: __('Purchase_code'), operate: 'LIKE'},
                        {field: 'purchase_exchange_id', title: __('Purchase_exchange_id')},
                        {field: 'purchase_exchange_code', title: __('Purchase_exchange_code'), operate: 'LIKE'},
                        {field: 'sale_id', title: __('Sale_id')},
                        {field: 'sale_code', title: __('Sale_code'), operate: 'LIKE'},
                        {field: 'sale_exchange_id', title: __('Sale_exchange_id')},
                        {field: 'sale_exchange_code', title: __('Sale_exchange_code'), operate: 'LIKE'},
                        {field: 'createtime', title: __('Createtime'),templet: function (d) {
                                return layui.util.toDateString(d.createtime*1000);
                            }},
                        {field: 'updatetime', title: __('Updatetime'),templet: function (d) {
                                return layui.util.toDateString(d.updatetime*1000);
                            }},
                        {field: 'accounts.account_name', title: __('Accounts.account_name'), operate: 'LIKE'},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemo', width:150}
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/goods_serial/import?file="+res.data.url, "",'',true);
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
                        url: "kuerp/goods_serial/multi",
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
                            Fast.api.open("kuerp/goods_serial/add", "添加", {
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
                            Fast.api.ajax("kuerp/goods_serial/del?ids="+ids, function () {
                                reloadTable()
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
                    if(obj.event === 'del'){
                        $.ajax( {
                            url: "kuerp/goods_serial/del",
                            type: "POST",
                            data:{'ids':data.id},
                            success: function(res){
                                if(res.code==1){
                                    Toastr.success('操作成功');
                                    setTimeout(function () {
                                        reloadTable();
                                    },1500)
                                }else{
                                    Toastr.error(res.msg);
                                }
                            }
                        });
                    } else if(obj.event === 'edit'){
                        Fast.api.open("kuerp/goods_serial/edit?ids="+data.id, "编辑");
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