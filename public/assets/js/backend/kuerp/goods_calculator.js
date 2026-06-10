define(['jquery', 'bootstrap', 'backend', 'table', 'form', '/assets/addons/kuerp/layui/layui.js'], function ($, undefined, Backend, Table, Form, Layui) {

    var Controller = {
        index: function () {
            layui.use(['layer'], function () {
                var layer = layui.layer;

                // 计算按钮事件
                $('#btn-calculate').on('click', function () {
                    var bomId = $('#bom-select').val();
                    var storesId = $('#stores-select').val() || '';

                    if (!bomId) {
                        Toastr.error(__('Please_select_bom'));
                        return;
                    }

                    var btn = $(this);
                    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + __('Calculating'));

                    $.ajax({
                        url: 'kuerp/goods_calculator/calculate',
                        type: 'POST',
                        data: {
                            bom_id: bomId,
                            stores_id: storesId
                        },
                        success: function (res) {
                            btn.prop('disabled', false).html('<i class="fa fa-calculator"></i> ' + __('Calculate'));
                            if (res.code == 1) {
                                renderResult(res.data);
                            } else {
                                Toastr.error(res.msg);
                            }
                        },
                        error: function () {
                            btn.prop('disabled', false).html('<i class="fa fa-calculator"></i> ' + __('Calculate'));
                            Toastr.error('计算失败，请重试');
                        }
                    });
                });

                // BOM选择变化时也可自动触发计算
                $('#bom-select').on('change', function () {
                    var bomId = $(this).val();
                    if (bomId) {
                        $('#btn-calculate').trigger('click');
                    }
                });
            });
        }
    };

    function renderResult(data) {
        $('#empty-state').hide();
        $('#result-area').show();

        // 更新数量
        $('#max-count').text(data.max_assemble_count);
        $('#parent-info').text(data.parent_goods_name + ' (' + data.parent_goods_code + ')');

        // 瓶颈提示
        if (data.bottleneck && data.bottleneck.length > 0) {
            $('#bottleneck-alert').show();
            $('#bottleneck-text').text(data.bottleneck.join('、') + ' （库存不足，限制了最大产量）');
        } else {
            $('#bottleneck-alert').hide();
        }

        // 改变最大数量的颜色（0=红色，>0=绿色）
        if (data.max_assemble_count == 0) {
            $('#max-count').css('color', '#e74c3c');
        } else {
            $('#max-count').css('color', '#18bc9c');
        }

        // 渲染明细表格
        var tbody = $('#calc-result-tbody');
        tbody.empty();

        if (data.children && data.children.length > 0) {
            $(data.children).each(function (k, child) {
                var isBottleneck = child.producible == data.max_assemble_count;
                var typeLabel = getProductTypeLabel(child.product_type);
                var rowClass = isBottleneck ? 'danger' : '';
                var bottleneckText = isBottleneck ? '<span class="label label-danger">' + __('Yes') + '</span>' : '<span class="label label-success">' + __('No') + '</span>';
                var producibleClass = isBottleneck ? 'text-danger' : 'text-success';

                var row = '<tr class="' + rowClass + '">' +
                    '<td>' + child.goods_name + '</td>' +
                    '<td>' + child.goods_code + '</td>' +
                    '<td>' + typeLabel + '</td>' +
                    '<td>' + child.consumption + '</td>' +
                    '<td><strong>' + child.total_stock + '</strong></td>' +
                    '<td><strong class="' + producibleClass + '" style="font-size:16px;">' + child.producible + '</strong></td>' +
                    '<td>' + bottleneckText + '</td>' +
                    '</tr>';
                tbody.append(row);
            });
        }
    }

    function getProductTypeLabel(type) {
        if (type == 'finished') return '成品';
        if (type == 'semi_finished') return '半成品';
        if (type == 'part') return '零件';
        return '';
    }

    return Controller;
});
