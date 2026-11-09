/**
 * 销货退货「退货入库」弹窗交互逻辑
 * 独立的纯 JS 文件，不依赖 RequireJS（与 goods_return_outbound_layer.js 同一套路）
 */
(function () {
    // 关闭父窗口弹层（触发父页面的 callback 刷新列表）
    window.closeParentLayer = function () {
        try {
            parent.Fast.api.close();
        } catch (e1) {
            try {
                parent.layer.closeAll();
            } catch (e2) {
                window.close();
            }
        }
    };

    function init() {
        var form = document.getElementById('returninbound-form');
        if (!form) return;

        var inputs = document.querySelectorAll('.in-number');

        function updateTotal() {
            var total = 0;
            inputs.forEach(function (input) {
                var val = parseFloat(input.value) || 0;
                if (val > 0) total += val;
            });
            var el = document.getElementById('in-total');
            if (el) el.textContent = (Math.round(total * 10000) / 10000).toString();
        }

        inputs.forEach(function (input) {
            input.addEventListener('input', updateTotal);
            input.addEventListener('change', function () {
                var val = parseFloat(this.value) || 0;
                var remain = parseFloat(this.getAttribute('data-remain')) || 0;
                if (val < 0) this.value = 0;
                if (val > remain) this.value = remain;
                updateTotal();
            });
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var hasQuantity = false;
            var allFull = true;
            var over = null;
            inputs.forEach(function (input) {
                var val = parseFloat(input.value) || 0;
                var remain = parseFloat(input.getAttribute('data-remain')) || 0;
                if (val > 0) hasQuantity = true;
                if (val < remain) allFull = false;
                if (val > remain + 0.00005) over = input;
            });
            if (over) {
                alert('本次入库数量不能超过剩余可入库数量');
                over.focus();
                return false;
            }
            if (!hasQuantity) {
                alert('请至少输入一项本次入库数量');
                return false;
            }
            if (!allFull) {
                if (!confirm('本次为部分入库，确认提交？')) {
                    return false;
                }
            }

            var submitBtn = form.querySelector('.btn-submit');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = '提交中...';
            }

            var formData = new FormData(form);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', form.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.code === 1) {
                            try { parent.Toastr.success(res.msg || '退货入库成功'); } catch (e) {}
                            try { parent.$('.btn-refresh').click(); } catch (e) {}
                            // 剩余待入库为 0 时直接关闭弹窗（否则重载会提示「没有待入库的退货单」）；
                            // 仍有剩余则重载本页，刷新剩余数量与入库记录
                            var remaining = (res.data && res.data.remaining !== undefined && res.data.remaining !== null)
                                ? parseInt(res.data.remaining, 10) : -1;
                            if (remaining === 0) {
                                closeParentLayer();
                            } else {
                                location.reload();
                            }
                            return;
                        }
                        alert(res.msg || '退货入库失败');
                    } catch (err) {
                        alert('请求失败，请重试');
                    }
                } else {
                    alert('请求失败，请重试');
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '确认入库';
                }
            };
            xhr.send(formData);
            return false;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
