define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'addtabs', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, undefined2, Layui) {

    var Controller = {
        index: function () {
            layui.use(['table', 'form'], function () {
                var table = layui.table;
                var set = Config.set;
                var pd = parseInt(set.price_decimal.value);
                if (isNaN(pd)) { pd = 2; }
                var nd = parseInt(set.number_decimal.value);
                if (isNaN(nd)) { nd = 2; }

                function money(v) {
                    return '￥' + (parseFloat(v) || 0).toFixed(pd);
                }

                function statusTag(recon) {
                    if (recon == '0') return '<span style="color:#999">待对账</span>';
                    if (recon == '1') return '<span style="color:#1E9FFF">待二审</span>';
                    return '<span style="color:#5fb878">已对账</span>';
                }

                function invoiceTag(v) {
                    if (v == '2') return '<span style="color:#5fb878">已开票</span>';
                    if (v == '1') return '<span style="color:#ffb800">部分开票</span>';
                    return '<span style="color:#999">未开票</span>';
                }

                function payTag(v) {
                    if (v == '2') return '<span style="color:#5fb878">已收款</span>';
                    if (v == '1') return '<span style="color:#1E9FFF">部分收款</span>';
                    return '<span style="color:#999">未收款</span>';
                }

                table.render({
                    elem: '#table'
                    , url: 'kuerp/sales_recon/index'
                    , parseData: function (res) {
                        return {code: 0, msg: '', count: res.total, data: res.rows};
                    }
                    , toolbar: '#thisToolbar'
                    , page: true
                    , cellMinWidth: 90
                    , cols: [[
                        {checkbox: true, fixed: 'left'},
                        {field: 'recon_code', title: '对账单号', width: 155, operate: 'LIKE'},
                        {field: 'customer_name', title: '客户', width: 170},
                        {field: 'period_text', title: '对账期间', width: 195},
                        {field: 'doc_count', title: '单据数', width: 80, align: 'center'},
                        {field: 'total_money', title: '销货合计金额', width: 120, align: 'right', templet: function (d) {
                            return money(d.total_money);
                        }},
                        {field: 'total_customers_money', title: '客户费用', width: 100, align: 'right', templet: function (d) {
                            return money(d.total_customers_money);
                        }},
                        {field: 'total_amount', title: '总金额', width: 120, align: 'right', templet: function (d) {
                            return '<b>' + money(d.total_amount) + '</b>';
                        }},
                        {field: 'diff_money', title: '差异金额', width: 100, align: 'right', templet: function (d) {
                            var v = parseFloat(d.diff_money) || 0;
                            if (!v) {
                                return '<span style="color:#ccc;">0.00</span>';
                            }
                            return '<span style="color:' + (v > 0 ? '#ff5722' : '#009688') + ';">' + money(v) + '</span>';
                        }},
                        {field: 'invoice_amount', title: '已开票', width: 100, align: 'right', templet: function (d) {
                            return money(d.invoice_amount);
                        }},
                        {field: 'pay_money', title: '已收款', width: 100, align: 'right', templet: function (d) {
                            return money(d.pay_money);
                        }},
                        {field: 'due_date', title: '收款到期日', width: 180, templet: function (d) {
                            if (!d.due_date_text) {
                                return '<span style="color:#ccc;">—</span>';
                            }
                            if (d.term_state == 3) {
                                return '<span style="color:#5fb878">' + d.due_date_text + '（已收款）</span>';
                            }
                            if (d.term_state == 2) {
                                return '<span style="color:#ff5722;font-weight:bold">' + d.due_date_text + '（逾期' + Math.abs(d.days_left) + '天）</span>';
                            }
                            if (d.term_state == 1) {
                                return '<span style="color:#ffb800;font-weight:bold">' + d.due_date_text + '（剩' + d.days_left + '天）</span>';
                            }
                            return d.due_date_text + (d.days_left !== null ? '（剩' + d.days_left + '天）' : '');
                        }},
                        {field: 'business_status', title: '业务状态', width: 130, templet: function (d) {
                            if (d.business_status == '待二审') return '<span style="color:#1E9FFF">' + d.business_status + '</span>';
                            if (d.business_status == '已完成') return '<span style="color:#5fb878">' + d.business_status + '</span>';
                            if (d.business_status && d.business_status.indexOf('驳回') >= 0) return '<span style="color:#ff5722">' + d.business_status + '</span>';
                            return d.business_status;
                        }},
                        {field: 'recon_status', title: '对账状态', width: 90, align: 'center', templet: function (d) {
                            return statusTag(d.recon_status);
                        }},
                        {field: 'invoice_status', title: '开票状态', width: 95, align: 'center', templet: function (d) {
                            return invoiceTag(d.invoice_status);
                        }},
                        {field: 'pay_status', title: '收款状态', width: 95, align: 'center', templet: function (d) {
                            return payTag(d.pay_status);
                        }},
                        {field: 'preparation_name', title: '制单人', width: 85},
                        {field: 'examine_name', title: '一审人', width: 85},
                        {field: 'second_examine_name', title: '二审人', width: 85},
                        {fixed: 'right', title: '操作', toolbar: '#barDemoRecon', width: 360}
                    ]]
                    , done: function () {
                        $('.layui-table-main tr').each(function (index, val) {
                            $('.layui-table-fixed').each(function () {
                                $($(this).find('.layui-table-body tbody tr')[index]).height($(val).height());
                            });
                        });
                        loadReminder();
                    }
                });

                function reloadTable() {
                    table.reload('table', {
                        where: {
                            filter: JSON.stringify(getSearch()),
                            op: JSON.stringify(getOp()),
                            term_state: $('#term_state').val() || ''
                        },
                        page: {curr: 1}
                    });
                }

                // 到期提醒条：已逾期 / N 天内即将到期
                function loadReminder() {
                    $.ajax({
                        url: Fast.api.fixurl('kuerp/sales_recon/reminder'),
                        type: 'GET',
                        dataType: 'json',
                        success: function (res) {
                            if (!res || res.code !== 1 || !res.data) {
                                return;
                            }
                            var d = res.data;
                            var html = '';
                            if (d.overdue > 0) {
                                html += '<div class="alert alert-danger" style="margin-bottom:8px;">'
                                    + '<i class="fa fa-exclamation-triangle"></i> 有 <b>' + d.overdue + '</b> 张对账单已逾期未收款。'
                                    + '<a href="javascript:;" class="btn-remind-filter" data-state="2" style="margin-left:10px;">查看逾期</a></div>';
                            }
                            if (d.due_soon > 0) {
                                html += '<div class="alert alert-warning" style="margin-bottom:8px;">'
                                    + '<i class="fa fa-bell-o"></i> 有 <b>' + d.due_soon + '</b> 张对账单将在 ' + d.remind_days + ' 天内到期。'
                                    + '<a href="javascript:;" class="btn-remind-filter" data-state="1" style="margin-left:10px;">查看即将到期</a></div>';
                            }
                            if (html) {
                                $('#term-reminder').html(html).show();
                            } else {
                                $('#term-reminder').hide().empty();
                            }
                        }
                    });
                }

                $('#term-reminder').on('click', '.btn-remind-filter', function () {
                    $('#term_state').val($(this).data('state'));
                    if (layui.form) {
                        layui.form.render('select');
                    }
                    reloadTable();
                });

                function getSearch() {
                    var obj = {};
                    $('.layui-search [name]').each(function () {
                        var name = $(this).attr('name');
                        if (name === 'term_state') {
                            return;
                        }
                        var value = $(this).val();
                        if (value != '') {
                            obj[name] = value;
                        }
                    });
                    return obj;
                }

                function getOp() {
                    var obj = {};
                    $('.layui-search [name]').each(function () {
                        var name = $(this).attr('name');
                        if (name === 'term_state') {
                            return;
                        }
                        if ($(this).val() != '') {
                            obj[name] = '=';
                        }
                    });
                    obj.recon_code = 'LIKE';
                    return obj;
                }

                $('#search').on('click', reloadTable);

                function selectedIds() {
                    var data = table.checkStatus('table').data;
                    return data.map(function (d) { return d.id; });
                }

                function doExport(ids) {
                    if (!ids.length) {
                        Toastr.error('请先勾选对账单');
                        return;
                    }
                    var form = $('<form>', {
                        action: Fast.api.fixurl('kuerp/sales_recon/export'),
                        method: 'post',
                        target: '_blank',
                        style: 'display:none'
                    }).appendTo('body');
                    form.append($('<input>', {type: 'hidden', name: 'ids', value: ids.join(',')}));
                    form.submit();
                    setTimeout(function () { form.remove(); }, 1000);
                }

                table.on('toolbar(table)', function (obj) {
                    if (obj.event === 'refresh') {
                        table.reload('table', []);
                    } else if (obj.event === 'export') {
                        doExport(selectedIds());
                    } else if (obj.event === 'gotoitems') {
                        Fast.api.addtabs('kuerp/sales_recon/items', '销货对账明细');
                    }
                });

                table.on('tool(table)', function (obj) {
                    var d = obj.data;
                    if (obj.event === 'detail') {
                        Fast.api.open('kuerp/sales_recon/detail?ids=' + d.id, '对账单详情', {area: ['96%', '92%']});
                    } else if (obj.event === 'print') {
                        Fast.api.open('kuerp/sales_recon/printpage?ids=' + d.id, '打印对账单', {area: ['96%', '92%']});
                    } else if (obj.event === 'exportone') {
                        doExport([d.id]);
                    } else if (obj.event === 'edit') {
                        Fast.api.open('kuerp/sales_recon/edit?ids=' + d.id, '编辑对账单', {area: ['85%', '80%'], callback: function () {
                            table.reload('table', []);
                        }});
                    } else if (obj.event === 'del') {
                        Layer.confirm('确认删除对账单 ' + d.recon_code + '？删除后所含单据将解锁。', function (index) {
                            Layer.close(index);
                            Fast.api.ajax({url: 'kuerp/sales_recon/del', data: {ids: d.id}}, function () {
                                table.reload('table', []);
                            });
                        });
                    } else if (obj.event === 'confirm') {
                        Layer.confirm('确认一审通过对账单 ' + d.recon_code + '？通过后将对所含单据加锁。', function (index) {
                            Layer.close(index);
                            Fast.api.ajax({url: 'kuerp/sales_recon/confirm?ids=' + d.id}, function () {
                                table.reload('table', []);
                            });
                        });
                    } else if (obj.event === 'secondconfirm') {
                        Layer.confirm('确认二审通过对账单 ' + d.recon_code + '？', function (index) {
                            Layer.close(index);
                            Fast.api.ajax({url: 'kuerp/sales_recon/secondconfirm?ids=' + d.id}, function () {
                                table.reload('table', []);
                            });
                        });
                    } else if (obj.event === 'reject') {
                        Layer.prompt({title: '请填写驳回原因', formType: 2}, function (value, index) {
                            if (!value) {
                                Toastr.error('请填写驳回原因');
                                return;
                            }
                            Fast.api.ajax({url: 'kuerp/sales_recon/reject?ids=' + d.id, data: {reason: value}}, function () {
                                Layer.close(index);
                                table.reload('table', []);
                            });
                        });
                    } else if (obj.event === 'cancelconfirm') {
                        Layer.confirm('撤销对账将解除对所含单据的锁定，确认继续？', function (index) {
                            Layer.close(index);
                            Fast.api.ajax({url: 'kuerp/sales_recon/cancelconfirm?ids=' + d.id}, function () {
                                table.reload('table', []);
                            });
                        });
                    } else if (obj.event === 'invoice') {
                        Fast.api.open('kuerp/sales_recon/invoice?ids=' + d.id, '开票 - ' + d.recon_code, {area: ['80%', '75%'], callback: function () {
                            table.reload('table', []);
                        }});
                    } else if (obj.event === 'diff') {
                        Fast.api.open('kuerp/sales_recon/diff?ids=' + d.id, '差异金额 - ' + d.recon_code, {area: ['85%', '85%'], callback: function () {
                            table.reload('table', []);
                        }});
                    } else if (obj.event === 'collect') {
                        //带 recon_id 进入收款单：客户预选、该对账单所含销货单/退货单自动填为源单
                        Fast.api.addtabs('kuerp/collection/add?recon_id=' + d.id, '新增收款单');
                    }
                });
            });
        },

        items: function () {
            // 销货对账明细：按销货单/退货单分卡片展示，单据头 + 折叠的商品明细
            layui.use(['form', 'laypage'], function () {
                var laypage = layui.laypage;
                var set = Config.set || {};
                var pd = parseInt(set.price_decimal && set.price_decimal.value);
                if (isNaN(pd)) { pd = 2; }
                var nd = parseInt(set.number_decimal && set.number_decimal.value);
                if (isNaN(nd)) { nd = 2; }

                var state = {page: 1, limit: 50, total: 0};

                function num(v) { return (parseFloat(v) || 0).toFixed(nd); }
                function money(v) { return (parseFloat(v) || 0).toFixed(pd); }

                function esc(v) {
                    if (v === null || v === undefined) { return ''; }
                    return String(v).replace(/[&<>"']/g, function (c) {
                        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
                    });
                }

                function outTag(v, type) {
                    if (type == '2') return '<span style="color:#ccc">—</span>';
                    if (v == '2') return '<span style="color:#5fb878">已出库</span>';
                    if (v == '1') return '<span style="color:#ffb800">部分出库</span>';
                    return '<span style="color:#999">未出库</span>';
                }

                function reconTag(v) {
                    if (v == '2') return '<span style="color:#5fb878">已对账</span>';
                    if (v == '1') return '<span style="color:#1E9FFF">待二审</span>';
                    return '<span style="color:#999">未对账</span>';
                }

                function invoiceTag(v, type) {
                    if (type == '2' || v === null || v === undefined || v === '') return '<span style="color:#ccc">—</span>';
                    return v == '1' ? '<span style="color:#5fb878">已开票</span>' : '<span style="color:#999">未开票</span>';
                }

                function payTag(v) {
                    if (v == '2') return '<span style="color:#5fb878">已收款</span>';
                    if (v == '1') return '<span style="color:#1E9FFF">部分收款</span>';
                    return '<span style="color:#999">未收款</span>';
                }

                function getFilters() {
                    var obj = {};
                    $('.layui-search [name]').each(function () {
                        var v = $(this).val();
                        if (v !== '' && v !== null) {
                            obj[$(this).attr('name')] = v;
                        }
                    });
                    return obj;
                }

                function goodsHtml(g) {
                    var neg = parseFloat(g.number) < 0 || parseFloat(g.line_amount) < 0;
                    var amountColor = parseFloat(g.line_amount) < 0 ? ' style="color:#ff5722"' : '';
                    var stockColor = parseFloat(g.number) < 0 ? ' style="color:#ff5722"' : '';
                    var name = esc(g.goods_name);
                    if (g.attr_name) {
                        name += ' <span style="color:#999">(' + esc(g.attr_name) + ')</span>';
                    }
                    return '<tr>'
                        + '<td>' + esc(g.goods_code) + '</td>'
                        + '<td>' + name + '</td>'
                        + '<td>' + esc(g.specification) + '</td>'
                        + '<td class="center">' + esc(g.unit_name) + '</td>'
                        + '<td class="num"><span' + stockColor + '>' + num(g.number) + '</span></td>'
                        + '<td class="num">' + money(g.tax_included) + '</td>'
                        + '<td class="num">' + money(g.unit_price) + '</td>'
                        + '<td class="center">' + (parseFloat(g.tax_rate) || 0) + '%</td>'
                        + '<td class="num"><span' + amountColor + '>' + money(g.line_amount) + '</span></td>'
                        + '<td>' + esc(g.purchase_order_id) + '</td>'
                        + '<td>' + esc(g.remark) + '</td>'
                        + '</tr>';
                }

                function docHtml(d) {
                    var isReturn = String(d.doc_type) === '2';
                    var locked = parseInt(d.recon_id, 10) > 0;
                    var infos = d.info_list || [];
                    var s = '';
                    s += '<div class="recon-doc" data-key="' + esc(d.doc_key) + '">';
                    s += '<div class="recon-doc-head">';
                    s += '<div class="recon-doc-row1">';
                    s += '<input type="checkbox" class="doc-check" value="' + esc(d.doc_key) + '"'
                        + (locked ? ' disabled title="该单据已对账，不能重复生成对账单"' : '') + '>';
                    s += '<span class="recon-doc-caret layui-icon layui-icon-right"></span>';
                    s += '<span class="recon-doc-code">' + esc(d.doc_code) + '</span>';
                    s += '<span class="recon-doc-type' + (isReturn ? ' return' : '') + '">'
                        + (isReturn ? '销货退货单' : '销货单') + '</span>';
                    s += '<span>' + esc(d.doc_time_text) + '</span>';
                    s += '<span>客户：' + esc(d.customer_name) + '</span>';
                    s += '<span>销售员：' + esc(d.sale_user_name || '—') + '</span>';
                    s += '<span class="recon-doc-tags">'
                        + outTag(d.out_status, d.doc_type) + ' '
                        + reconTag(d.recon_status) + ' '
                        + invoiceTag(d.invoice_status, d.doc_type) + ' '
                        + payTag(d.pay_status) + '</span>';
                    s += '<span class="recon-doc-amount" style="margin-left:auto">总金额 ' + money(d.total_amount) + '</span>';
                    s += '</div>';
                    s += '<div class="recon-doc-row2">';
                    s += '<span>销货合计：' + money(d.real_money) + '</span>';
                    s += '<span>客户承担费用：' + money(d.customers_money) + '</span>';
                    s += '<span>商品 ' + infos.length + ' 项</span>';
                    if (d.purchase_order_id) {
                        s += '<span>关联销售单号：' + esc(d.purchase_order_id) + '</span>';
                    }
                    if (d.express_no) {
                        s += '<span>物流单号：' + esc(d.express_no) + '</span>';
                    }
                    if (d.recon_code) {
                        s += '<span>对账单号：' + esc(d.recon_code) + '</span>';
                    }
                    s += '</div>';
                    s += '</div>';
                    s += '<div class="recon-doc-body">';
                    if (!infos.length) {
                        s += '<div class="recon-doc-empty">该单据没有可显示的商品明细</div>';
                    } else {
                        s += '<div style="overflow-x:auto"><table>';
                        s += '<thead><tr>'
                            + '<th>商品编号</th><th>商品名称</th><th>型号/规格</th><th>单位</th>'
                            + '<th style="text-align:right">数量</th><th style="text-align:right">含税单价</th>'
                            + '<th style="text-align:right">未税单价</th><th style="text-align:center">税点</th>'
                            + '<th style="text-align:right">销货合计金额</th><th>关联销售单号</th><th>备注</th>'
                            + '</tr></thead><tbody>';
                        for (var i = 0; i < infos.length; i++) {
                            s += goodsHtml(infos[i]);
                        }
                        s += '</tbody></table></div>';
                    }
                    s += '</div>';
                    s += '</div>';
                    return s;
                }

                function refreshBar() {
                    var all = $('#items-list .doc-check:not(:disabled)');
                    var checked = all.filter(':checked');
                    $('#check-all').prop('checked', all.length > 0 && checked.length === all.length);
                }

                function render(res) {
                    state.total = parseInt(res.total, 10) || 0;
                    var docs = res.rows || [];
                    var html = '';
                    for (var i = 0; i < docs.length; i++) {
                        html += docHtml(docs[i]);
                    }
                    if (!html) {
                        html = '<div style="padding:40px;text-align:center;color:#999">暂无符合条件的单据</div>';
                    }
                    $('#items-list').html(html);
                    $('#total-money').text(money(res.all_money));
                    $('#total-customers').text(money(res.all_customers_money));
                    $('#total-amount').text(money(res.all_amount));
                    $('#doc-count').text('共 ' + state.total + ' 张单据');
                    $('#check-all').prop('checked', false);
                    laypage.render({
                        elem: 'items-pager',
                        count: state.total,
                        curr: state.page,
                        limit: state.limit,
                        limits: [20, 50, 100, 200],
                        layout: ['prev', 'page', 'next', 'skip', 'limit'],
                        jump: function (obj, first) {
                            if (first) { return; }
                            load(obj.curr, obj.limit);
                        }
                    });
                }

                function load(page, limit) {
                    if (page) { state.page = page; }
                    if (limit) { state.limit = limit; }
                    var data = getFilters();
                    data.page = state.page;
                    data.limit = state.limit;
                    // itemlist 返回 {total, rows, ...} 裸 JSON（无 FastAdmin 的 code 包裹），
                    // 不能用 Fast.api.ajax（它只认 code===1），这里直接 $.ajax
                    $.ajax({
                        url: Fast.api.fixurl('kuerp/sales_recon/itemlist'),
                        type: 'POST',
                        dataType: 'json',
                        data: data,
                        success: function (res) {
                            render(res);
                        },
                        error: function () {
                            Toastr.error('数据加载失败');
                        }
                    });
                }

                // 展开/收起商品明细（点复选框不触发展开）
                $('#items-list').on('click', '.recon-doc-head', function (e) {
                    if ($(e.target).is('input')) { return; }
                    var $doc = $(this).closest('.recon-doc');
                    $doc.toggleClass('is-open');
                    $doc.find('.recon-doc-body').toggle($doc.hasClass('is-open'));
                });

                $('#items-list').on('change', '.doc-check', function () {
                    $(this).closest('.recon-doc').toggleClass('is-selected', this.checked);
                    refreshBar();
                });

                $('#check-all').on('change', function () {
                    var checked = $(this).prop('checked');
                    $('#items-list .doc-check:not(:disabled)').prop('checked', checked).trigger('change');
                });

                $('#search').on('click', function () {
                    load(1);
                });

                $('#btn-refresh').on('click', function () {
                    load(state.page);
                });

                $('#btn-generate').on('click', function () {
                    var keys = [];
                    $('#items-list .doc-check:checked').each(function () {
                        var v = $(this).val();
                        if (v && keys.indexOf(v) < 0) {
                            keys.push(v);
                        }
                    });
                    if (!keys.length) {
                        Layer.confirm('未勾选单据，将按当前筛选结果生成对账单，是否继续？', function (index) {
                            Layer.close(index);
                            doGenerate([], true);
                        });
                        return;
                    }
                    doGenerate(keys, false);
                });

                function doGenerate(keys, all) {
                    var filters = getFilters();
                    var data = {
                        docs: keys.join(','),
                        all: all ? 1 : 0,
                        period_start: filters.date_start || '',
                        period_end: filters.date_end || '',
                        date_start: filters.date_start || '',
                        date_end: filters.date_end || '',
                        customers_id: filters.customers_id || '',
                        sale_user: filters.sale_user || '',
                        doc_type: filters.doc_type || '',
                        f_out_status: filters.out_status || '',
                        f_recon_status: filters.recon_status || '',
                        f_invoice_status: filters.invoice_status || '',
                        f_pay_status: filters.pay_status || ''
                    };
                    Fast.api.ajax({url: 'kuerp/sales_recon/add', data: data}, function (res) {
                        var list = (res.data && res.data.list) ? res.data.list : [];
                        var names = list.map(function (v) { return v.recon_code; }).join('、');
                        Toastr.success('已生成 ' + list.length + ' 张对账单：' + names);
                        Fast.api.addtabs('kuerp/sales_recon', '销货对账');
                    });
                }

                load(1);
            });
        },

        edit: function () {
            $('#check-all').on('change', function () {
                $('.doc-check').prop('checked', $(this).prop('checked'));
            });
            $('#btn-save').on('click', function () {
                var keys = [];
                $('.doc-check:checked').each(function () {
                    keys.push($(this).val());
                });
                if (!keys.length) {
                    Toastr.error('请至少选择一张单据');
                    return;
                }
                var btn = $(this).prop('disabled', true);
                Fast.api.ajax({
                    url: 'kuerp/sales_recon/edit?ids=' + Config.reconId,
                    data: {docs: keys.join(','), remark: $('#remark').val()}
                }, function () {
                    if (parent && parent.$) {
                        parent.$('.btn-refresh').click();
                    }
                    Fast.api.close();
                }, function () {
                    btn.prop('disabled', false);
                });
            });
        },

        invoice: function () {
            var enclosureId = 0;
            var $file = $('#invoice-file');
            var pd = parseInt(Config.set.price_decimal.value);
            if (isNaN(pd)) { pd = 2; }

            // 账期天数 → 预览收款到期日（开票日 + 账期天数）
            function refreshDuePreview() {
                var days = parseInt($('#invoice-term-days').val(), 10);
                if (isNaN(days) || days <= 0) {
                    $('#due-preview').text('—');
                    return;
                }
                var base = new Date(($('#invoice-date').val() || '').replace(/-/g, '/'));
                if (isNaN(base.getTime())) {
                    base = new Date();
                }
                base.setDate(base.getDate() + days);
                var y = base.getFullYear();
                var m = ('0' + (base.getMonth() + 1)).slice(-2);
                var d = ('0' + base.getDate()).slice(-2);
                $('#due-preview').text(y + '-' + m + '-' + d);
            }

            $('#invoice-term-days, #invoice-date').on('input change', refreshDuePreview);
            refreshDuePreview();

            $file.on('change', function () {
                if (!this.files || !this.files.length) {
                    return;
                }
                var formData = new FormData();
                formData.append('file', this.files[0]);
                $('#invoice-file-tip').text('上传中...');
                $.ajax({
                    url: Fast.api.fixurl('kuerp/sales_recon/uploadfile/ids/' + Config.reconId),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (res) {
                        if (res.code === 1) {
                            enclosureId = res.data.id;
                            $('#invoice-file-tip').text('已上传：' + res.data.name);
                        } else {
                            $('#invoice-file-tip').text(res.msg || '上传失败');
                            Toastr.error(res.msg || '上传失败');
                        }
                    },
                    error: function () {
                        $('#invoice-file-tip').text('上传失败');
                    }
                });
            });

            $('#btn-add-invoice').on('click', function () {
                var no = $.trim($('#invoice-no').val());
                var amount = parseFloat($('#invoice-amount').val());
                if (!enclosureId) {
                    Toastr.error('请先上传发票文件');
                    return;
                }
                if (!no) {
                    Toastr.error('请填写发票号');
                    return;
                }
                if (!(amount > 0)) {
                    Toastr.error('开票金额必须大于 0');
                    return;
                }
                var btn = $(this).prop('disabled', true);
                Fast.api.ajax({
                    url: 'kuerp/sales_recon/invoice/ids/' + Config.reconId,
                    data: {
                        enclosure_id: enclosureId,
                        invoice_no: no,
                        invoice_amount: amount,
                        invoice_date: $('#invoice-date').val(),
                        term_days: $('#invoice-term-days').val()
                    }
                }, function () {
                    if (parent && parent.$) {
                        parent.$('.btn-refresh').click();
                    }
                    location.reload();
                }, function () {
                    btn.prop('disabled', false);
                });
            });

            $('.btn-del').on('click', function () {
                var id = $(this).data('id');
                Layer.confirm('确认删除该发票？删除后会同步回退开票台账。', function (index) {
                    Layer.close(index);
                    Fast.api.ajax({url: 'kuerp/sales_recon/delinvoice?ids=' + id}, function () {
                        if (parent && parent.$) {
                            parent.$('.btn-refresh').click();
                        }
                        location.reload();
                    });
                });
            });
        },

        // 对账差异金额：上传附件 + 保存 + 删除
        diff: function () {
            var uploaded = [];

            function esc(s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                    return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
                });
            }

            function renderFiles() {
                var html = '';
                uploaded.forEach(function (f, i) {
                    html += '<span style="display:inline-block;margin-right:10px;">'
                        + (f.is_image == 1
                            ? '<img src="' + esc(f.url) + '" style="height:36px;vertical-align:middle;margin-right:4px;">'
                            : '<i class="fa fa-file-o" style="margin-right:4px;"></i>')
                        + '<span>' + esc(f.name) + '</span>'
                        + ' <a href="javascript:;" class="btn-rm-file" data-idx="' + i + '" style="color:#ff5722;">&times;</a>'
                        + '</span>';
                });
                $('#diff-file-list').html(html);
            }

            $('#diff-file').on('change', function () {
                var files = this.files;
                if (!files || !files.length) {
                    return;
                }
                var pending = files.length;
                $('#diff-file-tip').text('上传中...');
                for (var i = 0; i < files.length; i++) {
                    (function (file) {
                        var formData = new FormData();
                        formData.append('file', file);
                        $.ajax({
                            url: Fast.api.fixurl('kuerp/sales_recon/uploaddiff'),
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            dataType: 'json',
                            success: function (res) {
                                if (res.code === 1) {
                                    uploaded.push(res.data);
                                } else {
                                    Toastr.error(res.msg || '上传失败');
                                }
                            },
                            error: function () {
                                Toastr.error('上传失败');
                            },
                            complete: function () {
                                pending--;
                                if (pending <= 0) {
                                    $('#diff-file-tip').text(uploaded.length ? ('已上传 ' + uploaded.length + ' 个文件') : '');
                                    $('#diff-file').val('');
                                    renderFiles();
                                }
                            }
                        });
                    })(files[i]);
                }
            });

            $(document).on('click', '.btn-rm-file', function () {
                uploaded.splice(parseInt($(this).data('idx')), 1);
                renderFiles();
            });

            $('#btn-add-diff').on('click', function () {
                var docCode = $('#diff-doc').val();
                var money = parseFloat($('#diff-money').val());
                var reason = $.trim($('#diff-reason').val());
                if (!docCode) {
                    Toastr.error('请选择单据');
                    return;
                }
                if (isNaN(money) || Math.abs(money) < 0.0001) {
                    Toastr.error('差异金额不能为空或 0');
                    return;
                }
                if (!reason) {
                    Toastr.error('请填写差异原因');
                    return;
                }
                var btn = $(this).prop('disabled', true);
                Fast.api.ajax({
                    url: 'kuerp/sales_recon/diff/ids/' + Config.reconId,
                    data: {
                        doc_code: docCode,
                        diff_money: money,
                        reason: reason,
                        enclosure_ids: uploaded.map(function (f) { return f.id; }).join(',')
                    }
                }, function () {
                    if (parent && parent.$) {
                        parent.$('.btn-refresh').click();
                    }
                    location.reload();
                }, function () {
                    btn.prop('disabled', false);
                });
            });

            $('.btn-del-diff').on('click', function () {
                var id = $(this).data('id');
                Layer.confirm('确认删除该差异记录？删除后应收金额会同步回退。', function (index) {
                    Layer.close(index);
                    Fast.api.ajax({url: 'kuerp/sales_recon/deldiff?ids=' + id}, function () {
                        if (parent && parent.$) {
                            parent.$('.btn-refresh').click();
                        }
                        location.reload();
                    });
                });
            });
        }
    };

    return Controller;
});
