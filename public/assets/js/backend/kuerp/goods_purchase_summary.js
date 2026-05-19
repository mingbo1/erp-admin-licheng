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
                var taxes = parseInt(set.taxes.is_checked);
                if(isNaN(taxes)){
                    taxes = 0;
                }
                if(taxes!=1){
                    taxesStatus = true;
                }else{
                    taxesStatus = false;
                }

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_purchase_summary/index'
                    ,parseData: function(res){ //res 即为原始返回的数据
                        return {
                            "code": 0, //解析接口状态
                            "msg": '', //解析提示文本
                            "count": res.total, //解析数据长度
                            "data": res.rows, //解析数据列表
                            'countArr':res.count
                         };
                    }
                    ,toolbar: '#thisToolbar' //开启头部工具栏，并为其绑定左侧模板
                    ,totalRow: true
                    ,cellMinWidth: 100 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                    ,cols: [[
                        {field: 'supplier_type_name', title: __('Supplier_type_name'),totalRowText: '合计',templet: function (d) {
                                if(d.supplier_type_name){
                                    return d.supplier_type_name;
                                }else{
                                    return '';
                                }
                            },'totalRow':'合计'},
                        {field: 'goods_code', title: __('Goods_code'),width: 100 },
                        {field: 'goods_name', title: __('Goods.goods_name')},
                        {field: 'goods_specification_id', title: __('Goods_specification_id')},
                        {field: 's_name', title: __('Stores_id'),templet: function (d) {
                                if(d.s_name){
                                    return d.s_name;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'unit_conversion', title: __('Unit_conversion'),templet: function (d) {
                                if(d.unit_conversion){
                                    return d.unit_conversion;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'basic_nmber', title: '副单位数',templet: function (d) {
                                if(d.unit_conversion){
                                    return d.basic_nmber;
                                }else{
                                    return '';
                                }
                            }},
                        {field: 'u_name', title: __('Default_unit')},
                        {field: 'i_number', title: __('Basic_nmber'),totalRow:true},
                        {field: 'unit_price', title: __('Unit_price'),totalRow:true},
                        {field: 'all_money', title: __('All_money'),totalRow:true},
                        {field: 'purchase_money', title: __('Purchase_money'),totalRow:true},
                        {field: 'tax_money', title: __('税额'),hide:taxesStatus,totalRow:true},
                        {field: 'money_tax_price', title: __('价税合计'),hide:taxesStatus,totalRow:true},
                    ]],
                    done: function(res, curr, count){
                        var obj = this.elem.next();
                        obj.find('.layui-table-total td[data-field="i_number"] .layui-table-cell').text(res.countArr.i_number);
                        obj.find('.layui-table-total td[data-field="unit_price"] .layui-table-cell').text(res.countArr.unit_price);
                        obj.find('.layui-table-total td[data-field="all_money"] .layui-table-cell').text(res.countArr.all_money);
                        obj.find('.layui-table-total td[data-field="purchase_money"] .layui-table-cell').text(res.countArr.purchase_money);
                        obj.find('.layui-table-total td[data-field="tax_money"] .layui-table-cell').text(res.countArr.tax_money);
                        obj.find('.layui-table-total td[data-field="money_tax_price"] .layui-table-cell').text(res.countArr.money_tax_price);
                    }
                });

                upload.render({
                    elem: '#import'
                    ,url: 'ajax/upload'
                    ,done: function(res, index, upload){ //上传后的回调
                         if(res.code==1){
                             Fast.api.ajax("kuerp/goods_purchase_summary/import?file="+res.data.url, "",'',true);
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
                            Fast.api.open("kuerp/goods_purchase_summary/add", "添加", {
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
                            Fast.api.ajax("kuerp/goods_purchase_summary/del?ids="+ids, function () {
                                reloadTable();
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
                    });
                }
                //监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'del'){
                        $.ajax( {
                            url: "kuerp/goods_purchase_summary/del",
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
                        Fast.api.open("kuerp/goods_purchase_summary/edit?ids="+data.id, "编辑");
                    }
                });
                //搜索事件
                $('#search').on('click', function () {
                    table.reload('table', {
                        where: {
                            filter:JSON.stringify(getSearch()),
                            op:JSON.stringify(getOp()),
                        }
                    });
                });
                function getSearch() {
                    var search = {};
                    var purchase_code = $('#purchase_code').val();
                    if(purchase_code!=''){
                        search.purchase_code = purchase_code;
                    }
                    var goods_code = $('#goods_code').val();
                    if(goods_code!=''){
                        search.goods_code = goods_code;
                    }
                    var stores_id = $('#stores_id').val();
                    if(stores_id!=''){
                        search.stores_id = stores_id;
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
        
        recyclebin: function () {

            layui.use('table', function(){
                var table = layui.table;

                table.render({
                     elem: '#table'
                     ,url:'goods_purchase_summary/recyclebin'
                     ,parseData: function(res){ //res 即为原始返回的数据
                         return {
                             "code": 0, //解析接口状态
                             "msg": '', //解析提示文本
                             "count": res.total, //解析数据长度
                             "data": res.rows //解析数据列表
                         };
                     }
                     ,toolbar: '#recycleDemo' //开启头部工具栏，并为其绑定左侧模板
                     ,page: true
                     ,cellMinWidth: 80 //全局定义常规单元格的最小宽度，layui 2.2.1 新增
                     ,cols: [[
                         {type: 'checkbox', fixed: 'left'},
                         {field: 'id', title: __('Id')},
                         
                         {fixed: 'right', title:__('Operate'), toolbar: '#recycleBtn', width:150}
                     ]]
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
                        case 'reduction':
                            Fast.api.ajax("goods_purchase_summary/restore?ids="+ids, "",'',true);
                            break;
                        case 'realdel':
                            Fast.api.ajax("goods_purchase_summary/destroy?ids="+ids, "",'',true);
                            break;
                    };
                });

                //监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    if(obj.event === 'realdel'){
                        Fast.api.ajax("goods_purchase_summary/destroy?ids="+data.id, "",'',true);
                    } else if(obj.event === 'reduction'){
                        Fast.api.ajax("goods_purchase_summary/restore?ids="+data.id, "",'',true);
                    }
                });

            });
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
                    laydate.render({
                        elem: '#c-delivery_time',
                        type: 'datetime'
                    });
                    laydate.render({
                         elem: '#delivery_time',
                         type: 'datetime',
                         range: true
                    });
                    laydate.render({
                        elem: '#c-examine_time',
                        type: 'datetime'
                    });
                    laydate.render({
                         elem: '#examine_time',
                         type: 'datetime',
                         range: true
                    });
                    laydate.render({
                        elem: '#c-createtime',
                        type: 'datetime'
                    });
                    laydate.render({
                         elem: '#createtime',
                         type: 'datetime',
                         range: true
                    });
                    laydate.render({
                        elem: '#c-updatetime',
                        type: 'datetime'
                    });
                    laydate.render({
                         elem: '#updatetime',
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