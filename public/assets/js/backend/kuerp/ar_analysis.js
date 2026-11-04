define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'addtabs', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, undefined2, Layui) {

    var Controller = {
        index: function () {
            layui.use(['table', 'form', 'laydate'], function () {
                var table = layui.table;
                var pd = parseInt(Config.set.price_decimal.value);
                if (isNaN(pd)) {
                    pd = 2;
                }

                function money(v) {
                    return (parseFloat(v) || 0).toFixed(pd);
                }

                function rate(v) {
                    var r = parseFloat(v);
                    if (isNaN(r)) {
                        r = 0;
                    }
                    return (r * 100).toFixed(2) + '%';
                }

                function moneyCell(v, row, style) {
                    var val = parseFloat(v) || 0;
                    var color = style || (val < 0 ? 'color:#ff5722;' : '');
                    return '<span style="' + color + '">' + money(val) + '</span>';
                }

                var cols = [[
                    {field: 'sale_user_name', title: '销售负责人', width: 110},
                    {field: 'customer_name', title: '客户名称', minWidth: 200},
                    {field: 'credit_days', title: '客户信用账期', width: 110, align: 'center', templet: function (d) {
                        return d.credit_days > 0 ? d.credit_days + '天' : '<span style="color:#ccc;">—</span>';
                    }},
                    {field: 'begin_money', title: '期初应收账款（上月末余额）', width: 190, align: 'right', templet: function (d) {
                        return moneyCell(d.begin_money);
                    }},
                    {field: 'sale_money', title: '本月含税销售总额（新增）', width: 190, align: 'right', templet: function (d) {
                        return moneyCell(d.sale_money);
                    }},
                    {field: 'collection_money', title: '本月实际回款总额', width: 160, align: 'right', templet: function (d) {
                        return moneyCell(d.collection_money);
                    }},
                    {field: 'return_money', title: '本月退货/红冲金额', width: 160, align: 'right', templet: function (d) {
                        return moneyCell(d.return_money);
                    }},
                    {field: 'end_money', title: '本月账面应收余额', width: 170, align: 'right', templet: function (d) {
                        return '<b style="' + ((parseFloat(d.end_money) || 0) > 0 ? 'color:#ff5722;' : '') + '">' + money(d.end_money) + '</b>';
                    }},
                    {field: 'overdue_money', title: '其中：逾期应收账款', width: 170, align: 'right', templet: function (d) {
                        var v = parseFloat(d.overdue_money) || 0;
                        return v > 0 ? '<span style="color:#ff5722;">' + money(v) + '</span>' : '<span style="color:#ccc;">0.00</span>';
                    }},
                    {field: 'collection_rate', title: '本月销售回款率', width: 130, align: 'right', templet: function (d) {
                        return '<span style="' + ((parseFloat(d.collection_rate) || 0) >= 1 ? 'color:#5fb878;' : 'color:#ffb800;') + '">' + rate(d.collection_rate) + '</span>';
                    }},
                    {field: 'diff_money', title: '对账差异金额', width: 140, align: 'right', templet: function (d) {
                        var v = parseFloat(d.diff_money) || 0;
                        if (!v) {
                            return '<span style="color:#ccc;">0.00</span>';
                        }
                        return '<span style="color:' + (v > 0 ? '#ff5722' : '#009688') + ';">' + money(v) + '</span>';
                    }},
                    {field: 'diff_reason', title: '差异原因备注', minWidth: 220}
                ]];

                function currentPeriod() {
                    return $('#period').val() || '';
                }

                // 月份走独立 GET 参数（服务端单独读），客户/销售负责人走 FastAdmin 的 filter+op
                function searchParams() {
                    var filter = {};
                    var customerName = $.trim($('#customer_name').val());
                    if (customerName) {
                        filter.customer_name = customerName;
                    }
                    var saleUser = $.trim($('#sale_user_name').val());
                    if (saleUser) {
                        filter.sale_user_name = saleUser;
                    }
                    var params = {
                        period: currentPeriod(),
                        filter: JSON.stringify(filter),
                        op: JSON.stringify({customer_name: 'LIKE', sale_user_name: 'LIKE'})
                    };
                    return params;
                }

                table.render({
                    elem: '#table'
                    , url: 'kuerp/ar_analysis/index'
                    , where: searchParams()
                    , toolbar: '#arToolbar'
                    , defaultToolbar: []
                    , cellMinWidth: 100
                    , page: true
                    , limit: 100
                    , limits: [50, 100, 200, 500]
                    , cols: cols
                    , parseData: function (res) {
                        return {
                            code: 0,
                            msg: '',
                            count: res.total,
                            data: res.rows
                        };
                    }
                    , done: function () {
                        // 行高同步（含 fixed 列的场景统一处理）
                        $('.layui-table-main tr').each(function (index, val) {
                            $('.layui-table-fixed').each(function () {
                                $($(this).find('.layui-table-body tbody tr')[index]).height($(val).height());
                            });
                        });
                    }
                });

                table.on('toolbar(table)', function (obj) {
                    if (obj.event === 'refresh') {
                        table.reload('table', {where: searchParams()});
                    } else if (obj.event === 'rebuild') {
                        var period = currentPeriod();
                        if (!period) {
                            Toastr.error('请先选择分析月份');
                            return;
                        }
                        Layer.confirm('重新分析会覆盖 ' + period + ' 的现有快照，确认继续？', function (index) {
                            Layer.close(index);
                            Fast.api.ajax({
                                url: 'kuerp/ar_analysis/rebuild',
                                data: {period: period}
                            }, function (ret) {
                                table.reload('table', {where: searchParams()});
                            });
                        });
                    } else if (obj.event === 'export') {
                        var period = currentPeriod();
                        if (!period) {
                            Toastr.error('请先选择分析月份');
                            return;
                        }
                        // ajax 拿不到二进制流，用隐藏 form POST
                        var form = $('<form method="post" target="_blank" style="display:none;"></form>');
                        form.attr('action', Fast.api.fixurl('kuerp/ar_analysis/export'));
                        form.append($('<input type="hidden" name="period">').val(period));
                        $(document.body).append(form);
                        form[0].submit();
                        setTimeout(function () {
                            form.remove();
                        }, 1000);
                    }
                });

                layui.form.on('select(period)', function (data) {
                    table.reload('table', {
                        where: searchParams(),
                        page: {curr: 1}
                    });
                });

                $('#search').on('click', function () {
                    table.reload('table', {
                        where: searchParams(),
                        page: {curr: 1}
                    });
                });
            });
        }
    };
    return Controller;
});
