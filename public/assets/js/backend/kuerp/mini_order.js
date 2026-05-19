define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            layui.use(['table', 'form'], function () {
                var table = layui.table;

                table.render({
                    elem: '#table',
                    url: 'kuerp/mini_order/index',
                    parseData: function (res) {
                        return { "code": 0, "msg": '', "count": res.total, "data": res.rows };
                    },
                    toolbar: '#toolbar',
                    page: true,
                    cellMinWidth: 100,
                    cols: [[
                        { checkbox: true },
                        { field: 'order_no', title: __('Order_no'), width: 180 },
                        { field: 'customer_name', title: __('Customer_name'), width: 120 },
                        { field: 'payable_amount', title: __('Payable_amount'), width: 100 },
                        { field: 'status', title: __('Status'), width: 90, templet: function (d) {
                            var map = { 'pending_confirm': 'label-warning', 'confirmed': 'label-primary', 'shipped': 'label-info', 'received': 'label-success', 'completed': 'label-success', 'cancelled': 'label-default', 'returned': 'label-danger' };
                            var label = map[d.status] || 'label-default';
                            return '<span class="label ' + label + '">' + (d.status_text || d.status) + '</span>';
                        }},
                        { field: 'createdtime', title: __('Createdtime'), width: 160, templet: function (d) {
                            return d.createdtime ? new Date(d.createdtime * 1000).toLocaleString() : '-';
                        }},
                        { field: 'sales_code', title: __('Sales_code'), width: 140 },
                        { field: 'id', title: __('Operate'), width: 280, templet: function (d) {
                            var base = 'kuerp/mini_order';
                            var html = '<a href="' + base + '/detail/ids/' + d.id + '" class="layui-btn layui-btn-xs">详情</a>';
                            if (d.status == 'pending_confirm') {
                                html += '<a href="' + base + '/confirm/ids/' + d.id + '" class="layui-btn layui-btn-xs layui-btn-normal">确认</a>';
                                html += '<a href="javascript:" class="layui-btn layui-btn-xs layui-btn-danger" onclick="if(confirm(\'确认取消该订单?\')){ $.ajax({type:\'post\',url:\'' + base + '/cancel/ids/' + d.id + '\',success:function(ret){Toastr.success(ret.msg);location.reload()}})}">取消</a>';
                            }
                            if (d.status == 'confirmed') {
                                html += '<a href="' + base + '/ship/ids/' + d.id + '" class="layui-btn layui-btn-xs layui-btn-warm">发货</a>';
                                html += '<a href="javascript:" class="layui-btn layui-btn-xs layui-btn-danger" onclick="if(confirm(\'确认取消该订单?\')){ $.ajax({type:\'post\',url:\'' + base + '/cancel/ids/' + d.id + '\',success:function(ret){Toastr.success(ret.msg);location.reload()}})}">取消</a>';
                            }
                            return html;
                        }}
                    ]]
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
