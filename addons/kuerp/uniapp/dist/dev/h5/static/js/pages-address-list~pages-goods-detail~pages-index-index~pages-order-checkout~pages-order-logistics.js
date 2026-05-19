(window["webpackJsonp"] = window["webpackJsonp"] || []).push([["pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-logistics"],{

/***/ "ARdH":
/*!*********************************************************!*\
  !*** ./node_modules/uview-ui/components/u-tag/props.js ***!
  \*********************************************************/
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
    // 标签类型info、primary、success、warning、error
    type: {
      type: String,
      default: uni.$u.props.tag.type
    },
    // 不可用
    disabled: {
      type: [Boolean, String],
      default: uni.$u.props.tag.disabled
    },
    // 标签的大小，large，medium，mini
    size: {
      type: String,
      default: uni.$u.props.tag.size
    },
    // tag的形状，circle（两边半圆形）, square（方形，带圆角）
    shape: {
      type: String,
      default: uni.$u.props.tag.shape
    },
    // 标签文字
    text: {
      type: [String, Number],
      default: uni.$u.props.tag.text
    },
    // 背景颜色，默认为空字符串，即不处理
    bgColor: {
      type: String,
      default: uni.$u.props.tag.bgColor
    },
    // 标签字体颜色，默认为空字符串，即不处理
    color: {
      type: String,
      default: uni.$u.props.tag.color
    },
    // 标签的边框颜色
    borderColor: {
      type: String,
      default: uni.$u.props.tag.borderColor
    },
    // 关闭按钮图标的颜色
    closeColor: {
      type: String,
      default: uni.$u.props.tag.closeColor
    },
    // 点击时返回的索引值，用于区分例遍的数组哪个元素被点击了
    name: {
      type: [String, Number],
      default: uni.$u.props.tag.name
    },
    // // 模式选择，dark|light|plain
    // mode: {
    // 	type: String,
    // 	default: 'light'
    // },
    // 镂空时是否填充背景色
    plainFill: {
      type: Boolean,
      default: uni.$u.props.tag.plainFill
    },
    // 是否镂空
    plain: {
      type: Boolean,
      default: uni.$u.props.tag.plain
    },
    // 是否可关闭
    closable: {
      type: Boolean,
      default: uni.$u.props.tag.closable
    },
    // 是否显示
    show: {
      type: Boolean,
      default: uni.$u.props.tag.show
    },
    // 内置图标，或绝对路径的图片
    icon: {
      type: String,
      default: uni.$u.props.tag.icon
    }
  }
};

/***/ }),

/***/ "HpH/":
/*!**********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/@dcloudio/vue-cli-plugin-uni/packages/h5-vue-style-loader??ref--9-oneOf-1-0!./node_modules/css-loader/dist/cjs.js??ref--9-oneOf-1-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!./node_modules/postcss-loader/src??ref--9-oneOf-1-3!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./node_modules/uview-ui/components/u-tag/u-tag.vue?vue&type=style&index=0&id=1481d46d&lang=scss&scoped=true& ***!
  \**********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// style-loader: Adds some css to the DOM by adding a <style> tag

// load the styles
var content = __webpack_require__(/*! !../../../css-loader/dist/cjs.js??ref--9-oneOf-1-1!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!../../../postcss-loader/src??ref--9-oneOf-1-3!../../../@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./u-tag.vue?vue&type=style&index=0&id=1481d46d&lang=scss&scoped=true& */ "PxxE");
if(content.__esModule) content = content.default;
if(typeof content === 'string') content = [[module.i, content, '']];
if(content.locals) module.exports = content.locals;
// add the styles to the DOM
var add = __webpack_require__(/*! ../../../@dcloudio/vue-cli-plugin-uni/packages/h5-vue-style-loader/lib/addStylesClient.js */ "TwZa").default
var update = add("7f1ae2f8", content, false, {"sourceMap":false,"shadowMode":false});
// Hot Module Replacement
if(false) {}

/***/ }),

/***/ "Ktbq":
/*!**********************************************************!*\
  !*** ./node_modules/uview-ui/components/u-tag/u-tag.vue ***!
  \**********************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _u_tag_vue_vue_type_template_id_1481d46d_scoped_true___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./u-tag.vue?vue&type=template&id=1481d46d&scoped=true& */ "dB/6");
/* harmony import */ var _u_tag_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./u-tag.vue?vue&type=script&lang=js& */ "sntY");
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _u_tag_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _u_tag_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__[key]; }) }(__WEBPACK_IMPORT_KEY__));
/* harmony import */ var _u_tag_vue_vue_type_style_index_0_id_1481d46d_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./u-tag.vue?vue&type=style&index=0&id=1481d46d&lang=scss&scoped=true& */ "xbbh");
/* harmony import */ var _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/runtime/componentNormalizer.js */ "8MXW");

var renderjs





/* normalize component */

var component = Object(_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__["default"])(
  _u_tag_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__["default"],
  _u_tag_vue_vue_type_template_id_1481d46d_scoped_true___WEBPACK_IMPORTED_MODULE_0__["render"],
  _u_tag_vue_vue_type_template_id_1481d46d_scoped_true___WEBPACK_IMPORTED_MODULE_0__["staticRenderFns"],
  false,
  null,
  "1481d46d",
  null,
  false,
  _u_tag_vue_vue_type_template_id_1481d46d_scoped_true___WEBPACK_IMPORTED_MODULE_0__["components"],
  renderjs
)

/* hot reload */
if (false) { var api; }
component.options.__file = "node_modules/uview-ui/components/u-tag/u-tag.vue"
/* harmony default export */ __webpack_exports__["default"] = (component.exports);

/***/ }),

/***/ "PxxE":
/*!***************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/css-loader/dist/cjs.js??ref--9-oneOf-1-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!./node_modules/postcss-loader/src??ref--9-oneOf-1-3!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./node_modules/uview-ui/components/u-tag/u-tag.vue?vue&type=style&index=0&id=1481d46d&lang=scss&scoped=true& ***!
  \***************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// Imports
var ___CSS_LOADER_API_IMPORT___ = __webpack_require__(/*! ../../../css-loader/dist/runtime/api.js */ "JPst");
exports = ___CSS_LOADER_API_IMPORT___(false);
// Module
exports.push([module.i, "uni-view[data-v-1481d46d], uni-scroll-view[data-v-1481d46d], uni-swiper-item[data-v-1481d46d] {\n  display: flex;\n  flex-direction: column;\n  flex-shrink: 0;\n  flex-grow: 0;\n  flex-basis: auto;\n  align-items: stretch;\n  align-content: flex-start;\n}\n.u-tag-wrapper[data-v-1481d46d] {\n  position: relative;\n}\n.u-tag[data-v-1481d46d] {\n\n  display: flex;\n\n  flex-direction: row;\n  align-items: center;\n  border-style: solid;\n}\n.u-tag--circle[data-v-1481d46d] {\n  border-radius: 100px;\n}\n.u-tag--square[data-v-1481d46d] {\n  border-radius: 3px;\n}\n.u-tag__icon[data-v-1481d46d] {\n  margin-right: 4px;\n}\n.u-tag__text--mini[data-v-1481d46d] {\n  font-size: 12px;\n  line-height: 12px;\n}\n.u-tag__text--medium[data-v-1481d46d] {\n  font-size: 13px;\n  line-height: 13px;\n}\n.u-tag__text--large[data-v-1481d46d] {\n  font-size: 15px;\n  line-height: 15px;\n}\n.u-tag--mini[data-v-1481d46d] {\n  height: 22px;\n  line-height: 22px;\n  padding: 0 5px;\n}\n.u-tag--medium[data-v-1481d46d] {\n  height: 26px;\n  line-height: 22px;\n  padding: 0 10px;\n}\n.u-tag--large[data-v-1481d46d] {\n  height: 32px;\n  line-height: 32px;\n  padding: 0 15px;\n}\n.u-tag--primary[data-v-1481d46d] {\n  background-color: #3c9cff;\n  border-width: 1px;\n  border-color: #3c9cff;\n}\n.u-tag--primary--plain[data-v-1481d46d] {\n  border-width: 1px;\n  border-color: #3c9cff;\n}\n.u-tag--primary--plain--fill[data-v-1481d46d] {\n  background-color: #ecf5ff;\n}\n.u-tag__text--primary[data-v-1481d46d] {\n  color: #FFFFFF;\n}\n.u-tag__text--primary--plain[data-v-1481d46d] {\n  color: #3c9cff;\n}\n.u-tag--error[data-v-1481d46d] {\n  background-color: #f56c6c;\n  border-width: 1px;\n  border-color: #f56c6c;\n}\n.u-tag--error--plain[data-v-1481d46d] {\n  border-width: 1px;\n  border-color: #f56c6c;\n}\n.u-tag--error--plain--fill[data-v-1481d46d] {\n  background-color: #fef0f0;\n}\n.u-tag__text--error[data-v-1481d46d] {\n  color: #FFFFFF;\n}\n.u-tag__text--error--plain[data-v-1481d46d] {\n  color: #f56c6c;\n}\n.u-tag--warning[data-v-1481d46d] {\n  background-color: #f9ae3d;\n  border-width: 1px;\n  border-color: #f9ae3d;\n}\n.u-tag--warning--plain[data-v-1481d46d] {\n  border-width: 1px;\n  border-color: #f9ae3d;\n}\n.u-tag--warning--plain--fill[data-v-1481d46d] {\n  background-color: #fdf6ec;\n}\n.u-tag__text--warning[data-v-1481d46d] {\n  color: #FFFFFF;\n}\n.u-tag__text--warning--plain[data-v-1481d46d] {\n  color: #f9ae3d;\n}\n.u-tag--success[data-v-1481d46d] {\n  background-color: #5ac725;\n  border-width: 1px;\n  border-color: #5ac725;\n}\n.u-tag--success--plain[data-v-1481d46d] {\n  border-width: 1px;\n  border-color: #5ac725;\n}\n.u-tag--success--plain--fill[data-v-1481d46d] {\n  background-color: #f5fff0;\n}\n.u-tag__text--success[data-v-1481d46d] {\n  color: #FFFFFF;\n}\n.u-tag__text--success--plain[data-v-1481d46d] {\n  color: #5ac725;\n}\n.u-tag--info[data-v-1481d46d] {\n  background-color: #909399;\n  border-width: 1px;\n  border-color: #909399;\n}\n.u-tag--info--plain[data-v-1481d46d] {\n  border-width: 1px;\n  border-color: #909399;\n}\n.u-tag--info--plain--fill[data-v-1481d46d] {\n  background-color: #f4f4f5;\n}\n.u-tag__text--info[data-v-1481d46d] {\n  color: #FFFFFF;\n}\n.u-tag__text--info--plain[data-v-1481d46d] {\n  color: #909399;\n}\n.u-tag__close[data-v-1481d46d] {\n  position: absolute;\n  z-index: 999;\n  top: 10px;\n  right: 10px;\n  border-radius: 100px;\n  background-color: #C6C7CB;\n\n  display: flex;\n\n  flex-direction: row;\n  align-items: center;\n  justify-content: center;\n  -webkit-transform: scale(0.6) translate(80%, -80%);\n          transform: scale(0.6) translate(80%, -80%);\n}\n.u-tag__close--mini[data-v-1481d46d] {\n  width: 18px;\n  height: 18px;\n}\n.u-tag__close--medium[data-v-1481d46d] {\n  width: 22px;\n  height: 22px;\n}\n.u-tag__close--large[data-v-1481d46d] {\n  width: 25px;\n  height: 25px;\n}", ""]);
// Exports
module.exports = exports;


/***/ }),

/***/ "dB/6":
/*!*****************************************************************************************************!*\
  !*** ./node_modules/uview-ui/components/u-tag/u-tag.vue?vue&type=template&id=1481d46d&scoped=true& ***!
  \*****************************************************************************************************/
/*! exports provided: render, staticRenderFns, recyclableRender, components */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_template_id_1481d46d_scoped_true___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--15-0!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/filter-modules-template.js!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/page-meta.js!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./u-tag.vue?vue&type=template&id=1481d46d&scoped=true& */ "imX4");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "render", function() { return _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_template_id_1481d46d_scoped_true___WEBPACK_IMPORTED_MODULE_0__["render"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "staticRenderFns", function() { return _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_template_id_1481d46d_scoped_true___WEBPACK_IMPORTED_MODULE_0__["staticRenderFns"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "recyclableRender", function() { return _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_template_id_1481d46d_scoped_true___WEBPACK_IMPORTED_MODULE_0__["recyclableRender"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "components", function() { return _dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_template_id_1481d46d_scoped_true___WEBPACK_IMPORTED_MODULE_0__["components"]; });



/***/ }),

/***/ "imX4":
/*!************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--15-0!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/filter-modules-template.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/page-meta.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./node_modules/uview-ui/components/u-tag/u-tag.vue?vue&type=template&id=1481d46d&scoped=true& ***!
  \************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! exports provided: render, staticRenderFns, recyclableRender, components */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "render", function() { return render; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "staticRenderFns", function() { return staticRenderFns; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "recyclableRender", function() { return recyclableRender; });
/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, "components", function() { return components; });
var components
try {
  components = {
    uTransition: __webpack_require__(/*! uview-ui/components/u-transition/u-transition.vue */ "Un92")
      .default,
    uIcon: __webpack_require__(/*! uview-ui/components/u-icon/u-icon.vue */ "+Guo").default,
  }
} catch (e) {
  if (
    e.message.indexOf("Cannot find module") !== -1 &&
    e.message.indexOf(".vue") !== -1
  ) {
    console.error(e.message)
    console.error("1. 排查组件名称拼写是否正确")
    console.error(
      "2. 排查组件是否符合 easycom 规范，文档：https://uniapp.dcloud.net.cn/collocation/pages?id=easycom"
    )
    console.error(
      "3. 若组件不符合 easycom 规范，需手动引入，并在 components 中注册该组件"
    )
  } else {
    throw e
  }
}
var render = function () {
  var _vm = this
  var _h = _vm.$createElement
  var _c = _vm._self._c || _h
  return _c(
    "u-transition",
    { attrs: { mode: "fade", show: _vm.show } },
    [
      _c(
        "v-uni-view",
        { staticClass: "u-tag-wrapper" },
        [
          _c(
            "v-uni-view",
            {
              staticClass: "u-tag",
              class: [
                "u-tag--" + _vm.shape,
                !_vm.plain && "u-tag--" + _vm.type,
                _vm.plain && "u-tag--" + _vm.type + "--plain",
                "u-tag--" + _vm.size,
                _vm.plain &&
                  _vm.plainFill &&
                  "u-tag--" + _vm.type + "--plain--fill",
              ],
              style: [
                {
                  marginRight: _vm.closable ? "10px" : 0,
                  marginTop: _vm.closable ? "10px" : 0,
                },
                _vm.style,
              ],
              on: {
                click: function ($event) {
                  $event.stopPropagation()
                  arguments[0] = $event = _vm.$handleEvent($event)
                  _vm.clickHandler.apply(void 0, arguments)
                },
              },
            },
            [
              _vm._t("icon", [
                _vm.icon
                  ? _c(
                      "v-uni-view",
                      { staticClass: "u-tag__icon" },
                      [
                        _vm.$u.test.image(_vm.icon)
                          ? _c("v-uni-image", {
                              style: [_vm.imgStyle],
                              attrs: { src: _vm.icon },
                            })
                          : _c("u-icon", {
                              attrs: {
                                color: _vm.elIconColor,
                                name: _vm.icon,
                                size: _vm.iconSize,
                              },
                            }),
                      ],
                      1
                    )
                  : _vm._e(),
              ]),
              _c(
                "v-uni-text",
                {
                  staticClass: "u-tag__text",
                  class: [
                    "u-tag__text--" + _vm.type,
                    _vm.plain && "u-tag__text--" + _vm.type + "--plain",
                    "u-tag__text--" + _vm.size,
                  ],
                  style: [_vm.textColor],
                },
                [_vm._v(_vm._s(_vm.text))]
              ),
            ],
            2
          ),
          _vm.closable
            ? _c(
                "v-uni-view",
                {
                  staticClass: "u-tag__close",
                  class: ["u-tag__close--" + _vm.size],
                  style: { backgroundColor: _vm.closeColor },
                  on: {
                    click: function ($event) {
                      $event.stopPropagation()
                      arguments[0] = $event = _vm.$handleEvent($event)
                      _vm.closeHandler.apply(void 0, arguments)
                    },
                  },
                },
                [
                  _c("u-icon", {
                    attrs: {
                      name: "close",
                      size: _vm.closeSize,
                      color: "#ffffff",
                    },
                  }),
                ],
                1
              )
            : _vm._e(),
        ],
        1
      ),
    ],
    1
  )
}
var recyclableRender = false
var staticRenderFns = []
render._withStripped = true



/***/ }),

/***/ "q+32":
/*!**************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--13-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--16-0!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/using-components.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./node_modules/uview-ui/components/u-tag/u-tag.vue?vue&type=script&lang=js& ***!
  \**************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


__webpack_require__(/*! core-js/modules/es.object.define-property.js */ "eoL8");
var _interopRequireDefault = __webpack_require__(/*! ./node_modules/@babel/runtime/helpers/interopRequireDefault.js */ "TqRt").default;
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _props = _interopRequireDefault(__webpack_require__(/*! ./props.js */ "ARdH"));
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
//
//
//
//
//
//
/**
 * Tag 标签
 * @description tag组件一般用于标记和选择，我们提供了更加丰富的表现形式，能够较全面的涵盖您的使用场景
 * @tutorial https://www.uviewui.com/components/tag.html
 * @property {String}			type		标签类型info、primary、success、warning、error （默认 'primary' ）
 * @property {Boolean | String}	disabled	不可用（默认 false ）
 * @property {String}			size		标签的大小，large，medium，mini （默认 'medium' ）
 * @property {String}			shape		tag的形状，circle（两边半圆形）, square（方形，带圆角）（默认 'square' ）
 * @property {String | Number}	text		标签的文字内容 
 * @property {String}			bgColor		背景颜色，默认为空字符串，即不处理
 * @property {String}			color		标签字体颜色，默认为空字符串，即不处理
 * @property {String}			borderColor	镂空形式标签的边框颜色
 * @property {String}			closeColor	关闭按钮图标的颜色（默认 #C6C7CB）
 * @property {String | Number}	name		点击时返回的索引值，用于区分例遍的数组哪个元素被点击了
 * @property {Boolean}			plainFill	镂空时是否填充背景色（默认 false ）
 * @property {Boolean}			plain		是否镂空（默认 false ）
 * @property {Boolean}			closable	是否可关闭，设置为true，文字右边会出现一个关闭图标（默认 false ）
 * @property {Boolean}			show		标签显示与否（默认 true ）
 * @property {String}			icon		内置图标，或绝对路径的图片
 * @event {Function(index)} click 点击标签时触发 index: 传递的index参数值
 * @event {Function(index)} close closable为true时，点击标签关闭按钮触发 index: 传递的index参数值	
 * @example <u-tag text="标签" type="error" plain plainFill></u-tag>
 */
var _default = exports.default = {
  name: 'u-tag',
  mixins: [uni.$u.mpMixin, uni.$u.mixin, _props.default],
  data: function data() {
    return {};
  },
  computed: {
    style: function style() {
      var style = {};
      if (this.bgColor) {
        style.backgroundColor = this.bgColor;
      }
      if (this.color) {
        style.color = this.color;
      }
      if (this.borderColor) {
        style.borderColor = this.borderColor;
      }
      return style;
    },
    // nvue下，文本颜色无法继承父元素
    textColor: function textColor() {
      var style = {};
      if (this.color) {
        style.color = this.color;
      }
      return style;
    },
    imgStyle: function imgStyle() {
      var width = this.size === 'large' ? '17px' : this.size === 'medium' ? '15px' : '13px';
      return {
        width: width,
        height: width
      };
    },
    // 文本的样式
    closeSize: function closeSize() {
      var size = this.size === 'large' ? 15 : this.size === 'medium' ? 13 : 12;
      return size;
    },
    // 图标大小
    iconSize: function iconSize() {
      var size = this.size === 'large' ? 21 : this.size === 'medium' ? 19 : 16;
      return size;
    },
    // 图标颜色
    elIconColor: function elIconColor() {
      return this.iconColor ? this.iconColor : this.plain ? this.type : '#ffffff';
    }
  },
  methods: {
    // 点击关闭按钮
    closeHandler: function closeHandler() {
      this.$emit('close', this.name);
    },
    // 点击标签
    clickHandler: function clickHandler() {
      this.$emit('click', this.name);
    }
  }
};

/***/ }),

/***/ "sntY":
/*!***********************************************************************************!*\
  !*** ./node_modules/uview-ui/components/u-tag/u-tag.vue?vue&type=script&lang=js& ***!
  \***********************************************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../babel-loader/lib!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--13-1!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--16-0!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/using-components.js!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./u-tag.vue?vue&type=script&lang=js& */ "q+32");
/* harmony import */ var _babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__[key]; }) }(__WEBPACK_IMPORT_KEY__));
 /* harmony default export */ __webpack_exports__["default"] = (_babel_loader_lib_index_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ }),

/***/ "xbbh":
/*!********************************************************************************************************************!*\
  !*** ./node_modules/uview-ui/components/u-tag/u-tag.vue?vue&type=style&index=0&id=1481d46d&lang=scss&scoped=true& ***!
  \********************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_style_index_0_id_1481d46d_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../@dcloudio/vue-cli-plugin-uni/packages/h5-vue-style-loader??ref--9-oneOf-1-0!../../../css-loader/dist/cjs.js??ref--9-oneOf-1-1!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!../../../postcss-loader/src??ref--9-oneOf-1-3!../../../@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!../../../@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./u-tag.vue?vue&type=style&index=0&id=1481d46d&lang=scss&scoped=true& */ "HpH/");
/* harmony import */ var _dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_style_index_0_id_1481d46d_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_style_index_0_id_1481d46d_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0__);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_style_index_0_id_1481d46d_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_style_index_0_id_1481d46d_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0__[key]; }) }(__WEBPACK_IMPORT_KEY__));
 /* harmony default export */ __webpack_exports__["default"] = (_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_css_loader_dist_cjs_js_ref_9_oneOf_1_1_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_postcss_loader_src_index_js_ref_9_oneOf_1_3_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_u_tag_vue_vue_type_style_index_0_id_1481d46d_lang_scss_scoped_true___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ })

}]);