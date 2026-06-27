/**
 * 采购入库 storage 页面交互逻辑
 * 独立的纯 JS 文件，不依赖 RequireJS
 */
(function () {
    // 关闭父窗口弹层（触发父页面的 callback 刷新列表）
    window.closeParentLayer = function () {
        try {
            // FastAdmin 标准关闭方式
            parent.Fast.api.close();
        } catch (e1) {
            try {
                // layui layer 直接关闭
                parent.layer.closeAll();
            } catch (e2) {
                // 最终兜底
                window.close();
            }
        }
    };

    // 初始化入口
    function init() {
        var form = document.getElementById('storage-form');
        if (!form) return;

        // 计算入库总量
        function updateTotal() {
            var inputs = document.querySelectorAll('.storage-number');
            var total = 0;
            inputs.forEach(function (input) {
                var val = parseFloat(input.value) || 0;
                if (val < 0) val = 0;
                var remain = parseFloat(input.getAttribute('data-remain')) || 0;
                if (val > remain) val = remain;
                total += val;
            });
            var el = document.getElementById('storage-total');
            if (el) el.textContent = total.toFixed(4);
        }

        document.querySelectorAll('.storage-number').forEach(function (input) {
            input.addEventListener('input', updateTotal);
            input.addEventListener('change', function () {
                var val = parseFloat(this.value) || 0;
                var remain = parseFloat(this.getAttribute('data-remain')) || 0;
                if (val < 0) this.value = 0;
                if (val > remain) this.value = remain;
                updateTotal();
            });
        });

        // 表单 AJAX 提交
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var inputs = document.querySelectorAll('.storage-number');

            // 验证：至少有一个入库数量大于0
            var hasQuantity = false;
            inputs.forEach(function (input) {
                if (parseFloat(input.value) > 0) hasQuantity = true;
            });
            if (!hasQuantity) {
                alert('请至少输入一个商品的入库数量');
                return false;
            }

            // 部分入库确认
            var allFull = true;
            inputs.forEach(function (input) {
                var val = parseFloat(input.value) || 0;
                var remain = parseFloat(input.getAttribute('data-remain')) || 0;
                if (val < remain) allFull = false;
            });
            if (!allFull) {
                if (!confirm('本次为部分入库，确认提交？')) {
                    return false;
                }
            }

            // 禁用提交按钮防止重复提交
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
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var res = JSON.parse(xhr.responseText);
                            if (res.code === 1) {
                                // 入库成功 → 关闭弹窗，父页面 callback 自动刷新列表
                                closeParentLayer();
                            } else {
                                alert(res.msg || '入库失败');
                                if (submitBtn) {
                                    submitBtn.disabled = false;
                                    submitBtn.textContent = '确认入库';
                                }
                            }
                        } catch (err) {
                            alert('请求失败，请重试');
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.textContent = '确认入库';
                            }
                        }
                    } else {
                        alert('请求失败，请重试');
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = '确认入库';
                        }
                    }
                }
            };
            xhr.send(formData);
            return false;
        });
    }

    // DOM Ready 后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
