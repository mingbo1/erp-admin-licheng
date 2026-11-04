define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            layui.use(['table', 'form'], function () {
                var table = layui.table;
                var price_decimal = parseInt(Config.set.price_decimal.value);
                if (isNaN(price_decimal)) { price_decimal = 2; }

                table.render({
                    elem: '#table'
                    , url: 'kuerp/purchase_payment_apply/index'
                    , parseData: function (res) {
                        return {"code": 0, "msg": '', "count": res.total, "data": res.rows};
                    }
                    , toolbar: '#thisToolbar'
                    , page: true
                    , cellMinWidth: 100
                    , cols: [[
                        {field: 'apply_code', title: '付款申请单号', width: 160, operate: 'LIKE'},
                        {field: 'supplyer_code', title: '供应商', templet: function (d) {
                            return (d.supplyer_name || '') + (d.supplyer_code ? ' <span style="color:#999">' + d.supplyer_code + '</span>' : '');
                        }},
                        {field: 'recon_count', title: '对账单数', width: 90},
                        {field: 'total_money', title: '申请金额', width: 130, templet: function (d) {
                            return '<span style="color:#ff5722;">￥' + (parseFloat(d.total_money) || 0).toFixed(price_decimal) + '</span>';
                        }},
                        {field: 'status', title: '当前状态', width: 130, templet: function (d) {
                            var map = {
                                '0': ['草稿', '#999'],
                                '1': ['待财务审核', '#1E9FFF'],
                                '2': ['待审计审核', '#1E9FFF'],
                                '3': ['待领导审核', '#ffb800'],
                                '4': ['待出纳付款', '#ffb800'],
                                '5': ['已付款', '#5fb878'],
                                '6': ['已驳回', '#ff5722']
                            };
                            var item = map[d.status] || [d.status_text || '', '#999'];
                            return '<span style="color:' + item[1] + '">' + item[0] + '</span>';
                        }},
                        {field: 'need_leader', title: '领导审核', width: 90, templet: function (d) {
                            return d.need_leader == 1 ? '需要' : '不需要';
                        }},
                        {field: 'apply_admin_name', title: '申请人', width: 90},
                        {field: 'apply_time_text', title: '申请时间', width: 140},
                        {field: 'cashier_admin_name', title: '出纳', width: 90},
                        {field: 'pay_time_text', title: '付款时间', width: 140},
                        {fixed: 'right', title: '操作', toolbar: '#barDemoApplyPay', width: 300}
                    ]]
                    , done: function () {
                        $(".layui-table-main tr").each(function (index, val) {
                            $(".layui-table-fixed").each(function () {
                                $($(this).find(".layui-table-body tbody tr")[index]).height($(val).height());
                            });
                        });
                    }
                });

                function reloadTable() {
                    table.reload('table', {
                        where: {
                            filter: JSON.stringify(getSearch()),
                            op: JSON.stringify(getOp())
                        }
                        , page: {curr: 1}
                    });
                }

                function getSearch() {
                    var obj = {};
                    $(".layui-search [name]").each(function () {
                        var value = $(this).val();
                        if (value != '') { obj[$(this).attr("name")] = value; }
                    });
                    return obj;
                }

                function getOp() {
                    var obj = {};
                    $(".layui-search [name]").each(function () {
                        var value = $(this).val();
                        if (value != '') { obj[$(this).attr("name")] = '='; }
                    });
                    obj.apply_code = 'LIKE';
                    return obj;
                }

                table.on('toolbar(table)', function (obj) {
                    if (obj.event === 'refresh') {
                        table.reload('table', []);
                    } else if (obj.event === 'add') {
                        Fast.api.open('kuerp/purchase_payment_apply/add', '新建付款申请', {
                            area: ['90%', '85%'],
                            callback: function () { reloadTable(); }
                        });
                    }
                });

                table.on('tool(table)', function (obj) {
                    var data = obj.data;
                    var id = data.id;
                    if (obj.event === 'detail') {
                        Fast.api.open('kuerp/purchase_payment_apply/detail/ids/' + id, '付款申请详情', {area: ['90%', '88%']});
                    } else if (obj.event === 'edit') {
                        Fast.api.open('kuerp/purchase_payment_apply/edit/ids/' + id, '编辑付款申请', {
                            area: ['90%', '85%'], callback: function () { reloadTable(); }
                        });
                    } else if (obj.event === 'submit') {
                        Layer.confirm('确定提交付款申请[' + data.apply_code + ']进入审批流程吗？', {icon:3, title:'提示'}, function (index) {
                            Layer.close(index);
                            Fast.api.ajax({url: 'kuerp/purchase_payment_apply/submit', data: {ids: id}}, function () { reloadTable(); });
                        });
                    } else if (obj.event === 'finance' || obj.event === 'audit' || obj.event === 'leader') {
                        var url = obj.event === 'finance' ? 'financeexamine' : (obj.event === 'audit' ? 'auditexamine' : 'leaderexamine');
                        var title = obj.event === 'finance' ? '财务审核' : (obj.event === 'audit' ? '审计审核' : '领导审核');
                        Fast.api.open('kuerp/purchase_payment_apply/' + url + '/ids/' + id, title, {
                            area: ['60%', '55%'], callback: function () { reloadTable(); }
                        });
                    } else if (obj.event === 'pay') {
                        Fast.api.open('kuerp/purchase_payment_apply/pay/ids/' + id, '确认打款', {
                            area: ['65%', '75%'], callback: function () { reloadTable(); }
                        });
                    } else if (obj.event === 'del') {
                        Layer.confirm('确定删除付款申请[' + data.apply_code + ']吗？删除后关联的对账单会恢复为未付款', {icon:3, title:'提示'}, function (index) {
                            Layer.close(index);
                            Fast.api.ajax({url: 'kuerp/purchase_payment_apply/del', data: {ids: id}}, function () { reloadTable(); });
                        });
                    }
                });

                $('#search').on('click', reloadTable);
            });
        },

        add: function () {
            Controller.api.bindevent();
        },

        edit: function () {
            Controller.api.bindevent();
        },

        financeexamine: function () {
            Controller.api.bindExamine();
        },

        auditexamine: function () {
            Controller.api.bindExamine();
        },

        leaderexamine: function () {
            Controller.api.bindExamine();
        },

        pay: function () {
            var form = $('#pay-form');
            if (!form.length) return;
            var applyId = form.data('apply-id');

            $('#voucher_file').on('change', function () {
                var input = this;
                if (!input.files || !input.files.length) return;
                var fd = new FormData();
                fd.append('file', input.files[0]);
                $('#voucher-tip').text('上传中...');
                $.ajax({
                    url: Fast.api.fixurl('kuerp/purchase_payment_apply/uploadvoucher/ids/' + applyId),
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (res) {
                        if (res.code === 1 && res.data) {
                            $('#voucher_enclosure_id').val(res.data.id);
                            $('#voucher-tip').html('已上传：<a href="' + res.data.url + '" target="_blank">' + res.data.name + '</a>');
                        } else {
                            $('#voucher-tip').text(res.msg || '上传失败');
                            input.value = '';
                        }
                    },
                    error: function () {
                        $('#voucher-tip').text('上传失败，请重试');
                        input.value = '';
                    }
                });
            });

            Form.api.bindevent(form, null, null, function () {
                if (!$('#c-accounts_id').val()) {
                    Toastr.error('请选择付款账户');
                    return false;
                }
                if (!$('#c-pay_date').val()) {
                    Toastr.error('请选择付款日期');
                    return false;
                }
            });
        },

        detail: function () {
            var box = $('#apply-detail');
            if (!box.length) return;
            var applyId = parseInt(box.data('apply-id')) || 0;

            $('#btn-submit').on('click', function () {
                Layer.confirm('确定提交付款申请进入审批流程吗？', {icon: 3, title: '提示'}, function (index) {
                    Layer.close(index);
                    var opts = {};
                    opts.url = 'kuerp/purchase_payment_apply/submit';
                    opts.data = {ids: applyId};
                    Fast.api.ajax(opts, function () { location.reload(); });
                });
            });

            function openStep(action, title) {
                Fast.api.open('kuerp/purchase_payment_apply/' + action + '/ids/' + applyId, title, {
                    area: ['60%', '55%'],
                    callback: function () { setTimeout(function () { location.reload(); }, 300); }
                });
            }

            $('#btn-finance').on('click', function () { openStep('financeexamine', '财务审核'); });
            $('#btn-audit').on('click', function () { openStep('auditexamine', '审计审核'); });
            $('#btn-leader').on('click', function () { openStep('leaderexamine', '领导审核'); });

            // 查看关联对账单明细（权限：采购对账-详情）
            $('.btn-recon-detail').on('click', function () {
                var reconId = parseInt($(this).data('recon-id')) || 0;
                if (!reconId) {
                    Toastr.error('该行未关联对账单');
                    return;
                }
                Fast.api.open('kuerp/purchase_recon/detail/ids/' + reconId, '采购对账单详情', {
                    area: ['82%', '86%']
                });
            });
            $('#btn-pay').on('click', function () {
                Fast.api.open('kuerp/purchase_payment_apply/pay/ids/' + applyId, '确认打款', {
                    area: ['65%', '75%'],
                    callback: function () { setTimeout(function () { location.reload(); }, 300); }
                });
            });
        },

        api: {
            bindExamine: function () {
                var form = $('#step-examine-form');
                if (!form.length) return;
                $('input[name=result]').on('change', function () {
                    $('#remark-wrap').toggle($('input[name=result]:checked').val() == '2');
                }).trigger('change');
                Form.api.bindevent(form);
            },

            bindevent: function () {
                var form = $('#apply-form');
                if (!form.length) return;

                var applyId = parseInt(form.data('apply-id')) || 0;
                var presetReconId = parseInt(Config.presetReconId) || 0;
                var price_decimal = parseInt(Config.set.price_decimal.value);
                if (isNaN(price_decimal)) { price_decimal = 2; }

                var $body = $('#recon-body');
                var $empty = $('#recon-empty');
                var $total = $('#selected-total');
                var $count = $('#selected-count');

                function supplierCode() {
                    return $.trim($('#c-supplyer_code').val() || form.data('supplier') || '');
                }

                function updateSummary() {
                    var count = 0, money = 0, ids = [];
                    $body.find('.recon-check:checked').each(function () {
                        count++;
                        money += parseFloat($(this).data('money')) || 0;
                        ids.push($(this).val());
                    });
                    $total.text('￥' + money.toFixed(price_decimal));
                    $count.text(count);
                    $('#recon_ids').val(ids.join(','));
                }

                function escapeHtml(s) {
                    return String(s == null ? '' : s)
                        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                }

                function fileLink(f) {
                    if (!f || !f.url) return '';
                    var suffix = escapeHtml((f.file_suffix || '').toLowerCase());
                    var inner;
                    if (parseInt(f.is_image, 10) === 1) {
                        inner = '<img src="' + escapeHtml(f.url) + '" style="max-height:30px;vertical-align:middle;">';
                    } else {
                        inner = '<img src="' + Fast.api.fixurl('ajax/icon') + '?suffix=' + suffix + '" style="max-height:22px;vertical-align:middle;">' +
                            '<span style="margin-left:3px;">' + suffix.toUpperCase() + '</span>';
                    }
                    return '<a href="' + escapeHtml(f.url) + '" target="_blank" style="margin-right:12px;">' + inner +
                        '<span style="margin-left:4px;">' + escapeHtml(f.name) + '</span></a>';
                }

                function materialsHtml(m) {
                    var html = '<div style="background:#fafafa;padding:8px 10px;font-size:12px;text-align:left;">';
                    html += '<p style="margin-bottom:6px;"><b>对账单文件：</b>';
                    html += (m.files && m.files.length) ? m.files.map(fileLink).join('') : '<span style="color:#999">未上传</span>';
                    html += '</p>';
                    html += '<p style="margin-bottom:6px;"><b>采购合同：</b>';
                    html += m.contract ? fileLink(m.contract) : '<span style="color:#999">未上传</span>';
                    (m.orders || []).forEach(function (o) {
                        html += '<span style="margin-left:10px;color:#666;">订单' + escapeHtml(o.purchase_code) + '：</span>';
                        html += (o.files && o.files.length) ? o.files.map(fileLink).join('') : '<span style="color:#999">未上传</span>';
                    });
                    html += '</p>';
                    html += '<p style="margin-bottom:6px;"><b>入库单：</b>';
                    if (m.storages && m.storages.length) {
                        m.storages.forEach(function (st) {
                            html += '<span style="margin-left:6px;color:#666;">' + escapeHtml(st.purchase_code) + '：</span>';
                            if (st.logs && st.logs.length) {
                                st.logs.forEach(function (log) {
                                    html += '<span style="margin-right:12px;">' + escapeHtml(log.createtime) + ' / ' +
                                        escapeHtml(log.total_number) + ' 件' +
                                        ((log.files && log.files.length) ? log.files.map(function (f) {
                                            return '<span style="margin-left:4px;">' + fileLink(f) + '</span>';
                                        }).join('') : '') +
                                        '</span>';
                                });
                            } else {
                                html += '<span style="color:#999">暂无入库记录</span>';
                            }
                        });
                    } else {
                        html += '<span style="color:#999">无采购单明细</span>';
                    }
                    html += '</p>';
                    html += '<p style="margin-bottom:6px;"><b>退货单（冲减）：</b>';
                    if (m.returns && m.returns.length) {
                        m.returns.forEach(function (ret) {
                            html += '<span style="margin-right:12px;color:#ff5722;">' + escapeHtml(ret.doc_code) +
                                '（' + escapeHtml(ret.doc_time) + '，' + escapeHtml(ret.number) + ' 件，￥' +
                                (parseFloat(ret.real_money) || 0).toFixed(2) + '）</span>';
                        });
                    } else {
                        html += '<span style="color:#999">无</span>';
                    }
                    html += '</p>';
                    html += '<p style="margin-bottom:0;"><b>发票：</b>';
                    if (m.invoices && m.invoices.length) {
                        m.invoices.forEach(function (inv) {
                            html += '<span style="margin-right:14px;">' + escapeHtml(inv.invoice_no) +
                                '（￥' + (parseFloat(inv.invoice_amount) || 0).toFixed(2) + '，账期' + inv.term_days + '天）' +
                                (inv.file ? fileLink(inv.file) : '') + '</span>';
                        });
                    } else {
                        html += '<span style="color:#999">未上传</span>';
                    }
                    html += '</p></div>';
                    return html;
                }

                function renderRows(rows) {
                    $body.empty();
                    if (!rows || !rows.length) {
                        $empty.text('该供应商没有「已审核 + 已开票 + 未付款」的对账单').show();
                        updateSummary();
                        return;
                    }
                    $empty.hide();
                    var html = '';
                    rows.forEach(function (row) {
                        html += '<tr>'
                            + '<td><input type="checkbox" class="recon-check" value="' + row.id + '" data-money="' + row.total_money + '"' + ((row.checked || (presetReconId && row.id == presetReconId)) ? ' checked' : '') + '></td>'
                            + '<td>' + row.recon_code + '</td>'
                            + '<td>' + (row.period_text || '') + '</td>'
                            + '<td style="text-align:right">￥' + (parseFloat(row.total_money) || 0).toFixed(price_decimal) + '</td>'
                            + '<td>' + (row.due_date_text || '<span style="color:#ccc">—</span>') + '</td>'
                            + '<td><button type="button" class="btn btn-default btn-xs btn-mat" data-id="' + row.id + '">资料</button></td>'
                            + '</tr>';
                    });
                    $body.html(html);
                    updateSummary();
                }

                function loadList() {
                    var supplier = supplierCode();
                    if (!supplier) {
                        $body.empty();
                        $empty.text('请选择供应商后加载对账单').show();
                        updateSummary();
                        return;
                    }
                    $empty.text('加载中...').show();
                    $.ajax({
                        url: Fast.api.fixurl('kuerp/purchase_payment_apply/reconlist'),
                        type: 'POST',
                        dataType: 'json',
                        data: {supplyer_code: supplier, apply_id: applyId},
                        success: function (res) {
                            renderRows(res.rows || []);
                        },
                        error: function () {
                            $empty.text('对账单加载失败，请重试').show();
                        }
                    });
                }

                $('#c-supplyer_code').on('change', loadList);
                $('#btn-load-recon').on('click', loadList);
                $body.on('change', '.recon-check', updateSummary);
                $body.on('click', '.btn-mat', function () {
                    var id = $(this).data('id');
                    var $tr = $(this).closest('tr');
                    var $next = $tr.next('.mat-row');
                    if ($next.length) {
                        $next.toggle();
                        return;
                    }
                    var $row = $('<tr class="mat-row"><td colspan="6" style="color:#999;">资料加载中...</td></tr>');
                    $tr.after($row);
                    $.getJSON(Fast.api.fixurl('kuerp/purchase_recon/materials'), {ids: id}, function (res) {
                        var m = res && res.data ? res.data[id] : null;
                        $row.find('td').html(m ? materialsHtml(m) : '<span style="color:#999">资料加载失败</span>');
                    }).fail(function () {
                        $row.find('td').html('<span style="color:#999">资料加载失败</span>');
                    });
                });
                $('#check-all').on('change', function () {
                    $body.find('.recon-check').prop('checked', $(this).prop('checked'));
                    updateSummary();
                });

                $('#btn-save-draft').on('click', function () {
                    $('#form-action').val('save');
                });
                $('#btn-submit-apply').on('click', function () {
                    $('#form-action').val('submit');
                });

                Form.api.bindevent(form, null, null, function () {
                    if (!($('#recon_ids').val() || '')) {
                        Toastr.error('请至少勾选一张对账单');
                        return false;
                    }
                });

                loadList();
            }
        }
    };

    return Controller;
});
