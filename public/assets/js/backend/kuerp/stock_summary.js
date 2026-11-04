define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'addtabs', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, undefined2, Layui) {

    var Controller = {
        index: function () {
            layui.use(['table', 'form', 'laydate'], function () {
                var table = layui.table;
                var pd = parseInt(Config.set.price_decimal.value);
                if (isNaN(pd)) {
                    pd = 2;
                }
                var nd = parseInt(Config.set.number_decimal.value);
                if (isNaN(nd)) {
                    nd = 0;
                }

                // 未满月份期末三列为 null，显示空白而不是 0
                function blankable(v, decimal) {
                    if (v === null || v === undefined) {
                        return '';
                    }
                    return (parseFloat(v) || 0).toFixed(decimal);
                }

                function numCell(field) {
                    return function (d) {
                        return blankable(d[field], nd);
                    };
                }

                function moneyCell(field, strong) {
                    return function (d) {
                        var v = d[field];
                        if (v === null || v === undefined) {
                            return '';
                        }
                        var txt = (parseFloat(v) || 0).toFixed(pd);
                        return strong ? '<b>' + txt + '</b>' : txt;
                    };
                }

                var cols = [[
                    {field: 'goods_code', title: '物料编码', width: 130, fixed: 'left'},
                    {field: 'goods_name', title: '物料名称', minWidth: 160, fixed: 'left'},
                    {field: 'goods_spec', title: '规格型号', width: 130},
                    {field: 'goods_unit', title: '单位', width: 70, align: 'center'},
                    {field: 'begin_qty', title: '期初数量', width: 110, align: 'right', templet: numCell('begin_qty')},
                    {field: 'begin_price', title: '期初单价', width: 110, align: 'right', templet: moneyCell('begin_price')},
                    {field: 'begin_money', title: '期初金额', width: 120, align: 'right', templet: moneyCell('begin_money')},
                    {field: 'in_qty', title: '本期入库数量', width: 120, align: 'right', templet: numCell('in_qty')},
                    {field: 'in_price', title: '本期入库单价', width: 120, align: 'right', templet: moneyCell('in_price')},
                    {field: 'in_money', title: '本期入库金额', width: 130, align: 'right', templet: moneyCell('in_money')},
                    {field: 'out_qty', title: '本期出库数量', width: 120, align: 'right', templet: numCell('out_qty')},
                    {field: 'out_price', title: '本期出库单价', width: 120, align: 'right', templet: moneyCell('out_price')},
                    {field: 'out_money', title: '本期出库金额', width: 130, align: 'right', templet: moneyCell('out_money')},
                    {field: 'end_qty', title: '期末数量', width: 110, align: 'right', templet: numCell('end_qty')},
                    {field: 'end_price', title: '期末单价', width: 110, align: 'right', templet: moneyCell('end_price')},
                    {field: 'end_money', title: '期末金额', width: 130, align: 'right', templet: moneyCell('end_money', true)}
                ]];

                function currentPeriod() {
                    return $('#period').val() || '';
                }

                function currentStores() {
                    return $('#stores_id').val() || 0;
                }

                function queryParams() {
                    var filter = {};
                    var goodsCode = $.trim($('#goods_code').val());
                    if (goodsCode) {
                        filter.goods_code = goodsCode;
                    }
                    var goodsName = $.trim($('#goods_name').val());
                    if (goodsName) {
                        filter.goods_name = goodsName;
                    }
                    return {
                        period: currentPeriod(),
                        stores_id: currentStores(),
                        filter: JSON.stringify(filter),
                        op: JSON.stringify({goods_code: 'LIKE', goods_name: 'LIKE'})
                    };
                }

                function renderFoot(res) {
                    var t = res.total_row;
                    if (!t) {
                        return;
                    }
                    var html = '<b>合计</b>　'
                        + '期初数量 <b>' + blankable(t.begin_qty, nd) + '</b> ／ 期初金额 <b>' + blankable(t.begin_money, pd) + '</b>　'
                        + '本期入库数量 <b>' + blankable(t.in_qty, nd) + '</b> ／ 金额 <b>' + blankable(t.in_money, pd) + '</b>　'
                        + '本期出库数量 <b>' + blankable(t.out_qty, nd) + '</b> ／ 金额 <b>' + blankable(t.out_money, pd) + '</b>　'
                        + '期末数量 <b>' + blankable(t.end_qty, nd) + '</b> ／ 期末金额 <b>' + blankable(t.end_money, pd) + '</b>'
                        + (blankable(t.end_qty, nd) === '' ? '　<span style="color:#999;">（月份未结束，期末留空）</span>' : '');
                    $('#ss-total').html(html);
                }

                table.render({
                    elem: '#table'
                    , url: 'kuerp/stock_summary/index'
                    , where: queryParams()
                    , toolbar: '#ssToolbar'
                    , defaultToolbar: []
                    , cellMinWidth: 90
                    , page: true
                    , limit: 100
                    , limits: [50, 100, 200, 500]
                    , cols: cols
                    , parseData: function (res) {
                        renderFoot(res);
                        if (res.is_complete === 0) {
                            if (!window.__ssTipShown) {
                                Toastr.info('所选月份未结束：期末数量/单价/金额留空，月份结束后重新计算即可补齐');
                                window.__ssTipShown = true;
                            }
                        } else {
                            window.__ssTipShown = false;
                        }
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
                        table.reload('table', {where: queryParams()});
                    } else if (obj.event === 'rebuild') {
                        var period = currentPeriod();
                        if (!period) {
                            Toastr.error('请先选择月份');
                            return;
                        }
                        var tip = period === new Date().toISOString().slice(0, 7)
                            ? '重新计算会覆盖 ' + period + ' 的数据；该月未结束，期末三列将留空。确认继续？'
                            : '重新计算会覆盖 ' + period + ' 的现有快照，确认继续？';
                        Layer.confirm(tip, function (index) {
                            Layer.close(index);
                            Fast.api.ajax({
                                url: 'kuerp/stock_summary/rebuild',
                                data: {period: period}
                            }, function () {
                                table.reload('table', {where: queryParams()});
                            });
                        });
                    } else if (obj.event === 'export') {
                        var period = currentPeriod();
                        if (!period) {
                            Toastr.error('请先选择月份');
                            return;
                        }
                        // ajax 拿不到二进制流，用隐藏 form POST
                        var form = $('<form method="post" target="_blank" style="display:none;"></form>');
                        form.attr('action', Fast.api.fixurl('kuerp/stock_summary/export'));
                        form.append($('<input type="hidden" name="period">').val(period));
                        form.append($('<input type="hidden" name="stores_id">').val(currentStores()));
                        $(document.body).append(form);
                        form[0].submit();
                        setTimeout(function () {
                            form.remove();
                        }, 1000);
                    }
                });

                layui.form.on('select(period)', function () {
                    table.reload('table', {where: queryParams(), page: {curr: 1}});
                });
                layui.form.on('select(stores_id)', function () {
                    table.reload('table', {where: queryParams(), page: {curr: 1}});
                });

                $('#search').on('click', function () {
                    table.reload('table', {where: queryParams(), page: {curr: 1}});
                });
            });
        }
    };
    return Controller;
});
