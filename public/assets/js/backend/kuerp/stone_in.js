define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格
            Table.api.init({
                extend: {
                    index_url: 'kuerp/stone_in/index',
                    add_url: 'kuerp/stone_in/add',
                    edit_url: 'kuerp/stone_in/edit',
                    del_url: 'kuerp/stone_in/del',
                    table: 'kuerp_stone_inbound',
                }
            });

            var table = $("#table");

            // 表格配置
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                sortOrder: 'desc',
                search: true,
                searchFormVisible: false,
                columns: [
                    [
                        { checkbox: true },
                        { field: 'in_no', title: __('入库单号'), sortable: true },
                        { field: 'goods_code', title: __('商品编码') },
                        { field: 'goods_name', title: __('商品名称') },
                        { field: 'unit_name', title: __('单位') },
                        { field: 'quantity', title: __('数量'), sortable: true },
                        { field: 'unit_price', title: __('单价'), sortable: true, operate: 'BETWEEN' },
                        { field: 'supplier_name', title: __('供应商') },
                        { field: 'store_name', title: __('仓库') },
                        { field: 'total_amount', title: __('总金额'), sortable: true, operate: 'BETWEEN' },
                        {
                            field: 'examine_status',
                            title: __('审核状态'),
                            formatter: Table.api.formatter.status,
                            cellStyle: function (value) {
                                return value == 1 ? { css: { "color": "green" } } : {};
                            },
                            replace: [__('未审核'), __('已审核')]
                        },
                        {
                            field: 'id',
                            title: __('操作'),
                            width: 280,
                            events: Table.api.events.operate,
                            formatter: function (value, row) {
                                var html = '<a href="kuerp/stone_in/detail/ids/' + row.id + '" class="btn btn-info btn-xs"><i class="fa fa-eye"></i> ' + __('详情') + '</a>';
                                if (row.examine_status == 0) {
                                    html += '<a href="javascript:;" class="btn btn-success btn-xs examine-btn" data-id="' + row.id + '"><i class="fa fa-check"></i> ' + __('审核') + '</a>';
                                } else {
                                    html += '<a href="javascript:;" class="btn btn-warning btn-xs examine-btn" data-id="' + row.id + '"><i class="fa fa-undo"></i> ' + __('反审核') + '</a>';
                                }
                                return html;
                            }
                        }
                    ]
                ]
            });

            // 审核/反审核
            $(document).on('click', '.examine-btn', function () {
                var id = $(this).data('id');
                var action = $(this).hasClass('btn-success') ? '审核' : '反审核';
                Layer.confirm(__('确定' + action + '吗？'), function (index) {
                    Fast.api.ajax({
                        url: 'kuerp/stone_in/examine/ids/' + id,
                        success: function () {
                            $('#table').bootstrapTable('refresh');
                            Layer.close(index);
                        }
                    });
                });
            });

            // 通用搜索
            Table.api.search();
        },

        add: function () {
            Controller.bindGoodsEvents();
            Form.api.bindevent($("#add-form"));
        },

        bindGoodsEvents: function () {
            // 选择商品
            $(document).on('click', '.select-goods-btn', function () {
                var tr = $(this).closest('tr');
                var id = tr.data('id');
                var code = tr.data('code');
                var name = tr.data('name');
                var unit = tr.data('unit');
                var price = tr.data('price');

                // 检查是否已添加
                var exists = false;
                $('#goods-table tbody tr').each(function () {
                    if ($(this).data('id') == id) {
                        exists = true;
                        return false;
                    }
                });

                if (exists) {
                    Layer.msg(__('商品已添加'));
                    return;
                }

                var row = '<tr data-id="' + id + '">';
                row += '<td>' + code + '</td>';
                row += '<td>' + name + '</td>';
                row += '<td>' + (tr.data('spec') || '') + '</td>';
                row += '<td>' + (tr.data('vendor') || '') + '</td>';
                row += '<td>' + (tr.data('pack') || '') + '</td>';
                row += '<td>' + unit + '</td>';
                row += '<td><input type="number" name="goods[' + id + '][quantity]" class="form-control input-sm goods-qty" value="1" min="0.01" step="0.01" style="width:100px"></td>';
                row += '<td><input type="number" name="goods[' + id + '][unit_price]" class="form-control input-sm goods-price" value="' + price + '" min="0" step="0.01" style="width:100px"></td>';
                row += '<td class="goods-total">' + parseFloat(price).toFixed(2) + '</td>';
                row += '<td><button type="button" class="btn btn-danger btn-xs remove-goods-btn"><i class="fa fa-trash"></i></button></td>';
                row += '<input type="hidden" name="goods[' + id + '][goods_id]" value="' + id + '">';
                row += '<input type="hidden" name="goods[' + id + '][unit_name]" value="' + unit + '">';
                $('#goods-table tbody').append(row);
                Controller.calcTotal();
                $('#goods-modal').modal('hide');
            });

            // 删除商品行
            $(document).on('click', '.remove-goods-btn', function () {
                $(this).closest('tr').remove();
                Controller.calcTotal();
            });

            // 数量/单价变更重新计算
            $(document).on('input', '.goods-qty, .goods-price', function () {
                var tr = $(this).closest('tr');
                var qty = parseFloat(tr.find('.goods-qty').val()) || 0;
                var price = parseFloat(tr.find('.goods-price').val()) || 0;
                tr.find('.goods-total').text((qty * price).toFixed(2));
                Controller.calcTotal();
            });

            // 搜索商品
            $('#goods-search').on('keyup', function () {
                var keyword = $(this).val().toLowerCase();
                $('#goods-select-table tbody tr').each(function () {
                    var text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(keyword) > -1);
                });
            });

            // 打开商品选择弹窗
            $('#add-goods-btn').on('click', function () {
                $('#goods-search').val('');
                $('#goods-select-table tbody tr').show();
                $('#goods-modal').modal('show');
            });
        },

        calcTotal: function () {
            var total = 0;
            $('#goods-table tbody tr').each(function () {
                var val = parseFloat($(this).find('.goods-total').text()) || 0;
                total += val;
            });
            $('#total-amount').text(total.toFixed(2));
        }
    };

    return Controller;
});
