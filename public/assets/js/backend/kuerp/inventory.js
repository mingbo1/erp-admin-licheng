define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table','upload','form'], function(){
                var table = layui.table;
                upload = layui.upload;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/inventory/index'
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
                        {field: 'purchase_code', title: __('Purchase_code'), operate: 'LIKE'},
                        {field: 'purchase_time', title: __('Purchase_time'),templet: function (d) {
                                return layui.util.toDateString(d.purchase_time*1000);
                            }},
                        {field: 'store_type', title: __('Store_type'),templet: function (d) {
                            if(d.store_type==1){
                                return __('Store_type 1');
                            }
                            if(d.store_type==2){
                                return __('Store_type 2');
                            }
                        }},
                        {field: 'result', title: __('Result'), operate: 'LIKE',templet:function (d) {
                                return d.result;
                            }},
                        {fixed: 'right', title:__('Operate'), toolbar: '#thisBarDemo', width:250}
                    ]]
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/inventory/import?file="+res.data.url, "",'',true);
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
                            Fast.api.open("kuerp/inventory/add", "盘点", {
                               callback:function(value){
                                  Controller.api.bindevent();
                               },
                                area:['90%','75%'],
                            });
                        break;
                        case 'delAll':
                            var data = checkStatus.data;
                            var ids = '';
                            $(data).each(function (k,v) {
                                ids+=v.id+',';
                            });
                            if(data.length<=0){
                                Toastr.error('请选择要删除的数据');return;
                            }
                            Layer.confirm(
                                __('Are you sure you want to delete the %s selected item?', data.length),
                                {icon: 3, title: __('Warning'), shadeClose: true},
                                function (index) {
                                    Fast.api.ajax("kuerp/inventory/del?ids="+ids, function () {
                                        $('.btn-refresh').click();
                                    },'',true);
                                    Layer.close(index);
                                }
                            );
                        break;
                        case 'export':
                            top.location.href="inventory/export";
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
                        var that = this;
                        var top = $(that).offset().top - $(window).scrollTop();
                        var left = $(that).offset().left - $(window).scrollLeft() - 260;
                        if (top + 154 > $(window).height()) {
                            top = top - 154;
                        }
                        if ($(window).width() < 480) {
                            top = left = undefined;
                        }
                        Layer.confirm(
                            __('Are you sure you want to delete this item?'),
                            {icon: 3, title: __('Warning'),offset: [top, left], shadeClose: true},
                            function (index) {
                                $.ajax( {
                                    url: "kuerp/inventory/del",
                                    type: "POST",
                                    data:{'ids':data.id},
                                    success: function(res){
                                        if(res.code==1){
                                            Toastr.success('操作成功');
                                            setTimeout(function () {
                                                $('.btn-refresh').click();
                                            },1500)
                                        }else{
                                            Toastr.error(res.msg);
                                        }
                                    }
                                });
                                Layer.close(index);
                            }
                        );
                    } else if(obj.event === 'edit'){
                        Fast.api.open("kuerp/inventory/edit?ids="+data.id, "编辑",{ area:['90%','75%'],});
                    }else if(obj.event === 'exchange'){
                        $.ajax( {
                            url: "kuerp/Warehousing/checkInventory",
                            type: "POST",
                            data:{
                                'purchase_code':data.purchase_code,
                                'type':1
                            },
                            success: function(res){
                                if(res.code==1){
                                    var data = res.msg;
                                    var html = '';
                                    $(data).each(function (k,v) {
                                        html+='<div class="rk_btn" data-purchase_code="'+v.purchase_code+'" data-status="'+v.status+'" data-type="'+v.type+'">'+v.title+'</div>';
                                    });
                                    layer.open({
                                        title:'盘点数据',
                                        type: 1,
                                        skin: 'layui-layer-rim', //加上边框
                                        area: ['420px', '240px'], //宽高
                                        content: html
                                    });
                                }else{
                                    Toastr.error(res.msg);
                                }
                            }
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
                    var purchase_time = $('#purchase_time').val();
                    if(purchase_time!=''){
                        search.purchase_time = purchase_time;
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
            Controller.api.bindevent(false);
        },
        api: {
            bindevent: function (reloadStatus=true) {

                layui.use(['table','form'], function(){
                    var supplyer_div = xmSelect.render({
                        el: '#stores_ids',
                        filterable: true,
                        tips: '请选择仓库',
                        // remoteSearch: true,
                        pageRemote: true,
                        remoteMethod: function (val, cb, show, pageIndex) {
                            $.ajax({
                                url: "kuerp/stores/index",
                                type: 'GET',
                                crossOrigin: true,
                                dataType: 'json',
                                cache: false,
                                async: true,
                                data: {
                                    keywords: val,
                                    page: pageIndex,
                                    limit: 5
                                },
                                success: function (data) {
                                    var TotalCount = data.total;
                                    var Pages = Math.ceil(TotalCount/5);
                                    var list = [];
                                    $(data.rows).each(function (k,v) {
                                        var row = [];
                                        row['name'] = v.name;
                                        row['value'] = v.id;
                                        list.push(row)
                                    });
                                    setTimeout(function () {
                                        cb(list, Pages)
                                    }, 100)
                                },
                                error: function (data) {
                                    cb([], 0);
                                }
                            });
                        },
                        on: function(data){
                            var arr = data.arr;
                            var change = data.change;
                            var isAdd = data.isAdd;
                            if(!isAdd){
                                $('#stores_id').val('');
                            }
                            if(change.length>0){
                                for (let i in arr){
                                    if(change[0].parentId==arr[i].parentId){
                                        arr.splice(i,1,change[0]);
                                    }
                                }
                            }
                            if(arr.length>0){
                                if(arr[0]['value']!=undefined){
                                    $('#stores_id').val(arr[0]['value']);
                                }
                            }
                            return Array.from(new Set(arr));
                        },
                    });

                    var kuerp_stores = Config.kuerp_stores;
                    if(kuerp_stores!=undefined){
                        var newArr = [];
                        newArr.push({name: kuerp_stores.name, value: kuerp_stores.id,});
                        supplyer_div.setValue(newArr);
                    }

                    var set = Config.set;
                    var examine = parseInt(set.examine.is_checked);
                    if(isNaN(examine)){
                        examine = 0;
                    }
                    if(examine==1 && Config.examine_status==1){
                        supplyer_div.update({disabled:true})
                    }
                });

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




                //数量格式化
                $(document).on("change", ".number_decimal", function(){
                    var set = Config.set;
                    var number_decimal = parseInt(set.number_decimal.value);
                    if(isNaN(number_decimal)){
                        number_decimal = 1;
                    }
                    var period = parseFloat($(this).val());
                    if(!isNaN(period)){
                        period = period.toFixed(number_decimal);
                        $(this).val(period);
                    }else{
                        $(this).val('');
                    }
                });

                //盘点
                $(document).on("change", ".invertory_stock", function(){
                    var set = Config.set;
                    var number_decimal = parseInt(set.number_decimal.value);
                    if(isNaN(number_decimal)){
                        number_decimal = 1;
                    }

                    var obj = $(this).parents('tr');
                    var stock = obj.find('.stock').val();
                    var invertory = obj.find('.invertory_stock').val();
                    var profit = (invertory-stock).toFixed(number_decimal);
                    obj.find('.profit').val(profit);
                });

                $(document).on("click", ".exchange", function(){
                    $.ajax( {
                        url: "kuerp/Warehousing/checkInventory",
                        type: "POST",
                        data:{
                            'purchase_code':$('#purchase_code').val(),
                            'type':1
                        },
                        success: function(res){
                            if(res.code==1){
                                var data = res.msg;
                                var html = '';
                                $(data).each(function (k,v) {
                                    html+='<div class="rk_btn" data-purchase_code="'+v.purchase_code+'" data-status="'+v.status+'" data-type="'+v.type+'">'+v.title+'</div>';
                                });
                                layer.open({
                                    title:'盘点数据',
                                    type: 1,
                                    skin: 'layui-layer-rim', //加上边框
                                    area: ['420px', '240px'], //宽高
                                    content: html
                                });
                            }else{
                                Toastr.error(res.msg);
                            }
                        }
                    });
                });
                // 入库单
                $(document).on("click", ".rk_btn", function(){
                    var purchase_code=$(this).data('purchase_code');
                    var status=$(this).data('status');
                    var type=$(this).data('type');
                    if(status==1){
                        //盘盈
                        parent.Fast.api.open("kuerp/Warehousing/add?purchase_code="+purchase_code+'&type='+type, "盘盈单",{area:['70%','75%'],});
                    }else if(status==2){
                        //盘亏
                        parent.Fast.api.open("kuerp/Warehouse/add?purchase_code="+purchase_code+'&type='+type, "盘亏单",{area:['70%','75%'],});
                    }
                });

                Form.api.bindevent($("form[role=form]"), function(data, ret){
                    if(reloadStatus){
                        parent.location.reload();
                    }else{
                        $('.btn-refresh').click();
                    }
                });
            }
        }
    };
    return Controller;
});