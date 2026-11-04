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

            // 原始单据附件还在上传（按钮被禁用=上传中），此时提交会漏掉该附件
            var uploadBtn = document.getElementById('btn-upload-new');
            if (uploadBtn && uploadBtn.disabled) {
                alert('附件正在上传，请稍候再提交');
                return false;
            }

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
                                // 入库成功：刷新父列表 + 重载本页（已入库/待入库数量与下方「操作记录」同步更新）
                                // 不直接关闭弹窗，方便紧接着给这次入库上传原始单据；点「关闭」仍会触发父页面回调刷新
                                try { parent.Toastr.success(res.msg || '入库成功'); } catch (e) {}
                                try { parent.$('.btn-refresh').click(); } catch (e) {}
                                location.reload();
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

        initEnclosureSelect();
        initLogPanel();
    }

    // 本次入库前「选择原始单据」：选中的单据随入库一起提交，挂在本次入库的操作记录上
    function initEnclosureSelect() {
        var box = document.getElementById('enc-select');
        if (!box) return;

        var btn = document.getElementById('btn-chose-enc');
        var hidden = document.getElementById('enclosure_ids');
        var listEl = document.getElementById('sel-enc-list');
        if (!btn || !hidden || !listEl) return;

        var iconUrl = box.getAttribute('data-icon-url') || '';
        var listUrl = box.getAttribute('data-list-url') || '';
        var uploadUrl = box.getAttribute('data-upload-url') || '';
        var uploadBtn = document.getElementById('btn-upload-new');
        var uploadInput = document.getElementById('enc-upload-file');
        var selected = [];

        function esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function render() {
            listEl.innerHTML = '';
            if (!selected.length) {
                hidden.value = '';
                listEl.innerHTML = '<span class="sel-enc-empty">未选择（可留空）</span>';
                return;
            }
            hidden.value = selected.map(function (v) {
                return v.id;
            }).join(',');
            selected.forEach(function (enc) {
                var span = document.createElement('span');
                span.className = 'sel-enc-item';
                var img = parseInt(enc.is_image, 10) === 1
                    ? '<img src="' + esc(enc.url) + '">'
                    : '<img src="' + iconUrl + '?suffix=' + esc((enc.file_suffix || 'file').toLowerCase()) + '">';
                span.innerHTML = '<a href="' + esc(enc.url) + '" target="_blank">' + img +
                    '<span class="sel-enc-name">' + esc(enc.name) + '</span></a>' +
                    '<a href="javascript:;" class="sel-enc-del" data-id="' + parseInt(enc.id, 10) + '" title="移除">×</a>';
                listEl.appendChild(span);
            });
        }

        // 已选单据：回查名称/缩略图
        function loadSelected(ids) {
            ids = (ids || []).filter(function (v) {
                return parseInt(v, 10) > 0;
            });
            if (!ids.length) {
                selected = [];
                render();
                return;
            }
            var xhr = new XMLHttpRequest();
            xhr.open('POST', listUrl, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                var res = {};
                try { res = JSON.parse(xhr.responseText); } catch (err) {}
                if (res && res.rows) {
                    selected = res.rows;
                    render();
                } else {
                    alert('原始单据信息获取失败');
                }
            };
            xhr.send('ids=' + encodeURIComponent(ids.join(',')));
        }

        // 点 [选择原始单据] → 打开采购原始单据列表弹窗（多选）
        btn.addEventListener('click', function () {
            if (!window.parent || !window.parent.Fast) {
                alert('无法打开单据选择窗口');
                return;
            }
            window.parent.Fast.api.open('kuerp/goods_purchase_storage/enclosurepicker', '选择原始单据', {
                area: ['80%', '60%'],
                callback: function (ids) {
                    if (ids === undefined || ids === null) return;
                    loadSelected(Array.isArray(ids) ? ids : String(ids).split(','));
                }
            });
        });

        // 点 [添加单据附件] → 直接上传新文件，上传成功后加入本次入库的原始单据（提交时随 enclosure_ids 关联）
        if (uploadBtn && uploadInput && uploadUrl) {
            uploadBtn.addEventListener('click', function () {
                uploadInput.value = '';
                uploadInput.click();
            });
            uploadInput.addEventListener('change', function () {
                var file = uploadInput.files && uploadInput.files[0];
                if (!file) return;
                var oldText = uploadBtn.textContent;
                uploadBtn.disabled = true;
                uploadBtn.textContent = '上传中…';
                var formData = new FormData();
                formData.append('file', file);
                var xhr = new XMLHttpRequest();
                xhr.open('POST', uploadUrl, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) return;
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = oldText;
                    uploadInput.value = '';
                    var res = {};
                    try { res = JSON.parse(xhr.responseText); } catch (err) {}
                    if (res && res.code === 1 && res.data && parseInt(res.data.id, 10) > 0) {
                        var enc = res.data;
                        var exists = selected.some(function (v) {
                            return parseInt(v.id, 10) === parseInt(enc.id, 10);
                        });
                        if (!exists) {
                            selected.push({
                                id: enc.id,
                                name: enc.name,
                                url: enc.url,
                                is_image: enc.is_image,
                                file_suffix: enc.file_suffix
                            });
                        }
                        render();
                    } else {
                        alert((res && res.msg) ? res.msg : '上传失败，请重试');
                    }
                };
                xhr.send(formData);
            });
        }

        // 移除已选单据
        listEl.addEventListener('click', function (e) {
            var del = e.target.closest ? e.target.closest('.sel-enc-del') : null;
            if (!del) return;
            var id = parseInt(del.getAttribute('data-id'), 10);
            selected = selected.filter(function (v) {
                return parseInt(v.id, 10) !== id;
            });
            render();
        });

        render();
    }

    // 操作记录面板：每条入库记录后可上传/删除该次入库的原始单据
    function initLogPanel() {
        var panel = document.getElementById('log-panel');
        if (!panel) return;

        var uploadUrl = panel.getAttribute('data-upload-url');
        var delUrl = panel.getAttribute('data-del-url');
        var iconUrl = panel.getAttribute('data-icon-url');

        function escapeHtml(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        // 与服务端 storage.html 里的附件节点保持一致
        function encItemHtml(enc) {
            var url = escapeHtml(enc.url);
            var suffix = escapeHtml((enc.file_suffix || '').toLowerCase());
            var inner;
            if (parseInt(enc.is_image, 10) === 1) {
                inner = '<a href="' + url + '" target="_blank"><img src="' + url + '"></a>';
            } else {
                inner = '<a href="' + url + '" target="_blank"><img src="' + iconUrl + '?suffix=' + suffix + '">' +
                    '<span class="enc-suffix">' + suffix.toUpperCase() + '</span></a>';
            }
            return '<span class="enc-item" data-id="' + parseInt(enc.id, 10) + '">' + inner +
                '<a href="javascript:;" class="enc-del" data-id="' + parseInt(enc.id, 10) + '" title="删除该单据">×</a></span>';
        }

        // 点 [上传原始单据] → 触发该记录对应的隐藏 file input
        panel.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.btn-upload-enc') : null;
            if (btn) {
                var input = panel.querySelector('.enc-file[data-log-id="' + btn.getAttribute('data-log-id') + '"]');
                if (input) input.click();
                return;
            }
            var del = e.target.closest ? e.target.closest('.enc-del') : null;
            if (del) {
                if (!confirm('确认删除该原始单据？')) return;
                var span = del.closest('.enc-item');
                var fd = new FormData();
                fd.append('ids', del.getAttribute('data-id'));
                var xhr = new XMLHttpRequest();
                xhr.open('POST', delUrl + '/ids/' + del.getAttribute('data-id'), true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) return;
                    var res = {};
                    try { res = JSON.parse(xhr.responseText); } catch (err) {}
                    if (res.code === 1) {
                        if (span && span.parentNode) span.parentNode.removeChild(span);
                    } else {
                        alert(res.msg || '删除失败');
                    }
                };
                xhr.send(fd);
            }
        });

        // 选中文件后立即上传到该条入库记录
        panel.addEventListener('change', function (e) {
            var input = e.target;
            if (!input.classList || !input.classList.contains('enc-file')) return;
            if (!input.files || !input.files.length) return;
            var logId = input.getAttribute('data-log-id');
            var fd = new FormData();
            fd.append('file', input.files[0]);
            var xhr = new XMLHttpRequest();
            xhr.open('POST', uploadUrl + '/ids/' + logId, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                input.value = '';
                var res = {};
                try { res = JSON.parse(xhr.responseText); } catch (err) {}
                if (res.code === 1 && res.data) {
                    var list = panel.querySelector('.enc-list[data-log-id="' + logId + '"]');
                    if (list) list.insertAdjacentHTML('beforeend', encItemHtml(res.data));
                } else {
                    alert(res.msg || '上传失败');
                }
            };
            xhr.send(fd);
        });
    }

    // DOM Ready 后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
