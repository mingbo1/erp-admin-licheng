(global["webpackJsonp"] = global["webpackJsonp"] || []).push([["node-modules/uview-ui/components/u-sticky/u-sticky"],{

/***/ 372:
/*!****************************************************************!*\
  !*** ./node_modules/uview-ui/components/u-sticky/u-sticky.vue ***!
  \****************************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _u_sticky_vue_vue_type_template_id_c187ecf2_scoped_true___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./u-sticky.vue?vue&type=template&id=c187ecf2&scoped=true& */ 373);
/* harmony import */ var _u_sticky_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./u-sticky.vue?vue&type=script&lang=js& */ 375);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _u_sticky_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _u_sticky_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__[key]; }) }(__WEBPACK_IMPORT_KEY__));
/* harmony import */ var _u_sticky_vue_vue_type_style_index_0_id_c187ecf2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./u-sticky.vue?vue&type=style&index=0&id=c187ecf2&lang=scss&scoped=true& */ 378);
/* harmony import */ var _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/runtime/componentNormalizer.js */ 19);

var renderjs





/* normalize component */

var component = Object(_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__["default"])(
  _u_sticky_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__["default"],
  _u_sticky_vue_vue_type_template_id_c187ecf2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["render"],
  _u_sticky_vue_vue_type_template_id_c187ecf2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["staticRenderFns"],
  false,
  null,
  "c187ecf2",
  null,
  false,
  _u_sticky_vue_vue_type_template_id_c187ecf2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["components"],
  renderjs
)

component.options.__file = "node_modules/uview-ui/components/u-sticky/u-sticky.vue"
/* harmony default export */ __webpack_exports__["default"] = (component.exports);

/***/ }),

/***/ 373:
/*!***********************************************************************************************************!*\
  !*** ./node_modules/uview-ui/components/u-sticky/u-sticky.vue?vue&type=template&id=c187ecf2&scoped=true& ***!
  \***********************************************************************************************************/
/*! exports provided: render, staticRenderFns, recyclableRender, components */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_webpack_uni_mp_loader_lib_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_template_id_c187ecf2_scoped_true___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--15-0!../../../@dcloudio/webpack-uni-mp-loader/lib/template.js!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/page-meta.js!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../@dcloudio/webpack-uni-mp-loader/lib/style.js!./u-sticky.vue?vue&type=template&id=c187ecf2&scoped=true& */ 374);
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "render", function() { return _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_webpack_uni_mp_loader_lib_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_template_id_c187ecf2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["render"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "staticRenderFns", function() { return _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_webpack_uni_mp_loader_lib_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_template_id_c187ecf2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["staticRenderFns"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "recyclableRender", function() { return _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_webpack_uni_mp_loader_lib_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_template_id_c187ecf2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["recyclableRender"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "components", function() { return _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_webpack_uni_mp_loader_lib_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_template_id_c187ecf2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["components"]; });



/***/ }),

/***/ 374:
/*!***********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--15-0!./node_modules/@dcloudio/webpack-uni-mp-loader/lib/template.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/page-meta.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/webpack-uni-mp-loader/lib/style.js!./node_modules/uview-ui/components/u-sticky/u-sticky.vue?vue&type=template&id=c187ecf2&scoped=true& ***!
  \***********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! exports provided: render, staticRenderFns, recyclableRender, components */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "render", function() { return render; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "staticRenderFns", function() { return staticRenderFns; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "recyclableRender", function() { return recyclableRender; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "components", function() { return components; });
var components
var render = function () {
  var _vm = this
  var _h = _vm.$createElement
  var _c = _vm._self._c || _h
  var s0 = _vm.__get_style([_vm.style])
  var s1 = _vm.__get_style([_vm.stickyContent])
  _vm.$mp.data = Object.assign(
    {},
    {
      $root: {
        s0: s0,
        s1: s1,
      },
    }
  )
}
var recyclableRender = false
var staticRenderFns = []
render._withStripped = true



/***/ }),

/***/ 375:
/*!*****************************************************************************************!*\
  !*** ./node_modules/uview-ui/components/u-sticky/u-sticky.vue?vue&type=script&lang=js& ***!
  \*****************************************************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_webpack_uni_mp_loader_lib_script_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../babel-loader/lib!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--13-1!../../../@dcloudio/webpack-uni-mp-loader/lib/script.js!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../@dcloudio/webpack-uni-mp-loader/lib/style.js!./u-sticky.vue?vue&type=script&lang=js& */ 376);
/* harmony import */ var _babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_webpack_uni_mp_loader_lib_script_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_webpack_uni_mp_loader_lib_script_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_webpack_uni_mp_loader_lib_script_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_webpack_uni_mp_loader_lib_script_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__[key]; }) }(__WEBPACK_IMPORT_KEY__));
 /* harmony default export */ __webpack_exports__["default"] = (_babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_webpack_uni_mp_loader_lib_script_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ }),

/***/ 376:
/*!************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--13-1!./node_modules/@dcloudio/webpack-uni-mp-loader/lib/script.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/webpack-uni-mp-loader/lib/style.js!./node_modules/uview-ui/components/u-sticky/u-sticky.vue?vue&type=script&lang=js& ***!
  \************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/* WEBPACK VAR INJECTION */(function(uni) {

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _props = _interopRequireDefault(__webpack_require__(/*! ./props.js */ 377));
function _interopRequireDefault(e) { return e && e.__esModule ? e : { default: e }; }
function _regenerator() { /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/babel/babel/blob/main/packages/babel-helpers/LICENSE */ var e, t, r = "function" == typeof Symbol ? Symbol : {}, n = r.iterator || "@@iterator", o = r.toStringTag || "@@toStringTag"; function i(r, n, o, i) { var c = n && n.prototype instanceof Generator ? n : Generator, u = Object.create(c.prototype); return _regeneratorDefine2(u, "_invoke", function (r, n, o) { var i, c, u, f = 0, p = o || [], y = !1, G = { p: 0, n: 0, v: e, a: d, f: d.bind(e, 4), d: function d(t, r) { return i = t, c = 0, u = e, G.n = r, a; } }; function d(r, n) { for (c = r, u = n, t = 0; !y && f && !o && t < p.length; t++) { var o, i = p[t], d = G.p, l = i[2]; r > 3 ? (o = l === n) && (u = i[(c = i[4]) ? 5 : (c = 3, 3)], i[4] = i[5] = e) : i[0] <= d && ((o = r < 2 && d < i[1]) ? (c = 0, G.v = n, G.n = i[1]) : d < l && (o = r < 3 || i[0] > n || n > l) && (i[4] = r, i[5] = n, G.n = l, c = 0)); } if (o || r > 1) return a; throw y = !0, n; } return function (o, p, l) { if (f > 1) throw TypeError("Generator is already running"); for (y && 1 === p && d(p, l), c = p, u = l; (t = c < 2 ? e : u) || !y;) { i || (c ? c < 3 ? (c > 1 && (G.n = -1), d(c, u)) : G.n = u : G.v = u); try { if (f = 2, i) { if (c || (o = "next"), t = i[o]) { if (!(t = t.call(i, u))) throw TypeError("iterator result is not an object"); if (!t.done) return t; u = t.value, c < 2 && (c = 0); } else 1 === c && (t = i.return) && t.call(i), c < 2 && (u = TypeError("The iterator does not provide a '" + o + "' method"), c = 1); i = e; } else if ((t = (y = G.n < 0) ? u : r.call(n, G)) !== a) break; } catch (t) { i = e, c = 1, u = t; } finally { f = 1; } } return { value: t, done: y }; }; }(r, o, i), !0), u; } var a = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} t = Object.getPrototypeOf; var c = [][n] ? t(t([][n]())) : (_regeneratorDefine2(t = {}, n, function () { return this; }), t), u = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(c); function f(e) { return Object.setPrototypeOf ? Object.setPrototypeOf(e, GeneratorFunctionPrototype) : (e.__proto__ = GeneratorFunctionPrototype, _regeneratorDefine2(e, o, "GeneratorFunction")), e.prototype = Object.create(u), e; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, _regeneratorDefine2(u, "constructor", GeneratorFunctionPrototype), _regeneratorDefine2(GeneratorFunctionPrototype, "constructor", GeneratorFunction), GeneratorFunction.displayName = "GeneratorFunction", _regeneratorDefine2(GeneratorFunctionPrototype, o, "GeneratorFunction"), _regeneratorDefine2(u), _regeneratorDefine2(u, o, "Generator"), _regeneratorDefine2(u, n, function () { return this; }), _regeneratorDefine2(u, "toString", function () { return "[object Generator]"; }), (_regenerator = function _regenerator() { return { w: i, m: f }; })(); }
function _regeneratorDefine2(e, r, n, t) { var i = Object.defineProperty; try { i({}, "", {}); } catch (e) { i = 0; } _regeneratorDefine2 = function _regeneratorDefine(e, r, n, t) { function o(r, n) { _regeneratorDefine2(e, r, function (e) { return this._invoke(r, n, e); }); } r ? i ? i(e, r, { value: n, enumerable: !t, configurable: !t, writable: !t }) : e[r] = n : (o("next", 0), o("throw", 1), o("return", 2)); }, _regeneratorDefine2(e, r, n, t); }
function asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }
function _asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { asyncGeneratorStep(a, r, o, _next, _throw, "next", n); } function _throw(n) { asyncGeneratorStep(a, r, o, _next, _throw, "throw", n); } _next(void 0); }); }; } //
//
//
//
//
//
//
//
//
//
//
//
//
//
//
;
/**
 * sticky 吸顶
 * @description 该组件与CSS中position: sticky属性实现的效果一致，当组件达到预设的到顶部距离时， 就会固定在指定位置，组件位置大于预设的顶部距离时，会重新按照正常的布局排列。
 * @tutorial https://www.uviewui.com/components/sticky.html
 * @property {String ｜ Number}	offsetTop		吸顶时与顶部的距离，单位px（默认 0 ）
 * @property {String ｜ Number}	customNavHeight	自定义导航栏的高度 （h5 默认44  其他默认 0 ）
 * @property {Boolean}			disabled		是否开启吸顶功能 （默认 false ）
 * @property {String}			bgColor			组件背景颜色（默认 '#ffffff' ）
 * @property {String ｜ Number}	zIndex			吸顶时的z-index值
 * @property {String ｜ Number}	index			自定义标识，用于区分是哪一个组件
 * @property {Object}			customStyle		组件的样式，对象形式
 * @event {Function} fixed		组件吸顶时触发
 * @event {Function} unfixed	组件取消吸顶时触发
 * @example <u-sticky offsetTop="200"><view>塞下秋来风景异，衡阳雁去无留意</view></u-sticky>
 */
var _default = exports.default = {
  name: 'u-sticky',
  mixins: [uni.$u.mpMixin, uni.$u.mixin, _props.default],
  data: function data() {
    return {
      cssSticky: false,
      // 是否使用css的sticky实现
      stickyTop: 0,
      // 吸顶的top值，因为可能受自定义导航栏影响，最终的吸顶值非offsetTop值
      elId: uni.$u.guid(),
      left: 0,
      // js模式时，吸顶的内容因为处于postition: fixed模式，为了和原来保持一致的样式，需要记录并重新设置它的left，height，width属性
      width: 'auto',
      height: 'auto',
      fixed: false // js模式时，是否处于吸顶模式
    };
  },
  computed: {
    style: function style() {
      var style = {};
      if (!this.disabled) {
        if (this.cssSticky) {
          style.position = 'sticky';
          style.zIndex = this.uZindex;
          style.top = uni.$u.addUnit(this.stickyTop);
        } else {
          style.height = this.fixed ? this.height + 'px' : 'auto';
        }
      } else {
        // 无需吸顶时，设置会默认的relative(nvue)和非nvue的static静态模式即可

        style.position = 'static';
      }
      style.backgroundColor = this.bgColor;
      return uni.$u.deepMerge(uni.$u.addStyle(this.customStyle), style);
    },
    // 吸顶内容的样式
    stickyContent: function stickyContent() {
      var style = {};
      if (!this.cssSticky) {
        style.position = this.fixed ? 'fixed' : 'static';
        style.top = this.stickyTop + 'px';
        style.left = this.left + 'px';
        style.width = this.width == 'auto' ? 'auto' : this.width + 'px';
        style.zIndex = this.uZindex;
      }
      return style;
    },
    uZindex: function uZindex() {
      return this.zIndex ? this.zIndex : uni.$u.zIndex.sticky;
    }
  },
  mounted: function mounted() {
    this.init();
  },
  methods: {
    init: function init() {
      this.getStickyTop();
      // 判断使用的模式
      this.checkSupportCssSticky();
      // 如果不支持css sticky，则使用js方案，此方案性能比不上css方案
      if (!this.cssSticky) {
        !this.disabled && this.initObserveContent();
      }
    },
    initObserveContent: function initObserveContent() {
      var _this = this;
      // 获取吸顶内容的高度，用于在js吸顶模式时，给父元素一个填充高度，防止"塌陷"
      this.$uGetRect('#' + this.elId).then(function (res) {
        _this.height = res.height;
        _this.left = res.left;
        _this.width = res.width;
        _this.$nextTick(function () {
          _this.observeContent();
        });
      });
    },
    observeContent: function observeContent() {
      var _this2 = this;
      // 先断掉之前的观察
      this.disconnectObserver('contentObserver');
      var contentObserver = uni.createIntersectionObserver({
        // 检测的区间范围
        thresholds: [0.95, 0.98, 1]
      });
      // 到屏幕顶部的高度时触发
      contentObserver.relativeToViewport({
        top: -this.stickyTop
      });
      // 绑定观察的元素
      contentObserver.observe("#".concat(this.elId), function (res) {
        _this2.setFixed(res.boundingClientRect.top);
      });
      this.contentObserver = contentObserver;
    },
    setFixed: function setFixed(top) {
      // 判断是否出于吸顶条件范围
      var fixed = top <= this.stickyTop;
      this.fixed = fixed;
    },
    disconnectObserver: function disconnectObserver(observerName) {
      // 断掉观察，释放资源
      var observer = this[observerName];
      observer && observer.disconnect();
    },
    getStickyTop: function getStickyTop() {
      this.stickyTop = uni.$u.getPx(this.offsetTop) + uni.$u.getPx(this.customNavHeight);
    },
    checkSupportCssSticky: function checkSupportCssSticky() {
      var _this3 = this;
      return _asyncToGenerator(/*#__PURE__*/_regenerator().m(function _callee() {
        return _regenerator().w(function (_context) {
          while (1) switch (_context.n) {
            case 0:
              // 如果安卓版本高于8.0，依然认为是支持css sticky的(因为安卓7在某些机型，可能不支持sticky)
              if (uni.$u.os() === 'android' && Number(uni.$u.sys().system) > 8) {
                _this3.cssSticky = true;
              }

              // APP-Vue和微信平台，通过computedStyle判断是否支持css sticky
              _context.n = 1;
              return _this3.checkComputedStyle();
            case 1:
              _this3.cssSticky = _context.v;
              // ios上，从ios6开始，都是支持css sticky的
              if (uni.$u.os() === 'ios') {
                _this3.cssSticky = true;
              }

              // nvue，是支持css sticky的
            case 2:
              return _context.a(2);
          }
        }, _callee);
      }))();
    },
    // 在APP和微信小程序上，通过uni.createSelectorQuery可以判断是否支持css sticky
    checkComputedStyle: function checkComputedStyle() {
      var _this4 = this;
      // 方法内进行判断，避免在其他平台生成无用代码

      return new Promise(function (resolve) {
        uni.createSelectorQuery().in(_this4).select('.u-sticky').fields({
          computedStyle: ["position"]
        }).exec(function (e) {
          resolve('sticky' === e[0].position);
        });
      });
    },
    // H5通过创建元素的形式嗅探是否支持css sticky
    // 判断浏览器是否支持sticky属性
    checkCssStickyForH5: function checkCssStickyForH5() {
      // 方法内进行判断，避免在其他平台生成无用代码
    }
  },
  beforeDestroy: function beforeDestroy() {
    this.disconnectObserver('contentObserver');
  }
};
/* WEBPACK VAR INJECTION */}.call(this, __webpack_require__(/*! ./node_modules/@dcloudio/uni-mp-weixin/dist/index.js */ 2)["default"]))

/***/ }),

/***/ 378:
/*!**************************************************************************************************************************!*\
  !*** ./node_modules/uview-ui/components/u-sticky/u-sticky.vue?vue&type=style&index=0&id=c187ecf2&lang=scss&scoped=true& ***!
  \**************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _mini_css_extract_plugin_dist_loader_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_style_index_0_id_c187ecf2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../mini-css-extract-plugin/dist/loader.js??ref--9-oneOf-1-0!../../../css-loader/dist/cjs.js??ref--9-oneOf-1-1!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!../../../postcss-loader/src??ref--9-oneOf-1-3!../../../@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../@dcloudio/webpack-uni-mp-loader/lib/style.js!./u-sticky.vue?vue&type=style&index=0&id=c187ecf2&lang=scss&scoped=true& */ 379);
/* harmony import */ var _mini_css_extract_plugin_dist_loader_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_style_index_0_id_c187ecf2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_mini_css_extract_plugin_dist_loader_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_style_index_0_id_c187ecf2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0__);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _mini_css_extract_plugin_dist_loader_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_style_index_0_id_c187ecf2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _mini_css_extract_plugin_dist_loader_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_style_index_0_id_c187ecf2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0__[key]; }) }(__WEBPACK_IMPORT_KEY__));
 /* harmony default export */ __webpack_exports__["default"] = (_mini_css_extract_plugin_dist_loader_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_webpack_uni_mp_loader_lib_style_js_u_sticky_vue_vue_type_style_index_0_id_c187ecf2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ }),

/***/ 379:
/*!******************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/mini-css-extract-plugin/dist/loader.js??ref--9-oneOf-1-0!./node_modules/css-loader/dist/cjs.js??ref--9-oneOf-1-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!./node_modules/postcss-loader/src??ref--9-oneOf-1-3!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/webpack-uni-mp-loader/lib/style.js!./node_modules/uview-ui/components/u-sticky/u-sticky.vue?vue&type=style&index=0&id=c187ecf2&lang=scss&scoped=true& ***!
  \******************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin
    if(false) { var cssReload; }
  

/***/ })

}]);
//# sourceMappingURL=../../../../../.sourcemap/mp-weixin/node-modules/uview-ui/components/u-sticky/u-sticky.js.map
;(global["webpackJsonp"] = global["webpackJsonp"] || []).push([
    'node-modules/uview-ui/components/u-sticky/u-sticky-create-component',
    {
        'node-modules/uview-ui/components/u-sticky/u-sticky-create-component':(function(module, exports, __webpack_require__){
            __webpack_require__('2')['createComponent'](__webpack_require__(372))
        })
    },
    [['node-modules/uview-ui/components/u-sticky/u-sticky-create-component']]
]);
