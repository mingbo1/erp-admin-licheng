define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'kuerp/stone_stock/index',
                    table: 'kuerp_goods_stock',
                }
            });

            var table = $("#table");

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'goods_code',
                sortOrder: 'asc',
                search: true,
                columns: [
                    [
                        { field: 'goods_code', title: __('商品编码'), sortable: true },
                        { field: 'goods_name', title: __('商品名称'), sortable: true },
                        { field: 'unit_name', title: __('单位') },
                        { field: 'store_name', title: __('仓库') },
                        { field: 'stock', title: __('当前库存'), sortable: true },
                        { field: 'total_in', title: __('累计入库') },
                        { field: 'total_out', title: __('累计出库') },
                        { field: 'purchase', title: __('成本单价'), sortable: true, operate: 'BETWEEN' },
                        { field: 'stock_amount', title: __('库存金额'), sortable: true, operate: 'BETWEEN' },
                    ]
                ]
            });

            // 仓库筛选
            $(document).on('change', 'select[name=store_id]', function () {
                $('#table').bootstrapTable('refresh', {query: {store_id: $(this).val()}});
            });

            // 分类筛选
            $(document).on('change', 'select[name=goods_type_id]', function () {
                $('#table').bootstrapTable('refresh', {query: {goods_type_id: $(this).val()}});
            });

            Table.api.search();
        }
    };

    return Controller;
});
