define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            layui.use(['table', 'upload', 'form'], function () {
                var table = layui.table;
                upload = layui.upload;

                var set = Config.set;
                var examine = parseInt(set.examine.is_checked);
                if (isNaN(examine)) { examine = 0; }
                var hideStatus = examine != 1;
                var number_decimal = parseInt(set.number_decimal.value);
                if (isNaN(number_decimal)) { number_decimal = 1; }
                  var price_decimal = parseInt(set.price_decimal.value);
                if (isNaN(price_decimal)) { price_decimal = 1; }

                table.render({
                    elem: '#table',
                    url: 'kuerp/process_order/index',
                    parseData: function (res) {
                        return { "code": 0, "msg": '', "count": res.total, "data": res.rows };
                    },
                    toolbar: '#thisToolbar',
                    page: true,
                    cellMinWidth: 100,
                    cols: [[
                        { checkbox: true },
                        { field: 'process_time', title: __('Process_time'), templet: function (d) {
                                return layui.util.toDateString(d.process_time * 1000, "yyyy-MM-dd");
                            }, width: 110 },
                        { field: 'process_code', title: __('Process_code'), operate: 'LIKE', width: 160 },
                        { field: 'supplyer_code', title: __('Supplyer_code'), templet: function (d) {
                                if (d.suppliers) { return d.suppliers.supplyer_code + ' ' + d.suppliers.supplyer_name; }
                                return d.supplyer_code;
                            }},
                        { field: 'number', title: __('Number'), templet: function (d) {
                                return (parseFloat(d.number) || 0).toFixed(number_decimal);
                            }},
                        { field: 'in_number', title: __('In_number'), templet: function (d) {
                                return (parseFloat(d.in_number) || 0).toFixed(number_decimal);
                            }},

                        { field: 'total_process_fee', title: __('Total_process_fee'), templet: function (d) {
                                return (parseFloat(d.total_process_fee) || 0).toFixed(price_decimal);
                            }},
                        { field: 'pay_status', title: __('Pay_status'), templet: function (d) {
                                if (d.pay_status == 1) return '<span style="color:green">已付清</span>';
                                if (d.pay_status == 2) return '<span style="color:orange">部分付款</span>';
                                return '<span style="color:red">未付款</span>';
                            }},
                        { field: 'examine_status', title: __('Examine_status'), templet: function (d) {
                                if (d.examine_status == 0) return '<span style="color:red">' + __('Examine_status 0') + '</span>';
                                return '<span style="color:green">' + __('Examine_status 1') + '</span>';
                            }, hide: hideStatus },
                        { field: 'in_status', title: __('In_status'), templet: function (d) {
                                if (d.in_status == 0) return '<span style="color:#999">' + __('In_status 0') + '</span>';
                                if (d.in_status == 1) return '<span style="color:orange">' + __('In_status 1') + '</span>';
                                return '<span style="color:green">' + __('In_status 2') + '</span>';
                            }},
                        { field: 'preparation_id', title: __('Preparation_id'), templet: function (d) {
                                return d.preparation ? d.preparation.username : '';
                            }},
                        { field: 'examine_id', title: __('Examine_id'), templet: function (d) {
                                if (d.examine && d.examine.username != null) { return d.examine.username; }
                                return '';
                            }, hide: hideStatus },
                        { field: 'remark', title: __('Remark') },
                        { fixed: 'right', title: __('Operate'), toolbar: '#barDemoOrder', width: 180 }
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
                            Fast.api.open("kuerp/process_order/add", "添加外协订单", {
                                callback: function () { Controller.api.bindevent(); },
                                area: ['90%', '75%'],
                            }); break;
                        case 'delAll':
                            if (data.length <= 0) { Toastr.error('请选择要删除的数据'); return; }
                            Layer.confirm(__('Are you sure you want to delete the %s selected item?', data.length),
                                { icon: 3, title: __('Warning'), shadeClose: true },
                                function (index) {
                                    Fast.api.ajax("kuerp/process_order/del?ids=" + ids, function () { $('.btn-refresh').click(); }, '', true);
                                    Layer.close(index);
                                }); break;
                        case 'print':
                            if (data.length <= 0) { Toastr.error('请选择要打印的数据'); return; }
                            if (data.length > 1) { Toastr.error('一次只能打印一条数据'); return; }
                            parent.Fast.api.open("kuerp/process_order/printpage?ids=" + data[0].id, "打印", { area: ['90%', '75%'] });
                            break;
                        case 'examine':
                            Fast.api.open("kuerp/process_order/examine_fee?ids=" + ids, "审核-填写加工费", {
                                area: ['95%', '80%'],
                                callback: function () { $('.btn-refresh').click(); }
                            }); break;
                        case 'examine_f':
                            Fast.api.ajax("kuerp/process_order/examine?examine=2&ids=" + ids, function () { $('.btn-refresh').click(); }, '', true); break;
                    }
                });

                table.on('tool(table)', function (obj) {
                    var data = obj.data;
                    if (obj.event === 'del') {
                        var that = this;
                        var top = $(that).offset().top - $(window).scrollTop();
                        var left = $(that).offset().left - $(window).scrollLeft() - 260;
                        if (top + 154 > $(window).height()) { top = top - 154; }
                        if ($(window).width() < 480) { top = left = undefined; }
                        Layer.confirm(__('Are you sure you want to delete this item?'),
                            { icon: 3, title: __('Warning'), offset: [top, left], shadeClose: true },
                            function (index) {
                                $.ajax({ url: "kuerp/process_order/del", type: "POST", data: { 'ids': data.id },
                                    success: function (res) {
                                        if (res.code == 1) { Toastr.success('操作成功'); setTimeout(function () { $('.btn-refresh').click(); }, 1500); }
                                        else { Toastr.error(res.msg); }
                                    }
                                });
                                Layer.close(index);
                            });
                    } else if (obj.event === 'edit') {
                        Fast.api.open("kuerp/process_order/edit?ids=" + data.id, "编辑外协订单", { area: ['90%', '75%'] });
                    } else if (obj.event === 'detail') {
                        Fast.api.open("kuerp/process_order/detail?ids=" + data.id, "外协订单详情", { area: ['95%', '85%'] });
                    }
                });

                $('#search').on('click', function () {
                    table.reload('table', { where: { filter: JSON.stringify(getSearch()), op: JSON.stringify(getOp()) }, page: { curr: 1 } });
                });
                function getSearch() {
                    var search = {};
                    var v;
                    v = $('#process_code').val(); if (v != '') search.process_code = v;
                    v = $('#process_time').val(); if (v != '') search.process_time = v;
                    v = $('#supplyer_code').val(); if (v != '') search.supplyer_code = v;
                    v = $('#preparation_id').val(); if (v != '') search.preparation_id = v;
                    v = $('#examine_id').val(); if (v != '') search.examine_id = v;
                    v = $('#examine_status').val(); if (v != '') search.examine_status = v;
                    v = $('#in_status').val(); if (v != '') search.in_status = v;
                    return search;
                }
                function getOp() { return { process_code: 'LIKE', process_time: 'RANGE' }; }
            });
            Controller.api.bindevent();
        },

        add: function () { Controller.api.bindevent(); },
        edit: function () { Controller.api.bindevent(false); },
        detail: function () {}, // 纯服务端渲染，无需JS初始化

        examine_fee: function () {
            // 自动计算加工费小计和合计
            function calcFee() {
                var total = 0;
                $('.process-fee').each(function () {
                    var fee = parseFloat($(this).val()) || 0;
                    var num = parseFloat($(this).attr('data-number')) || 0;
                    var subtotal = fee * num;
                    $(this).closest('tr').find('.fee-subtotal').text(subtotal.toFixed(Config.price_decimal || 2));
                    total += subtotal;
                });
                $('#total-fee').text(total.toFixed(Config.price_decimal || 2));
            }

            // 页面加载后初始化计算
            calcFee();

            // 输入时实时计算
            $(document).on('input change keyup', '.process-fee', calcFee);

            // 表单提交
            $('#examine-fee-form').on('submit', function (e) {
                e.preventDefault();
                var total = parseFloat($('#total-fee').text()) || 0;
                if (total <= 0) {
                    if (!confirm('加工费合计为0，确定要审核吗？')) return;
                }
                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function (res) {
                        if (res.code == 1) {
                            Toastr.success('审核成功');
                            setTimeout(function () {
                                parent.$('.btn-refresh').click();
                                parent.Layer.closeAll();
                            }, 1000);
                        } else {
                            Toastr.error(res.msg || '操作失败');
                        }
                    },
                    error: function () {
                        Toastr.error('请求失败');
                    }
                });
            });
        },

        api: {
            bindevent: function (reloadStatus) {
                if (reloadStatus === undefined) { reloadStatus = true; }
                layui.use(['table', 'form'], function () {
                    // ===== 供应商选择器 (xmSelect) =====
                    var supplyer_div = xmSelect.render({
                        el: '#supplyer_codes',
                        filterable: true,
                        paging: true,
                        pageSize: 5,
                        pageRemote: true,
                        remoteMethod: function (val, cb, show, pageIndex) {
                            $.ajax({
                                url: "kuerp/suppliers/index",
                                type: 'GET',
                                crossOrigin: true,
                                dataType: 'json',
                                cache: false,
                                async: true,
                                data: { keywords: val, filter: JSON.stringify({ "supplyer_name": val }), op: JSON.stringify({ "supplyer_name": 'like' }), page: pageIndex, limit: 5 },
                                success: function (data) {
                                    var TotalCount = data.total;
                                    var Pages = Math.ceil(TotalCount / 5);
                                    var list = [];
                                    $(data.rows).each(function (k, v) { list.push({ name: v.supplyer_name, value: v.supplyer_code }); });
                                    setTimeout(function () { cb(list, Pages); }, 100);
                                },
                                error: function () { cb([], 0); }
                            });
                        },
                        on: function (data) {
                            var arr = data.arr;
                            var change = data.change;
                            var isAdd = data.isAdd;
                            if (!isAdd) { $('#supplyer_code').val(''); }
                            if (change.length > 0) {
                                for (let i in arr) { if (change[0].parentId == arr[i].parentId) { arr.splice(i, 1, change[0]); } }
                            }
                            if (arr.length > 0) { if (arr[0]['value'] != undefined) { $('#supplyer_code').val(arr[0]['value']); } }
                            return Array.from(new Set(arr));
                        },
                    });
                    var kuerp_suppliers = Config.kuerp_suppliers;
                    if (kuerp_suppliers != undefined) {
                        var newArr = [{ name: kuerp_suppliers.supplyer_name, value: kuerp_suppliers.supplyer_code }];
                        supplyer_div.setValue(newArr);
                    }

                    var set = Config.set;
                    var examine = parseInt(set.examine.is_checked);
                    if (isNaN(examine)) { examine = 0; }
                    if (examine == 1 && Config.examine_status == 1) {
                        $('input').attr('disabled', 'true');
                        $('select').attr('disabled', 'true');
                        $('textarea').attr('disabled', 'true');
                        $('.btn').attr('disabled', 'true');
                    }
                    var price_decimal = parseInt(set.price_decimal.value);
                    if (isNaN(price_decimal)) { price_decimal = 1; }
                    var number_decimal = parseInt(set.number_decimal.value);
                    if (isNaN(number_decimal)) { number_decimal = 1; }

                    // 日期
                    layui.use('laydate', function () {
                        var laydate = layui.laydate;
                        laydate.render({ elem: '#c-process_time' });
                        lay('.manufacture').each(function () { laydate.render({ elem: this, trigger: "click" }); });
                        lay('.validity').each(function () { laydate.render({ elem: this, trigger: "click" }); });
                    });

                    var goodsList = Config.goodsList;
                    var goods;
                    var tableobj; // 当前悬浮框关联的行

                    // ===== 数量/价格格式化 =====
                    $(document).on("change", ".number_decimal", function () {
                        var period = parseFloat($(this).val());
                        if (!isNaN(period)) { $(this).val(period.toFixed(number_decimal)); } else { $(this).val(''); }
                    });
                    $(document).on("change", ".price_decimal", function () {
                        var period = parseFloat($(this).val());
                        if (!isNaN(period)) { $(this).val(period.toFixed(price_decimal)); } else { $(this).val(''); }
                    });

                    // ===== 出库原材料: 扫码枪 =====
                    var switchs = 0;
                    $(document).on("change", "#c-switchs", function () {
                        switchs = $(this).val();
                        if (switchs == 1) { $('#out_table .goods_code_spans').hide(); $('#out_table .goods_names').first().focus(); }
                        else { $('#out_table .goods_code_spans').show(); }
                    });
                    // 入库成品: 扫码枪
                    var switchs2 = 0;
                    $(document).on("change", "#c-switch", function () {
                        switchs2 = $(this).val();
                        if (switchs2 == 1) { $('#in_table .goods_code_span').hide(); $('#in_table .goods_name').first().focus(); }
                        else { $('#in_table .goods_code_span').show(); }
                    });

                    // ===== 扫码枪回车录入 =====
                    $(document).on("keydown", "input", function (e) {
                        if (e.keyCode == 13) {
                            var code = $(this).val();
                            var obj = $(this).parents('tr');
                            var thisobj = this;
                            var objp = $(this).parents('table')[0].id;
                            $(this).val('');
                            var isOut = (objp == 'out_table');
                            $.ajax({
                                url: "kuerp/goods/getGoods", type: "POST", data: { 'code': code },
                                success: function (res) {
                                    if (res.code == 1) {
                                        var g = res.msg;
                                        obj.find('.goods_code').val(g.goods_code);
                                        if (isOut) {
                                            obj.find('.goods_names').val(g.goods_code + ' ' + g.goods_name);
                                            changeGoods(g.goods_code, g.stores_id, g.goods_attr, obj, g.goods_unit, 1, 'out');
                                        } else {
                                            obj.find('.goods_name').val(g.goods_code + ' ' + g.goods_name);
                                            changeGoods(g.goods_code, g.stores_id, g.goods_attr, obj, g.goods_unit, 1, 'in');
                                        }
                                        var inputs = $("#" + objp + " tr").find(isOut ? ".goods_names" : ".goods_name");
                                        for (var i = 0; i < inputs.length; i++) {
                                            if (i == (inputs.length - 1)) {
                                                var lawtable = document.getElementById(objp);
                                                var rows = lawtable.rows;
                                                var htmlp = (isOut ? rows[1] : rows[2]).innerHTML;
                                                var html = '<tr>' + htmlp + '</tr>';
                                                $(obj).after(html);
                                                inputs = $("#" + objp + " tr").find(isOut ? ".goods_names" : ".goods_name");
                                                inputs[i + 1].focus();
                                                break;
                                            } else if (thisobj == inputs[i]) { inputs[i + 1].focus(); break; }
                                        }
                                    } else { Toastr.error(res.msg); }
                                }
                            });
                            return false;
                        }
                    });

                    // ===== 出库原材料: 点击选择商品图标 → 打开多选弹窗 =====
                    $(document).on("click", "#out_table .goods_code_spans", function () {
                        if (switchs == 1) return false;
                        var obj = $(this).parents('tr');
                        var lawtable = document.getElementById("out_table");
                        var rows = lawtable.rows;
                        var htmlp = rows[1].innerHTML;
                        var html = '';
                        parent.Fast.api.open("kuerp/Goods/chosegoods", "选择商品", {
                            area: ['90%', '75%'],
                            callback: function (goodsData) {
                                if (goodsData != undefined) {
                                    $(goodsData).each(function (k, v) {
                                        if (k > 0) {
                                            html = '<tr class="goods_id' + v.goods_id + '_' + v.goods_attr + '">' + htmlp + '</tr>';
                                            $(obj).after(html);
                                        }
                                    });
                                    $.ajax({
                                        url: "kuerp/goods/getUnit", type: "POST",
                                        data: { 'where': arrayToJsonString(goodsData) },
                                        success: function (res) {
                                            res = JSON.parse(res);
                                            $(res).each(function (kp, vp) {
                                                var thisobj = (kp == 0) ? obj : $('.goods_id' + vp.goods_id + '_' + vp.goods_attr);
                                                thisobj.find('.goods_code').val(vp.goods.goods_code);
                                                thisobj.find('.goods_names').val(vp.goods.goods_code + ' ' + vp.goods.goods_name);
                                                thisobj.find('.goods_note').val(vp.note);
                                                $(vp.unit).each(function (k, v) {
                                                    if (v.is_default == 1) {
                                                        changeGoods(vp.goods.goods_code, vp.goods.manager_warehouse_id, vp.goods_attr, thisobj, '', vp.number, 'out');
                                                    }
                                                });
                                            });
                                        }
                                    });
                                }
                            }
                        });
                    });

                    // ===== 出库原材料: 商品名称悬浮搜索 =====
                    $(document).on("input", "#out_table .goods_names", function () {
                        if (switchs != 1) {
                            var obj = $(this);
                            var goods_name = obj.val();
                            var top = obj.offset().top - (-30);
                            var left = obj.offset().left - (-15);
                            tableobj = $(this).parents('tr');
                            $.ajax({
                                url: "kuerp/goods/chosegoods", type: "POST", data: { 'goods_name': goods_name },
                                success: function (res) {
                                    if (res.total > 0) {
                                        var html = '<div class="chose_goods_box" style="left:' + left + 'px;top: ' + top + 'px;">\n' +
                                            '<table><tr><th>商品编号</th><th>商品名称</th><th>规格型号</th><th>计量单位</th><th>商品类别</th><th>库存数量</th><th>备注</th></tr>\n';
                                        var max = res.total > 5 ? 5 : res.total;
                                        for (var a = 0; a < max; a++) {
                                            var v = res.rows[a];
                                            html += '<tr class="chose_goods_tr" data-code="' + v.goods_code + '" data-name="' + v.goods_name + '" data-note="' + v.note + '" data-store="' + v.manager_warehouse_id + '" data-type="out"><td>' + v.goods_code + '</td><td>' + v.goods_name + '</td><td>' + (v.goods_specification_id || '') + '</td><td>' + (v.unit ? v.unit.name : '') + '</td><td>' + (v.goods_type ? v.goods_type.name : '') + '</td><td>' + v.stock + '</td><td>' + (v.note || '') + '</td></tr>';
                                        }
                                        html += '</table></div>';
                                        $('.chose_goods_boxs').html(html);
                                    }
                                }
                            });
                        }
                    });

                    // ===== 入库成品: 点击选择商品图标 =====
                    $(document).on("click", "#in_table .goods_code_span", function () {
                        if (switchs2 == 1) return false;
                        var obj = $(this).parents('tr');
                        var lawtable = document.getElementById("in_table");
                        var rows = lawtable.rows;
                        var htmlp = rows[1].innerHTML;
                        var html = '';
                        parent.Fast.api.open("kuerp/Goods/chosegoods", "选择商品", {
                            area: ['90%', '75%'],
                            callback: function (goodsData) {
                                if (goodsData != undefined) {
                                    $(goodsData).each(function (k, v) {
                                        if (k > 0) {
                                            html = '<tr class="goods_id' + v.goods_id + '_' + v.goods_attr + '">' + htmlp + '</tr>';
                                            $(obj).after(html);
                                        }
                                    });
                                    $.ajax({
                                        url: "kuerp/goods/getUnit", type: "POST",
                                        data: { 'where': arrayToJsonString(goodsData) },
                                        success: function (res) {
                                            res = JSON.parse(res);
                                            $(res).each(function (kp, vp) {
                                                var thisobj = (kp == 0) ? obj : $('.goods_id' + vp.goods_id + '_' + vp.goods_attr);
                                                thisobj.find('.goods_code').val(vp.goods.goods_code);
                                                thisobj.find('.goods_name').val(vp.goods.goods_code + ' ' + vp.goods.goods_name);
                                                $(vp.unit).each(function (k, v) {
                                                    if (v.is_default == 1) {
                                                        changeGoods(vp.goods.goods_code, vp.goods.manager_warehouse_id, vp.goods_attr, thisobj, '', vp.number, 'in');
                                                    }
                                                });
                                            });
                                        }
                                    });
                                }
                            }
                        });
                    });

                    // 入库成品: 悬浮搜索
                    $(document).on("input", "#in_table .goods_name", function () {
                        if (switchs2 != 1) {
                            var obj = $(this);
                            var goods_name = obj.val();
                            var top = obj.offset().top - (-30);
                            var left = obj.offset().left - (-15);
                            tableobj = $(this).parents('tr');
                            $.ajax({
                                url: "kuerp/goods/chosegoods", type: "POST", data: { 'goods_name': goods_name },
                                success: function (res) {
                                    if (res.total > 0) {
                                        var html = '<div class="chose_goods_box" style="left:' + left + 'px;top: ' + top + 'px;">\n' +
                                            '<table><tr><th>商品编号</th><th>商品名称</th><th>规格型号</th><th>计量单位</th><th>商品类别</th><th>库存数量</th><th>备注</th></tr>\n';
                                        var max = res.total > 5 ? 5 : res.total;
                                        for (var a = 0; a < max; a++) {
                                            var v = res.rows[a];
                                            html += '<tr class="chose_goods_tr" data-code="' + v.goods_code + '" data-name="' + v.goods_name + '" data-note="' + v.note + '" data-store="' + v.manager_warehouse_id + '" data-type="in"><td>' + v.goods_code + '</td><td>' + v.goods_name + '</td><td>' + (v.goods_specification_id || '') + '</td><td>' + (v.unit ? v.unit.name : '') + '</td><td>' + (v.goods_type ? v.goods_type.name : '') + '</td><td>' + v.stock + '</td><td>' + (v.note || '') + '</td></tr>';
                                        }
                                        html += '</table></div>';
                                        $('.chose_goods_boxs').html(html);
                                    }
                                }
                            });
                        }
                    });

                    // ===== 悬浮框: 点击选中商品 =====
                    $(document).on("click", ".chose_goods_tr", function () {
                        var obj = tableobj;
                        var goods_code = $(this).data('code');
                        var goods_name = $(this).data('name');
                        var store = $(this).data('store');
                        var type = $(this).data('type');
                        $('.chose_goods_boxs').html('');
                        if (type == 'out') {
                            obj.find('.goods_code').val(goods_code);
                            obj.find('.goods_names').val(goods_code + ' ' + goods_name);
                            obj.find('.stores_id').val(store);
                            changeGoods(goods_code, store, '', obj, '', 1, 'out');
                        } else {
                            obj.find('.goods_code').val(goods_code);
                            obj.find('.goods_name').val(goods_code + ' ' + goods_name);
                            obj.find('.stores_id').val(store);
                            changeGoods(goods_code, store, '', obj, '', 1, 'in');
                        }
                    });
                    // 关闭悬浮框
                    $(document).on("click", ".is-dialog", function () { $('.chose_goods_boxs').html(''); });

                    // ===== 商品编码 change =====
                    $(document).on("change", "#out_table .goods_code", function () {
                        var obj = $(this).parents('tr');
                        var goods_code = $(this).val();
                        $(goodsList).each(function (k, v) {
                            if (goods_code == v.goods_code) {
                                obj.find('.stores_id').val(v.manager_warehouse_id);
                                changeGoods(v.goods_code, v.manager_warehouse_id, '', obj, '', '', 'out');
                            }
                        });
                    });
                    $(document).on("change", "#in_table .goods_code", function () {
                        var obj = $(this).parents('tr');
                        var goods_code = $(this).val();
                        $(goodsList).each(function (k, v) {
                            if (goods_code == v.goods_code) {
                                obj.find('.stores_id').val(v.manager_warehouse_id);
                                changeGoods(v.goods_code, v.manager_warehouse_id, '', obj, '', '', 'in');
                            }
                        });
                    });

                    // ===== 属性 / 单位 / 仓库 change =====
                    $(document).on("change", "#out_table .goods_attr", function () {
                        var obj = $(this).parents('tr');
                        var goods_code = obj.find('.goods_code').val();
                        var attr = $(this).val();
                        var goods_unit = obj.find('.goods_unit').val();
                        var stores_id = obj.find('.stores_id').val();
                        var number = parseFloat(obj.find('.number').val()) || 1;
                        var supplyer_code = $('#supplyer_code').val();
                        $.ajax({
                            url: "kuerp/process_order/getUnit", type: "POST",
                            data: { 'goods_code': goods_code, 'store': stores_id, 'attr': attr, 'supplyer_code': supplyer_code },
                            success: function (res) {
                                res = JSON.parse(res);
                                $(res.unit).each(function (k, v) {
                                    if (v.id == goods_unit || v.is_default == 1) {
                                        obj.find('.allow_stock').val(v.stock);
                                        obj.find('.unit_price').val(parseFloat(v.purchase).toFixed(price_decimal));
                                        obj.find('.all_money').val((parseFloat(v.purchase) * number).toFixed(price_decimal));
                                    }
                                });
                            }
                        });
                    });
                    $(document).on("change", "#out_table .goods_unit", function () {
                        var obj = $(this).parents('tr');
                        var goods_code = obj.find('.goods_code').val();
                        var attr = obj.find('.goods_attr').val();
                        var goods_unit = $(this).val();
                        var stores_id = obj.find('.stores_id').val();
                        var number = parseFloat(obj.find('.number').val()) || 1;
                        var supplyer_code = $('#supplyer_code').val();
                        $.ajax({
                            url: "kuerp/process_order/getUnit", type: "POST",
                            data: { 'goods_code': goods_code, 'store': stores_id, 'attr': attr, 'supplyer_code': supplyer_code },
                            success: function (res) {
                                res = JSON.parse(res);
                                $(res.unit).each(function (k, v) {
                                    if (v.id == goods_unit) {
                                        obj.find('.allow_stock').val(v.stock);
                                        obj.find('.unit_price').val(parseFloat(v.purchase).toFixed(price_decimal));
                                        obj.find('.all_money').val((parseFloat(v.purchase) * number).toFixed(price_decimal));
                                    }
                                });
                            }
                        });
                    });
                    $(document).on("change", "#out_table .stores_id", function () {
                        var obj = $(this).parents('tr');
                        var goods_code = obj.find('.goods_code').val();
                        var attr = obj.find('.goods_attr').val();
                        var goods_unit = obj.find('.goods_unit').val();
                        var stores_id = $(this).val();
                        var number = parseFloat(obj.find('.number').val()) || 1;
                        var supplyer_code = $('#supplyer_code').val();
                        $.ajax({
                            url: "kuerp/process_order/getUnit", type: "POST",
                            data: { 'goods_code': goods_code, 'store': stores_id, 'attr': attr, 'supplyer_code': supplyer_code },
                            success: function (res) {
                                res = JSON.parse(res);
                                $(res.unit).each(function (k, v) {
                                    if (v.id == goods_unit || v.is_default == 1) {
                                        obj.find('.allow_stock').val(v.stock);
                                        obj.find('.unit_price').val(parseFloat(v.purchase).toFixed(price_decimal));
                                        obj.find('.all_money').val((parseFloat(v.purchase) * number).toFixed(price_decimal));
                                    }
                                });
                            }
                        });
                    });
                    // 入库成品: 仓库change → 更新可用库存
                    $(document).on("change", "#in_table .stores_id", function () {
                        var obj = $(this).parents('tr');
                        var goods_code = obj.find('.goods_code').val();
                        var attr = obj.find('.goods_attr').val();
                        var stores_id = $(this).val();
                        $.ajax({
                            url: "kuerp/process_order/getUnit", type: "POST",
                            data: { 'goods_code': goods_code, 'store': stores_id, 'attr': attr },
                            success: function (res) {
                                res = JSON.parse(res);
                                $(res.unit).each(function (k, v) {
                                    if (v.is_default == 1) { obj.find('.allow_stock').val(v.stock); }
                                });
                            }
                        });
                    });

                    // ===== 出库: 数量change → 重算金额（后台计算，隐藏域保存） =====
                    $(document).on("change", "#out_table .number", function () {
                        var obj = $(this).parents('tr');
                        var unit_price = parseFloat(obj.find('.unit_price').val()) || 0;
                        var number = parseFloat($(this).val()) || 0;
                        obj.find('.all_money').val((unit_price * number).toFixed(price_decimal));
                    });

                    // ===== 核心: changeGoods (出库/入库通用) =====
                    function changeGoods(goods_code, store, attr, obj, goods_unit, number, type) {
                        var supplyer_code = $('#supplyer_code').val();
                        $.ajax({
                            url: "kuerp/process_order/getUnit", type: "POST",
                            data: { 'goods_code': goods_code, 'store': store, 'attr': attr, 'supplyer_code': type == 'out' ? supplyer_code : '' },
                            success: function (res) {
                                res = JSON.parse(res);
                                goods = res;
                                if (type == 'out') {
                                    if (goods.goods.batch == 1) { obj.find('.guarantee').val(goods.goods.shelf_life); }
                                }
                                if (number == '' || isNaN(parseFloat(number))) { number = 1; }
                                else { number = parseFloat(number); }
                                var attrarr = res.attr;
                                var attr_html = '<option value=""></option>';
                                $(attrarr).each(function (k, v) { attr_html += '<option value="' + v.id + '">' + v.attr_name + '</option>'; });
                                obj.find('.goods_attr').html(attr_html);
                                obj.find('.number').val(number.toFixed(number_decimal));
                                if (attr != '') { obj.find('.goods_attr').val(attr); }
                                if (store != '') { obj.find('.stores_id').val(store); }

                                var unit = res.unit;
                                var unit_html = '';
                                $(unit).each(function (k, v) {
                                    unit_html += '<option value="' + v.id + '">' + v.name + '</option>';
                                });
                                obj.find('.goods_unit').html(unit_html);
                                if (goods_unit != '') { obj.find('.goods_unit').val(goods_unit); }

                                $(unit).each(function (k, v) {
                                    if ((goods_unit != '' && v.id == goods_unit) || (goods_unit == '' && v.is_default == 1)) {
                                        if (type == 'out') {
                                            obj.find('.allow_stock').val(v.stock);
                                            // 成本数据写入隐藏域（页面不展示）
                                            obj.find('.unit_price').val(parseFloat(v.purchase).toFixed(price_decimal));
                                            obj.find('.all_money').val((parseFloat(v.purchase) * number).toFixed(price_decimal));
                                        }
                                    }
                                });
                            }
                        });
                    }

                    function arrayToJsonString(o) {
                        var len = o.length;
                        var new_arr = new Array();
                        var str = '', strone = '', strs = '', jsonstr = '';
                        for (var i = 0; i < len; i++) {
                            new_arr = o[i];
                            for (var k in new_arr) { strone += '"' + k + '"' + ':' + '"' + new_arr[k] + '"' + ','; }
                            str = '{' + strone.substring(0, strone.length - 1) + '}';
                            strone = '';
                            strs += str + ',';
                            new_arr = [];
                        }
                        strs = '[' + strs.substring(0, strs.length - 1) + ']';
                        return strs;
                    }

                    // ===== 添加行 / 删除行 =====
                    $(document).on("click", "#out_table .add", function () {
                        var lawtable = document.getElementById("out_table");
                        var rows = lawtable.rows;
                        // 从最后一行（模板行）克隆，保证新行是空的
                        var html = rows[rows.length - 1].innerHTML;
                        html = '<tr>' + html + '</tr>';
                        var obj = $(this).parents('tr');
                        $(obj).after(html);
                        layui.use('laydate', function () {
                            var laydate = layui.laydate;
                            lay('.manufacture').each(function () { laydate.render({ elem: this, trigger: "click" }); });
                            lay('.validity').each(function () { laydate.render({ elem: this, trigger: "click" }); });
                        });
                    });
                    $(document).on("click", "#out_table .del", function () {
                        var lawtable = document.getElementById("out_table");
                        var rows = lawtable.rows;
                        if (rows.length > 3) { $(this).parents('tr').remove(); }
                        else { Toastr.error('至少保留一条出库原材料'); }
                    });
                    $(document).on("click", "#in_table .add", function () {
                        var lawtable = document.getElementById("in_table");
                        var rows = lawtable.rows;
                        // 从最后一行（模板行）克隆，保证新行是空的
                        var html = rows[rows.length - 1].innerHTML;
                        html = '<tr>' + html + '</tr>';
                        var obj = $(this).parents('tr');
                        $(obj).after(html);
                    });
                    $(document).on("click", "#in_table .del", function () {
                        var lawtable = document.getElementById("in_table");
                        var rows = lawtable.rows;
                        // 至少保留 header + 1条数据行
                        if (rows.length > 3) { $(this).parents('tr').remove(); }
                        else { Toastr.error('至少保留一条入库成品'); }
                    });

                    // ===== 批次选择 =====
                    var batch_obj;
                    $(document).on("click", "#out_table .batch_span", function () {
                        var obj = $(this).parents('tr');
                        batch_obj = obj;
                        var goods_code = obj.find('.goods_code').val();
                        var attr = obj.find('.goods_attr').val();
                        var store = obj.find('.stores_id').val();
                        $.ajax({
                            url: "kuerp/goods_purchase/getBranch", type: "POST",
                            data: { 'goods_code': goods_code, 'attr': attr, 'store': store },
                            success: function (res) {
                                res = JSON.parse(res);
                                var html = '<form class="form-horizontal"><div class="list list_form"><table id="serial_table">' +
                                    '<tr><th><span class="must">*</span>批次</th><th>生产日期</th><th>保质期</th><th>过期时间</th></tr><tbody id="serial_tbody">';
                                for (var a = 0; a < res.batch.length; a++) {
                                    html += '<tr><td><div><input class="batch_radio" type="radio" name="batch_id" value="' + res.batch[a].id + '">' + res.batch[a].batch_name + '</td>' +
                                        '<td><div>' + res.batch[a].manufacture + '</td><td><div>' + res.batch[a].guarantee + '</td><td><div>' + res.batch[a].validity + '</td></tr>';
                                }
                                html += '</tbody></table></div><div class="form-group"><label class="control-label col-xs-12 col-sm-2"></label>' +
                                    '<div class="col-xs-12 col-sm-8"><button type="button" id="batch_sure_btn" class="btn btn-success btn-embossed">确定</button>' +
                                    '<button type="button" class="btn btn-default btn-embossed" onclick="layer.closeAll()">取消</button></div></div></form>';
                                layer.open({ type: 1, title: '选择批次', shadeClose: true, shade: false, maxmin: true, area: ['780px', '550px'], content: html });
                            }
                        });
                    });
                    $(document).on("click", "#batch_sure_btn", function () {
                        var batch_id = $("input[name='batch_id']:checked").val();
                        $.ajax({
                            url: "kuerp/goods_purchase/getBranch", type: "POST",
                            data: { 'goods_code': batch_obj.find('.goods_code').val(), 'attr': batch_obj.find('.goods_attr').val(), 'store': batch_obj.find('.stores_id').val() },
                            success: function (res) {
                                res = JSON.parse(res);
                                $(res.batch).each(function (k, v) {
                                    if (v.id == batch_id) {
                                        batch_obj.find('.batch_id').val(v.id);
                                        batch_obj.find('.batch_sec').val(v.batch_name);
                                        batch_obj.find('.manufacture').val(v.manufacture);
                                        batch_obj.find('.guarantee').val(v.guarantee);
                                        batch_obj.find('.validity').val(v.validity);
                                        layer.closeAll();
                                    }
                                });
                            }
                        });
                    });
                    // 手动改批次 → 清空batch_id
                    $(document).on("change", "#out_table .batch_sec", function () {
                        $(this).parents('tr').find('.batch_id').val('');
                    });

                    // 表单提交
                    Form.api.bindevent($("form[role=form]"), function (data, ret) {
                        $.ajax({
                            url: "kuerp/Goods/getMenu", type: "POST", data: { 'url': 'kuerp/process_order' },
                            success: function (res) {
                                if (res.code == 1) {
                                    if (reloadStatus) { Fast.api.addtabs('kuerp/process_order', res.msg.title, res.msg.icon); parent.location.reload(); }
                                    else { $('.btn-refresh').click(); }
                                }
                            }
                        });
                    });
                });
            }
        }
    };
    return Controller;
});
