(window["webpackJsonp"] = window["webpackJsonp"] || []).push([["pages-order-detail"],{

/***/ "J3dy":
/*!****************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--13-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--16-0!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/using-components.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./src/pages/order/detail.vue?vue&type=script&lang=js& ***!
  \****************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
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
__webpack_require__(/*! core-js/modules/es.array.concat.js */ "ma9I");
__webpack_require__(/*! core-js/modules/es.number.constructor.js */ "qePV");
__webpack_require__(/*! core-js/modules/es.string.pad-start.js */ "TZCg");
var _api = __webpack_require__(/*! ../../api */ "Nlzp");
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

var STATUS_MAP = {
  pending_confirm: {
    text: '待确认',
    desc: '等待后台管理员确认订单',
    bg: 'linear-gradient(135deg, #E6A23C, #d4891c)',
    icon: 'clock'
  },
  confirmed: {
    text: '已确认',
    desc: '订单已确认，等待发货',
    bg: 'linear-gradient(135deg, #4A90D9, #357ABD)',
    icon: 'checkmark-circle'
  },
  shipped: {
    text: '已发货',
    desc: '商品已发出，请注意查收',
    bg: 'linear-gradient(135deg, #2c8cff, #1a6dcc)',
    icon: 'car'
  },
  received: {
    text: '已收货',
    desc: '商品已签收',
    bg: 'linear-gradient(135deg, #67C23A, #529b2e)',
    icon: 'checkmark-circle-fill'
  },
  completed: {
    text: '已完成',
    desc: '订单已完成',
    bg: 'linear-gradient(135deg, #67C23A, #529b2e)',
    icon: 'checkmark-circle-fill'
  },
  cancelled: {
    text: '已取消',
    desc: '订单已取消',
    bg: 'linear-gradient(135deg, #909399, #72767b)',
    icon: 'close-circle'
  },
  returned: {
    text: '已退货',
    desc: '订单已退货',
    bg: 'linear-gradient(135deg, #f56c6c, #d33b3b)',
    icon: 'close-circle'
  }
};
var _default = exports.default = {
  data: function data() {
    return {
      order: null,
      orderId: 0
    };
  },
  computed: {
    statusInfo: function statusInfo() {
      return STATUS_MAP[this.order && this.order.status] || {};
    }
  },
  onLoad: function onLoad(options) {
    this.orderId = Number(options.id) || 0;
    if (this.orderId) this.loadDetail();
  },
  onShow: function onShow() {
    if (this.orderId) this.loadDetail();
  },
  methods: {
    loadDetail: function loadDetail() {
      var _this = this;
      return (0, _asyncToGenerator2.default)(/*#__PURE__*/(0, _regenerator2.default)().m(function _callee() {
        var _t;
        return (0, _regenerator2.default)().w(function (_context) {
          while (1) switch (_context.p = _context.n) {
            case 0:
              _context.p = 0;
              _context.n = 1;
              return _api.orderApi.detail(_this.orderId);
            case 1:
              _this.order = _context.v;
              _context.n = 3;
              break;
            case 2:
              _context.p = 2;
              _t = _context.v;
              uni.showToast({
                title: _t,
                icon: 'none'
              });
            case 3:
              return _context.a(2);
          }
        }, _callee, null, [[0, 2]]);
      }))();
    },
    formatTime: function formatTime(t) {
      if (!t) return '';
      var d = new Date(t * 1000);
      var pad = function pad(n) {
        return String(n).padStart(2, '0');
      };
      return "".concat(d.getFullYear(), "-").concat(pad(d.getMonth() + 1), "-").concat(pad(d.getDate()), " ").concat(pad(d.getHours()), ":").concat(pad(d.getMinutes()));
    },
    goLogistics: function goLogistics() {
      uni.navigateTo({
        url: '/pages/order/logistics?order_id=' + this.orderId
      });
    },
    cancelOrder: function cancelOrder() {
      var _this2 = this;
      return (0, _asyncToGenerator2.default)(/*#__PURE__*/(0, _regenerator2.default)().m(function _callee2() {
        var _t2;
        return (0, _regenerator2.default)().w(function (_context2) {
          while (1) switch (_context2.p = _context2.n) {
            case 0:
              _context2.p = 0;
              _context2.n = 1;
              return _api.orderApi.cancel(_this2.orderId);
            case 1:
              uni.showToast({
                title: '订单已取消',
                icon: 'none'
              });
              _this2.loadDetail();
              _context2.n = 3;
              break;
            case 2:
              _context2.p = 2;
              _t2 = _context2.v;
              uni.showToast({
                title: _t2,
                icon: 'none'
              });
            case 3:
              return _context2.a(2);
          }
        }, _callee2, null, [[0, 2]]);
      }))();
    },
    confirmReceived: function confirmReceived() {
      var _this3 = this;
      return (0, _asyncToGenerator2.default)(/*#__PURE__*/(0, _regenerator2.default)().m(function _callee4() {
        return (0, _regenerator2.default)().w(function (_context4) {
          while (1) switch (_context4.n) {
            case 0:
              uni.showModal({
                title: '确认收货',
                content: '是否确认已收到商品？',
                success: function () {
                  var _success = (0, _asyncToGenerator2.default)(/*#__PURE__*/(0, _regenerator2.default)().m(function _callee3(res) {
                    var _t3;
                    return (0, _regenerator2.default)().w(function (_context3) {
                      while (1) switch (_context3.p = _context3.n) {
                        case 0:
                          if (!res.confirm) {
                            _context3.n = 4;
                            break;
                          }
                          _context3.p = 1;
                          _context3.n = 2;
                          return _api.orderApi.received(_this3.orderId);
                        case 2:
                          uni.showToast({
                            title: '确认收货成功'
                          });
                          _this3.loadDetail();
                          _context3.n = 4;
                          break;
                        case 3:
                          _context3.p = 3;
                          _t3 = _context3.v;
                          uni.showToast({
                            title: _t3,
                            icon: 'none'
                          });
                        case 4:
                          return _context3.a(2);
                      }
                    }, _callee3, null, [[1, 3]]);
                  }));
                  function success(_x) {
                    return _success.apply(this, arguments);
                  }
                  return success;
                }()
              });
            case 1:
              return _context4.a(2);
          }
        }, _callee4);
      }))();
    }
  }
};

/***/ }),

/***/ "Rn6I":
/*!************************************!*\
  !*** ./src/pages/order/detail.vue ***!
  \************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _detail_vue_vue_type_template_id_57d42baa_scoped_true___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./detail.vue?vue&type=template&id=57d42baa&scoped=true& */ "uBuK");
/* harmony import */ var _detail_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./detail.vue?vue&type=script&lang=js& */ "ztBm");
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _detail_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _detail_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__[key]; }) }(__WEBPACK_IMPORT_KEY__));
/* harmony import */ var _detail_vue_vue_type_style_index_0_id_57d42baa_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./detail.vue?vue&type=style&index=0&id=57d42baa&scoped=true&lang=css& */ "pU3H");
/* harmony import */ var _node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/runtime/componentNormalizer.js */ "8MXW");

var renderjs





/* normalize component */

var component = Object(_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__["default"])(
  _detail_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__["default"],
  _detail_vue_vue_type_template_id_57d42baa_scoped_true___WEBPACK_IMPORTED_MODULE_0__["render"],
  _detail_vue_vue_type_template_id_57d42baa_scoped_true___WEBPACK_IMPORTED_MODULE_0__["staticRenderFns"],
  false,
  null,
  "57d42baa",
  null,
  false,
  _detail_vue_vue_type_template_id_57d42baa_scoped_true___WEBPACK_IMPORTED_MODULE_0__["components"],
  renderjs
)

/* hot reload */
if (false) { var api; }
component.options.__file = "pages/order/detail.vue"
/* harmony default export */ __webpack_exports__["default"] = (component.exports);

/***/ }),

/***/ "TLbX":
/*!****************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/css-loader/dist/cjs.js??ref--7-oneOf-1-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--7-oneOf-1-2!./node_modules/postcss-loader/src??ref--7-oneOf-1-3!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./src/pages/order/detail.vue?vue&type=style&index=0&id=57d42baa&scoped=true&lang=css& ***!
  \****************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// Imports
var ___CSS_LOADER_API_IMPORT___ = __webpack_require__(/*! ../../../node_modules/css-loader/dist/runtime/api.js */ "JPst");
exports = ___CSS_LOADER_API_IMPORT___(false);
// Module
exports.push([module.i, "\n.page[data-v-57d42baa] { min-height: 100vh; background: #f5f7fa; padding-bottom: %?40?%;\n}\n\n/* 状态头 */\n.status-header[data-v-57d42baa] { display: flex; align-items: center; padding: %?40?% %?32?%; color: #fff;\n}\n.status-icon-wrap[data-v-57d42baa] { width: %?80?%; height: %?80?%; display: flex; align-items: center; justify-content: center; margin-right: %?20?%; flex-shrink: 0;\n}\n.status-content[data-v-57d42baa] { display: flex; flex-direction: column;\n}\n.status-title[data-v-57d42baa] { font-size: %?36?%; font-weight: 700;\n}\n.status-desc[data-v-57d42baa] { font-size: %?24?%; opacity: 0.85; margin-top: %?6?%;\n}\n.section-card[data-v-57d42baa] { background: #fff; margin: %?20?%; border-radius: %?16?%; padding: %?24?%;\n}\n.section-header[data-v-57d42baa] { display: flex; align-items: center; justify-content: space-between; margin-bottom: %?16?%;\n}\n.section-title[data-v-57d42baa] { font-size: %?28?%; font-weight: 600; color: #303133;\n}\n\n/* 收货地址 */\n.address-row[data-v-57d42baa] { display: flex; gap: %?16?%;\n}\n.address-info[data-v-57d42baa] { flex: 1;\n}\n.address-top[data-v-57d42baa] { display: flex; align-items: center; gap: %?12?%; margin-bottom: %?6?%;\n}\n.receiver-name[data-v-57d42baa] { font-size: %?30?%; font-weight: 600; color: #303133;\n}\n.receiver-mobile[data-v-57d42baa] { font-size: %?26?%; color: #606266;\n}\n.receiver-address[data-v-57d42baa] { font-size: %?26?%; color: #909399; line-height: 1.5;\n}\n\n/* 配送 */\n.delivery-item[data-v-57d42baa] { padding: %?8?% 0;\n}\n.delivery-type[data-v-57d42baa] { font-size: %?26?%; font-weight: 500; color: #303133; display: block;\n}\n.delivery-no[data-v-57d42baa], .delivery-company[data-v-57d42baa], .delivery-carrier[data-v-57d42baa], .delivery-time[data-v-57d42baa] { font-size: %?24?%; color: #909399; display: block; margin-top: %?4?%;\n}\n\n/* 商品 */\n.goods-item[data-v-57d42baa] { display: flex; align-items: center; padding: %?16?% 0;\n}\n.goods-item + .goods-item[data-v-57d42baa] { border-top: %?1?% solid #f5f7fa;\n}\n.goods-icon[data-v-57d42baa] { width: %?64?%; height: %?64?%; background: #f5f7fa; border-radius: %?8?%; display: flex; align-items: center; justify-content: center; margin-right: %?16?%; flex-shrink: 0; overflow: hidden;\n}\n.goods-detail[data-v-57d42baa] { flex: 1;\n}\n.goods-name[data-v-57d42baa] { font-size: %?26?%; color: #303133; display: block;\n}\n.goods-spec[data-v-57d42baa] { font-size: %?22?%; color: #c0c4cc; margin-top: %?2?%; display: block;\n}\n.goods-price[data-v-57d42baa] { font-size: %?22?%; color: #909399; margin-top: %?4?%; display: block;\n}\n.goods-amount[data-v-57d42baa] { font-size: %?26?%; color: #f56c6c; font-weight: 500;\n}\n\n/* 订单信息 */\n.info-list[data-v-57d42baa] {\n}\n.info-row[data-v-57d42baa] { display: flex; justify-content: space-between; padding: %?14?% 0;\n}\n.info-row + .info-row[data-v-57d42baa] { border-top: %?1?% solid #f5f7fa;\n}\n.info-label[data-v-57d42baa] { font-size: %?26?%; color: #909399; flex-shrink: 0;\n}\n.info-value[data-v-57d42baa] { font-size: %?26?%; color: #303133; text-align: right; max-width: 60%;\n}\n.price-value[data-v-57d42baa] { color: #f56c6c; font-weight: 700;\n}\n.remark-value[data-v-57d42baa] { color: #606266;\n}\n\n/* 操作 */\n.action-bar[data-v-57d42baa] { display: flex; justify-content: center; gap: %?20?%; padding: %?20?%;\n}\n", ""]);
// Exports
module.exports = exports;


/***/ }),

/***/ "ZL97":
/*!**************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--15-0!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/filter-modules-template.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/page-meta.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./src/pages/order/detail.vue?vue&type=template&id=57d42baa&scoped=true& ***!
  \**************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
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
    uIcon: __webpack_require__(/*! uview-ui/components/u-icon/u-icon.vue */ "+Guo").default,
    uButton: __webpack_require__(/*! uview-ui/components/u-button/u-button.vue */ "2a1d").default,
    uDivider: __webpack_require__(/*! uview-ui/components/u-divider/u-divider.vue */ "ShZs").default,
    uSkeleton: __webpack_require__(/*! uview-ui/components/u-skeleton/u-skeleton.vue */ "pGqa").default,
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
  return _vm.order
    ? _c(
        "v-uni-view",
        { staticClass: "page" },
        [
          _c(
            "v-uni-view",
            {
              staticClass: "status-header",
              style: { background: _vm.statusInfo.bg },
            },
            [
              _c(
                "v-uni-view",
                { staticClass: "status-icon-wrap" },
                [
                  _c("u-icon", {
                    attrs: {
                      name: _vm.statusInfo.icon,
                      size: "56",
                      color: "#fff",
                    },
                  }),
                ],
                1
              ),
              _c(
                "v-uni-view",
                { staticClass: "status-content" },
                [
                  _c("v-uni-text", { staticClass: "status-title" }, [
                    _vm._v(_vm._s(_vm.statusInfo.text)),
                  ]),
                  _c("v-uni-text", { staticClass: "status-desc" }, [
                    _vm._v(_vm._s(_vm.statusInfo.desc)),
                  ]),
                ],
                1
              ),
            ],
            1
          ),
          _vm.order.receiver_name
            ? _c(
                "v-uni-view",
                { staticClass: "section-card" },
                [
                  _c(
                    "v-uni-view",
                    { staticClass: "address-row" },
                    [
                      _c("u-icon", {
                        attrs: { name: "map", size: "36", color: "#4A90D9" },
                      }),
                      _c(
                        "v-uni-view",
                        { staticClass: "address-info" },
                        [
                          _c(
                            "v-uni-view",
                            { staticClass: "address-top" },
                            [
                              _c(
                                "v-uni-text",
                                { staticClass: "receiver-name" },
                                [_vm._v(_vm._s(_vm.order.receiver_name))]
                              ),
                              _c(
                                "v-uni-text",
                                { staticClass: "receiver-mobile" },
                                [_vm._v(_vm._s(_vm.order.receiver_mobile))]
                              ),
                            ],
                            1
                          ),
                          _c(
                            "v-uni-text",
                            { staticClass: "receiver-address" },
                            [
                              _vm._v(
                                _vm._s(_vm.order.receiver_province) +
                                  " " +
                                  _vm._s(_vm.order.receiver_city) +
                                  " " +
                                  _vm._s(_vm.order.receiver_district) +
                                  " " +
                                  _vm._s(_vm.order.receiver_address)
                              ),
                            ]
                          ),
                        ],
                        1
                      ),
                    ],
                    1
                  ),
                ],
                1
              )
            : _vm._e(),
          _vm.order.delivery && _vm.order.delivery.length > 0
            ? _c(
                "v-uni-view",
                { staticClass: "section-card" },
                [
                  _c(
                    "v-uni-view",
                    { staticClass: "section-header" },
                    [
                      _c("v-uni-text", { staticClass: "section-title" }, [
                        _vm._v("配送信息"),
                      ]),
                      _c(
                        "u-button",
                        {
                          attrs: {
                            type: "primary",
                            size: "mini",
                            plain: true,
                            hairline: true,
                            shape: "circle",
                          },
                          on: {
                            click: function ($event) {
                              arguments[0] = $event = _vm.$handleEvent($event)
                              _vm.goLogistics.apply(void 0, arguments)
                            },
                          },
                        },
                        [_vm._v("查看物流")]
                      ),
                    ],
                    1
                  ),
                  _c("u-divider", {
                    attrs: { "half-width": "20", hairline: true },
                  }),
                  _vm._l(_vm.order.delivery, function (d) {
                    return _c(
                      "v-uni-view",
                      { key: d.id, staticClass: "delivery-item" },
                      [
                        _c("v-uni-text", { staticClass: "delivery-type" }, [
                          _vm._v(
                            _vm._s(
                              d.delivery_type == "logistics"
                                ? "物流配送"
                                : "专业配送"
                            )
                          ),
                        ]),
                        d.delivery_no
                          ? _c("v-uni-text", { staticClass: "delivery-no" }, [
                              _vm._v("单号: " + _vm._s(d.delivery_no)),
                            ])
                          : _vm._e(),
                        d.express_company
                          ? _c(
                              "v-uni-text",
                              { staticClass: "delivery-company" },
                              [
                                _vm._v(
                                  _vm._s(d.express_company) +
                                    " " +
                                    _vm._s(d.express_no)
                                ),
                              ]
                            )
                          : _vm._e(),
                        d.carrier_name
                          ? _c(
                              "v-uni-text",
                              { staticClass: "delivery-carrier" },
                              [
                                _vm._v(
                                  "配送员: " +
                                    _vm._s(d.carrier_name) +
                                    " " +
                                    _vm._s(d.carrier_mobile)
                                ),
                              ]
                            )
                          : _vm._e(),
                        d.shipped_time
                          ? _c("v-uni-text", { staticClass: "delivery-time" }, [
                              _vm._v(
                                "发货时间: " +
                                  _vm._s(_vm.formatTime(d.shipped_time))
                              ),
                            ])
                          : _vm._e(),
                      ],
                      1
                    )
                  }),
                ],
                2
              )
            : _vm._e(),
          _c(
            "v-uni-view",
            { staticClass: "section-card" },
            [
              _c(
                "v-uni-view",
                { staticClass: "section-header" },
                [
                  _c("v-uni-text", { staticClass: "section-title" }, [
                    _vm._v("商品信息"),
                  ]),
                ],
                1
              ),
              _c("u-divider", {
                attrs: { "half-width": "20", hairline: true },
              }),
              _vm._l(_vm.order.goods || [], function (oi) {
                return _c(
                  "v-uni-view",
                  { key: oi.id, staticClass: "goods-item" },
                  [
                    _c(
                      "v-uni-view",
                      { staticClass: "goods-icon" },
                      [
                        oi.goods_image
                          ? _c("v-uni-image", {
                              staticStyle: {
                                width: "100%",
                                height: "100%",
                                "border-radius": "8rpx",
                              },
                              attrs: {
                                src: _vm.$img(oi.goods_image),
                                mode: "aspectFill",
                              },
                            })
                          : _c("u-icon", {
                              attrs: {
                                name: "shopping-cart",
                                size: "28",
                                color: "#c0c4cc",
                              },
                            }),
                      ],
                      1
                    ),
                    _c(
                      "v-uni-view",
                      { staticClass: "goods-detail" },
                      [
                        _c("v-uni-text", { staticClass: "goods-name" }, [
                          _vm._v(_vm._s(oi.goods_name)),
                        ]),
                        oi.attr_name
                          ? _c("v-uni-text", { staticClass: "goods-spec" }, [
                              _vm._v("规格: " + _vm._s(oi.attr_name)),
                            ])
                          : _vm._e(),
                        oi.unit_name
                          ? _c("v-uni-text", { staticClass: "goods-spec" }, [
                              _vm._v("单位: " + _vm._s(oi.unit_name)),
                            ])
                          : _vm._e(),
                        _c("v-uni-text", { staticClass: "goods-price" }, [
                          _vm._v(
                            "¥" +
                              _vm._s(oi.unit_price) +
                              " × " +
                              _vm._s(oi.quantity)
                          ),
                        ]),
                      ],
                      1
                    ),
                    _c("v-uni-text", { staticClass: "goods-amount" }, [
                      _vm._v("¥" + _vm._s(oi.all_money)),
                    ]),
                  ],
                  1
                )
              }),
            ],
            2
          ),
          _c(
            "v-uni-view",
            { staticClass: "section-card" },
            [
              _c(
                "v-uni-view",
                { staticClass: "section-header" },
                [
                  _c("v-uni-text", { staticClass: "section-title" }, [
                    _vm._v("订单信息"),
                  ]),
                ],
                1
              ),
              _c("u-divider", {
                attrs: { "half-width": "20", hairline: true },
              }),
              _c(
                "v-uni-view",
                { staticClass: "info-list" },
                [
                  _c(
                    "v-uni-view",
                    { staticClass: "info-row" },
                    [
                      _c("v-uni-text", { staticClass: "info-label" }, [
                        _vm._v("订单编号"),
                      ]),
                      _c("v-uni-text", { staticClass: "info-value" }, [
                        _vm._v(_vm._s(_vm.order.order_no)),
                      ]),
                    ],
                    1
                  ),
                  _c(
                    "v-uni-view",
                    { staticClass: "info-row" },
                    [
                      _c("v-uni-text", { staticClass: "info-label" }, [
                        _vm._v("配送方式"),
                      ]),
                      _c("v-uni-text", { staticClass: "info-value" }, [
                        _vm._v(
                          _vm._s(
                            _vm.order.delivery_type == "logistics"
                              ? "物流配送"
                              : "专业配送"
                          )
                        ),
                      ]),
                    ],
                    1
                  ),
                  _c(
                    "v-uni-view",
                    { staticClass: "info-row" },
                    [
                      _c("v-uni-text", { staticClass: "info-label" }, [
                        _vm._v("商品金额"),
                      ]),
                      _c(
                        "v-uni-text",
                        { staticClass: "info-value price-value" },
                        [_vm._v("¥" + _vm._s(_vm.order.total_amount))]
                      ),
                    ],
                    1
                  ),
                  _vm.order.preferential > 0
                    ? _c(
                        "v-uni-view",
                        { staticClass: "info-row" },
                        [
                          _c("v-uni-text", { staticClass: "info-label" }, [
                            _vm._v("优惠"),
                          ]),
                          _c(
                            "v-uni-text",
                            { staticClass: "info-value price-value" },
                            [_vm._v("-¥" + _vm._s(_vm.order.preferential))]
                          ),
                        ],
                        1
                      )
                    : _vm._e(),
                  _c(
                    "v-uni-view",
                    { staticClass: "info-row" },
                    [
                      _c("v-uni-text", { staticClass: "info-label" }, [
                        _vm._v("实付金额"),
                      ]),
                      _c(
                        "v-uni-text",
                        { staticClass: "info-value price-value" },
                        [
                          _vm._v(
                            "¥" +
                              _vm._s(
                                _vm.order.payable_amount ||
                                  _vm.order.total_amount
                              )
                          ),
                        ]
                      ),
                    ],
                    1
                  ),
                  _c(
                    "v-uni-view",
                    { staticClass: "info-row" },
                    [
                      _c("v-uni-text", { staticClass: "info-label" }, [
                        _vm._v("下单时间"),
                      ]),
                      _c("v-uni-text", { staticClass: "info-value" }, [
                        _vm._v(_vm._s(_vm.formatTime(_vm.order.createdtime))),
                      ]),
                    ],
                    1
                  ),
                  _vm.order.confirm_time
                    ? _c(
                        "v-uni-view",
                        { staticClass: "info-row" },
                        [
                          _c("v-uni-text", { staticClass: "info-label" }, [
                            _vm._v("确认时间"),
                          ]),
                          _c("v-uni-text", { staticClass: "info-value" }, [
                            _vm._v(
                              _vm._s(_vm.formatTime(_vm.order.confirm_time))
                            ),
                          ]),
                        ],
                        1
                      )
                    : _vm._e(),
                  _vm.order.complete_time
                    ? _c(
                        "v-uni-view",
                        { staticClass: "info-row" },
                        [
                          _c("v-uni-text", { staticClass: "info-label" }, [
                            _vm._v("完成时间"),
                          ]),
                          _c("v-uni-text", { staticClass: "info-value" }, [
                            _vm._v(
                              _vm._s(_vm.formatTime(_vm.order.complete_time))
                            ),
                          ]),
                        ],
                        1
                      )
                    : _vm._e(),
                  _vm.order.sales_code
                    ? _c(
                        "v-uni-view",
                        { staticClass: "info-row" },
                        [
                          _c("v-uni-text", { staticClass: "info-label" }, [
                            _vm._v("销货单号"),
                          ]),
                          _c("v-uni-text", { staticClass: "info-value" }, [
                            _vm._v(_vm._s(_vm.order.sales_code)),
                          ]),
                        ],
                        1
                      )
                    : _vm._e(),
                  _vm.order.remark
                    ? _c(
                        "v-uni-view",
                        { staticClass: "info-row" },
                        [
                          _c("v-uni-text", { staticClass: "info-label" }, [
                            _vm._v("备注"),
                          ]),
                          _c(
                            "v-uni-text",
                            { staticClass: "info-value remark-value" },
                            [_vm._v(_vm._s(_vm.order.remark))]
                          ),
                        ],
                        1
                      )
                    : _vm._e(),
                ],
                1
              ),
            ],
            1
          ),
          _c(
            "v-uni-view",
            { staticClass: "action-bar" },
            [
              _vm.order.status === "pending_confirm"
                ? _c(
                    "u-button",
                    {
                      attrs: {
                        type: "info",
                        plain: true,
                        hairline: true,
                        shape: "circle",
                      },
                      on: {
                        click: function ($event) {
                          arguments[0] = $event = _vm.$handleEvent($event)
                          _vm.cancelOrder.apply(void 0, arguments)
                        },
                      },
                    },
                    [_vm._v("取消订单")]
                  )
                : _vm._e(),
              _vm.order.status === "shipped"
                ? _c(
                    "u-button",
                    {
                      attrs: { type: "success", shape: "circle" },
                      on: {
                        click: function ($event) {
                          arguments[0] = $event = _vm.$handleEvent($event)
                          _vm.confirmReceived.apply(void 0, arguments)
                        },
                      },
                    },
                    [_vm._v("确认收货")]
                  )
                : _vm._e(),
            ],
            1
          ),
        ],
        1
      )
    : _c(
        "v-uni-view",
        { staticClass: "page" },
        [
          _c("u-skeleton", {
            attrs: { rows: "5", loading: true, title: false },
          }),
        ],
        1
      )
}
var recyclableRender = false
var staticRenderFns = []
render._withStripped = true



/***/ }),

/***/ "lexu":
/*!***********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/@dcloudio/vue-cli-plugin-uni/packages/h5-vue-style-loader??ref--7-oneOf-1-0!./node_modules/css-loader/dist/cjs.js??ref--7-oneOf-1-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--7-oneOf-1-2!./node_modules/postcss-loader/src??ref--7-oneOf-1-3!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./src/pages/order/detail.vue?vue&type=style&index=0&id=57d42baa&scoped=true&lang=css& ***!
  \***********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// style-loader: Adds some css to the DOM by adding a <style> tag

// load the styles
var content = __webpack_require__(/*! !../../../node_modules/css-loader/dist/cjs.js??ref--7-oneOf-1-1!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--7-oneOf-1-2!../../../node_modules/postcss-loader/src??ref--7-oneOf-1-3!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./detail.vue?vue&type=style&index=0&id=57d42baa&scoped=true&lang=css& */ "TLbX");
if(content.__esModule) content = content.default;
if(typeof content === 'string') content = [[module.i, content, '']];
if(content.locals) module.exports = content.locals;
// add the styles to the DOM
var add = __webpack_require__(/*! ../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/h5-vue-style-loader/lib/addStylesClient.js */ "TwZa").default
var update = add("865e2bc6", content, false, {"sourceMap":false,"shadowMode":false});
// Hot Module Replacement
if(false) {}

/***/ }),

/***/ "pU3H":
/*!*********************************************************************************************!*\
  !*** ./src/pages/order/detail.vue?vue&type=style&index=0&id=57d42baa&scoped=true&lang=css& ***!
  \*********************************************************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_7_oneOf_1_2_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_3_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_style_index_0_id_57d42baa_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/h5-vue-style-loader??ref--7-oneOf-1-0!../../../node_modules/css-loader/dist/cjs.js??ref--7-oneOf-1-1!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--7-oneOf-1-2!../../../node_modules/postcss-loader/src??ref--7-oneOf-1-3!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./detail.vue?vue&type=style&index=0&id=57d42baa&scoped=true&lang=css& */ "lexu");
/* harmony import */ var _node_modules_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_7_oneOf_1_2_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_3_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_style_index_0_id_57d42baa_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_7_oneOf_1_2_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_3_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_style_index_0_id_57d42baa_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _node_modules_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_7_oneOf_1_2_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_3_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_style_index_0_id_57d42baa_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _node_modules_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_7_oneOf_1_2_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_3_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_style_index_0_id_57d42baa_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0__[key]; }) }(__WEBPACK_IMPORT_KEY__));
 /* harmony default export */ __webpack_exports__["default"] = (_node_modules_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_7_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_7_oneOf_1_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_7_oneOf_1_2_node_modules_postcss_loader_src_index_js_ref_7_oneOf_1_3_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_style_index_0_id_57d42baa_scoped_true_lang_css___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ }),

/***/ "uBuK":
/*!*******************************************************************************!*\
  !*** ./src/pages/order/detail.vue?vue&type=template&id=57d42baa&scoped=true& ***!
  \*******************************************************************************/
/*! exports provided: render, staticRenderFns, recyclableRender, components */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_template_id_57d42baa_scoped_true___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--15-0!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/filter-modules-template.js!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/page-meta.js!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./detail.vue?vue&type=template&id=57d42baa&scoped=true& */ "ZL97");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "render", function() { return _node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_template_id_57d42baa_scoped_true___WEBPACK_IMPORTED_MODULE_0__["render"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "staticRenderFns", function() { return _node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_template_id_57d42baa_scoped_true___WEBPACK_IMPORTED_MODULE_0__["staticRenderFns"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "recyclableRender", function() { return _node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_template_id_57d42baa_scoped_true___WEBPACK_IMPORTED_MODULE_0__["recyclableRender"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "components", function() { return _node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_template_id_57d42baa_scoped_true___WEBPACK_IMPORTED_MODULE_0__["components"]; });



/***/ }),

/***/ "ztBm":
/*!*************************************************************!*\
  !*** ./src/pages/order/detail.vue?vue&type=script&lang=js& ***!
  \*************************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--13-1!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--16-0!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/using-components.js!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../../../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./detail.vue?vue&type=script&lang=js& */ "J3dy");
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_babel_loader_lib_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _node_modules_babel_loader_lib_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _node_modules_babel_loader_lib_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__[key]; }) }(__WEBPACK_IMPORT_KEY__));
 /* harmony default export */ __webpack_exports__["default"] = (_node_modules_babel_loader_lib_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_detail_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ })

}]);