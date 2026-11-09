define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            layui.use(['table', 'form'], function () {
                var table = layui.table;

                var set = Config.set;
                var number_decimal = parseInt(set.number_decimal.value);
                if (isNaN(number_decimal)) { number_decimal = 1; }
                var price_decimal = parseInt(set.price_decimal.value);
                if (isNaN(price_decimal)) { price_decimal = 2; }

                function money(v) {
                    return '￥' + (parseFloat(v) || 0).toFixed(price_decimal);
                }

                table.render({
                    elem: '#table'
                    , url: 'kuerp/purchase_recon/index'
                    , parseData: function (res) {
                        return {"code": 0, "msg": '', "count": res.total, "data": res.rows};
                    }
                    , toolbar: '#thisToolbar'
                    , page: true
                    , cellMinWidth: 100
                    , cols: [[
                        {field: 'recon_code', title: '对账单号', width: 150, operate: 'LIKE'},
                        {field: 'supplyer_code', title: '供应商', templet: function (d) {
                            return (d.supplyer_name || '') + (d.supplyer_code ? ' <span style="color:#999">' + d.supplyer_code + '</span>' : '');
                        }},
                        {field: 'period_start', title: '对账期间', width: 180, templet: function (d) {
                            var s = d.period_start ? layui.util.toDateString(d.period_start * 1000, 'yyyy-MM-dd') : '';
                            var e = d.period_end ? layui.util.toDateString(d.period_end * 1000, 'yyyy-MM-dd') : '';
                            return s + (s || e ? ' 至 ' : '') + e;
                        }},
                        {field: 'purchase_count', title: '单据数', width: 110, templet: function (d) {
                            return '采购 ' + (d.purchase_count || 0) + ' / 退 ' + (d.return_count || 0);
                        }},
                        {field: 'total_number', title: '数量净额', width: 100, templet: function (d) {
                            return (parseFloat(d.total_number) || 0).toFixed(number_decimal);
                        }},
                        {field: 'total_money', title: '对账净额', width: 120, templet: function (d) {
                            var v = parseFloat(d.total_money) || 0;
                            return '<span style="color:' + (v < 0 ? '#ff5722' : '#333') + '">' + money(v) + '</span>';
                        }},
                        {field: 'status', title: '对账状态', width: 110, templet: function (d) {
                            if (d.status == '0') return '<span style="color:#999">待对账</span>';
                            if (d.status == '1') return '<span style="color:#1E9FFF">待财务审核</span>';
                            if (d.status == '2') return '<span style="color:#ffb800">待二审</span>';
                            if (d.status == '3') return '<span style="color:#5fb878">已对账</span>';
                            return '<span style="color:#ff5722">已驳回</span>';
                        }},
                        {field: 'invoice_status', title: '开票', width: 90, templet: function (d) {
                            if (d.invoice_status == '2') return '<span style="color:#5fb878">已开票</span>';
                            if (d.invoice_status == '1') return '<span style="color:#ffb800">部分开票</span>';
                            return '<span style="color:#999">未开票</span>';
                        }},
                        {field: 'pay_status', title: '付款', width: 100, templet: function (d) {
                            if (d.pay_status == '2') return '<span style="color:#5fb878">已付款</span>';
                            if (d.pay_status == '1') return '<span style="color:#1E9FFF">付款申请中</span>';
                            return '<span style="color:#999">未付款</span>';
                        }},
                        {field: 'due_date', title: '付款到期日', width: 160, templet: function (d) {
                            if (!d.due_date_text) return '<span style="color:#ccc">—</span>';
                            if (d.term_state == 3) return '<span style="color:#5fb878">' + d.due_date_text + '（已付款）</span>';
                            if (d.term_state == 2) return '<span style="color:#ff5722;font-weight:bold">' + d.due_date_text + '（逾期' + Math.abs(d.days_left) + '天）</span>';
                            if (d.term_state == 1) return '<span style="color:#ffb800;font-weight:bold">' + d.due_date_text + '（剩' + d.days_left + '天）</span>';
                            return d.due_date_text + (d.days_left !== null ? '（剩' + d.days_left + '天）' : '');
                        }},
                        {field: 'apply_code', title: '付款申请单', width: 150, templet: function (d) {
                            return d.apply_code ? d.apply_code : '<span style="color:#ccc">—</span>';
                        }},
                        {field: 'preparation_name', title: '制单人', width: 90},
                        {fixed: 'right', title: '操作', toolbar: '#barDemoRecon', width: 360}
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
                        if (value != '') {
                            obj[$(this).attr("name")] = value;
                        }
                    });
                    return obj;
                }

                function getOp() {
                    var obj = {};
                    $(".layui-search [name]").each(function () {
                        var value = $(this).val();
                        if (value != '') {
                            obj[$(this).attr("name")] = '=';
                        }
                    });
                    obj.recon_code = 'LIKE';
                    return obj;
                }

                function exportRecon(id) {
                    var $form = $('<form method="POST" target="_blank" style="display:none;"></form>')
                        .attr('action', Fast.api.fixurl('kuerp/purchase_recon/export'));
                    $form.append('<input type="hidden" name="ids" value="' + id + '">');
                    var token = $("input[name='__token__']").val();
                    if (token) {
                        $form.append('<input type="hidden" name="__token__" value="' + token + '">');
                    }
                    $('body').append($form);
                    $form[0].submit();
                    setTimeout(function () { $form.remove(); }, 2000);
                }

                table.on('toolbar(table)', function (obj) {
                    if (obj.event === 'refresh') {
                        table.reload('table', []);
                    } else if (obj.event === 'add') {
                        Fast.api.open('kuerp/purchase_recon/add', '新建采购对账单', {
                            area: ['90%', '85%'],
                            callback: function () { reloadTable(); }
                        });
                    }
                });

                table.on('tool(table)', function (obj) {
                    var data = obj.data;
                    if (obj.event === 'detail') {
                        Fast.api.open('kuerp/purchase_recon/detail/ids/' + data.id, '采购对账单详情', {
                            area: ['90%', '88%']
                        });
                    } else if (obj.event === 'edit') {
                        Fast.api.open('kuerp/purchase_recon/edit/ids/' + data.id, '编辑采购对账单', {
                            area: ['90%', '85%'],
                            callback: function () { reloadTable(); }
                        });
                    } else if (obj.event === 'submit') {
                        Layer.confirm('确定提交对账单[' + data.recon_code + ']给财务审核吗？', {icon:3, title:'提示'}, function (index) {
                            Layer.close(index);
                            Fast.api.ajax({url: 'kuerp/purchase_recon/submit', data: {ids: data.id}}, function () {
                                reloadTable();
                            });
                        });
                    } else if (obj.event === 'audit') {
                        Fast.api.open('kuerp/purchase_recon/audit/ids/' + data.id, '财务一审', {
                            area: ['82%', '86%'],
                            callback: function () { reloadTable(); }
                        });
                    } else if (obj.event === 'secondaudit') {
                        Fast.api.open('kuerp/purchase_recon/secondaudit/ids/' + data.id, '二审', {
                            area: ['82%', '86%'],
                            callback: function () { reloadTable(); }
                        });
                    } else if (obj.event === 'invoice') {
                        Fast.api.open('kuerp/purchase_recon/invoice/ids/' + data.id, '上传发票 / 账期记录', {
                            area: ['75%', '80%'],
                            callback: function () { reloadTable(); }
                        });
                    } else if (obj.event === 'applypay') {
                        Fast.api.open('kuerp/purchase_payment_apply/add?recon_id=' + data.id, '采购付款申请', {
                            area: ['90%', '85%'],
                            callback: function () { reloadTable(); }
                        });
                    } else if (obj.event === 'revoke') {
                        Layer.confirm('确定撤销对账单[' + data.recon_code + ']吗？撤销后所含单据解锁，需重新提交审核。', {icon:3, title:'提示'}, function (index) {
                            Layer.close(index);
                            Fast.api.ajax({url: 'kuerp/purchase_recon/revoke', data: {ids: data.id}}, function () {
                                reloadTable();
                            });
                        });
                    } else if (obj.event === 'export') {
                        exportRecon(data.id);
                    } else if (obj.event === 'print') {
                        Fast.api.open('kuerp/purchase_recon/printpage/ids/' + data.id, '打印采购对账单', {
                            area: ['90%', '85%']
                        });
                    } else if (obj.event === 'del') {
                        Layer.confirm('确定删除对账单[' + data.recon_code + ']吗？删除后勾选的单据会恢复为未对账', {icon:3, title:'提示'}, function (index) {
                            Layer.close(index);
                            Fast.api.ajax({url: 'kuerp/purchase_recon/del', data: {ids: data.id}}, function () {
                                reloadTable();
                            });
                        });
                    }
                });

                $('#search').on('click', reloadTable);
            });
        },

        // 全供应商单据列表（采购单 + 退货单）
        purchases: function () {
            layui.use(['table', 'form'], function () {
                var table = layui.table;
                var price_decimal = parseInt(Config.set.price_decimal.value);
                if (isNaN(price_decimal)) { price_decimal = 2; }
                var number_decimal = parseInt(Config.set.number_decimal.value);
                if (isNaN(number_decimal)) { number_decimal = 1; }

                function money(v) {
                    return '￥' + (parseFloat(v) || 0).toFixed(price_decimal);
                }
                function docTypeText(d) {
                    return d.doc_type == '2' ? '<span style="color:#ff5722">退货单</span>' : '采购单';
                }
                function reconStateText(d) {
                    if (!d.recon_id) return '<span style="color:#999">未对账</span>';
                    var map = {'1': ['待财务审核', '#1E9FFF'], '2': ['待二审', '#ffb800'], '3': ['已对账', '#5fb878'], '4': ['已驳回', '#ff5722']};
                    var item = map[d.recon_status] || [d.recon_status_text || '', '#333'];
                    return '<span style="color:' + item[1] + '">' + item[0] + '</span><br><span style="color:#999;font-size:12px;">' + (d.recon_code || '') + '</span>';
                }
                function invoiceText(d) {
                    if (d.invoice_status == '2') return '<span style="color:#5fb878">已开票</span>';
                    if (d.invoice_status == '1') return '<span style="color:#ffb800">部分开票</span>';
                    return '<span style="color:#999">未开票</span>';
                }
                function payText(d) {
                    if (d.doc_type == '2') {
                        if (d.pay_status == '1') return '<span style="color:#5fb878">已退款</span>';
                        if (d.pay_status == '2') return '<span style="color:#ffb800">部分退款</span>';
                        return '<span style="color:#999">未退款</span>';
                    }
                    if (d.pay_status == '2') return '<span style="color:#5fb878">已付款</span>';
                    if (d.pay_status == '1') return '<span style="color:#1E9FFF">付款申请中</span>';
                    return '<span style="color:#999">未付款</span>';
                }

                table.render({
                    elem: '#table'
                    , url: 'kuerp/purchase_recon/purchases'
                    , parseData: function (res) {
                        return {"code": 0, "msg": '', "count": res.total, "data": res.rows};
                    }
                    , toolbar: '#thisToolbar'
                    , page: true
                    , cellMinWidth: 100
                    , cols: [[
                        {field: 'doc_type', title: '单据类型', width: 90, templet: docTypeText},
                        {field: 'doc_time_text', title: '单据日期', width: 110},
                        {field: 'doc_code', title: '单据编号', width: 150},
                        {field: 'supplyer_name', title: '供应商', templet: function (d) {
                            return (d.supplyer_name || '') + (d.supplyer_code ? ' <span style="color:#999">' + d.supplyer_code + '</span>' : '');
                        }},
                        {field: 'number', title: '数量', width: 100, templet: function (d) {
                            var v = parseFloat(d.number) || 0;
                            return '<span style="color:' + (v < 0 ? '#ff5722' : '#333') + '">' + v.toFixed(number_decimal) + '</span>';
                        }},
                        {field: 'real_money', title: '金额', width: 120, templet: function (d) {
                            var v = parseFloat(d.real_money) || 0;
                            return '<span style="color:' + (v < 0 ? '#ff5722' : '#333') + '">' + money(v) + '</span>';
                        }},
                        {field: 'storage_status', title: '入库状态', width: 100, templet: function (d) {
                            if (d.doc_type == '2') return '<span style="color:#ccc">—</span>';
                            if (d.storage_status == 2) return '<span style="color:#5fb878">已入库</span>';
                            if (d.storage_status == 1) return '<span style="color:#ffb800">部分入库</span>';
                            return '<span style="color:#999">未入库</span>';
                        }},
                        {field: 'recon_status', title: '对账状态', width: 130, templet: reconStateText},
                        {field: 'invoice_status', title: '开票状态', width: 100, templet: invoiceText},
                        {field: 'pay_status', title: '付款状态', width: 110, templet: payText},
                        {field: 'pay_money', title: '已付/已退', width: 120, templet: function (d) {
                            var v = parseFloat(d.pay_money) || 0;
                            return '<span style="color:' + (v < 0 ? '#ff5722' : '#333') + '">' + money(v) + '</span>';
                        }},
                        {fixed: 'right', title: '操作', toolbar: '#barDemoPurchaseList', width: 130}
                    ]]
                    , done: function () {
                        $(".layui-table-main tr").each(function (index, val) {
                            $(".layui-table-fixed").each(function () {
                                $($(this).find(".layui-table-body tbody tr")[index]).height($(val).height());
                            });
                        });
                    }
                });

                function buildFilter() {
                    var filter = {};
                    var start = $('#filter_start').val();
                    var end = $('#filter_end').val();
                    if (start && end) { filter.purchase_time = start + ' - ' + end; }
                    if ($('#purchase_code').val()) { filter.purchase_code = $.trim($('#purchase_code').val()); }
                    if ($('#supplyer_code').val()) { filter.supplyer_code = $('#supplyer_code').val(); }
                    if ($('#doc_type').val() !== '') { filter.doc_type = $('#doc_type').val(); }
                    if ($('#recon_state').val() !== '') { filter.recon_state = $('#recon_state').val(); }
                    if ($('#invoice_state').val() !== '') { filter.invoice_state = $('#invoice_state').val(); }
                    if ($('#pay_state').val() !== '') { filter.pay_state = $('#pay_state').val(); }
                    return filter;
                }

                function reload() {
                    table.reload('table', {
                        where: {filter: JSON.stringify(buildFilter())},
                        page: {curr: 1}
                    });
                }

                table.on('toolbar(table)', function (obj) {
                    if (obj.event === 'refresh') {
                        table.reload('table', []);
                    } else if (obj.event === 'add') {
                        Fast.api.open('kuerp/purchase_recon/add', '新建采购对账单', {
                            area: ['90%', '85%'],
                            callback: function () { table.reload('table', []); }
                        });
                    }
                });

                table.on('tool(table)', function (obj) {
                    if (obj.event === 'recon' && obj.data.recon_id) {
                        Fast.api.open('kuerp/purchase_recon/detail/ids/' + obj.data.recon_id, '采购对账单详情', {
                            area: ['90%', '88%']
                        });
                    }
                });

                $('#search').on('click', reload);
            });
        },

        // 供应商待付款统计
        supplierstat: function () {
            layui.use(['table', 'form'], function () {
                var table = layui.table;
                var price_decimal = parseInt(Config.set.price_decimal.value);
                if (isNaN(price_decimal)) { price_decimal = 2; }
                function money(v) { return '￥' + (parseFloat(v) || 0).toFixed(price_decimal); }

                table.render({
                    elem: '#table'
                    , url: 'kuerp/purchase_recon/supplierstat'
                    , parseData: function (res) {
                        var s = res.summary || {};
                        $('#stat-summary').html(
                            '采购额合计：<b style="color:#333;">' + money(s.total_money) + '</b>　' +
                            '已对账：<b style="color:#5fb878;">' + money(s.reconed_money) + '</b>　' +
                            '未对账：<b style="color:#999;">' + money(s.unreconed_money) + '</b>　' +
                            '已开票：<b style="color:#1E9FFF;">' + money(s.invoiced_money) + '</b>　' +
                            '待开票：<b style="color:#999;">' + money(s.uninvoiced_money) + '</b>　' +
                            '已付款：<b style="color:#5fb878;">' + money(s.paid_money) + '</b>　' +
                            '待付款：<b style="color:#ff5722;">' + money(s.unpaid_money) + '</b>　' +
                            '逾期：<b style="color:#ff5722;">' + money(s.overdue_money) + '</b>'
                        );
                        return {"code": 0, "msg": '', "count": res.total, "data": res.rows};
                    }
                    , toolbar: '#thisToolbar'
                    , page: false
                    , cellMinWidth: 100
                    , cols: [[
                        {field: 'supplyer_name', title: '供应商', templet: function (d) {
                            return (d.supplyer_name || '') + ' <span style="color:#999">' + (d.supplyer_code || '') + '</span>';
                        }},
                        {field: 'purchase_count', title: '采购单数', width: 90},
                        {field: 'return_count', title: '退货单数', width: 90},
                        {field: 'total_money', title: '采购额合计(净额)', width: 150, templet: function (d) { return money(d.total_money); }},
                        {field: 'reconed_money', title: '已对账', width: 120, templet: function (d) { return money(d.reconed_money); }},
                        {field: 'unreconed_money', title: '未对账', width: 120, templet: function (d) { return money(d.unreconed_money); }},
                        {field: 'invoiced_money', title: '已开票', width: 120, templet: function (d) { return money(d.invoiced_money); }},
                        {field: 'uninvoiced_money', title: '待开票', width: 120, templet: function (d) { return money(d.uninvoiced_money); }},
                        {field: 'paid_money', title: '已付款(净额)', width: 140, templet: function (d) { return money(d.paid_money); }},
                        {field: 'unpaid_money', title: '待付款', width: 120, templet: function (d) {
                            return '<span style="color:#ff5722;font-weight:bold">' + money(d.unpaid_money) + '</span>';
                        }},
                        {field: 'overdue_money', title: '逾期金额', width: 120, templet: function (d) {
                            return (parseFloat(d.overdue_money) || 0) > 0 ? '<span style="color:#ff5722">' + money(d.overdue_money) + '</span>' : money(0);
                        }}
                    ]]
                });

                function reload() {
                    var filter = {};
                    var start = $('#filter_start').val();
                    var end = $('#filter_end').val();
                    if (start && end) { filter.purchase_time = start + ' - ' + end; }
                    if ($('#supplyer_code').val()) { filter.supplyer_code = $('#supplyer_code').val(); }
                    table.reload('table', {where: {filter: JSON.stringify(filter)}, page: {curr: 1}});
                }
                table.on('toolbar(table)', function (obj) {
                    if (obj.event === 'refresh') { table.reload('table', []); }
                });
                $('#search').on('click', reload);
            });
        },

        add: function () {
            Controller.api.bindevent();
        },

        edit: function () {
            Controller.api.bindevent();
        },

        detail: function () {
            var box = $('#recon-detail');
            if (!box.length) return;
            var reconId = parseInt(box.data('recon-id')) || 0;
            // ⚠️ 上载地址必须在 JS 里拼 ids：{:url('.../uploadfile/ids/' . $id)} 会被 TP5 当成
            // module/controller/action 三段解析，丢掉 kuerp/purchase_recon 前缀（得到 /uploadfile/ids/N → 404）
            var uploadUrl = (box.data('upload-url') || '').replace(/\/+$/, '') + '/ids/' + reconId;
            var delUrl = box.data('del-url');

            function escapeHtml(s) {
                return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            }

            function fileHtml(f) {
                var url = escapeHtml(f.url);
                var suffix = escapeHtml((f.file_suffix || '').toLowerCase());
                var inner;
                if (parseInt(f.is_image, 10) === 1) {
                    inner = '<img src="' + url + '" style="max-height:40px;vertical-align:middle;">';
                } else {
                    inner = '<img src="' + Fast.api.fixurl('ajax/icon') + '?suffix=' + suffix + '" style="max-height:28px;vertical-align:middle;">' +
                        '<span style="margin-left:4px;">' + suffix.toUpperCase() + '</span>';
                }
                return '<span class="recon-file-item" data-id="' + parseInt(f.id, 10) + '" style="display:inline-block;margin:0 16px 8px 0;">' +
                    '<a href="' + url + '" target="_blank">' + inner + '<span style="margin-left:6px;">' + escapeHtml(f.name) + '</span></a>' +
                    '<a href="javascript:;" class="file-del" data-id="' + parseInt(f.id, 10) + '" style="color:#ff5722;margin-left:6px;font-weight:bold;">×</a></span>';
            }

            $('#btn-upload-recon-file').on('click', function () {
                $('#recon-file-input').click();
            });

            $('#recon-file-input').on('change', function () {
                if (!this.files || !this.files.length) return;
                var fd = new FormData();
                fd.append('file', this.files[0]);
                fd.append('type', '8');
                var input = this;
                $.ajax({
                    url: uploadUrl,
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (res) {
                        input.value = '';
                        if (res.code === 1 && res.data) {
                            $('#recon-file-list').prepend(fileHtml(res.data));
                        } else {
                            Layer.msg(res.msg || '上传失败');
                        }
                    },
                    error: function () {
                        input.value = '';
                        Layer.msg('上传失败，请重试');
                    }
                });
            });

            $(document).on('click', '.file-del', function () {
                var id = $(this).data('id');
                var span = $(this).closest('.recon-file-item');
                Layer.confirm('确定删除该文件吗？', {icon: 3, title: '提示'}, function (index) {
                    Layer.close(index);
                    var opts = {};
                    opts.url = delUrl + '/ids/' + id;
                    opts.type = 'POST';
                    Fast.api.ajax(opts, function () {
                        if (span.length) span.remove();
                        setTimeout(function () { location.reload(); }, 400);
                    });
                });
            });

            $('#btn-submit').on('click', function () {
                Layer.confirm('确定提交给财务审核吗？提交前会按源单据刷新金额。', {icon: 3, title: '提示'}, function (index) {
                    Layer.close(index);
                    var opts = {};
                    opts.url = 'kuerp/purchase_recon/submit';
                    opts.data = {ids: reconId};
                    Fast.api.ajax(opts, function () { location.reload(); });
                });
            });

            function openStep(action, title) {
                Fast.api.open('kuerp/purchase_recon/' + action + '/ids/' + reconId, title, {
                    area: ['82%', '86%'],
                    callback: function () { setTimeout(function () { location.reload(); }, 300); }
                });
            }

            $('#btn-audit').on('click', function () { openStep('audit', '财务一审'); });
            $('#btn-secondaudit').on('click', function () { openStep('secondaudit', '二审'); });

            $('#btn-invoice').on('click', function () {
                Fast.api.open('kuerp/purchase_recon/invoice/ids/' + reconId, '上传发票 / 账期记录', {
                    area: ['75%', '80%'],
                    callback: function () { setTimeout(function () { location.reload(); }, 300); }
                });
            });

            $('#btn-applypay').on('click', function () {
                Fast.api.open('kuerp/purchase_payment_apply/add?recon_id=' + reconId, '采购付款申请', {
                    area: ['90%', '85%'],
                    callback: function () { setTimeout(function () { location.reload(); }, 300); }
                });
            });

            $('#btn-revoke').on('click', function () {
                Layer.confirm('确定撤销对账吗？撤销后单据解锁，需要重新提交审核。', {icon: 3, title: '提示'}, function (index) {
                    Layer.close(index);
                    var opts = {};
                    opts.url = 'kuerp/purchase_recon/revoke';
                    opts.data = {ids: reconId};
                    Fast.api.ajax(opts, function () { location.reload(); });
                });
            });

            $('#btn-export').on('click', function () {
                var $form = $('<form method="POST" target="_blank" style="display:none;"></form>')
                    .attr('action', Fast.api.fixurl('kuerp/purchase_recon/export'));
                $form.append('<input type="hidden" name="ids" value="' + reconId + '">');
                var token = $("input[name='__token__']").val();
                if (token) {
                    $form.append('<input type="hidden" name="__token__" value="' + token + '">');
                }
                $('body').append($form);
                $form[0].submit();
                setTimeout(function () { $form.remove(); }, 2000);
            });

            $('#btn-print').on('click', function () {
                Fast.api.open('kuerp/purchase_recon/printpage/ids/' + reconId, '打印采购对账单', {
                    area: ['90%', '85%']
                });
            });
        },

        audit: function () {
            var form = $('#audit-form');
            if (!form.length) return;
            $('input[name=result]').on('change', function () {
                $('#remark-wrap').toggle($('input[name=result]:checked').val() == '2');
            }).trigger('change');
            // 对账单明细默认折叠，点标题展开/收起（一审、二审共用本视图）
            $('#recon-items-toggle').on('click', function () {
                var $body = $('#recon-items-body');
                var willOpen = !$body.is(':visible');
                $body.toggle(willOpen);
                $('#recon-items-caret')
                    .toggleClass('glyphicon-chevron-down', willOpen)
                    .toggleClass('glyphicon-chevron-right', !willOpen);
            });
            Form.api.bindevent(form);
        },

        secondaudit: function () {
            Controller.audit();
        },

        invoice: function () {
            var form = $('#invoice-form');
            if (!form.length) return;
            var reconId = form.data('recon-id');

            function refreshDue() {
                var days = parseInt($('#term_days').val()) || 0;
                var d = new Date();
                d.setHours(0, 0, 0, 0);
                d.setDate(d.getDate() + days);
                $('#due-preview').text(d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2));
            }
            $('#term_days').on('input change', refreshDue);
            refreshDue();

            $('#invoice_file').on('change', function () {
                var input = this;
                if (!input.files || !input.files.length) return;
                var fd = new FormData();
                fd.append('file', input.files[0]);
                fd.append('type', '9');
                $('#upload-tip').text('上传中...');
                $.ajax({
                    url: Fast.api.fixurl('kuerp/purchase_recon/uploadfile/ids/' + reconId),
                    type: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function (res) {
                        if (res.code === 1 && res.data) {
                            $('#enclosure_id').val(res.data.id);
                            $('#upload-tip').html('已上传：<a href="' + res.data.url + '" target="_blank">' + res.data.name + '</a>');
                        } else {
                            $('#upload-tip').text(res.msg || '上传失败');
                            input.value = '';
                        }
                    },
                    error: function () {
                        $('#upload-tip').text('上传失败，请重试');
                        input.value = '';
                    }
                });
            });

            Form.api.bindevent(form, null, null, function () {
                if (!$('#enclosure_id').val()) {
                    Toastr.error('请先上传发票文件');
                    return false;
                }
                if (!(parseFloat($('#invoice_amount').val()) > 0)) {
                    Toastr.error('请填写开票金额');
                    return false;
                }
            });
        },

        api: {
            bindevent: function () {
                var form = $('#recon-form');
                if (!form.length) return;

                var reconId = parseInt(form.data('recon-id')) || 0;
                var price_decimal = parseInt(Config.set.price_decimal.value);
                if (isNaN(price_decimal)) { price_decimal = 2; }
                var number_decimal = parseInt(Config.set.number_decimal.value);
                if (isNaN(number_decimal)) { number_decimal = 1; }

                var $body = $('#purchase-body');
                var $empty = $('#purchase-empty');
                var $total = $('#selected-total');
                var $count = $('#selected-count');

                function supplierCode() {
                    return $.trim($('#c-supplyer_code').val() || form.data('supplier') || '');
                }

                function updateSummary() {
                    var purchaseIds = [], exchangeIds = [], money = 0, count = 0;
                    $body.find('.doc-check:checked').each(function () {
                        count++;
                        money += parseFloat($(this).data('money')) || 0;
                        if ($(this).data('type') == '2') {
                            exchangeIds.push($(this).val());
                        } else {
                            purchaseIds.push($(this).val());
                        }
                    });
                    $total.text('￥' + money.toFixed(price_decimal));
                    $count.text(count);
                    $('#purchase_ids').val(purchaseIds.join(','));
                    $('#exchange_ids').val(exchangeIds.join(','));
                }

                function renderRows(rows) {
                    $body.empty();
                    if (!rows || !rows.length) {
                        $empty.show();
                        updateSummary();
                        return;
                    }
                    $empty.hide();
                    var html = '';
                    rows.forEach(function (row) {
                        var isReturn = row.doc_type == '2';
                        var storage = isReturn ? '<span style="color:#ccc">—</span>'
                            : (row.storage_status == 2 ? '<span style="color:#5fb878">已入库</span>'
                                : (row.storage_status == 1 ? '<span style="color:#ffb800">部分入库</span>' : '<span style="color:#999">未入库</span>'));
                        html += '<tr>'
                            + '<td><input type="checkbox" class="doc-check" value="' + row.doc_id + '" data-type="' + row.doc_type + '" data-money="' + row.real_money + '"' + (row.checked ? ' checked' : '') + '></td>'
                            + '<td>' + (isReturn ? '<span style="color:#ff5722">购货退货单</span>' : '采购单') + '</td>'
                            + '<td>' + row.doc_code + '</td>'
                            + '<td>' + row.doc_time_text + '</td>'
                            + '<td style="text-align:right;color:' + (row.number < 0 ? '#ff5722' : '#333') + '">' + (parseFloat(row.number) || 0).toFixed(number_decimal) + '</td>'
                            + '<td style="text-align:right;color:' + (row.real_money < 0 ? '#ff5722' : '#333') + '">￥' + (parseFloat(row.real_money) || 0).toFixed(price_decimal) + '</td>'
                            + '<td style="text-align:right;">￥' + (parseFloat(row.paid_money) || 0).toFixed(price_decimal) + '</td>'
                            + '<td>' + storage + '</td>'
                            + '</tr>';
                    });
                    $body.html(html);
                    updateSummary();
                }

                function loadList() {
                    var supplier = supplierCode();
                    if (!supplier) {
                        $body.empty();
                        $empty.text('请选择供应商后加载单据').show();
                        updateSummary();
                        return;
                    }
                    $empty.text('加载中...').show();
                    $.ajax({
                        url: Fast.api.fixurl('kuerp/purchase_recon/purchaselist'),
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            supplyer_code: supplier,
                            period_start: $('#c-period_start').val() || '',
                            period_end: $('#c-period_end').val() || '',
                            recon_id: reconId
                        },
                        success: function (res) {
                            renderRows(res.rows || []);
                            if (!res.rows || !res.rows.length) {
                                $empty.text('该供应商在所选期间内没有可对账的采购单/退货单').show();
                            }
                        },
                        error: function () {
                            $empty.text('单据加载失败，请重试').show();
                        }
                    });
                }

                $('#c-supplyer_code').on('change', loadList);
                $('#c-period_start, #c-period_end').on('change', loadList);
                $('#btn-load-purchase').on('click', loadList);

                $body.on('change', '.doc-check', updateSummary);
                $('#check-all').on('change', function () {
                    $body.find('.doc-check').prop('checked', $(this).prop('checked'));
                    updateSummary();
                });

                Form.api.bindevent(form, null, null, function () {
                    if (!($('#purchase_ids').val() || '') && !($('#exchange_ids').val() || '')) {
                        Toastr.error('请至少勾选一张采购单或退货单');
                        return false;
                    }
                });

                loadList();
            }
        }
    };

    return Controller;
});
