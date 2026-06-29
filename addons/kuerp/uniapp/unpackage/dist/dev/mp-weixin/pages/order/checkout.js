(global["webpackJsonp"] = global["webpackJsonp"] || []).push([["pages/order/checkout"],{

/***/ 185:
/*!****************************************************************************************************************************************!*\
  !*** /Applications/phpstudy/WWW/2026/05-ERP-licheng/erp-admin-licheng/addons/kuerp/uniapp/main.js?{"page":"pages%2Forder%2Fcheckout"} ***!
  \****************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/* WEBPACK VAR INJECTION */(function(wx, createPage) {

var _interopRequireDefault = __webpack_require__(/*! @babel/runtime/helpers/interopRequireDefault */ 4);
__webpack_require__(/*! uni-pages */ 26);
var _vue = _interopRequireDefault(__webpack_require__(/*! vue */ 25));
var _checkout = _interopRequireDefault(__webpack_require__(/*! ./pages/order/checkout.vue */ 186));
// @ts-ignore
wx.__webpack_require_UNI_MP_PLUGIN__ = __webpack_require__;
createPage(_checkout.default);
/* WEBPACK VAR INJECTION */}.call(this, __webpack_require__(/*! ./node_modules/@dcloudio/uni-mp-weixin/dist/wx.js */ 1)["default"], __webpack_require__(/*! ./node_modules/@dcloudio/uni-mp-weixin/dist/index.js */ 2)["createPage"]))

/***/ }),

/***/ 186:
/*!*********************************************************************************************************************!*\
  !*** /Applications/phpstudy/WWW/2026/05-ERP-licheng/erp-admin-licheng/addons/kuerp/uniapp/pages/order/checkout.vue ***!
  \*********************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _checkout_vue_vue_type_template_id_c64697c0_scoped_true___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./checkout.vue?vue&type=template&id=c64697c0&scoped=true& */ 187);
/* harmony import */ var _checkout_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./checkout.vue?vue&type=script&lang=js& */ 189);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _checkout_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _checkout_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__[key]; }) }(__WEBPACK_IMPORT_KEY__));
/* harmony import */ var _checkout_vue_vue_type_style_index_0_id_c64697c0_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./checkout.vue?vue&type=style&index=0&id=c64697c0&scoped=true&lang=css& */ 191);
/* harmony import */ var _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/runtime/componentNormalizer.js */ 38);

var renderjs





/* normalize component */

var component = Object(_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__["default"])(
  _checkout_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__["default"],
  _checkout_vue_vue_type_template_id_c64697c0_scoped_true___WEBPACK_IMPORTED_MODULE_0__["render"],
  _checkout_vue_vue_type_template_id_c64697c0_scoped_true___WEBPACK_IMPORTED_MODULE_0__["staticRenderFns"],
  false,
  null,
  "c64697c0",
  null,
  false,
  _checkout_vue_vue_type_template_id_c64697c0_scoped_true___WEBPACK_IMPORTED_MODULE_0__["components"],
  renderjs
)

component.options.__file = "pages/order/checkout.vue"
/* harmony default export */ __webpack_exports__["default"] = (component.exports);

/***/ }),

/***/ 187:
/*!****************************************************************************************************************************************************************!*\
  !*** /Applications/phpstudy/WWW/2026/05-ERP-licheng/erp-admin-licheng/addons/kuerp/uniapp/pages/order/checkout.vue?vue&type=template&id=c64697c0&scoped=true& ***!
  \****************************************************************************************************************************************************************/
/*! exports provided: render, staticRenderFns, recyclableRender, components */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_17_0_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_template_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_template_id_c64697c0_scoped_true___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--17-0!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/webpack-uni-mp-loader/lib/template.js!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/page-meta.js!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/webpack-uni-mp-loader/lib/style.js!./checkout.vue?vue&type=template&id=c64697c0&scoped=true& */ 188);
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "render", function() { return _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_17_0_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_template_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_template_id_c64697c0_scoped_true___WEBPACK_IMPORTED_MODULE_0__["render"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "staticRenderFns", function() { return _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_17_0_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_template_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_template_id_c64697c0_scoped_true___WEBPACK_IMPORTED_MODULE_0__["staticRenderFns"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "recyclableRender", function() { return _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_17_0_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_template_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_template_id_c64697c0_scoped_true___WEBPACK_IMPORTED_MODULE_0__["recyclableRender"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "components", function() { return _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_17_0_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_template_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_template_id_c64697c0_scoped_true___WEBPACK_IMPORTED_MODULE_0__["components"]; });



/***/ }),

/***/ 188:
/*!****************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--17-0!./node_modules/@dcloudio/webpack-uni-mp-loader/lib/template.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/page-meta.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/webpack-uni-mp-loader/lib/style.js!/Applications/phpstudy/WWW/2026/05-ERP-licheng/erp-admin-licheng/addons/kuerp/uniapp/pages/order/checkout.vue?vue&type=template&id=c64697c0&scoped=true& ***!
  \****************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
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
    uIcon: function () {
      return Promise.all(/*! import() | node-modules/uview-ui/components/u-icon/u-icon */[__webpack_require__.e("common/vendor"), __webpack_require__.e("node-modules/uview-ui/components/u-icon/u-icon")]).then(__webpack_require__.bind(null, /*! uview-ui/components/u-icon/u-icon.vue */ 290))
    },
    "u-Input": function () {
      return Promise.all(/*! import() | node-modules/uview-ui/components/u--input/u--input */[__webpack_require__.e("common/vendor"), __webpack_require__.e("node-modules/uview-ui/components/u--input/u--input")]).then(__webpack_require__.bind(null, /*! uview-ui/components/u--input/u--input.vue */ 389))
    },
    "u-Textarea": function () {
      return Promise.all(/*! import() | node-modules/uview-ui/components/u--textarea/u--textarea */[__webpack_require__.e("common/vendor"), __webpack_require__.e("node-modules/uview-ui/components/u--textarea/u--textarea")]).then(__webpack_require__.bind(null, /*! uview-ui/components/u--textarea/u--textarea.vue */ 395))
    },
    uButton: function () {
      return Promise.all(/*! import() | node-modules/uview-ui/components/u-button/u-button */[__webpack_require__.e("common/vendor"), __webpack_require__.e("node-modules/uview-ui/components/u-button/u-button")]).then(__webpack_require__.bind(null, /*! uview-ui/components/u-button/u-button.vue */ 371))
    },
    uTag: function () {
      return Promise.all(/*! import() | node-modules/uview-ui/components/u-tag/u-tag */[__webpack_require__.e("common/vendor"), __webpack_require__.e("node-modules/uview-ui/components/u-tag/u-tag")]).then(__webpack_require__.bind(null, /*! uview-ui/components/u-tag/u-tag.vue */ 322))
    },
    uDivider: function () {
      return Promise.all(/*! import() | node-modules/uview-ui/components/u-divider/u-divider */[__webpack_require__.e("common/vendor"), __webpack_require__.e("node-modules/uview-ui/components/u-divider/u-divider")]).then(__webpack_require__.bind(null, /*! uview-ui/components/u-divider/u-divider.vue */ 330))
    },
    uPopup: function () {
      return Promise.all(/*! import() | node-modules/uview-ui/components/u-popup/u-popup */[__webpack_require__.e("common/vendor"), __webpack_require__.e("node-modules/uview-ui/components/u-popup/u-popup")]).then(__webpack_require__.bind(null, /*! uview-ui/components/u-popup/u-popup.vue */ 401))
    },
    uSearch: function () {
      return Promise.all(/*! import() | node-modules/uview-ui/components/u-search/u-search */[__webpack_require__.e("common/vendor"), __webpack_require__.e("node-modules/uview-ui/components/u-search/u-search")]).then(__webpack_require__.bind(null, /*! uview-ui/components/u-search/u-search.vue */ 274))
    },
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
  var l0 = _vm.__map(_vm.goodsList, function (item, __i0__) {
    var $orig = _vm.__get_orig(item)
    var m0 = item.goods_image ? _vm.$img(item.goods_image) : null
    var m1 = _vm.calcItemTotal(item)
    return {
      $orig: $orig,
      m0: m0,
      m1: m1,
    }
  })
  var g0 = _vm.customerList.length
  var g1 = _vm.addressList.length
  if (!_vm._isMounted) {
    _vm.e0 = function ($event) {
      _vm.showCustomerPicker = true
    }
    _vm.e1 = function ($event) {
      _vm.showCustomerPicker = true
    }
    _vm.e2 = function ($event) {
      _vm.showCustomerPicker = true
    }
    _vm.e3 = function ($event) {
      _vm.showAddressPicker = true
    }
    _vm.e4 = function ($event) {
      _vm.showAddressPicker = true
    }
    _vm.e5 = function ($event) {
      _vm.showAddressEdit = false
    }
    _vm.e6 = function ($event) {
      _vm.showCustomerPicker = false
    }
    _vm.e7 = function ($event) {
      _vm.showCustomerPicker = false
    }
    _vm.e8 = function ($event) {
      _vm.showAddCustomer = false
    }
    _vm.e9 = function ($event) {
      _vm.showAddCustomer = false
    }
    _vm.e10 = function ($event) {
      _vm.showAddressPicker = false
    }
    _vm.e11 = function ($event) {
      _vm.showAddressPicker = false
    }
    _vm.e12 = function ($event) {
      _vm.showAddressPicker = false
      _vm.openManualAddress()
    }
  }
  _vm.$mp.data = Object.assign(
    {},
    {
      $root: {
        l0: l0,
        g0: g0,
        g1: g1,
      },
    }
  )
}
var recyclableRender = false
var staticRenderFns = []
render._withStripped = true



/***/ }),

/***/ 189:
/*!**********************************************************************************************************************************************!*\
  !*** /Applications/phpstudy/WWW/2026/05-ERP-licheng/erp-admin-licheng/addons/kuerp/uniapp/pages/order/checkout.vue?vue&type=script&lang=js& ***!
  \**********************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_babel_loader_lib_index_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_script_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/babel-loader/lib!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--13-1!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/webpack-uni-mp-loader/lib/script.js!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/webpack-uni-mp-loader/lib/style.js!./checkout.vue?vue&type=script&lang=js& */ 190);
/* harmony import */ var _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_babel_loader_lib_index_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_script_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_babel_loader_lib_index_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_script_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_babel_loader_lib_index_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_script_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_babel_loader_lib_index_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_script_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__[key]; }) }(__WEBPACK_IMPORT_KEY__));
 /* harmony default export */ __webpack_exports__["default"] = (_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_babel_loader_lib_index_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_script_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ }),

/***/ 190:
/*!*****************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--13-1!./node_modules/@dcloudio/webpack-uni-mp-loader/lib/script.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/webpack-uni-mp-loader/lib/style.js!/Applications/phpstudy/WWW/2026/05-ERP-licheng/erp-admin-licheng/addons/kuerp/uniapp/pages/order/checkout.vue?vue&type=script&lang=js& ***!
  \*****************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/* WEBPACK VAR INJECTION */(function(uni) {

var _interopRequireDefault = __webpack_require__(/*! @babel/runtime/helpers/interopRequireDefault */ 4);
var _typeof = __webpack_require__(/*! @babel/runtime/helpers/typeof */ 13);
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
var _regenerator = _interopRequireDefault(__webpack_require__(/*! @babel/runtime/regenerator */ 30));
var _toConsumableArray2 = _interopRequireDefault(__webpack_require__(/*! @babel/runtime/helpers/toConsumableArray */ 18));
var _defineProperty2 = _interopRequireDefault(__webpack_require__(/*! @babel/runtime/helpers/defineProperty */ 11));
var _asyncToGenerator2 = _interopRequireDefault(__webpack_require__(/*! @babel/runtime/helpers/asyncToGenerator */ 32));
var _api = __webpack_require__(/*! ../../api */ 33);
function _getRequireWildcardCache(nodeInterop) { if (typeof WeakMap !== "function") return null; var cacheBabelInterop = new WeakMap(); var cacheNodeInterop = new WeakMap(); return (_getRequireWildcardCache = function _getRequireWildcardCache(nodeInterop) { return nodeInterop ? cacheNodeInterop : cacheBabelInterop; })(nodeInterop); }
function _interopRequireWildcard(obj, nodeInterop) { if (!nodeInterop && obj && obj.__esModule) { return obj; } if (obj === null || _typeof(obj) !== "object" && typeof obj !== "function") { return { default: obj }; } var cache = _getRequireWildcardCache(nodeInterop); if (cache && cache.has(obj)) { return cache.get(obj); } var newObj = {}; var hasPropertyDescriptor = Object.defineProperty && Object.getOwnPropertyDescriptor; for (var key in obj) { if (key !== "default" && Object.prototype.hasOwnProperty.call(obj, key)) { var desc = hasPropertyDescriptor ? Object.getOwnPropertyDescriptor(obj, key) : null; if (desc && (desc.get || desc.set)) { Object.defineProperty(newObj, key, desc); } else { newObj[key] = obj[key]; } } } newObj.default = obj; if (cache) { cache.set(obj, newObj); } return newObj; }
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }
function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { (0, _defineProperty2.default)(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }
var _default = {
  data: function data() {
    return {
      goodsList: [],
      customer: null,
      address: null,
      remark: '',
      submitting: false,
      directGoods: null,
      cartIds: '',
      // 客户选择
      showCustomerPicker: false,
      customerList: [],
      customerKeyword: '',
      // 新增客户
      showAddCustomer: false,
      addCustomerName: '',
      addCustomerMobile: '',
      addCustomerContact: '',
      addCustomerAddress: '',
      addingCustomer: false,
      // 地址选择
      showAddressPicker: false,
      addressList: [],
      // 手动地址
      showAddressEdit: false,
      editReceiverName: '',
      editReceiverMobile: '',
      editReceiverAddress: ''
    };
  },
  computed: {
    totalAmount: function totalAmount() {
      return this.goodsList.reduce(function (s, i) {
        return s + (i.unit_price || i.show_price || 0) * i.quantity;
      }, 0).toFixed(2);
    },
    itemCount: function itemCount() {
      return this.goodsList.reduce(function (s, i) {
        return s + i.quantity;
      }, 0);
    }
  },
  onLoad: function onLoad(options) {
    if (options.goods_id) {
      this.directGoods = {
        goods_id: Number(options.goods_id),
        quantity: Number(options.quantity) || 1,
        attr_ids: decodeURIComponent(options.attr_ids || ''),
        goods_unit: Number(options.goods_unit) || 0,
        attr_name: decodeURIComponent(options.attr_name || '')
      };
    }
    if (options.ids) {
      this.cartIds = options.ids;
    }
  },
  onShow: function onShow() {
    if (this.directGoods) {
      this.loadDirectGoods();
    } else {
      this.loadCart();
    }
    this.loadCustomers();
  },
  methods: {
    // ========== 客户 ==========
    loadCustomers: function loadCustomers(keyword) {
      var _this = this;
      return (0, _asyncToGenerator2.default)( /*#__PURE__*/_regenerator.default.mark(function _callee() {
        var res, own;
        return _regenerator.default.wrap(function _callee$(_context) {
          while (1) {
            switch (_context.prev = _context.next) {
              case 0:
                _context.prev = 0;
                _context.next = 3;
                return _api.customerApi.list(keyword || '');
              case 3:
                res = _context.sent;
                _this.customerList = res.list || [];
                // 自动选中自己的客户
                if (!_this.customer) {
                  own = _this.customerList.find(function (c) {
                    return c.is_own;
                  });
                  if (own) {
                    _this.selectCustomer(own);
                  }
                }
                _context.next = 10;
                break;
              case 8:
                _context.prev = 8;
                _context.t0 = _context["catch"](0);
              case 10:
              case "end":
                return _context.stop();
            }
          }
        }, _callee, null, [[0, 8]]);
      }))();
    },
    searchCustomers: function searchCustomers() {
      this.loadCustomers(this.customerKeyword);
    },
    selectCustomer: function selectCustomer(c) {
      this.customer = c;
      this.showCustomerPicker = false;
      // 加载该客户的地址
      this.loadAddresses(c.id);
      // 用客户默认信息填充地址
      this.address = {
        first_connect_name: c.connet_name || c.customer_name,
        first_connect_mobile: c.customer_mobile || '',
        connect_address: c.getgoods_address || '',
        is_first: 1
      };
      this.showAddressEdit = false;
    },
    openAddCustomer: function openAddCustomer() {
      this.showCustomerPicker = false;
      this.showAddCustomer = true;
      this.addCustomerName = '';
      this.addCustomerMobile = '';
      this.addCustomerContact = '';
      this.addCustomerAddress = '';
    },
    submitAddCustomer: function submitAddCustomer() {
      var _this2 = this;
      return (0, _asyncToGenerator2.default)( /*#__PURE__*/_regenerator.default.mark(function _callee2() {
        var res;
        return _regenerator.default.wrap(function _callee2$(_context2) {
          while (1) {
            switch (_context2.prev = _context2.next) {
              case 0:
                if (_this2.addCustomerName) {
                  _context2.next = 3;
                  break;
                }
                uni.showToast({
                  title: '请输入客户名称',
                  icon: 'none'
                });
                return _context2.abrupt("return");
              case 3:
                _this2.addingCustomer = true;
                _context2.prev = 4;
                _context2.next = 7;
                return _api.customerApi.add({
                  customer_name: _this2.addCustomerName,
                  customer_mobile: _this2.addCustomerMobile,
                  connet_name: _this2.addCustomerContact || _this2.addCustomerName,
                  getgoods_address: _this2.addCustomerAddress
                });
              case 7:
                res = _context2.sent;
                _this2.showAddCustomer = false;
                _this2.addingCustomer = false;
                // 选中新客户
                if (res.customer) {
                  _this2.customer = res.customer;
                  _this2.address = {
                    first_connect_name: res.customer.connet_name || res.customer.customer_name,
                    first_connect_mobile: res.customer.customer_mobile || '',
                    connect_address: res.customer.getgoods_address || '',
                    is_first: 1
                  };
                  _this2.addressList = [];
                }
                _this2.loadCustomers();
                _context2.next = 17;
                break;
              case 14:
                _context2.prev = 14;
                _context2.t0 = _context2["catch"](4);
                uni.showToast({
                  title: typeof _context2.t0 === 'string' ? _context2.t0 : '添加失败',
                  icon: 'none'
                });
              case 17:
                _context2.prev = 17;
                _this2.addingCustomer = false;
                return _context2.finish(17);
              case 20:
              case "end":
                return _context2.stop();
            }
          }
        }, _callee2, null, [[4, 14, 17, 20]]);
      }))();
    },
    // ========== 地址 ==========
    loadAddresses: function loadAddresses(customerId) {
      var _this3 = this;
      return (0, _asyncToGenerator2.default)( /*#__PURE__*/_regenerator.default.mark(function _callee3() {
        var res, def;
        return _regenerator.default.wrap(function _callee3$(_context3) {
          while (1) {
            switch (_context3.prev = _context3.next) {
              case 0:
                _context3.prev = 0;
                _context3.next = 3;
                return _api.customerApi.addresses(customerId || (_this3.customer ? _this3.customer.id : 0));
              case 3:
                res = _context3.sent;
                _this3.addressList = res.list || [];
                if (res.customer && !_this3.customer) {
                  _this3.customer = res.customer;
                }
                def = _this3.addressList.find(function (a) {
                  return a.is_first == 1;
                }) || _this3.addressList[0];
                if (def) {
                  _this3.address = def;
                }
                _context3.next = 12;
                break;
              case 10:
                _context3.prev = 10;
                _context3.t0 = _context3["catch"](0);
              case 12:
              case "end":
                return _context3.stop();
            }
          }
        }, _callee3, null, [[0, 10]]);
      }))();
    },
    selectAddress: function selectAddress(a) {
      this.address = a;
      this.showAddressPicker = false;
      this.showAddressEdit = false;
    },
    openManualAddress: function openManualAddress() {
      this.editReceiverName = this.address ? this.address.first_connect_name || '' : '';
      this.editReceiverMobile = this.address ? this.address.first_connect_mobile || '' : '';
      this.editReceiverAddress = this.address ? this.address.connect_address || '' : '';
      this.showAddressEdit = true;
    },
    confirmManualAddress: function confirmManualAddress() {
      var _this4 = this;
      return (0, _asyncToGenerator2.default)( /*#__PURE__*/_regenerator.default.mark(function _callee4() {
        var res, idx;
        return _regenerator.default.wrap(function _callee4$(_context4) {
          while (1) {
            switch (_context4.prev = _context4.next) {
              case 0:
                if (!(!_this4.editReceiverName || !_this4.editReceiverAddress)) {
                  _context4.next = 3;
                  break;
                }
                uni.showToast({
                  title: '请填写收货人和地址',
                  icon: 'none'
                });
                return _context4.abrupt("return");
              case 3:
                if (!(_this4.customer && _this4.customer.id)) {
                  _context4.next = 16;
                  break;
                }
                _context4.prev = 4;
                _context4.next = 7;
                return _api.customerApi.saveAddress({
                  customer_id: _this4.customer.id,
                  first_connect_name: _this4.editReceiverName,
                  first_connect_mobile: _this4.editReceiverMobile,
                  connect_address: _this4.editReceiverAddress
                });
              case 7:
                res = _context4.sent;
                if (res && res.address) {
                  _this4.address = res.address;
                  // 同步更新到地址列表（去重替换）
                  idx = _this4.addressList.findIndex(function (a) {
                    return a.id === res.address.id;
                  });
                  if (idx >= 0) {
                    _this4.addressList.splice(idx, 1, res.address);
                  } else {
                    _this4.addressList.unshift(res.address);
                  }
                }
                _context4.next = 14;
                break;
              case 11:
                _context4.prev = 11;
                _context4.t0 = _context4["catch"](4);
                // 保存失败时降级为本地地址
                _this4.address = {
                  first_connect_name: _this4.editReceiverName,
                  first_connect_mobile: _this4.editReceiverMobile,
                  connect_address: _this4.editReceiverAddress,
                  is_first: 0
                };
              case 14:
                _context4.next = 17;
                break;
              case 16:
                // 无客户时仅本地使用
                _this4.address = {
                  first_connect_name: _this4.editReceiverName,
                  first_connect_mobile: _this4.editReceiverMobile,
                  connect_address: _this4.editReceiverAddress,
                  is_first: 0
                };
              case 17:
                _this4.showAddressEdit = false;
                uni.showToast({
                  title: '地址已更新',
                  icon: 'success'
                });
              case 19:
              case "end":
                return _context4.stop();
            }
          }
        }, _callee4, null, [[4, 11]]);
      }))();
    },
    // ========== 商品 ==========
    loadCart: function loadCart() {
      var _this5 = this;
      return (0, _asyncToGenerator2.default)( /*#__PURE__*/_regenerator.default.mark(function _callee5() {
        var res, list;
        return _regenerator.default.wrap(function _callee5$(_context5) {
          while (1) {
            switch (_context5.prev = _context5.next) {
              case 0:
                _context5.prev = 0;
                _context5.next = 3;
                return _api.cartApi.index({
                  ids: _this5.cartIds
                });
              case 3:
                res = _context5.sent;
                list = (res.list || []).filter(function (i) {
                  return !i.sell_out;
                });
                if (!(list.length === 0)) {
                  _context5.next = 9;
                  break;
                }
                uni.showToast({
                  title: '购物车为空',
                  icon: 'none'
                });
                setTimeout(function () {
                  return uni.navigateBack();
                }, 500);
                return _context5.abrupt("return");
              case 9:
                _this5.goodsList = list.map(function (item) {
                  return _objectSpread(_objectSpread({}, item), {}, {
                    goods_id: item.goods_id,
                    goods_name: item.goods_name,
                    goods_image: item.goods_image,
                    quantity: item.quantity,
                    unit_price: item.show_price || 0,
                    unit_name: item.unit_name,
                    attr_name: item.attr_name
                  });
                });
                _context5.next = 15;
                break;
              case 12:
                _context5.prev = 12;
                _context5.t0 = _context5["catch"](0);
                uni.showToast({
                  title: _context5.t0,
                  icon: 'none'
                });
              case 15:
              case "end":
                return _context5.stop();
            }
          }
        }, _callee5, null, [[0, 12]]);
      }))();
    },
    loadDirectGoods: function loadDirectGoods() {
      var _this6 = this;
      return (0, _asyncToGenerator2.default)( /*#__PURE__*/_regenerator.default.mark(function _callee6() {
        var _yield$import, goodsApi, res, goods, unitName, unitId, unit, attrName, attrIds, ids, names, source, skuPrice, skuStock, sortedIds, matched;
        return _regenerator.default.wrap(function _callee6$(_context6) {
          while (1) {
            switch (_context6.prev = _context6.next) {
              case 0:
                _context6.prev = 0;
                _context6.next = 3;
                return Promise.resolve().then(function () {
                  return _interopRequireWildcard(__webpack_require__(/*! ../../api */ 33));
                });
              case 3:
                _yield$import = _context6.sent;
                goodsApi = _yield$import.goodsApi;
                _context6.next = 7;
                return goodsApi.detail(_this6.directGoods.goods_id);
              case 7:
                res = _context6.sent;
                goods = res && res.goods ? res.goods : res;
                if (!(!goods || !goods.id)) {
                  _context6.next = 13;
                  break;
                }
                uni.showToast({
                  title: '商品不存在',
                  icon: 'none'
                });
                setTimeout(function () {
                  return uni.navigateBack();
                }, 500);
                return _context6.abrupt("return");
              case 13:
                unitName = '';
                unitId = _this6.directGoods.goods_unit;
                if (unitId && res.units) {
                  unit = res.units.find(function (u) {
                    return u.id == unitId;
                  });
                  if (unit) unitName = unit.name;
                }
                attrName = _this6.directGoods.attr_name || '';
                attrIds = _this6.directGoods.attr_ids;
                if (!attrName && attrIds) {
                  ids = ('' + attrIds).split('&').map(Number);
                  names = [];
                  source = res.attr_groups || res.attrs || [];
                  source.forEach(function (group) {
                    (group.values || []).forEach(function (v) {
                      var vid = v.attribute_id || v.id;
                      if (ids.includes(vid)) names.push(v.value_name || v.name || v);
                    });
                  });
                  attrName = names.join(' / ');
                }
                skuPrice = goods.show_price || goods.market_sell_price || 0;
                skuStock = goods.stock || 0;
                if (attrIds && res.attrs && res.attrs.length > 0) {
                  sortedIds = ('' + attrIds).split('&').map(Number).sort(function (a, b) {
                    return a - b;
                  }).join('&');
                  matched = res.attrs.find(function (a) {
                    if (!a.aux_value_ids || a.aux_value_ids.length === 0) return false;
                    var aSorted = (0, _toConsumableArray2.default)(a.aux_value_ids).sort(function (a, b) {
                      return a - b;
                    }).join('&');
                    return aSorted === sortedIds;
                  });
                  if (matched) {
                    skuPrice = matched.show_price || skuPrice;
                    skuStock = matched.stock || 0;
                  }
                }
                _this6.goodsList = [{
                  goods_id: goods.id,
                  goods_name: goods.goods_name,
                  goods_image: goods.goods_image,
                  quantity: _this6.directGoods.quantity,
                  unit_price: skuPrice,
                  goods_unit: unitId,
                  attr_ids: attrIds,
                  attr_name: attrName,
                  unit_name: unitName,
                  stock: skuStock
                }];
                _context6.next = 28;
                break;
              case 25:
                _context6.prev = 25;
                _context6.t0 = _context6["catch"](0);
                uni.showToast({
                  title: _context6.t0,
                  icon: 'none'
                });
              case 28:
              case "end":
                return _context6.stop();
            }
          }
        }, _callee6, null, [[0, 25]]);
      }))();
    },
    calcItemTotal: function calcItemTotal(item) {
      return ((item.unit_price || item.show_price || 0) * item.quantity).toFixed(2);
    },
    // ========== 提交订单 ==========
    submitOrder: function submitOrder() {
      var _this7 = this;
      return (0, _asyncToGenerator2.default)( /*#__PURE__*/_regenerator.default.mark(function _callee7() {
        var goods, orderData, res;
        return _regenerator.default.wrap(function _callee7$(_context7) {
          while (1) {
            switch (_context7.prev = _context7.next) {
              case 0:
                if (!_this7.submitting) {
                  _context7.next = 2;
                  break;
                }
                return _context7.abrupt("return");
              case 2:
                if (_this7.customer) {
                  _context7.next = 5;
                  break;
                }
                uni.showToast({
                  title: '请选择客户',
                  icon: 'none'
                });
                return _context7.abrupt("return");
              case 5:
                if (_this7.address) {
                  _context7.next = 8;
                  break;
                }
                uni.showToast({
                  title: '请填写收货地址',
                  icon: 'none'
                });
                return _context7.abrupt("return");
              case 8:
                _this7.submitting = true;
                _context7.prev = 9;
                goods = _this7.directGoods ? [{
                  goods_id: _this7.directGoods.goods_id,
                  quantity: _this7.directGoods.quantity,
                  attr_ids: _this7.directGoods.attr_ids,
                  goods_unit: _this7.directGoods.goods_unit
                }] : _this7.goodsList.map(function (item) {
                  return {
                    goods_id: item.goods_id,
                    quantity: item.quantity,
                    attr_ids: item.attr_ids || '',
                    goods_unit: item.goods_unit || 0
                  };
                }); // 构造下单参数
                orderData = {
                  customer_id: _this7.customer.id,
                  remark: _this7.remark,
                  goods: JSON.stringify(goods)
                }; // 地址：优先用 address_id，否则手动传
                if (_this7.address.id && _this7.address.id > 0) {
                  orderData.address_id = _this7.address.id;
                }
                orderData.receiver_name = _this7.address.first_connect_name || '';
                orderData.receiver_mobile = _this7.address.first_connect_mobile || '';
                orderData.receiver_address = _this7.address.connect_address || '';
                _context7.next = 18;
                return _api.orderApi.add(orderData);
              case 18:
                res = _context7.sent;
                uni.showToast({
                  title: '下单成功'
                });
                setTimeout(function () {
                  uni.redirectTo({
                    url: '/pages/order/detail?id=' + res.order.id
                  });
                }, 500);
                _context7.next = 26;
                break;
              case 23:
                _context7.prev = 23;
                _context7.t0 = _context7["catch"](9);
                uni.showToast({
                  title: typeof _context7.t0 === 'string' ? _context7.t0 : '提交失败',
                  icon: 'none'
                });
              case 26:
                _context7.prev = 26;
                _this7.submitting = false;
                return _context7.finish(26);
              case 29:
              case "end":
                return _context7.stop();
            }
          }
        }, _callee7, null, [[9, 23, 26, 29]]);
      }))();
    }
  }
};
exports.default = _default;
/* WEBPACK VAR INJECTION */}.call(this, __webpack_require__(/*! ./node_modules/@dcloudio/uni-mp-weixin/dist/index.js */ 2)["default"]))

/***/ }),

/***/ 191:
/*!******************************************************************************************************************************************************************************!*\
  !*** /Applications/phpstudy/WWW/2026/05-ERP-licheng/erp-admin-licheng/addons/kuerp/uniapp/pages/order/checkout.vue?vue&type=style&index=0&id=c64697c0&scoped=true&lang=css& ***!
  \******************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_css_loader_dist_cjs_js_ref_6_oneOf_1_1_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_6_oneOf_1_2_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_3_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_style_index_0_id_c64697c0_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/mini-css-extract-plugin/dist/loader.js??ref--6-oneOf-1-0!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/css-loader/dist/cjs.js??ref--6-oneOf-1-1!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--6-oneOf-1-2!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/postcss-loader/src??ref--6-oneOf-1-3!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../../../../../../../../HBuilderX.app/Contents/HBuilderX/plugins/uniapp-cli/node_modules/@dcloudio/webpack-uni-mp-loader/lib/style.js!./checkout.vue?vue&type=style&index=0&id=c64697c0&scoped=true&lang=css& */ 192);
/* harmony import */ var _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_css_loader_dist_cjs_js_ref_6_oneOf_1_1_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_6_oneOf_1_2_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_3_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_style_index_0_id_c64697c0_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_css_loader_dist_cjs_js_ref_6_oneOf_1_1_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_6_oneOf_1_2_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_3_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_style_index_0_id_c64697c0_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_css_loader_dist_cjs_js_ref_6_oneOf_1_1_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_6_oneOf_1_2_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_3_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_style_index_0_id_c64697c0_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_css_loader_dist_cjs_js_ref_6_oneOf_1_1_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_6_oneOf_1_2_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_3_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_style_index_0_id_c64697c0_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__[key]; }) }(__WEBPACK_IMPORT_KEY__));
 /* harmony default export */ __webpack_exports__["default"] = (_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_mini_css_extract_plugin_dist_loader_js_ref_6_oneOf_1_0_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_css_loader_dist_cjs_js_ref_6_oneOf_1_1_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_6_oneOf_1_2_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_postcss_loader_src_index_js_ref_6_oneOf_1_3_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_HBuilderX_app_Contents_HBuilderX_plugins_uniapp_cli_node_modules_dcloudio_webpack_uni_mp_loader_lib_style_js_checkout_vue_vue_type_style_index_0_id_c64697c0_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ }),

/***/ 192:
/*!**********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/mini-css-extract-plugin/dist/loader.js??ref--6-oneOf-1-0!./node_modules/css-loader/dist/cjs.js??ref--6-oneOf-1-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--6-oneOf-1-2!./node_modules/postcss-loader/src??ref--6-oneOf-1-3!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/webpack-uni-mp-loader/lib/style.js!/Applications/phpstudy/WWW/2026/05-ERP-licheng/erp-admin-licheng/addons/kuerp/uniapp/pages/order/checkout.vue?vue&type=style&index=0&id=c64697c0&scoped=true&lang=css& ***!
  \**********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// extracted by mini-css-extract-plugin
    if(false) { var cssReload; }
  

/***/ })

},[[185,"common/runtime","common/vendor"]]]);
//# sourceMappingURL=../../../.sourcemap/mp-weixin/pages/order/checkout.js.map