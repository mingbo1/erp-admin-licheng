define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            layui.use(['table', 'laydate'], function () {
                var table = layui.table;
                var laydate = layui.laydate;
                laydate.render({ elem: '#board_date', range: true });

                // 数量列格式化(去掉多余的0)
                var fmt = function (v) {
                    var n = parseFloat(v);
                    return isNaN(n) ? '0' : String(Math.round(n * 10000) / 10000);
                };
                var pct = function (v) {
                    var n = parseFloat(v);
                    if (isNaN(n)) { n = 0; }
                    return (n * 100).toFixed(2) + '%';
                };

                table.render({
                    elem: '#table',
                    url: 'kuerp/process_board/index',
                    parseData: function (res) {
                        return { "code": 0, "msg": '', "count": res.total, "data": res.rows };
                    },
                    toolbar: '#thisToolbar',
                    page: true,
                    cellMinWidth: 100,
                    cols: [[
                        { checkbox: true },
                        { field: 'board_date', title: __('Board_date'), templet: function (d) {
                                return layui.util.toDateString(d.board_date * 1000, "yyyy-MM-dd");
                            }, width: 110 },
                        { field: 'total_target', title: __('Total_target'), templet: function (d) { return fmt(d.total_target); } },
                        { field: 'first_pass_total', title: __('First_pass_total'), templet: function (d) { return fmt(d.first_pass_total); }, style: 'color:green;' },
                        { field: 'rework_total', title: __('Rework_total'), templet: function (d) { return fmt(d.rework_total); } },
                        { field: 'qualified_total', title: __('Qualified_total'), templet: function (d) { return fmt(d.qualified_total); }, style: 'color:green;' },
                        { field: 'first_pass_yield', title: __('First_pass_yield'), templet: function (d) { return pct(d.first_pass_yield); } },
                        { field: 'final_yield', title: __('Final_yield'), templet: function (d) { return pct(d.final_yield); } },
                        { field: 'first_pass_defect', title: __('First_pass_defect'), templet: function (d) { return fmt(d.first_pass_defect); }, style: 'color:red;' },
                        { field: 'rework_defect', title: __('Rework_defect'), templet: function (d) { return fmt(d.rework_defect); }, style: 'color:red;' },
                        { field: 'note', title: __('Note') },
                        { fixed: 'right', title: __('Operate'), toolbar: '#barDemoBoard', width: 190 }
                    ]]
                });

                table.on('toolbar(table)', function (obj) {
                    var checkStatus = table.checkStatus(obj.config.id);
                    var data = checkStatus.data;
                    var ids = '';
                    $(data).each(function (k, v) { ids += v.id + ','; });
                    switch (obj.event) {
                        case 'refresh': table.reload('table', []); break;
                        case 'add':
                            Fast.api.open("kuerp/process_board/add", "添加工序小时看板", {
                                callback: function () { $('.btn-refresh').click(); },
                                area: ['95%', '85%'],
                            }); break;
                        case 'delAll':
                            if (data.length <= 0) { Toastr.error('请选择要删除的数据'); return; }
                            Layer.confirm(__('Are you sure you want to delete the %s selected item?', data.length),
                                { icon: 3, title: __('Warning'), shadeClose: true },
                                function (index) {
                                    Fast.api.ajax("kuerp/process_board/del?ids=" + ids, function () { $('.btn-refresh').click(); }, '', true);
                                    Layer.close(index);
                                }); break;
                    }
                });

                table.on('tool(table)', function (obj) {
                    var data = obj.data;
                    if (obj.event === 'chart') {
                        Fast.api.open("kuerp/process_board/chart?ids=" + data.id, "工序小时看板 - " + layui.util.toDateString(data.board_date * 1000, "yyyy-MM-dd"), {
                            area: ['95%', '92%'],
                        });
                    } else if (obj.event === 'edit') {
                        Fast.api.open("kuerp/process_board/edit?ids=" + data.id, "编辑工序小时看板", {
                            callback: function () { $('.btn-refresh').click(); },
                            area: ['95%', '85%'],
                        });
                    } else if (obj.event === 'del') {
                        Layer.confirm(__('Are you sure you want to delete this item?'),
                            { icon: 3, title: __('Warning'), shadeClose: true },
                            function (index) {
                                $.ajax({
                                    url: "kuerp/process_board/del", type: "POST", data: { 'ids': data.id },
                                    success: function (res) {
                                        if (res.code == 1) { Toastr.success('操作成功'); setTimeout(function () { $('.btn-refresh').click(); }, 1500); }
                                        else { Toastr.error(res.msg); }
                                    }
                                });
                                Layer.close(index);
                            });
                    }
                });

                $('#search').on('click', function () {
                    table.reload('table', { where: { filter: JSON.stringify(getSearch()), op: JSON.stringify(getOp()) }, page: { curr: 1 } });
                });
                function getSearch() {
                    var search = {};
                    var v;
                    v = $('#board_date').val(); if (v != '') search.board_date = v;
                    v = $('#note').val(); if (v != '') search.note = v;
                    return search;
                }
                function getOp() { return { board_date: 'RANGE', note: 'LIKE' }; }
            });
            Controller.api.bindevent();
        },

        add: function () { Controller.api.bindevent(); },
        edit: function () { Controller.api.bindevent(); },

        api: {
            bindevent: function () {
                layui.use(['laydate'], function () {
                    var laydate = layui.laydate;
                    laydate.render({ elem: '#c-board_date' });
                });

                // 数据行选择器: 表头行/th 与模板行除外
                var dataRows = function () {
                    return $("#board_table tr:has(td)").not(".row_template");
                };

                var fmtNum = function (n) {
                    return String(Math.round(n * 10000) / 10000);
                };

                // 新增行的组装单号搜索选择器(模板行带 assemble_code_src 类, 克隆后转换为 selectpage 初始化)
                var initAssembleSelect = function ($row) {
                    var $input = $row.find('.assemble_code_src');
                    if ($input.length === 0) return;
                    $input.removeClass('assemble_code_src').addClass('selectpage');
                    require(['selectpage'], function () {
                        $input.selectPage({
                            eAjaxSuccess: function (data) {
                                data.list = typeof data.rows !== 'undefined' ? data.rows : (typeof data.list !== 'undefined' ? data.list : []);
                                data.totalRow = typeof data.total !== 'undefined' ? data.total : (typeof data.totalRow !== 'undefined' ? data.totalRow : data.list.length);
                                return data;
                            }
                        });
                    });
                };

                // 重算: mode='full' 级联重算累计合格(修改目标/一次合格时); mode='keep' 保留已填累计合格(手动改过/仅改备注等)
                var recalc = function (mode) {
                    var cumTarget = 0, cumQty = 0;
                    var sumTarget = 0, sumFirst = 0, sumRework = 0, sumDefect = 0;
                    dataRows().each(function () {
                        var $tr = $(this);
                        var target = parseFloat($tr.find('.target').val());
                        if (isNaN(target)) { target = 0; }
                        var first = parseFloat($tr.find('.first_pass_qty').val());
                        if (isNaN(first)) { first = 0; }
                        var rework = parseFloat($tr.find('.rework_qty').val());
                        if (isNaN(rework)) { rework = 0; }
                        var defect = parseFloat($tr.find('.defect_qty').val());
                        if (isNaN(defect)) { defect = 0; }

                        cumTarget += target;
                        if (mode === 'full') {
                            cumQty += first;
                            $tr.find('.cumulative_qty').val(fmtNum(cumQty));
                        } else {
                            var q = parseFloat($tr.find('.cumulative_qty').val());
                            cumQty = isNaN(q) ? cumQty : q;
                        }
                        var variance = Math.round((cumQty - cumTarget) * 10000) / 10000;
                        $tr.find('.cum_target').val(fmtNum(cumTarget));
                        $tr.find('.variance').val(fmtNum(variance));
                        var $st = $tr.find('.status_span');
                        if (cumQty >= cumTarget) { $st.text('OK').css('color', 'green'); }
                        else { $st.text('NG').css('color', 'red'); }

                        sumTarget += target;
                        sumFirst += first;
                        sumRework += rework;
                        sumDefect += defect;
                    });
                    var qualified = sumFirst + sumRework;
                    var base = sumFirst + sumDefect;
                    $('.sum_total_target').text(fmtNum(sumTarget));
                    $('.sum_first_pass_total').text(fmtNum(sumFirst));
                    $('.sum_rework_total').text(fmtNum(sumRework));
                    $('.sum_qualified_total').text(fmtNum(qualified));
                    $('.sum_first_pass_defect').text(fmtNum(sumDefect));
                    $('.sum_rework_defect').text(fmtNum(Math.max(sumDefect - sumRework, 0)));
                    $('.sum_first_pass_yield').text(base > 0 ? (sumFirst / base * 100).toFixed(2) + '%' : '0.00%');
                    $('.sum_final_yield').text(base > 0 ? (qualified / base * 100).toFixed(2) + '%' : '0.00%');
                };

                // ===== 行添加/删除 =====
                $(document).on("click", "#board_table .add", function () {
                    var $tpl = $("#board_table tr.row_template");
                    var $tr = $(this).parents('tr');
                    $tr.after('<tr>' + $tpl.html() + '</tr>');
                    var $new = $tr.next('tr');
                    initAssembleSelect($new);
                    recalc('full');
                    return false;
                });
                $(document).on("click", "#board_table .del", function () {
                    if (dataRows().length <= 1) {
                        Toastr.error('至少保留一个时段行！');
                        return false;
                    }
                    $(this).parents('tr').remove();
                    recalc('keep');
                    return false;
                });

                // ===== 时段生成: 每小时一个时段, 支持跨天(如 20点→次日8点) =====
                $(document).on("click", "#btn_gen_range", function () {
                    var start = parseInt($("#gen_start").val());
                    var end = parseInt($("#gen_end").val());
                    if (isNaN(start) || isNaN(end) || start === end) {
                        Toastr.error('请选择正确的时段范围');
                        return;
                    }
                    var hours = [];
                    if (start < end) {
                        for (var h = start; h < end; h++) { hours.push(h); }
                    } else {
                        for (var h = start; h < 24; h++) { hours.push(h); }
                        for (var h = 0; h < end; h++) { hours.push(h); }
                    }
                    var doGen = function () {
                        dataRows().remove();
                        var $tpl = $("#board_table tr.row_template");
                        $(hours).each(function (k, h) {
                            var endH = (h + 1) % 24;
                            var html = '<tr>' + $tpl.html() + '</tr>';
                            $("#board_table").append(html);
                            var $new = dataRows().last();
                            $new.find('.time_range').val(h + ':00-' + endH + ':00');
                            initAssembleSelect($new);
                        });
                        recalc('full');
                    };
                    if (dataRows().length > 0) {
                        Layer.confirm('生成时段将清空当前已填写的时段数据，是否继续？', { icon: 3, title: '提示' }, function (index) {
                            Layer.close(index);
                            doGen();
                        });
                    } else {
                        doGen();
                    }
                });

                // ===== 批量设置目标: 一键填充所有时段行的目标数量 =====
                $(document).on("click", "#btn_batch_target", function () {
                    var val = parseFloat($("#batch_target").val());
                    if (isNaN(val) || val < 0) {
                        Toastr.error('请输入正确的目标数量');
                        return;
                    }
                    var hasVal = false;
                    dataRows().each(function () {
                        var t = parseFloat($(this).find('.target').val());
                        if (!isNaN(t) && t !== 0) { hasVal = true; }
                    });
                    var doSet = function () {
                        var n = 0;
                        dataRows().each(function () {
                            $(this).find('.target').val(fmtNum(val));
                            n++;
                        });
                        recalc('keep');
                        Toastr.success('已为 ' + n + ' 个时段批量设置目标 ' + fmtNum(val));
                    };
                    if (hasVal) {
                        Layer.confirm('部分时段已设置目标，批量填充将覆盖这些值，是否继续？', { icon: 3, title: '提示' }, function (index) {
                            Layer.close(index);
                            doSet();
                        });
                    } else {
                        doSet();
                    }
                });

                // ===== 数量输入联动 =====
                $(document).on("input change", ".target", function () { recalc('keep'); });
                $(document).on("input change", ".first_pass_qty", function () { recalc('full'); });
                $(document).on("input change", ".cumulative_qty", function () { recalc('keep'); });

                // 表单提交
                Form.api.bindevent($("form[role=form]"), function (data, ret) {
                    parent.Layer.closeAll();
                    parent.$('.btn-refresh').click();
                });

                // 初始: 无数据行时按默认 8点-20点 生成时段(添加页); 有数据行时仅刷新状态颜色(编辑页)
                if (dataRows().length === 0) {
                    $("#btn_gen_range").click();
                } else {
                    recalc('keep');
                }
            }
        }
    };
    return Controller;
});
