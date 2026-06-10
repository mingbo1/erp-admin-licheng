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

        add: function () {
            initGoodsBomForm();
            Controller.api.bindevent();
        },
        edit: function () {
            initGoodsBomForm();
            Controller.api.bindevent();
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
        obj.find('.goods_code').val(g.goods_code);
        obj.find('.goods_id').val(g.id);
        obj.find('.goods_names').val(g.goods_code + ' ' + g.goods_name);

        var typeLabel = '';
        if (g.product_type == 'finished') typeLabel = '成品';
        else if (g.product_type == 'semi_finished') typeLabel = '半成品';
        else if (g.product_type == 'part') typeLabel = '零件';
        obj.find('.product-type-label').text(typeLabel);
    }

    // 填充子商品（data 来自 getUnit 返回的 {goods:{...}, unit:[...]} 结构）
    function fillChildGoods(obj, data) {
        var g = data.goods;
        obj.find('.goods_code').val(g.goods_code);
        obj.find('.goods_id').val(g.id);
        obj.find('.goods_name').val(g.goods_code + ' ' + g.goods_name);

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
