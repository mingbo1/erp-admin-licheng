define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    // 数量输入格式化（detail/edit 弹窗共用）
    function bindNumberFormat() {
        $(document).on("change", ".number_decimal", function () {
            var set = Config.set;
            var number_decimal = parseInt(set.number_decimal.value);
            if (isNaN(number_decimal)) { number_decimal = 1; }
            var val = parseFloat($(this).val());
            if (!isNaN(val)) {
                $(this).val(val.toFixed(number_decimal));
            } else {
                $(this).val('');
            }
        });
    }

    var Controller = {
        index: function () {

            layui.use(['table','form'], function(){
                var table = layui.table;

                table.render({
                    elem: '#table'
                    ,url:'kuerp/goods_outbound_apply/index'
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
                        {field: 'apply_code', title: __('Apply_code'), operate: 'LIKE', width:150},
                        {field: 'purchase_code', title: __('Purchase_code'), operate: 'LIKE', width:130},
                        {field: 'delivery_mode', title: __('Delivery_mode'), width: 90, templet: function (d) {
                                if(d.delivery_mode=='dropship'){
                                    return '<span style="color:#ff5722">'+__('Delivery_mode dropship')+'</span>';
                                }
                                return __('Delivery_mode direct');
                            }},
                        {field: 'customer_name', title: __('Customers_id')},
                        {field: 'apply_admin_name', title: __('Apply_admin_id'), width:100},
                        {field: 'apply_time', title: __('Apply_time'),templet: function (d) {
                                return d.apply_time ? layui.util.toDateString(d.apply_time*1000,"yyyy-MM-dd HH:mm:ss") : '';
                            },width:160},
                        {field: 'examine_admin_name', title: __('Examine_admin_id'), width:100},
                        {field: 'examine_time', title: __('Examine_time'),templet: function (d) {
                                return d.examine_time ? layui.util.toDateString(d.examine_time*1000,"yyyy-MM-dd HH:mm:ss") : '';
                            },width:160},
                        {field: 'status', title: __('Status'),templet: function (d) {
                                if(d.status=='0'){
                                    return '<span style="color:#1E9FFF">待审核</span>';
                                }
                                if(d.status=='1'){
                                    return '<span style="color:green">已同意出库</span>';
                                }
                                if(d.status=='2'){
                                    return '<span style="color:red">已拒绝</span>';
                                }
                                if(d.status=='3'){
                                    return '已撤销';
                                }
                                return '';
                            },width:100},
                        {field: 'refuse_reason', title: __('Refuse_reason')},
                        {fixed: 'right', title:__('Operate'), toolbar: '#barDemoApply', width:360}
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
                    if(obj.event === 'detail' || obj.event === 'examine'){
                        // 详情与审核同一弹窗：待审核状态可在弹窗内调整数量并同意出库
                        Fast.api.open("kuerp/goods_outbound_apply/detail/ids/"+data.id, "出库申请审核", {
                            type:2,
                            area:['95%','80%'],
                            callback:function(value){
                                $('.btn-refresh').click();
                            }
                        });
                    } else if(obj.event === 'print'){
                        // 打印出库申请单（凭单发料）
                        Fast.api.open("kuerp/goods_outbound_apply/printpage/ids/"+data.id, __('Print'), {
                            area:['90%','80%']
                        });
                    } else if(obj.event === 'deliverynote'){
                        // 打印送货单（已同意出库的申请；价格默认隐藏，打印页可勾选显示）
                        Fast.api.open("kuerp/goods_outbound_apply/deliverynote/ids/"+data.id, "送货单", {
                            area:['90%','85%']
                        });
                    } else if(obj.event === 'edit'){
                        // 编辑申请数量（仅待审核）
                        Fast.api.open("kuerp/goods_outbound_apply/edit/ids/"+data.id, "编辑出库申请", {
                            type:2,
                            area:['95%','80%'],
                            callback:function(value){
                                $('.btn-refresh').click();
                            }
                        });
                    } else if(obj.event === 'del'){
                        // 删除申请（已同意出库的禁止删除，后端双重拦截）
                        Layer.confirm('确定删除该出库申请[' + data.apply_code + ']吗？删除后不可恢复', {icon:3, title:'提示'}, function(index){
                            Layer.close(index);
                            Fast.api.ajax({url:'kuerp/goods_outbound_apply/del', data:{ids:data.id}}, function(){
                                reloadTable();
                            });
                        });
                    } else if(obj.event === 'refuse'){
                        layer.prompt({title: '请填写拒绝原因（必填）', formType: 2}, function(text, index){
                            if(text===''){
                                layer.msg('请填写拒绝原因');
                                return;
                            }
                            Fast.api.ajax({url:'kuerp/goods_outbound_apply/refuse', data:{ids:data.id, reason:text}}, function(){
                                layer.close(index);
                                reloadTable();
                            });
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
                    var apply_code = $('#apply_code').val();
                    if(apply_code!=''){
                        search.apply_code = apply_code;
                    }
                    var purchase_code = $('#purchase_code').val();
                    if(purchase_code!=''){
                        search.purchase_code = purchase_code;
                    }
                    var status = $('#status').val();
                    if(status!=''){
                        search.status = status;
                    }
                    var apply_time = $('#apply_time').val();
                    if(apply_time!=''){
                        search.apply_time = apply_time;
                    }
                    return search;
                }
                function getOp() { return { apply_code: 'LIKE', purchase_code: 'LIKE', status: '=', apply_time: 'RANGE' }; }
            });
        },

        detail: function () {
            // 审核/详情弹窗：数量格式化 + 同意出库表单提交 + 拒绝出库（必填原因）
            bindNumberFormat();
            // 打印送货单（已同意出库）
            $(document).on("click", ".btn-deliverynote", function () {
                var id = $("input[name='ids']").val();
                parent.Fast.api.open("kuerp/goods_outbound_apply/deliverynote/ids/" + id, "送货单", {
                    area: ['90%', '85%']
                });
            });
            // 拒绝出库
            $(document).on("click", ".btn-refuse", function(){
                layer.prompt({title: '请填写拒绝原因（必填）', formType: 2}, function(text, index){
                    if(text===''){
                        layer.msg('请填写拒绝原因');
                        return;
                    }
                    Fast.api.ajax({url:'kuerp/goods_outbound_apply/refuse', data:{ids:$("input[name='ids']").val(), reason:text}}, function(){
                        layer.close(index);
                        parent.Layer.closeAll();
                        parent.$('.btn-refresh').click();
                    });
                });
            });
            // 同意出库并执行
            Form.api.bindevent($("form[role=form]"), function (data, ret) {
                parent.Layer.closeAll();
                parent.$('.btn-refresh').click();
            });
        },

        edit: function () {
            // 编辑申请弹窗：数量格式化 + 表单提交
            bindNumberFormat();
            Form.api.bindevent($("form[role=form]"), function (data, ret) {
                parent.Layer.closeAll();
                parent.$('.btn-refresh').click();
            });
        },

        api: {
            bindevent: function () {}
        }
    };
    return Controller;
});
