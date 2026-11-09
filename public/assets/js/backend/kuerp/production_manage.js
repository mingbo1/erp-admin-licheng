define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.use(['table', 'form', 'laydate'], function () {
                var table = layui.table;
                var laydate = layui.laydate;

                var set = Config.set;
                var number_decimal = parseInt(set.number_decimal.value);
                if (isNaN(number_decimal)) {
                    number_decimal = 1;
                }

                // 生产日期搜索（单日，精确日期匹配）
                laydate.render({
                    elem: '#production_date',
                    type: 'date'
                });
                // 单据日期搜索（与计划排期一致：日期时间区间）
                laydate.render({
                    elem: '#purchase_time',
                    type: 'datetime',
                    range: true
                });

                table.render({
                    elem: '#table'
                    , url: 'kuerp/production_manage/index'
                    , parseData: function (res) {
                        return {
                            "code": 0,
                            "msg": '',
                            "count": res.total,
                            "data": res.rows
                        };
                    }
                    , toolbar: '#thisToolbar'
                    , page: true
                    , cellMinWidth: 100
                    , cols: [[
                        {checkbox: true},
                        {field: 'purchase_time', title: __('Purchase_time'), templet: function (d) {
                                return d.purchase_time ? layui.util.toDateString(d.purchase_time * 1000, "yyyy-MM-dd") : '<span style="color:#ccc">—</span>';
                            }, width: 110},
                        {field: 'purchase_code', title: __('Purchase_code'), width: 150},
                        {field: 'customers_id', title: __('Customers_id'), templet: function (d) {
                                return d.customers ? d.customers.customer_name : '';
                            }},
                        {field: 'sale_user', title: __('Sale_user'), templet: function (d) {
                                return d.staffmember ? d.staffmember.name : '';
                            }},
                        {field: 'delivery_mode', title: __('Delivery_mode'), width: 90, templet: function (d) {
                                if (d.delivery_mode == 'dropship') {
                                    return '<span style="color:#ff5722">' + __('Delivery_mode dropship') + '</span>';
                                }
                                return __('Delivery_mode direct');
                            }},
                        {field: 'number', title: __('Number'), templet: function (d) {
                                return (parseFloat(d.number) || 0).toFixed(number_decimal);
                            }},
                        {field: 'preparation_id', title: __('Preparation_id'), templet: function (d) {
                                return d.preparation ? d.preparation.nickname : '';
                            }},
                        {field: 'check_status', title: __('Check_status'), templet: function (d) {
                                if (d.check_status == 0) {
                                    return __('Check_status 0');
                                }
                                if (d.check_status == 1) {
                                    return __('Check_status 1');
                                }
                            }},
                        {field: 'production_status', title: __('Production_status'), width: 100, templet: function (d) {
                                if (d.production_status == 1) {
                                    return '<span style="color:#5FB878;">' + __('Production_status 1') + '</span>';
                                }
                                return '<span style="color:#999;">' + __('Production_status 0') + '</span>';
                            }},
                        {field: 'production_date', title: __('Production_date'), width: 110, templet: function (d) {
                                return d.production_date ? d.production_date : '<span style="color:#ccc">—</span>';
                            }},
                        {field: 'delivery_time', title: __('Delivery_time'), templet: function (d) {
                                return d.delivery_time ? layui.util.toDateString(d.delivery_time * 1000, "yyyy-MM-dd") : '<span style="color:#ccc">—</span>';
                            }, width: 110},
                        {field: 'expected_delivery_date', title: __('Expected_delivery_date'), width: 130, templet: function (d) {
                                return d.expected_delivery_date ? d.expected_delivery_date : '<span style="color:#ccc">—</span>';
                            }},
                        {field: 'note', title: __('Note')},
                        {fixed: 'right', title: __('Operate'), toolbar: '#barDemoProdManage', width: 120}
                    ]]
                });

                // 头工具栏事件：只有 刷新 / 导出
                table.on('toolbar(table)', function (obj) {
                    var checkStatus = table.checkStatus(obj.config.id);
                    var data = checkStatus.data;
                    var ids = '';
                    $(data).each(function (k, v) {
                        ids += v.id + ',';
                    });
                    switch (obj.event) {
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'export':
                            if (data.length <= 0) {
                                Toastr.error('请选择要导出的数据');
                                return;
                            }
                            // form 提交才能触发文件下载；action 需 fixurl 补全模块前缀
                            var $form = $('<form method="post" action="' + Fast.api.fixurl("kuerp/production_manage/export") + '" style="display:none;"></form>');
                            $form.append($('<input type="hidden" name="ids">').val(ids));
                            $form.appendTo('body').submit().remove();
                            break;
                    }
                });

                // 行操作：查看详情（只读）
                table.on('tool(table)', function (obj) {
                    var data = obj.data;
                    if (obj.event === 'detail') {
                        Fast.api.open("kuerp/production_manage/detail?ids=" + data.id, "查看详情", {
                            area: ['90%', '80%']
                        });
                    }
                });

                // 搜索
                $('#search').on('click', function () {
                    table.reload('table', {
                        where: {
                            filter: JSON.stringify(getSearch()),
                            op: JSON.stringify(getOp())
                        }
                        , page: {
                            curr: 1
                        }
                    });
                });

                function getSearch() {
                    var search = {};
                    var purchase_code = $('#purchase_code').val();
                    if (purchase_code != '') {
                        search.purchase_code = purchase_code;
                    }
                    var purchase_time = $('#purchase_time').val();
                    if (purchase_time != '') {
                        search.purchase_time = purchase_time;
                    }
                    var production_date = $('#production_date').val();
                    if (production_date != '') {
                        search.production_date = production_date;
                    }
                    var customers_id = $('#customers_id').val();
                    if (customers_id != '') {
                        search.customers_id = customers_id;
                    }
                    var preparation_id = $('#preparation_id').val();
                    if (preparation_id != '') {
                        search.preparation_id = preparation_id;
                    }
                    var check_status = $('#check_status').val();
                    if (check_status != '') {
                        search.check_status = check_status;
                    }
                    return search;
                }

                function getOp() {
                    var op = {};
                    op.purchase_code = 'LIKE';
                    op.purchase_time = 'RANGE';
                    op.production_date = 'LIKE';
                    return op;
                }
            });
        },
        api: {
            bindevent: function () {
            }
        }
    };
    return Controller;
});
