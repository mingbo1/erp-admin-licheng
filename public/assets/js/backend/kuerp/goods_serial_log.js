define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                var serialList = Config.serialList;
                var serial = xmSelect.render({
                    el: '#serial',
                    language: 'zn',
                    tips: '序列号',
                    paging: true,
                    pageSize: 5,
                    filterable: true,
                    data: serialList
                });

                var goodsList = Config.goodsList;
                var goods = xmSelect.render({
                    el: '#goods',
                    language: 'zn',
                    tips: '商品',
                    paging: true,
                    pageSize: 5,
                    filterable: true,
                    data: goodsList
                });

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_serial_log/index'
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
                        {field: 'goods_code', title: __('商品编号')},
                        {field: 'goods_name', title: __('商品名称')},
                        {field: 'goods_spec', title: __('规格型号')},
                        {field: 'serial', title: __('Serial'),},
                        {field: 'store_name', title: __('仓库')},
                        {field: 'createtime', title: __('日期'),templet: function (d) {
                                if(d.createtime!=null){
                                    return layui.util.toDateString(d.createtime*1000,"yyyy-MM-dd");
                                }else{
                                    return '';
                                }
                            },width:120},
                        {field: 'business_type', title: __('Business_type'),templet: function (d) {
                                if(d.business_type==1){
                                    return __('Business_type 1');
                                }
                                if(d.business_type==2){
                                    return __('Business_type 2');
                                }
                                if(d.business_type==3){
                                    return __('Business_type 3');
                                }
                                if(d.business_type==4){
                                    return __('Business_type 4');
                                }
                                if(d.business_type==5){
                                    return __('Business_type 5');
                                }
                                if(d.business_type==6){
                                    return __('Business_type 6');
                                }
                                if(d.business_type==7){
                                    return __('Business_type 7');
                                }
                                if(d.business_type==8){
                                    return __('Business_type 8');
                                }
                                if(d.business_type==9){
                                    return __('Business_type 9');
                                }
                                if(d.business_type==10){
                                    return __('Business_type 10');
                                }
                                if(d.business_type==11 || d.business_type==12){
                                    return __('组装单');
                                }
                                if(d.business_type==13 || d.business_type==14){
                                    return __('拆卸单');
                                }
                            }},
                        {field: 'customer_id', title: __('客户/供应商')},
                        {field: 'purchase_code', title: __('Purchase_code'),width:150},
                        {field: 'store_status', title: __('Store_status'),templet: function (d) {
                                if(d.store_status===''){
                                    return '';
                                }else if(d.store_status==0){
                                    return __('Store_status 0');
                                }else if(d.store_status==1){
                                    return __('Store_status 1');
                                }else if(d.store_status==2){
                                    return __('Store_status 2');
                                }
                            }},
                        {field: 'remarks', title: __('Remarks')},
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                        if(res.code==1){
                            Fast.api.ajax("kuerp/goods_serial_log/import?file="+res.data.url, "",'',true);
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
                        url: "kuerp/goods_serial_log/multi",
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
                            Fast.api.open("kuerp/goods_serial_log/add", "添加", {
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
                            Fast.api.ajax("kuerp/goods_serial_log/del?ids="+ids, "",'',true);
                            break;
                    }
                });

                //监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'del'){
                        $.ajax( {
                            url: "kuerp/goods_serial_log/del",
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
                        Fast.api.open("kuerp/goods_serial_log/edit?ids="+data.id, "编辑");
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    var serialArr = serial.getValue();
                    var serial_id = '';
                    if(serialArr.length>0){
                        $(serialArr).each(function (k,v) {
                            if(serial_id===''){
                                serial_id += v.value;
                            }else{
                                serial_id += ','+v.value;
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
                    if(goods_id!=''){
                        search.goods_id = goods_id;
                    }
                    if(serial_id!=''){
                        search.serial = serial_id;
                    }
                    var createtime = $('#createtime').val();
                    if(createtime!=''){
                        search.purchase_time = createtime;
                    }
                    table.reload('table', {
                        where: {
                            filter:JSON.stringify(search),
                            op:JSON.stringify({'goods_id':'IN','serial':'IN','purchase_time':'RANGE'}),
                        }
                        ,page: {
                            curr: 1
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