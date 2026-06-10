define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            layui.config({
                base: '/assets/addons/kuerp/layui/module/'
            }).extend({
                tableMerge: 'tableMerge'
            });

            layui.use(['table', 'form', 'tableMerge'], function () {
                var table = layui.table;
                var tableMerge = layui.tableMerge;

                table.render({
                    elem: '#table',
                    url: 'kuerp/mini_order/index',
                    parseData: function (res) {
                        return { "code": 0, "msg": '', "count": res.total, "data": res.rows };
                    },
                    toolbar: '#toolbar',
                    page: true,
                    cellMinWidth: 80,
                    cols: [[
                        { field: 'order_no', title: '订单号', width: 180, merge:['order_no'], },
                        { title: '商品编号', width: 100, field: 'goods_code' },
                        { title: '商品名称', width: 200, field: 'goods_name' },
                        { title: '图片', width: 70, align: 'center', templet: function (d) {
                            return d.goods_image
                                ? '<img src="' + d.goods_image + '" style="width:36px;height:36px;border-radius:4px;object-fit:cover;" onerror="this.style.display=\'none\'">'
                                : '-';
                        }},
                        { title: '单位', width: 60, field: 'unit_name' },
                        { title: '属性', width: 100, field: 'attr_name' },
                        { title: '单价', width: 90, field: 'unit_price', templet: function (d) {
                            return d.unit_price !== '' && d.unit_price !== null ? '¥' + d.unit_price : '-';
                        }},
                        { title: '数量', width: 80, field: 'quantity' },
                        { title: '支付状态', width: 80, align: 'center', templet: function (d) {
                            var paid = ['paid','shipped','received','completed'].indexOf(d.status) >= 0;
                            return paid ? '<span class="label label-success">已支付</span>' : '<span class="label label-warning">未支付</span>';
                        }},
                        { field: 'status', title: '订单状态', width: 85, templet: function (d) {
                            var map = { 'pending_confirm': 'label-warning', 'confirmed': 'label-primary', 'paid': 'label-success', 'shipped': 'label-info', 'received': 'label-success', 'completed': 'label-success', 'cancelled': 'label-default', 'returned': 'label-danger' };
                            return '<span class="label ' + (map[d.status] || 'label-default') + '">' + (d.status_text || d.status) + '</span>';
                        }},
                        { field: 'receiver_name', title: '收货人', width: 85, merge: ['order_no'] },
                        { field: 'receiver_mobile', title: '联系电话', width: 130, merge: ['order_no'] },
                        { field: 'delivery_type', title: '配送方式', width: 95, merge: ['order_no'], templet: function (d) {
                            return d.delivery_type == 'logistics' ? '物流配送' : '专业配送';
                        }},
                        { title: '收货地址', width: 200, merge: ['order_no'], templet: function (d) {
                            var addr = [d.receiver_province, d.receiver_city, d.receiver_district, d.receiver_address].filter(Boolean).join(' ');
                            return addr || '-';
                        }},
                        { field: 'createdtime', title: '下单时间', width: 160, merge: ['order_no'], templet: function (d) {
                            return d.createdtime ? new Date(d.createdtime * 1000).toLocaleString() : '-';
                        }},
                        { field: 'sales_code', title: '销货单号', width: 140, merge: ['order_no'] },
                        { fixed: 'right', title:__('Operate'), width:210,  merge: ['order_no'], templet: function (d) {
                            var html = '<a class="layui-btn layui-btn-xs" lay-event="detail">详情</a>';
                            if (d.status == 'pending_confirm') {
                                html += '<a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="confirm">审核</a>';
                                html += '<a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="cancel">取消</a>';
                            }
                            if (d.status == 'confirmed' && !d.sales_code) {
                                html += '<a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="sales">生成销货单</a>';
                            }
                            if (d.status == 'paid') {
                                html += '<a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="ship">发货</a>';
                                html += '<a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="cancel">取消</a>';
                            }
                            return html;
                        }}
                        
                    ]],
                    done: function () {
                        tableMerge.render({
                            id: 'table',
                            cols: this.cols
                        });
                        // 同步固定列与主表行高
                        $(".layui-table-main tr").each(function (index, val) {
                            $($(".layui-table-fixed-l .layui-table-body tbody tr")[index]).height($(val).height());
                            $($(".layui-table-fixed-r .layui-table-body tbody tr")[index]).height($(val).height());
                        });
                        $(window).resize(function () {
                            $(".layui-table-main tr").each(function (index, val) {
                                $($(".layui-table-fixed-r .layui-table-body tbody tr")[index]).height($(val).height());
                            });
                        });
                    }
                });

                //头工具栏事件
                table.on('toolbar(table)', function(obj){
                    switch(obj.event){
                        case 'refresh':
                            table.reload('table', []);
                            break;
                    }
                });

                //监听行工具事件
                table.on('tool(table)', function(obj){
                    var data = obj.data;
                    switch(obj.event){
                        case 'detail':
                            Fast.api.open("kuerp/mini_order/detail/ids/" + data.id, "订单详情", {area:['90%','75%']});
                            break;
                        case 'confirm':
                             Layer.confirm('审核通过该订单？', {icon: 3, title: __('Warning'), shadeClose: true}, function(index){
                                Fast.api.ajax({
                                    url: "kuerp/mini_order/confirm/ids/" + data.id,
                                    success: function(ret) {
                                        if (ret.code == 1) {
                                            Layer.msg(ret.msg);
                                        }
                                        $('.btn-refresh').click();
                                    }
                                });
                                Layer.close(index);
                            });

                            break;
                        case 'sales':
                            Fast.api.open("kuerp/goods_sales/add?order_id=" + data.id, "生成销货单", {area:['90%','75%']});
                            break;
                        case 'cancel':
                            Layer.confirm('确认取消该订单？', {icon: 3, title: __('Warning'), shadeClose: true}, function(index){
                                Fast.api.ajax("kuerp/mini_order/cancel/ids/" + data.id, function(){
                                    $('.btn-refresh').click();
                                },'',true);
                                Layer.close(index);
                            });
                            break;
                        case 'ship':
                            Fast.api.open("kuerp/mini_order/ship/ids/" + data.id, "发货", {area:['90%','75%']});
                            break;
                    }
                });

                $('#search').on('click', function () {
                    table.reload('table', {
                        where: {
                            filter: JSON.stringify(getSearch()),
                            op: JSON.stringify(getOp())
                        },
                        page: { curr: 1 }
                    });
                });

                function getSearch() {
                    var search = {};
                    var orderNo = $('#order_no').val();
                    if (orderNo) search.order_no = orderNo;
                    var status = $('#status').val();
                    if (status) search.status = status;
                    return search;
                }

                function getOp() {
                    return { order_no: 'LIKE' };
                }
            });
            Controller.api.bindevent();
        },

        add: function () { Controller.api.bindevent(); },
        edit: function () { Controller.api.bindevent(); },
        ship: function () { Controller.api.bindevent(); },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"), function (data, ret) {
                    parent.location.reload();
                });
            }
        }
    };
    return Controller;
});
