(window["webpackJsonp"] = window["webpackJsonp"] || []).push([["pages-address-list~pages-goods-detail~pages-order-detail~pages-order-logistics"],{

/***/ "/RvS":
/*!**********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--15-0!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/filter-modules-template.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/page-meta.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./node_modules/uview-ui/components/u-skeleton/u-skeleton.vue?vue&type=template&id=d0b470f2&scoped=true& ***!
  \**********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
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
  return _c(
    "v-uni-view",
    { staticClass: "u-skeleton" },
    [
      _vm.loading
        ? _c(
            "v-uni-view",
            {
              ref: "u-skeleton__wrapper",
              staticClass: "u-skeleton__wrapper",
              staticStyle: { display: "flex", "flex-direction": "row" },
            },
            [
              _vm.avatar
                ? _c("v-uni-view", {
                    staticClass: "u-skeleton__wrapper__avatar",
                    class: [
                      "u-skeleton__wrapper__avatar--" + _vm.avatarShape,
                      _vm.animate && "animate",
                    ],
                    style: {
                      height: _vm.$u.addUnit(_vm.avatarSize),
                      width: _vm.$u.addUnit(_vm.avatarSize),
                    },
                  })
                : _vm._e(),
              _c(
                "v-uni-view",
                {
                  ref: "u-skeleton__wrapper__content",
                  staticClass: "u-skeleton__wrapper__content",
                  staticStyle: { flex: "1" },
                },
                [
                  _vm.title
                    ? _c("v-uni-view", {
                        staticClass: "u-skeleton__wrapper__content__title",
                        class: [_vm.animate && "animate"],
                        style: {
                          width: _vm.uTitleWidth,
                          height: _vm.$u.addUnit(_vm.titleHeight),
                        },
                      })
                    : _vm._e(),
                  _vm._l(_vm.rowsArray, function (item, index) {
                    return _c("v-uni-view", {
                      key: index,
                      staticClass: "u-skeleton__wrapper__content__rows",
                      class: [_vm.animate && "animate"],
                      style: {
                        width: item.width,
                        height: item.height,
                        marginTop: item.marginTop,
                      },
                    })
                  }),
                ],
                2
              ),
            ],
            1
          )
        : _vm._t("default"),
    ],
    2
  )
}
var recyclableRender = false
var staticRenderFns = []
render._withStripped = true



/***/ }),

/***/ "4+op":
/*!************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--13-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--16-0!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/using-components.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./node_modules/uview-ui/components/u-skeleton/u-skeleton.vue?vue&type=script&lang=js& ***!
  \************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


__webpack_require__(/*! core-js/modules/es.object.define-property.js */ "eoL8");
var _interopRequireDefault = __webpack_require__(/*! ./node_modules/@babel/runtime/helpers/interopRequireDefault.js */ "TqRt").default;
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _regenerator2 = _interopRequireDefault(__webpack_require__(/*! ./node_modules/@babel/runtime/helpers/esm/regenerator.js */ "wU9g"));
var _asyncToGenerator2 = _interopRequireDefault(__webpack_require__(/*! ./node_modules/@babel/runtime/helpers/esm/asyncToGenerator.js */ "HaE+"));
__webpack_require__(/*! core-js/modules/es.array.push.js */ "FNk8");
__webpack_require__(/*! core-js/modules/es.parse-int.js */ "4l63");
__webpack_require__(/*! core-js/modules/es.regexp.exec.js */ "rB9j");
__webpack_require__(/*! core-js/modules/es.regexp.test.js */ "ALS0");
var _props = _interopRequireDefault(__webpack_require__(/*! ./props.js */ "jZsf"));
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
//
//
//
//
//
//
//
//
/**
 * Skeleton 骨架屏
 * @description 骨架屏一般用于页面在请求远程数据尚未完成时，页面用灰色块预显示本来的页面结构，给用户更好的体验。
 * @tutorial https://www.uviewui.com/components/skeleton.html
 * @property {Boolean}					loading		是否显示骨架占位图，设置为false将会展示子组件内容 (默认 true )
 * @property {Boolean}					animate		是否开启动画效果 (默认 true )
 * @property {String | Number}			rows		段落占位图行数 (默认 0 )
 * @property {String | Number | Array}	rowsWidth	段落占位图的宽度，可以为百分比，数值，带单位字符串等，可通过数组传入指定每个段落行的宽度 (默认 '100%' )
 * @property {String | Number | Array}	rowsHeight	段落的高度 (默认 18 )
 * @property {Boolean}					title		是否展示标题占位图 (默认 true )
 * @property {String | Number}			titleWidth	标题的宽度 (默认 '50%' )
 * @property {String | Number}			titleHeight	标题的高度 (默认 18 )
 * @property {Boolean}					avatar		是否展示头像占位图 (默认 false )
 * @property {String | Number}			avatarSize	头像占位图大小 (默认 32 )
 * @property {String}					avatarShape	头像占位图的形状，circle-圆形，square-方形 (默认 'circle' )
 * @example <u-search placeholder="日照香炉生紫烟" v-model="keyword"></u-search>
 */
var _default = exports.default = {
  name: 'u-skeleton',
  mixins: [uni.$u.mpMixin, uni.$u.mixin, _props.default],
  data: function data() {
    return {
      width: 0
    };
  },
  watch: {
    loading: function loading() {
      this.getComponentWidth();
    }
  },
  computed: {
    rowsArray: function rowsArray() {
      if (/%$/.test(this.rowsHeight)) {
        uni.$u.error('rowsHeight参数不支持百分比单位');
      }
      var rows = [];
      for (var i = 0; i < this.rows; i++) {
        var item = {},
          // 需要预防超出数组边界的情况
          rowWidth = uni.$u.test.array(this.rowsWidth) ? this.rowsWidth[i] || (i === this.row - 1 ? '70%' : '100%') : i === this.rows - 1 ? '70%' : this.rowsWidth,
          rowHeight = uni.$u.test.array(this.rowsHeight) ? this.rowsHeight[i] || '18px' : this.rowsHeight;
        // 如果有title占位图，第一个段落占位图的外边距需要大一些，如果没有title占位图，第一个段落占位图则无需外边距
        // 之所以需要这么做，是因为weex的无能，以提升性能为借口不支持css的一些伪类
        item.marginTop = !this.title && i === 0 ? 0 : this.title && i === 0 ? '20px' : '12px';
        // 如果设置的为百分比的宽度，转换为px值，因为nvue不支持百分比单位
        if (/%$/.test(rowWidth)) {
          // 通过parseInt提取出百分比单位中的数值部分，除以100得到百分比的小数值
          item.width = uni.$u.addUnit(this.width * parseInt(rowWidth) / 100);
        } else {
          item.width = uni.$u.addUnit(rowWidth);
        }
        item.height = uni.$u.addUnit(rowHeight);
        rows.push(item);
      }
      // console.log(rows);
      return rows;
    },
    uTitleWidth: function uTitleWidth() {
      var tWidth = 0;
      if (/%$/.test(this.titleWidth)) {
        // 通过parseInt提取出百分比单位中的数值部分，除以100得到百分比的小数值
        tWidth = uni.$u.addUnit(this.width * parseInt(this.titleWidth) / 100);
      } else {
        tWidth = uni.$u.addUnit(this.titleWidth);
      }
      return uni.$u.addUnit(tWidth);
    }
  },
  mounted: function mounted() {
    this.init();
  },
  methods: {
    init: function init() {
      this.getComponentWidth();
    },
    setNvueAnimation: function setNvueAnimation() {
      return (0, _asyncToGenerator2.default)(/*#__PURE__*/(0, _regenerator2.default)().m(function _callee() {
        return (0, _regenerator2.default)().w(function (_context) {
          while (1) switch (_context.n) {
            case 0:
              return _context.a(2);
          }
        }, _callee);
      }))();
    },
    // 获取组件的宽度
    getComponentWidth: function getComponentWidth() {
      var _this = this;
      return (0, _asyncToGenerator2.default)(/*#__PURE__*/(0, _regenerator2.default)().m(function _callee2() {
        return (0, _regenerator2.default)().w(function (_context2) {
          while (1) switch (_context2.n) {
            case 0:
              _context2.n = 1;
              return uni.$u.sleep(20);
            case 1:
              _this.$uGetRect('.u-skeleton__wrapper__content').then(function (size) {
                _this.width = size.width;
              });
            case 2:
              return _context2.a(2);
          }
        }, _callee2);
      }))();
    }
  }
};

/***/ }),

/***/ "MGu5":
/*!*************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/css-loader/dist/cjs.js??ref--9-oneOf-1-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!./node_modules/postcss-loader/src??ref--9-oneOf-1-3!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./node_modules/uview-ui/components/u-skeleton/u-skeleton.vue?vue&type=style&index=0&id=d0b470f2&lang=scss&scoped=true& ***!
  \*************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// Imports
var ___CSS_LOADER_API_IMPORT___ = __webpack_require__(/*! ../../../css-loader/dist/runtime/api.js */ "JPst");
exports = ___CSS_LOADER_API_IMPORT___(false);
// Module
exports.push([module.i, "uni-view[data-v-d0b470f2], uni-scroll-view[data-v-d0b470f2], uni-swiper-item[data-v-d0b470f2] {\n  display: flex;\n  flex-direction: column;\n  flex-shrink: 0;\n  flex-grow: 0;\n  flex-basis: auto;\n  align-items: stretch;\n  align-content: flex-start;\n}\n.u-skeleton[data-v-d0b470f2] {\n  flex: 1;\n}\n.u-skeleton__wrapper[data-v-d0b470f2] {\n\n  display: flex;\n\n  flex-direction: row;\n}\n.u-skeleton__wrapper__avatar[data-v-d0b470f2] {\n  background: linear-gradient(90deg, #F1F2F4 25%, #e6e6e6 37%, #F1F2F4 50%);\n  background-size: 400% 100%;\n  margin-right: 15px;\n}\n.u-skeleton__wrapper__avatar--circle[data-v-d0b470f2] {\n  border-radius: 100px;\n}\n.u-skeleton__wrapper__avatar--square[data-v-d0b470f2] {\n  border-radius: 4px;\n}\n.u-skeleton__wrapper__content[data-v-d0b470f2] {\n  flex: 1;\n}\n.u-skeleton__wrapper__content__rows[data-v-d0b470f2], .u-skeleton__wrapper__content__title[data-v-d0b470f2] {\n  background: linear-gradient(90deg, #F1F2F4 25%, #e6e6e6 37%, #F1F2F4 50%);\n  background-size: 400% 100%;\n  border-radius: 3px;\n}\n.animate[data-v-d0b470f2] {\n  -webkit-animation: skeleton-data-v-d0b470f2 1.8s ease infinite;\n          animation: skeleton-data-v-d0b470f2 1.8s ease infinite;\n}\n@-webkit-keyframes skeleton-data-v-d0b470f2 {\n0% {\n    background-position: 100% 50%;\n}\n100% {\n    background-position: 0 50%;\n}\n}\n@keyframes skeleton-data-v-d0b470f2 {\n0% {\n    background-position: 100% 50%;\n}\n100% {\n    background-position: 0 50%;\n}\n}", ""]);
// Exports
module.exports = exports;


/***/ }),

/***/ "df3G":
/*!********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/@dcloudio/vue-cli-plugin-uni/packages/h5-vue-style-loader??ref--9-oneOf-1-0!./node_modules/css-loader/dist/cjs.js??ref--9-oneOf-1-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!./node_modules/postcss-loader/src??ref--9-oneOf-1-3!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./node_modules/uview-ui/components/u-skeleton/u-skeleton.vue?vue&type=style&index=0&id=d0b470f2&lang=scss&scoped=true& ***!
  \********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// style-loader: Adds some css to the DOM by adding a <style> tag

// load the styles
var content = __webpack_require__(/*! !../../../css-loader/dist/cjs.js??ref--9-oneOf-1-1!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!../../../postcss-loader/src??ref--9-oneOf-1-3!../../../@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./u-skeleton.vue?vue&type=style&index=0&id=d0b470f2&lang=scss&scoped=true& */ "MGu5");
if(content.__esModule) content = content.default;
if(typeof content === 'string') content = [[module.i, content, '']];
if(content.locals) module.exports = content.locals;
// add the styles to the DOM
var add = __webpack_require__(/*! ../../../@dcloudio/vue-cli-plugin-uni/packages/h5-vue-style-loader/lib/addStylesClient.js */ "TwZa").default
var update = add("9d2b2e60", content, false, {"sourceMap":false,"shadowMode":false});
// Hot Module Replacement
if(false) {}

/***/ }),

/***/ "h0KJ":
/*!***************************************************************************************************************!*\
  !*** ./node_modules/uview-ui/components/u-skeleton/u-skeleton.vue?vue&type=template&id=d0b470f2&scoped=true& ***!
  \***************************************************************************************************************/
/*! exports provided: render, staticRenderFns, recyclableRender, components */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_template_id_d0b470f2_scoped_true___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--15-0!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/filter-modules-template.js!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/page-meta.js!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./u-skeleton.vue?vue&type=template&id=d0b470f2&scoped=true& */ "/RvS");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "render", function() { return _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_template_id_d0b470f2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["render"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "staticRenderFns", function() { return _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_template_id_d0b470f2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["staticRenderFns"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "recyclableRender", function() { return _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_template_id_d0b470f2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["recyclableRender"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "components", function() { return _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_template_id_d0b470f2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["components"]; });



/***/ }),

/***/ "jZsf":
/*!**************************************************************!*\
  !*** ./node_modules/uview-ui/components/u-skeleton/props.js ***!
  \**************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


__webpack_require__(/*! core-js/modules/es.object.define-property.js */ "eoL8");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
__webpack_require__(/*! core-js/modules/es.number.constructor.js */ "qePV");
var _default = exports.default = {
  props: {
    // 是否展示骨架组件
    loading: {
      type: Boolean,
      default: uni.$u.props.skeleton.loading
    },
    // 是否开启动画效果
    animate: {
      type: Boolean,
      default: uni.$u.props.skeleton.animate
    },
    // 段落占位图行数
    rows: {
      type: [String, Number],
      default: uni.$u.props.skeleton.rows
    },
    // 段落占位图的宽度
    rowsWidth: {
      type: [String, Number, Array],
      default: uni.$u.props.skeleton.rowsWidth
    },
    // 段落占位图的高度
    rowsHeight: {
      type: [String, Number, Array],
      default: uni.$u.props.skeleton.rowsHeight
    },
    // 是否展示标题占位图
    title: {
      type: Boolean,
      default: uni.$u.props.skeleton.title
    },
    // 段落标题的宽度
    titleWidth: {
      type: [String, Number],
      default: uni.$u.props.skeleton.titleWidth
    },
    // 段落标题的高度
    titleHeight: {
      type: [String, Number],
      default: uni.$u.props.skeleton.titleHeight
    },
    // 是否展示头像占位图
    avatar: {
      type: Boolean,
      default: uni.$u.props.skeleton.avatar
    },
    // 头像占位图大小
    avatarSize: {
      type: [String, Number],
      default: uni.$u.props.skeleton.avatarSize
    },
    // 头像占位图的形状，circle-圆形，square-方形
    avatarShape: {
      type: String,
      default: uni.$u.props.skeleton.avatarShape
    }
  }
};

/***/ }),

/***/ "pGqa":
/*!********************************************************************!*\
  !*** ./node_modules/uview-ui/components/u-skeleton/u-skeleton.vue ***!
  \********************************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _u_skeleton_vue_vue_type_template_id_d0b470f2_scoped_true___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./u-skeleton.vue?vue&type=template&id=d0b470f2&scoped=true& */ "h0KJ");
/* harmony import */ var _u_skeleton_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./u-skeleton.vue?vue&type=script&lang=js& */ "y3LX");
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _u_skeleton_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _u_skeleton_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__[key]; }) }(__WEBPACK_IMPORT_KEY__));
/* harmony import */ var _u_skeleton_vue_vue_type_style_index_0_id_d0b470f2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./u-skeleton.vue?vue&type=style&index=0&id=d0b470f2&lang=scss&scoped=true& */ "rcni");
/* harmony import */ var _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/runtime/componentNormalizer.js */ "8MXW");

var renderjs





/* normalize component */

var component = Object(_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__["default"])(
  _u_skeleton_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__["default"],
  _u_skeleton_vue_vue_type_template_id_d0b470f2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["render"],
  _u_skeleton_vue_vue_type_template_id_d0b470f2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["staticRenderFns"],
  false,
  null,
  "d0b470f2",
  null,
  false,
  _u_skeleton_vue_vue_type_template_id_d0b470f2_scoped_true___WEBPACK_IMPORTED_MODULE_0__["components"],
  renderjs
)

/* hot reload */
if (false) { var api; }
component.options.__file = "node_modules/uview-ui/components/u-skeleton/u-skeleton.vue"
/* harmony default export */ __webpack_exports__["default"] = (component.exports);

/***/ }),

/***/ "rcni":
/*!******************************************************************************************************************************!*\
  !*** ./node_modules/uview-ui/components/u-skeleton/u-skeleton.vue?vue&type=style&index=0&id=d0b470f2&lang=scss&scoped=true& ***!
  \******************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_style_index_0_id_d0b470f2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../@dcloudio/vue-cli-plugin-uni/packages/h5-vue-style-loader??ref--9-oneOf-1-0!../../../css-loader/dist/cjs.js??ref--9-oneOf-1-1!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!../../../postcss-loader/src??ref--9-oneOf-1-3!../../../@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./u-skeleton.vue?vue&type=style&index=0&id=d0b470f2&lang=scss&scoped=true& */ "df3G");
/* harmony import */ var _dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_style_index_0_id_d0b470f2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_style_index_0_id_d0b470f2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0__);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_style_index_0_id_d0b470f2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_style_index_0_id_d0b470f2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0__[key]; }) }(__WEBPACK_IMPORT_KEY__));
 /* harmony default export */ __webpack_exports__["default"] = (_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_style_index_0_id_d0b470f2_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ }),

/***/ "y3LX":
/*!*********************************************************************************************!*\
  !*** ./node_modules/uview-ui/components/u-skeleton/u-skeleton.vue?vue&type=script&lang=js& ***!
  \*********************************************************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../babel-loader/lib!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--13-1!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--16-0!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/using-components.js!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./u-skeleton.vue?vue&type=script&lang=js& */ "4+op");
/* harmony import */ var _babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__[key]; }) }(__WEBPACK_IMPORT_KEY__));
 /* harmony default export */ __webpack_exports__["default"] = (_babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_skeleton_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ })

}]);