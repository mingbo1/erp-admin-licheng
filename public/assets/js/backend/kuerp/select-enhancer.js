/**
 * 进销存下拉框增强：全部原生 <select> 转为 bootstrap-select 并支持关键字搜索
 *
 * 背景：kuerp 模块约 648 个下拉框，基础资料（客户/供应商/商品/业务员/账户等）数据量大，
 * 原生下拉翻找困难。FastAdmin 自带的 form.js 只在表单初始化时转换带 .selectpicker 的元素，
 * 覆盖不到列表页搜索栏、表格动态行、layer 弹窗。
 *
 * 本模块做三件事：
 *   1. 全局默认开启 liveSearch（可用 data-live-search="false" 单元素关闭）
 *   2. 页面加载时转换所有 select；动态插入的 select 由 MutationObserver 自动转换
 *   3. 已转换 select 的选项被重新填充、或被切换 disabled 时，自动 selectpicker('refresh')
 *
 * 例外：.layui-form 内的 select 由 layui 自己的 form 模块渲染（见下），不转换，改为开启 layui 的搜索。
 *
 * 依赖 jQuery / bootstrap-select / bootstrap-select-lang，仅 kuerp 页面加载
 * （入口在 application/admin/view/kuerp/common/menu.html，按 Config.jsname 判断）。
 */
define(['jquery', 'bootstrap-select', 'bootstrap-select-lang'], function ($) {
    'use strict';

    var WRAPPER_CLASS = 'bootstrap-select';
    var TITLE_CLASS = 'bs-title-option';           // selectpicker 渲染时插入的占位 option，需忽略
    var SKIP_SELECTOR = '[data-select-enhance="skip"]';  // 容器标记：内部 select 保持原生
    var FLAG = 'selectEnhanced';
    var MENU_SIZE = 10;                             // 下拉菜单最多显示条数，超出滚动

    // 列表页搜索栏用的是 <form class="layui-form layui-search">，layui 的 form 模块
    // （各 index 页 JS 里的 layui.use(['table','upload','form'])）在 DOM ready 时会把
    // .layui-form 里的 select 全部渲染成 layui 自己的下拉，原生 select 由
    // layui.css 的 .layui-form select{display:none} 隐藏。
    var LAYUI_FORM = '.layui-form';
    var LAYUI_RENDERED = '.layui-form-select';

    // layer 弹层（layer.open 生成的 .layui-layer，position:fixed）
    var LAYER = '.layui-layer';

    var refreshQueue = [];
    var refreshTimer = null;

    // layui 渲染的 select：打上 lay-search，layui 就会把下拉标题渲染成可输入的搜索框
    // （layui.js 中 c = "string" == typeof attr("lay-search")，为空串属性也生效）。
    function markLayuiSearch(el) {
        if (!el.hasAttribute('lay-search')) {
            el.setAttribute('lay-search', '');
        }
    }

    function markLayuiSearchAll(root) {
        var scope = (root && root.nodeType === 1) ? root : document;
        if (!scope.querySelectorAll) {
            return;
        }
        var list = scope.querySelectorAll(LAYUI_FORM + ' select');
        for (var i = 0; i < list.length; i++) {
            markLayuiSearch(list[i]);
        }
    }

    // 兜底：layui 若在我们的标记之前就完成了渲染，标题框会是 readonly（未开启搜索），
    // 此时重新渲染一次 select，让 search 生效。渲染取值自当前 selectedIndex，不影响已选值。
    function layuiRenderedWithoutSearch() {
        var rendered = document.querySelectorAll(LAYUI_FORM + ' ' + LAYUI_RENDERED);
        for (var i = 0; i < rendered.length; i++) {
            var input = rendered[i].querySelector('.layui-select-title input');
            if (input && input.hasAttribute('readonly')) {
                return true;
            }
        }
        return false;
    }

    function rerenderLayuiSelects() {
        if (window.layui && layui.form && typeof layui.form.render === 'function') {
            try {
                layui.form.render('select');
            } catch (e) {
                /* layui 未就绪则忽略：标记已写好，其首次渲染自然会带搜索 */
            }
        }
    }

    // 已初始化：selectpicker 会把 select 移入 .bootstrap-select 包裹层
    function isManaged(el) {
        var p = el.parentNode;
        return !!(p && p.nodeType === 1 && p.classList.contains(WRAPPER_CLASS));
    }

    // 克隆/复制模板行产生的残留结构：包裹层还在，但没有对应的 selectpicker 实例。
    // 拆掉残留的按钮、菜单与占位 option 并解包，交由 convert() 重新初始化。
    function repair(el) {
        var $wrapper = $(el).closest('.' + WRAPPER_CLASS);
        if (!$wrapper.length) {
            return;
        }
        $wrapper.children().not(el).remove();
        $(el).children('option.' + TITLE_CLASS).remove();
        $(el).unwrap();
    }

    function convert(el) {
        if (!el || el.nodeName !== 'SELECT') {
            return;
        }
        // 用 jQuery data 而非 data-* 属性记录状态：模板行的 innerHTML / clone() 会把属性一起带走
        if ($.data(el, FLAG)) {
            return;
        }
        if (el.closest && el.closest(SKIP_SELECTOR)) {
            return;
        }
        // selectpage / cxselect 有自己的 UI，不转换
        if (el.classList.contains('selectpage') || el.classList.contains('cxselect')) {
            return;
        }
        // layui 会自行渲染 .layui-form 内的 select，再包一层 bootstrap-select 会出现两份控件
        if (el.closest && el.closest(LAYUI_FORM)) {
            markLayuiSearch(el);
            return;
        }

        var $el = $(el);
        if ($el.data('selectpicker')) {
            $.data(el, FLAG, 1);            // 已由 form.js 初始化过
            return;
        }
        if (isManaged(el)) {
            repair(el);                     // 复制出来的残留包裹层
        }

        $.data(el, FLAG, 1);
        el.classList.add('selectpicker');   // 原生 select 靠 select.selectpicker 规则隐藏
        try {
            // 下拉菜单默认挂在 .bootstrap-select 包裹层里，会被祖先的 overflow:auto 裁切：
            //   单据表格 .list{overflow:auto}（kuerp.css）——行内下拉框只露出半截菜单
            //   layer 页面型弹层的 .layui-layer-content{overflow:auto}（layui.css）
            // 所以需要指定 container，把菜单移出这些裁切容器：
            //   弹层内 → 挂到该弹层（.layui-layer）自身。既逃出上述裁切，又留在弹层的
            //     层叠上下文内，天然盖在弹层内容之上（挂 body 会被弹层 z-index 19891014 盖住）；
            //     .layui-layer 自身没有 overflow，不会裁切菜单。
            //   弹层外的 .list → 挂到 body。
            // 其余（普通表单等）保持默认容器。
            // 注意：插件内部会读 options.template，不能传 null
            var layer = el.closest ? el.closest(LAYER) : null;
            if (layer) {
                $el.selectpicker({ container: layer });
            } else if (el.closest && el.closest('.list')) {
                $el.selectpicker({ container: 'body' });
            } else {
                $el.selectpicker();
            }
        } catch (e) {
            $.data(el, FLAG, 0);
            console.error('[select-enhancer] 初始化失败', e);
        }
    }

    // 视图里 class 带 selectpicker 的下拉框由 FastAdmin 的 form.js 在 business bindevent
    // 时初始化；本模块由 kuerp 视图（common/menu.html）在 require 配置就绪后引入，可能排在
    // 业务 JS 之后。这种情况下那些下拉框是在本模块的全局默认（liveSearch）生效之前初始化
    // 的，没有搜索框，这里重建一次补上。
    // 重建走 destroy + convert：data-* 等配置仍从元素属性读取，已选值不受影响。
    function upgradeExistingPickers() {
        $('select.selectpicker').each(function () {
            var inst = $(this).data('selectpicker');
            if (!inst || inst.options.liveSearch) {
                return;
            }
            try {
                $(this).selectpicker('destroy');
            } catch (e) {
                return;
            }
            $.removeData(this, FLAG);
            convert(this);
        });
    }

    function convertAll(root) {
        var scope = (root && root.nodeType === 1) ? root : document;
        if (scope.nodeName === 'SELECT') {
            convert(scope);
            return;
        }
        var list = scope.querySelectorAll('select');
        for (var i = 0; i < list.length; i++) {
            convert(list[i]);
        }
    }

    // 选项变化后刷新 UI。延后到当前同步逻辑结束再执行，
    // 这样 .html(新选项) 紧跟 .val(x) 的写法能一次刷新到位。
    function scheduleRefresh(el) {
        if (refreshQueue.indexOf(el) === -1) {
            refreshQueue.push(el);
        }
        if (refreshTimer) {
            return;
        }
        refreshTimer = setTimeout(flushRefresh, 0);
    }

    function flushRefresh() {
        refreshTimer = null;
        var queue = refreshQueue;
        refreshQueue = [];
        for (var i = 0; i < queue.length; i++) {
            var el = queue[i];
            if (!el.parentNode) {
                continue;
            }
            if (!isManaged(el)) {
                $.removeData(el, FLAG);     // 包裹层已被移除，重新初始化
                convert(el);
                continue;
            }
            try {
                $(el).selectpicker('refresh');
            } catch (e) {
                /* 元素即将被移除等情况，忽略 */
            }
        }
    }

    // selectpicker / 渲染产物：包裹层、占位 option
    function isArtifact(node) {
        if (!node || node.nodeType !== 1) {
            return false;
        }
        return node.classList.contains(TITLE_CLASS) || node.classList.contains(WRAPPER_CLASS);
    }

    // 排除只有 titleOption 变动的记录，避免与 selectpicker 自身渲染互相触发
    function hasRealOptionChange(record) {
        var i, n;
        for (i = 0; i < record.addedNodes.length; i++) {
            n = record.addedNodes[i];
            if (n.nodeType === 1 && !n.classList.contains(TITLE_CLASS)) {
                return true;
            }
        }
        for (i = 0; i < record.removedNodes.length; i++) {
            n = record.removedNodes[i];
            if (n.nodeType === 1 && !n.classList.contains(TITLE_CLASS)) {
                return true;
            }
        }
        return false;
    }

    function handle(mutations) {
        for (var i = 0; i < mutations.length; i++) {
            var record = mutations[i];
            var target = record.target;

            // 只读模式下 JS 会 $('select').attr('disabled', true)，同步控件外观
            if (record.type === 'attributes') {
                if (target && target.nodeName === 'SELECT' && isManaged(target)) {
                    scheduleRefresh(target);
                }
                continue;
            }
            if (record.type !== 'childList') {
                continue;
            }

            // 包裹层内部的菜单渲染（li 等）无需处理
            if (target && target.nodeType === 1 && target.nodeName !== 'SELECT' &&
                target.closest && target.closest('.' + WRAPPER_CLASS)) {
                continue;
            }

            // 1) 新增的 select：动态行、弹窗、模板行克隆/复制
            for (var j = 0; j < record.addedNodes.length; j++) {
                var node = record.addedNodes[j];
                if (node.nodeType !== 1 || isArtifact(node)) {
                    continue;
                }
                if (node.nodeName === 'SELECT') {
                    convert(node);
                } else if (node.querySelector && node.querySelector('select')) {
                    convertAll(node);
                }
            }

            // 2) 已转换 select 的选项被子树替换 → 刷新
            if (target && target.nodeName === 'SELECT' && isManaged(target) && hasRealOptionChange(record)) {
                scheduleRefresh(target);
            }
        }
    }

    // selectpicker 的 setStyle() 会把 select 自身的 class 原样复制到包裹层，
    // 而进销存单据代码大量用 class 选择器读写行内下拉框，例如：
    //     obj.find('.goods_attr').val()   // 80 处
    //     obj.find('.stores_id').val()    // 77 处
    //     obj.find('.goods_unit').val()   // 44 处
    // 复制后包裹 div 也带同样的 class，.val() 取到的会是 div 的空值，逻辑即失效。
    // 因此在每次 setStyle（含 refresh）后，把包裹层上的业务 class 去掉，
    // 只保留布局相关的 class（进销存代码未把 form-control / col-* 当选择器用过）。
    var KEEP_ON_WRAPPER = /^(form-control(-(lg|sm))?|col-(xs|sm|md|lg)(-\d+)?|input-(lg|sm))$/i;

    function preventClassCopy() {
        var proto = $.fn.selectpicker.Constructor.prototype;
        var originalSetStyle = proto.setStyle;
        proto.setStyle = function () {
            var result = originalSetStyle.apply(this, arguments);
            var cls = this.$element.attr('class');
            if (cls) {
                var drop = [];
                var parts = cls.split(/\s+/);
                for (var i = 0; i < parts.length; i++) {
                    if (parts[i] && parts[i] !== 'selectpicker' && !KEEP_ON_WRAPPER.test(parts[i])) {
                        drop.push(parts[i]);
                    }
                }
                if (drop.length) {
                    this.$newElement.removeClass(drop.join(' '));
                }
            }
            return result;
        };
    }

    // 业务代码常用 $(sel).val(x) 直接赋值（不改变 option，MutationObserver 看不到），
    // 这里劫持 jQuery.val 的写入路径，同步刷新控件显示。refresh 内部不会再调用 .val(值)，无循环风险。
    function hookVal() {
        var originalVal = $.fn.val;
        $.fn.val = function () {
            var result = originalVal.apply(this, arguments);
            if (arguments.length > 0) {
                for (var i = 0; i < this.length; i++) {
                    if (this[i].nodeName === 'SELECT' && isManaged(this[i])) {
                        scheduleRefresh(this[i]);
                    }
                }
            }
            return result;
        };
    }

    function start() {
        $.fn.selectpicker.Constructor.BootstrapVersion = '3';
        preventClassCopy();

        // 全局默认开启搜索；单元素可用 data-live-search="false" 覆盖（data 属性优先级更高）
        $.fn.selectpicker.defaults = $.extend({}, $.fn.selectpicker.defaults, {
            liveSearch: true,
            liveSearchStyle: 'contains',
            liveSearchPlaceholder: '输入关键字搜索',
            size: MENU_SIZE,
            // 空值选项（<option value=""></option>，进销存里到处都是）在原生下拉里是空白的，
            // bootstrap-select 默认会填上"没有选中任何项"，这里保持与原生一致的空白
            noneSelectedText: ''
        });

        // 先转换已有元素，再开始监听，避免处理初始化过程自身产生的变动
        markLayuiSearchAll(document);
        convertAll(document);
        upgradeExistingPickers();
        if (layuiRenderedWithoutSearch()) {
            rerenderLayuiSelects();
        }
        hookVal();

        if (window.MutationObserver) {
            new MutationObserver(handle).observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['disabled']
            });
        }
    }

    // 模块一加载就标记 .layui-form 内的 select：layui 的 form 模块（业务 JS 的 layui.use 触发）
// 会自动渲染这些 select，标记在前它首次渲染就带搜索框。layui 的自动渲染挂在 jQuery ready 上，
// 早于下面的 DOMContentLoaded 回调，所以不能只在 start() 里标记。
    // 反过来（本模块比 layui 渲染还晚），start() 里靠 layuiRenderedWithoutSearch() 重新渲染兜底。
    markLayuiSearchAll(document);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }

    return {
        start: start,
        convert: convert,
        convertAll: convertAll,
        refresh: function (el) {
            scheduleRefresh(el);
        }
    };
});