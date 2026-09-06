define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {

            layui.config({
                base: '/assets/addons/kuerp/layui/module/'
            }).extend({
                tableMerge: 'tableMerge'
            });

            layui.use(['table', 'upload', 'form', 'tableMerge'], function () {
                var table = layui.table;
                upload = layui.upload;
                tableMerge = layui.tableMerge;

                table.render({
                    elem: '#table'
                    , url: 'kuerp/goods_bom/index'
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
                        { checkbox: true, merge: ['id'] },
                        { field: 'bom_name', title: __('Bom_name'), merge: ['id'] },
                        { field: 'pname', title: __('Parent_goods'), merge: ['id'] },
                        { field: 'pcode', title: __('Goods_code'), merge: ['id'] },
                        { field: 'name', title: __('Child_goods_name') },
                        { field: 'code', title: __('Goods_code') },
                        { field: 'number', title: __('Child_number') },
                        { field: 'remark', title: __('Remark') },
                        { fixed: 'right', title: __('Operate'), toolbar: '#barDemo', merge: ['id'], width: 150 }
                    ]],
                    done: function () {
                        tableMerge.render(this)
                    }
                });

                table.on('toolbar(table)', function (obj) {
                    switch (obj.event) {
                        case 'refresh':
                            table.reload('table', []);
                            break;
                        case 'add':
                            Fast.api.open("kuerp/goods_bom/add", "添加BOM模板");
                            break;
                        case 'import':
                            Fast.api.open("kuerp/goods_bom/import", "导入BOM模板", {area: ['95%', '85%']});
                            break;
                        case 'delAll':
                            var checkStatus = table.checkStatus(obj.config.id);
                            var data = checkStatus.data;
                            if (data.length < 1) {
                                Toastr.error('请选择要删除的模板！');
                                return;
                            }
                            var ids = [];
                            $(data).each(function (k, v) {
                                if ($.inArray(v.id, ids) == -1) {
                                    ids.push(v.id);
                                }
                            });
                            Layer.confirm(
                                __('Are you sure you want to delete this item?'),
                                { icon: 3, title: __('Warning'), shadeClose: true },
                                function (index) {
                                    $.ajax({
                                        url: "kuerp/goods_bom/del",
                                        type: "POST",
                                        data: { 'ids': ids.join(',') },
                                        success: function (res) {
                                            if (res.code == 1) {
                                                Toastr.success('操作成功');
                                                table.reload('table', []);
                                            } else {
                                                Toastr.error(res.msg);
                                            }
                                        }
                                    });
                                    Layer.close(index);
                                }
                            );
                            break;
                    }
                });

                function reloadTable() {
                    table.reload('table', {
                        where: {
                            filter: JSON.stringify(getSearch()),
                            op: JSON.stringify(getOp()),
                        },
                        page: { curr: 1 }
                    });
                }

                table.on('tool(table)', function (obj) {
                    var data = obj.data;
                    if (obj.event === 'del') {
                        Layer.confirm(
                            __('Are you sure you want to delete this item?'),
                            { icon: 3, title: __('Warning'), shadeClose: true },
                            function (index) {
                                $.ajax({
                                    url: "kuerp/goods_bom/del",
                                    type: "POST",
                                    data: { 'ids': data.id },
                                    success: function (res) {
                                        if (res.code == 1) {
                                            Toastr.success('操作成功');
                                            setTimeout(function () { reloadTable() }, 1000);
                                        } else {
                                            Toastr.error(res.msg);
                                        }
                                    }
                                });
                                Layer.close(index);
                            }
                        );
                    } else if (obj.event === 'edit') {
                        Fast.api.open("kuerp/goods_bom/edit?ids=" + data.id, "编辑BOM模板");
                    }
                });

                $('#search').on('click', function () {
                    table.reload('table', {
                        where: {
                            filter: JSON.stringify(getSearch()),
                            op: JSON.stringify(getOp()),
                        },
                        page: { curr: 1 }
                    });
                });
                function getSearch() {
                    var search = {};
                    var bom_name = $('#bom_name').val();
                    if (bom_name != '') {
                        search.bom_name = bom_name;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.bom_name = 'LIKE';
                    return op;
                }
            });
            Controller.api.bindevent();
        },

        chosebom: function () {
            layui.config({
                base: '/assets/addons/kuerp/layui/module/'
            }).extend({
                tableMerge: 'tableMerge'
            });

            layui.use(['table', 'upload', 'form', 'tableMerge'], function () {
                var table = layui.table;
                upload = layui.upload;
                tableMerge = layui.tableMerge;

                table.render({
                    elem: '#table'
                    , url: 'kuerp/goods_bom/index'
                    , parseData: function (res) {
                        return {
                            "code": 0,
                            "msg": '',
                            "count": res.total,
                            "data": res.rows
                        };
                    }
                    , toolbar: '#btnToolbar'
                    , page: true
                    , cellMinWidth: 100
                    , cols: [[
                        { type: 'radio', merge: ['id'] },
                        { field: 'bom_name', title: __('Bom_name'), merge: ['id'] },
                        { field: 'pname', title: __('Parent_goods'), merge: ['id'] },
                        { field: 'pcode', title: __('Goods_code'), merge: ['id'] },
                        { field: 'name', title: __('Child_goods_name') },
                        { field: 'code', title: __('Goods_code') },
                        { field: 'number', title: __('Child_number') },
                        { field: 'remark', title: __('Remark') }
                    ]],
                    done: function () {
                        tableMerge.render(this)
                    }
                });

                table.on('toolbar(table)', function (obj) {
                    if (obj.event === 'sure') {
                        var checkStatus = table.checkStatus(obj.config.id);
                        var data = checkStatus.data;
                        if (data.length < 1) {
                            Toastr.error('请选择BOM模板！');
                            return;
                        }
                        var ids = [];
                        $(data).each(function (k, v) {
                            ids.push({ id: v.id });
                        });
                        Fast.api.close(ids);
                    }
                });

                $(document).on('click', '#cancel', function () {
                    Fast.api.close();
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
                    var bom_name = $('#bom_name').val();
                    if (bom_name != '') {
                        search.bom_name = bom_name;
                    }
                    return search;
                }
                function getOp() {
                    var op = {};
                    op.bom_name = 'LIKE';
                    return op;
                }
            });
            Controller.api.bindevent();
        },

        add: function () {
            initGoodsBomForm();
            Controller.api.bindevent();
        },
        edit: function () {
            initGoodsBomForm();
            Controller.api.bindevent();
        },
        import: function () {
            var importData = null;   // preview返回数据 {bom_name, parent, children[]}
            var childRows = [];      // 每行的解析结果与选中状态

            layui.use(['upload', 'form'], function () {
                var upload = layui.upload;

                // 上传文件 → 解析预览
                upload.render({
                    elem: '#upfile'
                    , url: 'ajax/upload'
                    , accept: 'file'
                    , size: 50
                    , done: function (res) {
                        if (res.code == 1) {
                            Toastr.info('正在解析文件...');
                            $.ajax({
                                url: 'kuerp/goods_bom/preview',
                                type: 'POST',
                                data: { file: res.data.url },
                                dataType: 'json',
                                success: function (resp) {
                                    if (resp.code == 1) {
                                        importData = resp.data;
                                        renderPreview();
                                    } else {
                                        Toastr.error(resp.msg);
                                    }
                                }
                            });
                        } else {
                            Toastr.error(res.msg);
                        }
                    }
                });

                // 渲染预览
                function renderPreview() {
                    $('#import-upload').hide();
                    $('#import-preview').show();
                    $('#bom_name').val(importData.bom_name || '');

                    // 父级商品
                    var p = importData.parent;
                    $('#parent-code').text(p.goods_code);
                    $('#parent-name').text(p.excel_name || p.goods_name);
                    $('#parent-spec').text(p.spec || '-');
                    $('#parent-vendor').text(p.vendor || '-');
                    $('#parent-packaging').text(p.packaging || '-');
                    $('#parent-status').html('<span class="label label-success">已匹配</span>');

                    // 子级商品
                    childRows = [];
                    $.each(importData.children, function (i, row) {
                        row.manual = false;   // 初始无手动选择
                        if (row.status == 'matched') {
                            row.chosen = row.default_goods;
                        } else {
                            row.chosen = null;
                        }
                        childRows[i] = row;
                    });
                    renderChildTable();
                }

                // 渲染子级商品表格（从 childRows 重建，供预览/刷新共用）
                function renderChildTable() {
                    var html = '';
                    $.each(childRows, function (i, row) {
                        var st = row.status;
                        var stHtml, actions;
                        if (st == 'matched') {
                            stHtml = '<span class="label label-success">已匹配</span>';
                            actions = '<button type="button" class="btn btn-xs btn-info btn-replace" data-idx="' + i + '">替换</button>';
                            row.chosen = row.chosen || row.default_goods;
                        } else if (st == 'pending') {
                            stHtml = '<span class="label label-warning">待确定</span>';
                            actions = '<button type="button" class="btn btn-xs btn-info btn-replace" data-idx="' + i + '">替换</button> ' +
                                '<button type="button" class="btn btn-xs btn-success btn-create" data-idx="' + i + '">新增</button> ' +
                                '<button type="button" class="btn btn-xs btn-default btn-ignore" data-idx="' + i + '">忽略</button>';
                        } else {
                            stHtml = '<span class="label label-danger">匹配失败</span>';
                            actions = '<button type="button" class="btn btn-xs btn-info btn-replace" data-idx="' + i + '">替换</button> ' +
                                '<button type="button" class="btn btn-xs btn-success btn-create" data-idx="' + i + '">新增</button>';
                        }
                        var chosenTxt = row.chosen
                            ? (row.chosen.goods_code + ' ' + escHtml(row.chosen.goods_name))
                            : (row.default_goods ? '<span class="text-muted">(默认:' + row.default_goods.goods_code + ' ' + escHtml(row.default_goods.goods_name) + ')</span>' : '<span class="text-muted">-</span>');
                        html += '<tr data-idx="' + i + '">' +
                            '<td>' + (i + 1) + '</td>' +
                            '<td>' + escHtml(row.name) + '</td>' +
                            '<td>' + escHtml(row.spec) + '</td>' +
                            '<td>' + escHtml(row.vendor) + '</td>' +
                            '<td>' + escHtml(row.packaging) + '</td>' +
                            '<td>' + row.number + '</td>' +
                            '<td class="col-status">' + stHtml + '</td>' +
                            '<td class="col-chosen">' + chosenTxt + '</td>' +
                            '<td class="col-actions">' + actions + '</td>' +
                            '</tr>';
                    });
                    $('#child-table tbody').html(html);
                    $('#child-count').text('（共 ' + childRows.length + ' 行）');
                    updateConfirmState();
                }

                // 刷新全部子商品的匹配（预览页"刷新匹配"按钮）——批量接口，一次请求完成
                function refreshAllMatch() {
                    var total = childRows.length;
                    if (total < 1) return;
                    var btn = $('#btn-refresh-all');
                    btn.attr('disabled', true).html('<i class="fa fa-refresh fa-spin"></i> 刷新中...');
                    var rows = [];
                    $.each(childRows, function (i, row) {
                        rows.push({ spec: row.spec || '', vendor: row.vendor || '', packaging: row.packaging || '' });
                    });
                    $.ajax({
                        url: 'kuerp/goods_bom/matchGoodsBatch',
                        type: 'POST',
                        data: { rows: rows },
                        dataType: 'json',
                        success: function (resp) {
                            if (resp.code == 1) {
                                $.each(resp.data, function (i, r) {
                                    if (!childRows[i]) return;
                                    childRows[i].candidates = r.candidates;
                                    childRows[i].default_goods = r.default_goods;
                                    childRows[i].status = r.status;
                                    // 保留手动选择；未手选的按新匹配结果
                                    if (!childRows[i].manual) {
                                        childRows[i].chosen = (r.status == 'matched') ? r.default_goods : null;
                                    }
                                });
                            } else {
                                Toastr.error(resp.msg);
                            }
                        },
                        complete: function () {
                            btn.removeAttr('disabled').html('<i class="fa fa-refresh"></i> 刷新匹配');
                            renderChildTable();
                        }
                    });
                }

                // 预览页"刷新匹配"按钮
                $('#btn-refresh-all').on('click', function () {
                    refreshAllMatch();
                });

                // 标记某行为已匹配
                function markChosen(i, goods) {
                    var row = childRows[i];
                    if (!row) return;
                    row.manual = true;   // 手动选择（替换/忽略），刷新时保留
                    row.chosen = goods;
                    row.status = 'matched';
                    var tr = $('#child-table tbody tr[data-idx="' + i + '"]');
                    tr.find('.col-status').html('<span class="label label-success">已匹配</span>');
                    tr.find('.col-chosen').html(goods.goods_code + ' ' + escHtml(goods.goods_name));
                    tr.find('.col-actions').html('<button type="button" class="btn btn-xs btn-info btn-replace" data-idx="' + i + '">替换</button>');
                    updateConfirmState();
                }

                // 更新“确认导入”按钮状态
                function updateConfirmState() {
                    var all = true;
                    $.each(childRows, function (i, row) {
                        if (!row.chosen) all = false;
                    });
                    if (all) {
                        $('#btn-confirm').removeAttr('disabled').removeClass('btn-disabled');
                    } else {
                        $('#btn-confirm').attr('disabled', 'disabled').addClass('btn-disabled');
                    }
                }

                // ========== 行操作 ==========
                $('#child-table').on('click', '.btn-replace', function () {
                    openReplacePopup(parseInt($(this).data('idx')));
                });
                $('#child-table').on('click', '.btn-ignore', function () {
                    var i = parseInt($(this).data('idx'));
                    var row = childRows[i];
                    if (row && row.default_goods) {
                        markChosen(i, row.default_goods);
                        Toastr.success('已采用默认商品：' + row.default_goods.goods_code);
                    }
                });
                $('#child-table').on('click', '.btn-create', function () {
                    openCreatePopup(parseInt($(this).data('idx')));
                });

                // ========== 替换弹窗 ==========
                var replaceIdx = -1;
                function openReplacePopup(i) {
                    var row = childRows[i];
                    if (!row) return;
                    replaceIdx = i;
                    Layer.open({
                        type: 1
                        , title: '选择匹配商品（' + row.name + '）'
                        , area: ['680px', '500px']
                        , content: $('#replace-popup').html()
                        , success: function (layero) {
                            // 注意：弹窗内容由 layer 复制生成，是全新的 DOM 元素，
                            // 渲染与事件都必须限定在 layero 内，否则会作用到隐藏的原始模板上。
                            // 当前子级商品信息
                            layero.find('#ri-name').text(row.name);
                            layero.find('#ri-spec').text(row.spec || '-');
                            layero.find('#ri-vendor').text(row.vendor || '-');
                            layero.find('#ri-packaging').text(row.packaging || '-');
                            layero.find('#ri-number').text(row.number);
                            if (row.status == 'matched') {
                                layero.find('#ri-status').html('<span class="label label-success">已匹配</span>');
                            } else if (row.status == 'pending') {
                                layero.find('#ri-status').html('<span class="label label-warning">待确定</span>');
                            } else {
                                layero.find('#ri-status').html('<span class="label label-danger">匹配失败</span>');
                            }
                            renderReplaceList(layero, i, row.candidates || []);
                            layero.find('#replace-search').val('');
                            layero.find('#replace-search').on('keyup', function () {
                                loadReplaceList(layero, i, $.trim(this.value));
                            });
                            // 刷新匹配按钮
                            layero.on('click', '#btn-refresh-match', function () {
                                loadReplaceList(layero, i, $.trim(layero.find('#replace-search').val()));
                            });
                            // 选择按钮
                            layero.on('click', '.btn-pick', function () {
                                var gid = parseInt($(this).data('gid'));
                                var code = $(this).data('code');
                                var name = $(this).data('name');
                                if (replaceIdx >= 0) {
                                    markChosen(replaceIdx, { goods_id: gid, goods_code: code, goods_name: name });
                                }
                                Layer.closeAll();
                            });
                        }
                    });
                }

                // 重新请求匹配候选并渲染
                function loadReplaceList(layero, i, kw) {
                    var row = childRows[i];
                    if (!row) return;
                    var btn = layero.find('#btn-refresh-match');
                    btn.attr('disabled', true).text('刷新中...');
                    $.ajax({
                        url: 'kuerp/goods_bom/matchGoods',
                        type: 'POST',
                        data: { spec: row.spec, vendor: row.vendor, packaging: row.packaging, keyword: kw || '' },
                        dataType: 'json',
                        success: function (resp) {
                            btn.removeAttr('disabled').text('刷新匹配');
                            if (resp.code == 1) {
                                renderReplaceList(layero, i, resp.data.candidates);
                            } else {
                                Toastr.error(resp.msg);
                            }
                        },
                        error: function () {
                            btn.removeAttr('disabled').text('刷新匹配');
                        }
                    });
                }

                // 渲染候选列表（$list 限定在弹窗内）
                function renderReplaceList(layero, i, candidates) {
                    var row = childRows[i];
                    var $list = layero.find('#replace-list');
                    var html = '';
                    if (!candidates || candidates.length < 1) {
                        html = '<div class="text-center text-muted" style="padding:30px;">未找到相似商品，可点击"新增"创建新商品</div>';
                    } else {
                        html = '<table class="table table-bordered table-hover table-condensed" style="margin-bottom:0;">' +
                            '<thead><tr><th>商品编号</th><th>商品名称</th><th>规格</th><th>厂商</th><th>封装</th><th>类型</th><th>匹配</th><th style="width:60px;"></th></tr></thead><tbody>';
                        $.each(candidates, function (k, g) {
                            var typeLabel = g.product_type == 'finished' ? '成品' : (g.product_type == 'semi_finished' ? '半成品' : '零件');
                            html += '<tr>' +
                                '<td>' + escHtml(g.goods_code) + '</td>' +
                                '<td>' + escHtml(g.goods_name) + '</td>' +
                                '<td>' + escHtml(g.goods_specification_id) + '</td>' +
                                '<td>' + escHtml(g.vendor_name) + '</td>' +
                                '<td>' + escHtml(g.packaging) + '</td>' +
                                '<td>' + typeLabel + '</td>' +
                                '<td>' + (g.match_count || 0) + '/3</td>' +
                                '<td><button type="button" class="btn btn-xs btn-primary btn-pick" data-gid="' + g.goods_id + '" data-code="' + escAttr(g.goods_code) + '" data-name="' + escAttr(g.goods_name) + '">选择</button></td>' +
                                '</tr>';
                        });
                        html += '</tbody></table>';
                    }
                    $list.html(html);
                }

                // ========== 新增商品弹窗 ==========
                function openCreatePopup(i) {
                    var row = childRows[i];
                    if (!row) return;

                    var unitOptions = '', typeOptions = '', supplierOptions = '', storeOptions = '';
                    $.each(Config.import_units || [], function (k, v) {
                        unitOptions += '<option value="' + v.id + '">' + v.name + '</option>';
                    });
                    $.each(Config.import_goods_types || [], function (k, v) {
                        typeOptions += '<option value="' + v.id + '">' + v.supplier_type_name + '</option>';
                    });
                    $.each(Config.import_suppliers || [], function (k, v) {
                        supplierOptions += '<option value="' + v.id + '">' + v.supplyer_name + '</option>';
                    });
                    $.each(Config.import_stores || [], function (k, v) {
                        storeOptions += '<option value="' + v.id + '">' + v.name + '</option>';
                    });

                    var content = '<form id="quick-create-form" class="form-horizontal" style="padding:15px;">' +
                        '<div class="form-group"><label class="col-sm-3 control-label">商品名称 *</label><div class="col-sm-8"><input type="text" class="form-control" name="goods_name" value="' + escAttr(row.name) + '"></div></div>' +
                        '<div class="form-group"><label class="col-sm-3 control-label">规格</label><div class="col-sm-8"><input type="text" class="form-control" name="goods_specification_id" value="' + escAttr(row.spec) + '"></div></div>' +
                        '<div class="form-group"><label class="col-sm-3 control-label">厂商</label><div class="col-sm-8"><input type="text" class="form-control" name="vendor_name" value="' + escAttr(row.vendor) + '"></div></div>' +
                        '<div class="form-group"><label class="col-sm-3 control-label">封装</label><div class="col-sm-8"><input type="text" class="form-control" name="packaging" value="' + escAttr(row.packaging) + '"></div></div>' +
                        '<div class="form-group"><label class="col-sm-3 control-label">商品类型</label><div class="col-sm-8"><select class="form-control" name="product_type"><option value="part">零件</option><option value="semi_finished">半成品</option></select></div></div>' +
                        '<div class="form-group"><label class="col-sm-3 control-label">计量单位 *</label><div class="col-sm-8"><select class="form-control" name="unit_id"><option value="">请选择</option>' + unitOptions + '</select></div></div>' +
                        '<div class="form-group"><label class="col-sm-3 control-label">商品类别</label><div class="col-sm-8"><select class="form-control" name="goods_type_id"><option value="0">默认</option>' + typeOptions + '</select></div></div>' +
                        '<div class="form-group"><label class="col-sm-3 control-label">供应商</label><div class="col-sm-8"><select class="form-control" name="supplier_id"><option value="0">无</option>' + supplierOptions + '</select></div></div>' +
                        '<div class="form-group"><label class="col-sm-3 control-label">首选仓库</label><div class="col-sm-8"><select class="form-control" name="manager_warehouse_id"><option value="0">无</option>' + storeOptions + '</select></div></div>' +
                        '</form>';

                    Layer.open({
                        type: 1
                        , title: '快速新建商品'
                        , area: ['560px', '560px']
                        , content: content
                        , btn: ['保存', '取消']
                        , yes: function (index, layero) {
                            var data = {};
                            layero.find('input,select').each(function () {
                                var name = $(this).attr('name');
                                if (name) data[name] = $(this).val();
                            });
                            if (!data.goods_name) { Toastr.error('请输入商品名称'); return; }
                            if (!data.unit_id) { Toastr.error('请选择计量单位'); return; }
                            $.ajax({
                                url: 'kuerp/goods_bom/createGoods',
                                type: 'POST',
                                data: data,
                                dataType: 'json',
                                success: function (resp) {
                                    if (resp.code == 1) {
                                        Layer.close(index);
                                        Toastr.success('商品创建成功：' + resp.data.goods_code);
                                        // 刷新匹配候选并弹出选择框
                                        refreshAfterCreate(i);
                                    } else {
                                        Toastr.error(resp.msg);
                                    }
                                }
                            });
                        }
                    });
                }

                // 新增商品后刷新候选，让用户选择刚创建的商品
                function refreshAfterCreate(i) {
                    var row = childRows[i];
                    if (!row) return;
                    $.ajax({
                        url: 'kuerp/goods_bom/matchGoods',
                        type: 'POST',
                        data: { spec: row.spec, vendor: row.vendor, packaging: row.packaging, keyword: '' },
                        dataType: 'json',
                        success: function (resp) {
                            if (resp.code == 1) {
                                row.candidates = resp.data.candidates;
                                row.default_goods = resp.data.default_goods;
                                openReplacePopup(i);
                            } else {
                                Toastr.error(resp.msg);
                            }
                        }
                    });
                }

                // ========== 确认导入 ==========
                $('#btn-confirm').on('click', function () {
                    var bom_name = $.trim($('#bom_name').val());
                    if (!bom_name) { Toastr.error('请输入模板名称'); return; }
                    var parentGoodsId = importData.parent.goods_id;
                    var children = [];
                    $.each(childRows, function (i, row) {
                        if (!row.chosen) return;
                        children.push({ goods_id: row.chosen.goods_id, number: row.number });
                    });
                    if (children.length < 1) { Toastr.error('没有可导入的子商品'); return; }

                    Layer.confirm('确认导入该BOM模板？', { icon: 3, title: '确认导入' }, function (idx) {
                        Layer.close(idx);
                        $.ajax({
                            url: 'kuerp/goods_bom/createBom',
                            type: 'POST',
                            data: {
                                bom_name: bom_name,
                                parent_goods_id: parentGoodsId,
                                children: children
                            },
                            dataType: 'json',
                            success: function (resp) {
                                if (resp.code == 1) {
                                    Toastr.success('导入成功');
                                    setTimeout(function () {
                                        parent.layer.closeAll();
                                        parent.location.reload();
                                    }, 800);
                                } else {
                                    Toastr.error(resp.msg);
                                }
                            }
                        });
                    });
                });

                // ========== 工具函数 ==========
                function escHtml(s) {
                    if (s === undefined || s === null) return '';
                    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                }
                function escAttr(s) {
                    return escHtml(s);
                }
            });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"), function (data, ret) {
                    parent.location.reload();
                });
            }
        }
    };

    // ============================================================
    // BOM 表单初始化 — 参照 assemble.js 的商品选择交互模式
    // ============================================================
    function initGoodsBomForm() {

        // ---------- 父商品：点击图标弹出选商品窗口 ----------
        $(document).on("click", ".goods_code_spans", function () {
            var obj = $(this).parents('tr');
            parent.Fast.api.open("kuerp/Goods/chosegoods", "选择商品", {
                area: ['90%', '75%'],
                callback: function (goodsData) {
                    if (goodsData != undefined) {
                        $.ajax({
                            url: "kuerp/goods/getUnit",
                            type: "POST",
                            data: { 'where': arrayToJsonString(goodsData) },
                            success: function (res) {
                                res = JSON.parse(res);
                                // 父商品只取第一个
                                if (res && res.length > 0) {
                                    fillParentGoods(obj, res[0]);
                                }
                            }
                        });
                    }
                }
            });
        });

        // ---------- 子商品：点击图标弹出选商品窗口 ----------
        $(document).on("click", ".goods_code_span", function () {
            var obj = $(this).parents('tr');
            parent.Fast.api.open("kuerp/Goods/chosegoods", "选择商品", {
                area: ['90%', '75%'],
                callback: function (goodsData) {
                    if (goodsData != undefined) {
                        $.ajax({
                            url: "kuerp/goods/getUnit",
                            type: "POST",
                            data: { 'where': arrayToJsonString(goodsData) },
                            success: function (res) {
                                res = JSON.parse(res);
                                $(res).each(function (k, vp) {
                                    if (k == 0) {
                                        // 第一个填入当前行
                                        fillChildGoods(obj, vp);
                                    } else {
                                        // 后续商品新增行
                                        addChildRow();
                                        var rows = $('#child_table').find('tr.child_data_row');
                                        fillChildGoods(rows.last(), vp);
                                    }
                                });
                            }
                        });
                    }
                }
            });
        });

        // ---------- 父商品：输入搜索下拉 ----------
        $(document).on("input", ".goods_names", function () {
            var obj = $(this).parents('tr');
            var val = $(this).val();
            if (val.length < 1) {
                $('.chose_goods_boxs').html('');
                return;
            }
            var pos = $(this).offset();
            // 只对父商品输入框做内联搜索
            $.ajax({
                url: "kuerp/goods/chosegoods",
                type: "POST",
                data: { 'goods_name': val },
                success: function (res) {
                    if (res.rows && res.rows.length > 0) {
                        var html = '<div class="chose_goods_box" style="left:' + pos.left + 'px;top: ' + (pos.top + 35) + 'px;">\n' +
                            '<table class="table table-bordered"><thead><tr><th>商品编号</th><th>商品名称</th><th>规格</th><th>单位</th><th>类型</th><th>库存</th></tr></thead><tbody>';
                        $(res.rows).each(function (k, v) {
                            var typeLabel = v.product_type == 'finished' ? '成品' : (v.product_type == 'semi_finished' ? '半成品' : '零件');
                            html += '<tr class="chose_goods_tr" data-code="' + v.goods_code + '" data-id="' + v.id + '" data-name="' + v.goods_name + '" data-type="' + v.product_type + '" data-unit="' + v.unit_id + '">' +
                                '<td>' + v.goods_code + '</td><td>' + v.goods_name + '</td><td>' + (v.goods_specification_id || '') + '</td><td>' + (v.unit ? v.unit.name : '') + '</td><td>' + typeLabel + '</td><td>' + (v.stock || 0) + '</td></tr>';
                        });
                        html += '</tbody></table></div>';
                        $('.chose_goods_boxs').html(html);
                    } else {
                        $('.chose_goods_boxs').html('');
                    }
                }
            });
        });

        // ---------- 子商品：输入搜索下拉 ----------
        $(document).on("input", ".goods_name", function () {
            var obj = $(this).parents('tr');
            var val = $(this).val();
            if (val.length < 1) {
                $('.chose_goods_boxs').html('');
                return;
            }
            var pos = $(this).offset();
            $.ajax({
                url: "kuerp/goods/chosegoods",
                type: "POST",
                data: { 'goods_name': val },
                success: function (res) {
                    if (res.rows && res.rows.length > 0) {
                        var html = '<div class="chose_goods_box" style="left:' + pos.left + 'px;top: ' + (pos.top + 35) + 'px;">\n' +
                            '<table class="table table-bordered"><thead><tr><th>商品编号</th><th>商品名称</th><th>规格</th><th>单位</th><th>类型</th><th>库存</th></tr></thead><tbody>';
                        $(res.rows).each(function (k, v) {
                            var typeLabel = v.product_type == 'finished' ? '成品' : (v.product_type == 'semi_finished' ? '半成品' : '零件');
                            html += '<tr class="chose_goods_tr" data-code="' + v.goods_code + '" data-id="' + v.id + '" data-name="' + v.goods_name + '" data-type="' + v.product_type + '" data-unit="' + v.unit_id + '">' +
                                '<td>' + v.goods_code + '</td><td>' + v.goods_name + '</td><td>' + (v.goods_specification_id || '') + '</td><td>' + (v.unit ? v.unit.name : '') + '</td><td>' + typeLabel + '</td><td>' + (v.stock || 0) + '</td></tr>';
                        });
                        html += '</tbody></table></div>';
                        $('.chose_goods_boxs').html(html);
                    } else {
                        $('.chose_goods_boxs').html('');
                    }
                }
            });
        });

        // ---------- 点击下拉行选中商品 ----------
        $(document).on("click", ".chose_goods_tr", function () {
            var code = $(this).data('code');
            var id = $(this).data('id');
            var name = $(this).data('name');
            var type = $(this).data('type');
            var unit = $(this).data('unit');

            // 找到触发搜索的输入框
            var targetInput = $('.goods_names:focus, .goods_name:focus');
            if (targetInput.length > 0) {
                var obj = targetInput.parents('tr');
                // 包装成 getUnit 兼容格式 {goods: {...}}
                var wrapData = { goods: { id: id, goods_code: code, goods_name: name, product_type: type, unit_id: unit } };
                if (targetInput.hasClass('goods_names')) {
                    fillParentGoods(obj, wrapData);
                } else {
                    fillChildGoods(obj, wrapData);
                }
            }
            $('.chose_goods_boxs').html('');
        });

        // ---------- 点击空白处关闭下拉 ----------
        $(document).on("click", function (e) {
            if (!$(e.target).closest('.chose_goods_box').length && !$(e.target).closest('.goods_name').length && !$(e.target).closest('.goods_names').length) {
                $('.chose_goods_boxs').html('');
            }
        });

        // ---------- 添加子商品行 ----------
        $(document).on("click", "#child_table .add", function () {
            addChildRow();
        });

        // ---------- 删除子商品行 ----------
        $(document).on("click", "#child_table .del", function () {
            var rows = $('#child_table').find('tr.child_data_row');
            if (rows.length <= 1) {
                Toastr.error('至少保留一行子商品');
                return;
            }
            $(this).parents('tr').remove();
        });
    }

    // 填充父商品（data 来自 getUnit 返回的 {goods:{...}, unit:[...]} 结构）
    function fillParentGoods(obj, data) {
        var g = data.goods;
        var code = $.trim(g.goods_code);
        var name = $.trim(g.goods_name);
        obj.find('.goods_code').val(code);
        obj.find('.goods_id').val(g.id);
        obj.find('.goods_names').val(code + ' ' + name);

        var typeLabel = '';
        if (g.product_type == 'finished') typeLabel = '成品';
        else if (g.product_type == 'semi_finished') typeLabel = '半成品';
        else if (g.product_type == 'part') typeLabel = '零件';
        obj.find('.product-type-label').text(typeLabel);
    }

    // 填充子商品（data 来自 getUnit 返回的 {goods:{...}, unit:[...]} 结构）
    function fillChildGoods(obj, data) {
        var g = data.goods;
        var code = $.trim(g.goods_code);
        var name = $.trim(g.goods_name);
        obj.find('.goods_code').val(code);
        obj.find('.goods_id').val(g.id);
        obj.find('.goods_name').val(code + ' ' + name);

        var typeLabel = '';
        if (g.product_type == 'finished') typeLabel = '成品';
        else if (g.product_type == 'semi_finished') typeLabel = '半成品';
        else if (g.product_type == 'part') typeLabel = '零件';
        obj.find('.product-type-label').text(typeLabel);
    }

    // 数组转 JSON 字符串（与 assemble.js 中 arrayToJsonString 一致）
    function arrayToJsonString(o) {
        var len = o.length;
        var new_arr = [];
        var str = '', strone = '', strs = '';
        for (var i = 0; i < len; i++) {
            new_arr = o[i];
            for (var k in new_arr) {
                strone += '"' + k + '"' + ':' + '"' + new_arr[k] + '"' + ',';
            }
            str = '{' + strone.substring(0, strone.length - 1) + '}';
            strone = '';
            strs += str + ',';
            new_arr = [];
        }
        return '[' + strs.substring(0, strs.length - 1) + ']';
    }

    // 新增子商品空行（克隆模板行）
    function addChildRow() {
        var template = $('#child_row_template').clone();
        template.removeAttr('id').removeAttr('style').addClass('child_data_row');
        // 清除模板中的值
        template.find('.goods_code').val('');
        template.find('.goods_id').val('');
        template.find('.goods_name').val('');
        template.find('.product-type-label').text('');
        template.find('.child_number').val('1');
        template.find('.child_remark').val('');
        $('#child_table').append(template);
    }

    return Controller;
});
