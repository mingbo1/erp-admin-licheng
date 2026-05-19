/******/ (function(modules) { // webpackBootstrap
/******/ 	// install a JSONP callback for chunk loading
/******/ 	function webpackJsonpCallback(data) {
/******/ 		var chunkIds = data[0];
/******/ 		var moreModules = data[1];
/******/ 		var executeModules = data[2];
/******/
/******/ 		// add "moreModules" to the modules object,
/******/ 		// then flag all "chunkIds" as loaded and fire callback
/******/ 		var moduleId, chunkId, i = 0, resolves = [];
/******/ 		for(;i < chunkIds.length; i++) {
/******/ 			chunkId = chunkIds[i];
/******/ 			if(Object.prototype.hasOwnProperty.call(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 				resolves.push(installedChunks[chunkId][0]);
/******/ 			}
/******/ 			installedChunks[chunkId] = 0;
/******/ 		}
/******/ 		for(moduleId in moreModules) {
/******/ 			if(Object.prototype.hasOwnProperty.call(moreModules, moduleId)) {
/******/ 				modules[moduleId] = moreModules[moduleId];
/******/ 			}
/******/ 		}
/******/ 		if(parentJsonpFunction) parentJsonpFunction(data);
/******/
/******/ 		while(resolves.length) {
/******/ 			resolves.shift()();
/******/ 		}
/******/
/******/ 		// add entry modules from loaded chunk to deferred list
/******/ 		deferredModules.push.apply(deferredModules, executeModules || []);
/******/
/******/ 		// run deferred modules when all chunks ready
/******/ 		return checkDeferredModules();
/******/ 	};
/******/ 	function checkDeferredModules() {
/******/ 		var result;
/******/ 		for(var i = 0; i < deferredModules.length; i++) {
/******/ 			var deferredModule = deferredModules[i];
/******/ 			var fulfilled = true;
/******/ 			for(var j = 1; j < deferredModule.length; j++) {
/******/ 				var depId = deferredModule[j];
/******/ 				if(installedChunks[depId] !== 0) fulfilled = false;
/******/ 			}
/******/ 			if(fulfilled) {
/******/ 				deferredModules.splice(i--, 1);
/******/ 				result = __webpack_require__(__webpack_require__.s = deferredModule[0]);
/******/ 			}
/******/ 		}
/******/
/******/ 		return result;
/******/ 	}
/******/
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// object to store loaded and loading chunks
/******/ 	// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 	// Promise = chunk loading, 0 = chunk loaded
/******/ 	var installedChunks = {
/******/ 		"index": 0
/******/ 	};
/******/
/******/ 	var deferredModules = [];
/******/
/******/ 	// script path function
/******/ 	function jsonpScriptSrc(chunkId) {
/******/ 		return __webpack_require__.p + "static/js/" + ({"pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764":"pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764","pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9":"pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9","pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-order-checkout~pages-~d38250cb":"pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-order-checkout~pages-~d38250cb","pages-address-edit~pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~page~a72566e0":"pages-address-edit~pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~page~a72566e0","pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-logistics":"pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-logistics","pages-address-list~pages-cart-cart~pages-index-index~pages-order-list~pages-order-logistics":"pages-address-list~pages-cart-cart~pages-index-index~pages-order-list~pages-order-logistics","pages-address-list~pages-goods-detail~pages-order-detail~pages-order-logistics":"pages-address-list~pages-goods-detail~pages-order-detail~pages-order-logistics","pages-address-list":"pages-address-list","pages-address-edit~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-detail~page~59b4d8f3":"pages-address-edit~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-detail~page~59b4d8f3","pages-address-edit~pages-user-login~pages-user-profile~pages-user-register":"pages-address-edit~pages-user-login~pages-user-profile~pages-user-register","pages-address-edit~pages-goods-detail":"pages-address-edit~pages-goods-detail","pages-address-edit":"pages-address-edit","pages-goods-detail~pages-order-checkout~pages-order-detail~pages-order-logistics":"pages-goods-detail~pages-order-checkout~pages-order-detail~pages-order-logistics","pages-cart-cart~pages-goods-detail":"pages-cart-cart~pages-goods-detail","pages-goods-detail":"pages-goods-detail","pages-order-checkout":"pages-order-checkout","pages-user-login":"pages-user-login","pages-user-profile":"pages-user-profile","pages-user-register":"pages-user-register","pages-index-index~pages-order-list":"pages-index-index~pages-order-list","pages-order-list":"pages-order-list","pages-order-detail":"pages-order-detail","pages-user-info":"pages-user-info","pages-cart-cart":"pages-cart-cart","pages-index-index":"pages-index-index","pages-order-logistics":"pages-order-logistics"}[chunkId]||chunkId) + ".js"
/******/ 	}
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/ 	// This file contains only the entry chunk.
/******/ 	// The chunk loading function for additional chunks
/******/ 	__webpack_require__.e = function requireEnsure(chunkId) {
/******/ 		var promises = [];
/******/
/******/
/******/ 		// JSONP chunk loading for javascript
/******/
/******/ 		var installedChunkData = installedChunks[chunkId];
/******/ 		if(installedChunkData !== 0) { // 0 means "already installed".
/******/
/******/ 			// a Promise means "currently loading".
/******/ 			if(installedChunkData) {
/******/ 				promises.push(installedChunkData[2]);
/******/ 			} else {
/******/ 				// setup Promise in chunk cache
/******/ 				var promise = new Promise(function(resolve, reject) {
/******/ 					installedChunkData = installedChunks[chunkId] = [resolve, reject];
/******/ 				});
/******/ 				promises.push(installedChunkData[2] = promise);
/******/
/******/ 				// start chunk loading
/******/ 				var script = document.createElement('script');
/******/ 				var onScriptComplete;
/******/
/******/ 				script.charset = 'utf-8';
/******/ 				script.timeout = 120;
/******/ 				if (__webpack_require__.nc) {
/******/ 					script.setAttribute("nonce", __webpack_require__.nc);
/******/ 				}
/******/ 				script.src = jsonpScriptSrc(chunkId);
/******/
/******/ 				// create error before stack unwound to get useful stacktrace later
/******/ 				var error = new Error();
/******/ 				onScriptComplete = function (event) {
/******/ 					// avoid mem leaks in IE.
/******/ 					script.onerror = script.onload = null;
/******/ 					clearTimeout(timeout);
/******/ 					var chunk = installedChunks[chunkId];
/******/ 					if(chunk !== 0) {
/******/ 						if(chunk) {
/******/ 							var errorType = event && (event.type === 'load' ? 'missing' : event.type);
/******/ 							var realSrc = event && event.target && event.target.src;
/******/ 							error.message = 'Loading chunk ' + chunkId + ' failed.\n(' + errorType + ': ' + realSrc + ')';
/******/ 							error.name = 'ChunkLoadError';
/******/ 							error.type = errorType;
/******/ 							error.request = realSrc;
/******/ 							chunk[1](error);
/******/ 						}
/******/ 						installedChunks[chunkId] = undefined;
/******/ 					}
/******/ 				};
/******/ 				var timeout = setTimeout(function(){
/******/ 					onScriptComplete({ type: 'timeout', target: script });
/******/ 				}, 120000);
/******/ 				script.onerror = script.onload = onScriptComplete;
/******/ 				document.head.appendChild(script);
/******/ 			}
/******/ 		}
/******/ 		return Promise.all(promises);
/******/ 	};
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "/";
/******/
/******/ 	// on error function for async loading
/******/ 	__webpack_require__.oe = function(err) { console.error(err); throw err; };
/******/
/******/ 	var jsonpArray = window["webpackJsonp"] = window["webpackJsonp"] || [];
/******/ 	var oldJsonpFunction = jsonpArray.push.bind(jsonpArray);
/******/ 	jsonpArray.push = webpackJsonpCallback;
/******/ 	jsonpArray = jsonpArray.slice();
/******/ 	for(var i = 0; i < jsonpArray.length; i++) webpackJsonpCallback(jsonpArray[i]);
/******/ 	var parentJsonpFunction = oldJsonpFunction;
/******/
/******/
/******/ 	// add entry module to deferred list
/******/ 	deferredModules.push([0,"chunk-vendors"]);
/******/ 	// run deferred modules when ready
/******/ 	return checkDeferredModules();
/******/ })
/************************************************************************/
/******/ ({

/***/ 0:
/*!***************************!*\
  !*** multi ./src/main.js ***!
  \***************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /Applications/phpstudy/WWW/2026/05-ERP-licheng/erp-admin-licheng/addons/kuerp/uniapp/src/main.js */"Vtdi");


/***/ }),

/***/ "20nU":
/*!***********************!*\
  !*** ./src/config.js ***!
  \***********************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


__webpack_require__(/*! core-js/modules/es.object.define-property.js */ "eoL8");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
// API 基础配置
var _default = exports.default = {
  // 开发环境使用空字符串（通过 vue.config.js proxy 代理到后端）
  // 小程序端需要配置为正式域名
  baseUrl: '',
  // baseUrl: 'http://erp.licheng.test',

  // 图片域名前缀（本地图片存储路径为相对路径，需拼接完整域名）
  imgBaseUrl: 'http://erp.licheng.test'
  // imgBaseUrl: 'http://erp.licheng.test',
};

/***/ }),

/***/ "9Lmf":
/*!*************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--15-0!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/filter-modules-template.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/page-meta.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/wrap-loader??ref--18!./src/App.vue?vue&type=template&id=472cff63& ***!
  \*************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
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
  return _c("App", { attrs: { keepAliveInclude: _vm.keepAliveInclude } })
}
var recyclableRender = false
var staticRenderFns = []
render._withStripped = true



/***/ }),

/***/ "DG18":
/*!****************************!*\
  !*** ./src/api/request.js ***!
  \****************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


__webpack_require__(/*! core-js/modules/es.object.define-property.js */ "eoL8");
var _interopRequireDefault = __webpack_require__(/*! ./node_modules/@babel/runtime/helpers/interopRequireDefault.js */ "TqRt").default;
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;
exports.get = get;
exports.getToken = getToken;
exports.post = post;
exports.setToken = setToken;
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "07d7");
var _regenerator2 = _interopRequireDefault(__webpack_require__(/*! ./node_modules/@babel/runtime/helpers/esm/regenerator.js */ "wU9g"));
var _asyncToGenerator2 = _interopRequireDefault(__webpack_require__(/*! ./node_modules/@babel/runtime/helpers/esm/asyncToGenerator.js */ "HaE+"));
var _config = _interopRequireDefault(__webpack_require__(/*! ../config */ "20nU"));
// token 引用，login 后设置
var token = uni.getStorageSync('token') || '';
function setToken(val) {
  token = val;
  if (val) {
    uni.setStorageSync('token', val);
  } else {
    uni.removeStorageSync('token');
  }
}
function getToken() {
  if (!token) {
    token = uni.getStorageSync('token') || '';
  }
  return token;
}
function request(_x) {
  return _request.apply(this, arguments);
}
function _request() {
  _request = (0, _asyncToGenerator2.default)(/*#__PURE__*/(0, _regenerator2.default)().m(function _callee(url) {
    var options,
      method,
      data,
      t,
      _args = arguments;
    return (0, _regenerator2.default)().w(function (_context) {
      while (1) switch (_context.n) {
        case 0:
          options = _args.length > 1 && _args[1] !== undefined ? _args[1] : {};
          method = options.method || 'GET';
          data = options.data || {}; // 自动附加 token
          t = getToken();
          if (t) {
            data.token = t;
          }
          return _context.a(2, new Promise(function (resolve, reject) {
            uni.request({
              url: _config.default.baseUrl + url,
              method: method,
              data: data,
              header: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Token': t
              },
              success: function success(res) {
                if (res.data.code === 0) {
                  resolve(res.data.data);
                } else {
                  var msg = res.data.msg || '请求失败';
                  // 未登录 → 触发 App 层重新登录
                  if (msg === '请先登录') {
                    setToken('');
                    uni.showToast({
                      title: '登录已过期',
                      icon: 'none'
                    });
                    setTimeout(function () {
                      return uni.reLaunch({
                        url: '/pages/user/login'
                      });
                    }, 1000);
                  }
                  reject(msg);
                }
              },
              fail: function fail() {
                reject('网络异常，请稍后重试');
              }
            });
          }));
      }
    }, _callee);
  }));
  return _request.apply(this, arguments);
}
function get(url, data) {
  return request(url, {
    method: 'GET',
    data: data
  });
}
function post(url, data) {
  return request(url, {
    method: 'POST',
    data: data
  });
}
var _default = exports.default = request;

/***/ }),

/***/ "E77e":
/*!****************************************************!*\
  !*** ./src/App.vue?vue&type=template&id=472cff63& ***!
  \****************************************************/
/*! exports provided: render, staticRenderFns, recyclableRender, components */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_template_id_472cff63___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/templateLoader.js??vue-loader-options!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--15-0!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/filter-modules-template.js!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/page-meta.js!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/wrap-loader??ref--18!./App.vue?vue&type=template&id=472cff63& */ "9Lmf");
/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "render", function() { return _node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_template_id_472cff63___WEBPACK_IMPORTED_MODULE_0__["render"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "staticRenderFns", function() { return _node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_template_id_472cff63___WEBPACK_IMPORTED_MODULE_0__["staticRenderFns"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "recyclableRender", function() { return _node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_template_id_472cff63___WEBPACK_IMPORTED_MODULE_0__["recyclableRender"]; });

/* harmony reexport (safe) */ __webpack_require__.d(__webpack_exports__, "components", function() { return _node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_templateLoader_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_15_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_filter_modules_template_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_page_meta_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_template_id_472cff63___WEBPACK_IMPORTED_MODULE_0__["components"]; });



/***/ }),

/***/ "GSuk":
/*!***********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/@dcloudio/vue-cli-plugin-uni/packages/h5-vue-style-loader??ref--9-oneOf-1-0!./node_modules/css-loader/dist/cjs.js??ref--9-oneOf-1-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!./node_modules/postcss-loader/src??ref--9-oneOf-1-3!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/wrap-loader??ref--18!./src/App.vue?vue&type=style&index=0&lang=scss& ***!
  \***********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// style-loader: Adds some css to the DOM by adding a <style> tag

// load the styles
var content = __webpack_require__(/*! !../node_modules/css-loader/dist/cjs.js??ref--9-oneOf-1-1!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!../node_modules/postcss-loader/src??ref--9-oneOf-1-3!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/wrap-loader??ref--18!./App.vue?vue&type=style&index=0&lang=scss& */ "yEaF");
if(content.__esModule) content = content.default;
if(typeof content === 'string') content = [[module.i, content, '']];
if(content.locals) module.exports = content.locals;
// add the styles to the DOM
var add = __webpack_require__(/*! ../node_modules/@dcloudio/vue-cli-plugin-uni/packages/h5-vue-style-loader/lib/addStylesClient.js */ "TwZa").default
var update = add("9efe5c22", content, false, {"sourceMap":false,"shadowMode":false});
// Hot Module Replacement
if(false) {}

/***/ }),

/***/ "I77X":
/*!**********************************************!*\
  !*** ./src/App.vue?vue&type=script&lang=js& ***!
  \**********************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../node_modules/babel-loader/lib!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--13-1!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--16-0!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/using-components.js!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/wrap-loader??ref--18!./App.vue?vue&type=script&lang=js& */ "O07N");
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_babel_loader_lib_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _node_modules_babel_loader_lib_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _node_modules_babel_loader_lib_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0__[key]; }) }(__WEBPACK_IMPORT_KEY__));
 /* harmony default export */ __webpack_exports__["default"] = (_node_modules_babel_loader_lib_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_13_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_16_0_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_uni_app_loader_using_components_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ }),

/***/ "Nlzp":
/*!**************************!*\
  !*** ./src/api/index.js ***!
  \**************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


__webpack_require__(/*! core-js/modules/es.object.define-property.js */ "eoL8");
Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.userApi = exports.orderApi = exports.goodsApi = exports.deliveryApi = exports.cartApi = exports.bannerApi = exports.addressApi = void 0;
var _request = __webpack_require__(/*! ./request */ "DG18");
// 用户
var userApi = exports.userApi = {
  login: function login(code) {
    return (0, _request.post)('/addons/kuerp/api.user/login', {
      code: code
    });
  },
  loginByPwd: function loginByPwd(mobile, password) {
    return (0, _request.post)('/addons/kuerp/api.user/loginByPassword', {
      mobile: mobile,
      password: password
    });
  },
  register: function register(data) {
    return (0, _request.post)('/addons/kuerp/api.user/register', data);
  },
  sendSmsCode: function sendSmsCode(mobile) {
    return (0, _request.post)('/addons/kuerp/api.user/sendSmsCode', {
      mobile: mobile
    });
  },
  info: function info() {
    return (0, _request.get)('/addons/kuerp/api.user/info');
  },
  update: function update(data) {
    return (0, _request.post)('/addons/kuerp/api.user/update', data);
  }
};

// 轮播
var bannerApi = exports.bannerApi = {
  index: function index() {
    return (0, _request.get)('/addons/kuerp/api.banner/index');
  }
};

// 商品
var goodsApi = exports.goodsApi = {
  index: function index(params) {
    return (0, _request.get)('/addons/kuerp/api.goods/index', params);
  },
  detail: function detail(id) {
    return (0, _request.get)('/addons/kuerp/api.goods/detail', {
      id: id
    });
  },
  categories: function categories() {
    return (0, _request.get)('/addons/kuerp/api.goods/categories');
  }
};

// 购物车
var cartApi = exports.cartApi = {
  index: function index() {
    return (0, _request.get)('/addons/kuerp/api.cart/index');
  },
  add: function add(goods_id, quantity, attr_ids, goods_unit) {
    return (0, _request.post)('/addons/kuerp/api.cart/add', {
      goods_id: goods_id,
      quantity: quantity,
      attr_ids: attr_ids,
      goods_unit: goods_unit
    });
  },
  edit: function edit(id, quantity) {
    return (0, _request.post)('/addons/kuerp/api.cart/edit', {
      id: id,
      quantity: quantity
    });
  },
  delete: function _delete(id) {
    return (0, _request.post)('/addons/kuerp/api.cart/delete', {
      id: id
    });
  },
  clear: function clear() {
    return (0, _request.post)('/addons/kuerp/api.cart/clear');
  },
  count: function count() {
    return (0, _request.get)('/addons/kuerp/api.cart/count');
  }
};

// 收货地址
var addressApi = exports.addressApi = {
  index: function index() {
    return (0, _request.get)('/addons/kuerp/api.address/index');
  },
  add: function add(data) {
    return (0, _request.post)('/addons/kuerp/api.address/add', data);
  },
  edit: function edit(data) {
    return (0, _request.post)('/addons/kuerp/api.address/edit', data);
  },
  delete: function _delete(id) {
    return (0, _request.post)('/addons/kuerp/api.address/delete', {
      id: id
    });
  },
  setDefault: function setDefault(id) {
    return (0, _request.post)('/addons/kuerp/api.address/setDefault', {
      id: id
    });
  }
};

// 订单
var orderApi = exports.orderApi = {
  add: function add(data) {
    return (0, _request.post)('/addons/kuerp/api.order/add', data);
  },
  index: function index(params) {
    return (0, _request.get)('/addons/kuerp/api.order/index', params);
  },
  detail: function detail(id) {
    return (0, _request.get)('/addons/kuerp/api.order/detail', {
      id: id
    });
  },
  cancel: function cancel(id) {
    return (0, _request.post)('/addons/kuerp/api.order/cancel', {
      id: id
    });
  },
  received: function received(id) {
    return (0, _request.post)('/addons/kuerp/api.order/received', {
      id: id
    });
  },
  buyNow: function buyNow(data) {
    return (0, _request.post)('/addons/kuerp/api.order/buyNow', data);
  }
};

// 物流
var deliveryApi = exports.deliveryApi = {
  index: function index(order_id) {
    return (0, _request.get)('/addons/kuerp/api.delivery/index', {
      order_id: order_id
    });
  }
};

/***/ }),

/***/ "O07N":
/*!***************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--13-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--16-0!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-uni-app-loader/using-components.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/wrap-loader??ref--18!./src/App.vue?vue&type=script&lang=js& ***!
  \***************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
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
var _api = __webpack_require__(/*! ./api */ "Nlzp");
var _default = exports.default = {
  globalData: {
    userInfo: null
  },
  onLaunch: function onLaunch() {
    this.autoLogin();
  },
  methods: {
    autoLogin: function autoLogin() {
      return (0, _asyncToGenerator2.default)(/*#__PURE__*/(0, _regenerator2.default)().m(function _callee() {
        var token, res, _t;
        return (0, _regenerator2.default)().w(function (_context) {
          while (1) switch (_context.p = _context.n) {
            case 0:
              token = uni.getStorageSync('token');
              if (!token) {
                _context.n = 4;
                break;
              }
              _context.p = 1;
              _context.n = 2;
              return _api.userApi.info();
            case 2:
              res = _context.v;
              getApp().globalData.userInfo = res;
              return _context.a(2);
            case 3:
              _context.p = 3;
              _t = _context.v;
              uni.removeStorageSync('token');
            case 4:
              getApp().globalData.userInfo = null;
            case 5:
              return _context.a(2);
          }
        }, _callee, null, [[1, 3]]);
      }))();
    }
  }
};

/***/ }),

/***/ "Pf3K":
/*!*********************!*\
  !*** ./src/App.vue ***!
  \*********************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _App_vue_vue_type_template_id_472cff63___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./App.vue?vue&type=template&id=472cff63& */ "E77e");
/* harmony import */ var _App_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./App.vue?vue&type=script&lang=js& */ "I77X");
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _App_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _App_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__[key]; }) }(__WEBPACK_IMPORT_KEY__));
/* harmony import */ var _App_vue_vue_type_style_index_0_lang_scss___WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./App.vue?vue&type=style&index=0&lang=scss& */ "XAuw");
/* harmony import */ var _node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/runtime/componentNormalizer.js */ "8MXW");

var renderjs





/* normalize component */

var component = Object(_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__["default"])(
  _App_vue_vue_type_script_lang_js___WEBPACK_IMPORTED_MODULE_1__["default"],
  _App_vue_vue_type_template_id_472cff63___WEBPACK_IMPORTED_MODULE_0__["render"],
  _App_vue_vue_type_template_id_472cff63___WEBPACK_IMPORTED_MODULE_0__["staticRenderFns"],
  false,
  null,
  null,
  null,
  false,
  _App_vue_vue_type_template_id_472cff63___WEBPACK_IMPORTED_MODULE_0__["components"],
  renderjs
)

/* hot reload */
if (false) { var api; }
component.options.__file = "App.vue"
/* harmony default export */ __webpack_exports__["default"] = (component.exports);

/***/ }),

/***/ "Vtdi":
/*!*********************!*\
  !*** ./src/main.js ***!
  \*********************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


var _interopRequireDefault = __webpack_require__(/*! ./node_modules/@babel/runtime/helpers/interopRequireDefault.js */ "TqRt").default;
__webpack_require__(/*! core-js/modules/es.regexp.exec.js */ "rB9j");
__webpack_require__(/*! core-js/modules/es.regexp.test.js */ "ALS0");
var _objectSpread2 = _interopRequireDefault(__webpack_require__(/*! ./node_modules/@babel/runtime/helpers/esm/objectSpread2.js */ "VTBJ"));
__webpack_require__(/*! ./node_modules/core-js/modules/es.array.iterator.js */ "4mDm");
__webpack_require__(/*! ./node_modules/core-js/modules/es.promise.js */ "5s+n");
__webpack_require__(/*! ./node_modules/core-js/modules/es.object.assign.js */ "zKZe");
__webpack_require__(/*! ./node_modules/core-js/modules/es.promise.finally.js */ "p532");
__webpack_require__(/*! uni-pages */ "bNx1");
__webpack_require__(/*! uni-h5 */ "HDER");
var _vue = _interopRequireDefault(__webpack_require__(/*! vue */ "4UNb"));
var _App = _interopRequireDefault(__webpack_require__(/*! ./App */ "Pf3K"));
var _uviewUi = _interopRequireDefault(__webpack_require__(/*! uview-ui */ "a1KO"));
var _config = _interopRequireDefault(__webpack_require__(/*! ./config */ "20nU"));
_vue.default.use(_uviewUi.default);
_vue.default.config.productionTip = false;

// 全局图片地址格式化：相对路径自动拼接域名
_vue.default.prototype.$img = function (url) {
  if (!url) return '';
  if (/^https?:\/\//.test(url)) return url;
  return (_config.default.imgBaseUrl || '') + url;
};
_App.default.mpType = 'app';
var app = new _vue.default((0, _objectSpread2.default)({}, _App.default));
app.$mount();

/***/ }),

/***/ "XAuw":
/*!*******************************************************!*\
  !*** ./src/App.vue?vue&type=style&index=0&lang=scss& ***!
  \*******************************************************/
/*! no static exports found */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_9_oneOf_1_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_node_modules_postcss_loader_src_index_js_ref_9_oneOf_1_3_node_modules_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_style_index_0_lang_scss___WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/h5-vue-style-loader??ref--9-oneOf-1-0!../node_modules/css-loader/dist/cjs.js??ref--9-oneOf-1-1!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!../node_modules/postcss-loader/src??ref--9-oneOf-1-3!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!../node_modules/@dcloudio/vue-cli-plugin-uni/packages/wrap-loader??ref--18!./App.vue?vue&type=style&index=0&lang=scss& */ "GSuk");
/* harmony import */ var _node_modules_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_9_oneOf_1_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_node_modules_postcss_loader_src_index_js_ref_9_oneOf_1_3_node_modules_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_style_index_0_lang_scss___WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_9_oneOf_1_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_node_modules_postcss_loader_src_index_js_ref_9_oneOf_1_3_node_modules_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_style_index_0_lang_scss___WEBPACK_IMPORTED_MODULE_0__);
/* harmony reexport (unknown) */ for(var __WEBPACK_IMPORT_KEY__ in _node_modules_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_9_oneOf_1_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_node_modules_postcss_loader_src_index_js_ref_9_oneOf_1_3_node_modules_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_style_index_0_lang_scss___WEBPACK_IMPORTED_MODULE_0__) if(["default"].indexOf(__WEBPACK_IMPORT_KEY__) < 0) (function(key) { __webpack_require__.d(__webpack_exports__, key, function() { return _node_modules_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_9_oneOf_1_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_node_modules_postcss_loader_src_index_js_ref_9_oneOf_1_3_node_modules_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_style_index_0_lang_scss___WEBPACK_IMPORTED_MODULE_0__[key]; }) }(__WEBPACK_IMPORT_KEY__));
 /* harmony default export */ __webpack_exports__["default"] = (_node_modules_dcloudio_vue_cli_plugin_uni_packages_h5_vue_style_loader_index_js_ref_9_oneOf_1_0_node_modules_css_loader_dist_cjs_js_ref_9_oneOf_1_1_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_loaders_stylePostLoader_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_2_node_modules_postcss_loader_src_index_js_ref_9_oneOf_1_3_node_modules_dcloudio_vue_cli_plugin_uni_packages_sass_loader_dist_cjs_js_ref_9_oneOf_1_4_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_preprocess_loader_index_js_ref_9_oneOf_1_5_node_modules_dcloudio_vue_cli_plugin_uni_packages_vue_loader_lib_index_js_vue_loader_options_node_modules_dcloudio_vue_cli_plugin_uni_packages_webpack_scoped_loader_index_js_node_modules_dcloudio_vue_cli_plugin_uni_packages_wrap_loader_index_js_ref_18_App_vue_vue_type_style_index_0_lang_scss___WEBPACK_IMPORTED_MODULE_0___default.a); 

/***/ }),

/***/ "bNx1":
/*!************************!*\
  !*** ./src/pages.json ***!
  \************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";
/* WEBPACK VAR INJECTION */(function(global) {

var _interopRequireDefault = __webpack_require__(/*! ./node_modules/@babel/runtime/helpers/interopRequireDefault.js */ "TqRt").default;
__webpack_require__(/*! core-js/modules/es.array.reduce.js */ "E9XD");
__webpack_require__(/*! core-js/modules/es.iterator.constructor.js */ "6fVz");
__webpack_require__(/*! core-js/modules/es.iterator.reduce.js */ "lIUY");
__webpack_require__(/*! core-js/modules/es.object.to-string.js */ "07d7");
__webpack_require__(/*! core-js/modules/es.regexp.exec.js */ "rB9j");
__webpack_require__(/*! core-js/modules/es.string.replace.js */ "UxlC");
var _vue = _interopRequireDefault(__webpack_require__(/*! vue */ "4UNb"));
var locales = {
  keys: function keys() {
    return [];
  }
};
global['________'] = true;
delete global['________'];
global.__uniConfig = {
  "easycom": {
    "^u-(.*)": "uview-ui/components/u-$1/u-$1.vue",
    "^unicloud-db$": "@dcloudio/uni-cli-shared/components/unicloud-db.vue",
    "^uniad$": "@dcloudio/uni-cli-shared/components/uniad.vue",
    "^ad-rewarded-video$": "@dcloudio/uni-cli-shared/components/ad-rewarded-video.vue",
    "^ad-fullscreen-video$": "@dcloudio/uni-cli-shared/components/ad-fullscreen-video.vue",
    "^ad-interstitial$": "@dcloudio/uni-cli-shared/components/ad-interstitial.vue",
    "^ad-interactive$": "@dcloudio/uni-cli-shared/components/ad-interactive.vue",
    "^page-meta$": "@dcloudio/uni-cli-shared/components/page-meta.vue",
    "^navigation-bar$": "@dcloudio/uni-cli-shared/components/navigation-bar.vue",
    "^uni-match-media$": "@dcloudio/uni-cli-shared/components/uni-match-media.vue"
  },
  "tabBar": {
    "color": "#909399",
    "selectedColor": "#4A90D9",
    "borderStyle": "black",
    "backgroundColor": "#ffffff",
    "list": [{
      "pagePath": "pages/index/index",
      "text": "首页",
      "iconPath": "/static/tabbar/home.png",
      "selectedIconPath": "/static/tabbar/home_selected.png",
      "redDot": false,
      "badge": ""
    }, {
      "pagePath": "pages/cart/cart",
      "text": "订货单",
      "iconPath": "/static/tabbar/cart.png",
      "selectedIconPath": "/static/tabbar/cart_selected.png",
      "redDot": false,
      "badge": ""
    }, {
      "pagePath": "pages/order/list",
      "text": "订单",
      "iconPath": "/static/tabbar/order.png",
      "selectedIconPath": "/static/tabbar/order_selected.png",
      "redDot": false,
      "badge": ""
    }, {
      "pagePath": "pages/user/info",
      "text": "我的",
      "iconPath": "/static/tabbar/user.png",
      "selectedIconPath": "/static/tabbar/user_selected.png",
      "redDot": false,
      "badge": ""
    }]
  },
  "globalStyle": {
    "navigationBarTextStyle": "white",
    "navigationBarTitleText": "kuerp",
    "navigationBarBackgroundColor": "#4A90D9",
    "backgroundColor": "#f5f7fa"
  }
};
global.__uniConfig.compilerVersion = '5.07';
global.__uniConfig.darkmode = false;
global.__uniConfig.themeConfig = {};
global.__uniConfig.uniPlatform = 'h5';
global.__uniConfig.appId = '';
global.__uniConfig.appName = 'kuerp';
global.__uniConfig.appVersion = '1.0.0';
global.__uniConfig.appVersionCode = '100';
global.__uniConfig.router = {
  "mode": "hash",
  "base": "/"
};
global.__uniConfig.publicPath = "/";
global.__uniConfig['async'] = {
  "loading": "AsyncLoading",
  "error": "AsyncError",
  "delay": 200,
  "timeout": 60000
};
global.__uniConfig.debug = false;
global.__uniConfig.networkTimeout = {
  "request": 60000,
  "connectSocket": 60000,
  "uploadFile": 60000,
  "downloadFile": 60000
};
global.__uniConfig.sdkConfigs = {};
global.__uniConfig.qqMapKey = undefined;
global.__uniConfig.googleMapKey = undefined;
global.__uniConfig.aMapKey = undefined;
global.__uniConfig.aMapSecurityJsCode = undefined;
global.__uniConfig.aMapServiceHost = undefined;
global.__uniConfig.locale = "";
global.__uniConfig.fallbackLocale = undefined;
global.__uniConfig.locales = locales.keys().reduce(function (res, key) {
  var locale = key.replace(/\.\/(uni-app.)?(.*).json/, '$2');
  var messages = locales(key);
  Object.assign(res[locale] || (res[locale] = {}), messages.common || messages);
  return res;
}, {});
global.__uniConfig.nvue = {
  "flex-direction": "column"
};
global.__uniConfig.__webpack_chunk_load__ = __webpack_require__.e;
_vue.default.component('pages-index-index', function (resolve) {
  var component = {
    component: Promise.all(/*! require.ensure | pages-index-index */[__webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9"), __webpack_require__.e("pages-address-edit~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-detail~page~59b4d8f3"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~page~a72566e0"), __webpack_require__.e("pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-logistics"), __webpack_require__.e("pages-address-list~pages-cart-cart~pages-index-index~pages-order-list~pages-order-logistics"), __webpack_require__.e("pages-index-index~pages-order-list"), __webpack_require__.e("pages-index-index")]).then((function () {
      return resolve(__webpack_require__(/*! ./src/pages/index/index.vue */ "91rt"));
    }).bind(null, __webpack_require__)).catch(__webpack_require__.oe),
    delay: __uniConfig['async'].delay,
    timeout: __uniConfig['async'].timeout
  };
  if (__uniConfig['async']['loading']) {
    component.loading = {
      name: 'SystemAsyncLoading',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['loading']);
      }
    };
  }
  if (__uniConfig['async']['error']) {
    component.error = {
      name: 'SystemAsyncError',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['error']);
      }
    };
  }
  return component;
});
_vue.default.component('pages-goods-detail', function (resolve) {
  var component = {
    component: Promise.all(/*! require.ensure | pages-goods-detail */[__webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-order-checkout~pages-~d38250cb"), __webpack_require__.e("pages-address-edit~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-detail~page~59b4d8f3"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~page~a72566e0"), __webpack_require__.e("pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-logistics"), __webpack_require__.e("pages-address-list~pages-goods-detail~pages-order-detail~pages-order-logistics"), __webpack_require__.e("pages-goods-detail~pages-order-checkout~pages-order-detail~pages-order-logistics"), __webpack_require__.e("pages-address-edit~pages-goods-detail"), __webpack_require__.e("pages-cart-cart~pages-goods-detail"), __webpack_require__.e("pages-goods-detail")]).then((function () {
      return resolve(__webpack_require__(/*! ./src/pages/goods/detail.vue */ "u8v1"));
    }).bind(null, __webpack_require__)).catch(__webpack_require__.oe),
    delay: __uniConfig['async'].delay,
    timeout: __uniConfig['async'].timeout
  };
  if (__uniConfig['async']['loading']) {
    component.loading = {
      name: 'SystemAsyncLoading',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['loading']);
      }
    };
  }
  if (__uniConfig['async']['error']) {
    component.error = {
      name: 'SystemAsyncError',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['error']);
      }
    };
  }
  return component;
});
_vue.default.component('pages-cart-cart', function (resolve) {
  var component = {
    component: Promise.all(/*! require.ensure | pages-cart-cart */[__webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-order-checkout~pages-~d38250cb"), __webpack_require__.e("pages-address-list~pages-cart-cart~pages-index-index~pages-order-list~pages-order-logistics"), __webpack_require__.e("pages-cart-cart~pages-goods-detail"), __webpack_require__.e("pages-cart-cart")]).then((function () {
      return resolve(__webpack_require__(/*! ./src/pages/cart/cart.vue */ "vxJb"));
    }).bind(null, __webpack_require__)).catch(__webpack_require__.oe),
    delay: __uniConfig['async'].delay,
    timeout: __uniConfig['async'].timeout
  };
  if (__uniConfig['async']['loading']) {
    component.loading = {
      name: 'SystemAsyncLoading',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['loading']);
      }
    };
  }
  if (__uniConfig['async']['error']) {
    component.error = {
      name: 'SystemAsyncError',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['error']);
      }
    };
  }
  return component;
});
_vue.default.component('pages-order-checkout', function (resolve) {
  var component = {
    component: Promise.all(/*! require.ensure | pages-order-checkout */[__webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-order-checkout~pages-~d38250cb"), __webpack_require__.e("pages-address-edit~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-detail~page~59b4d8f3"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~page~a72566e0"), __webpack_require__.e("pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-logistics"), __webpack_require__.e("pages-goods-detail~pages-order-checkout~pages-order-detail~pages-order-logistics"), __webpack_require__.e("pages-order-checkout")]).then((function () {
      return resolve(__webpack_require__(/*! ./src/pages/order/checkout.vue */ "i/wh"));
    }).bind(null, __webpack_require__)).catch(__webpack_require__.oe),
    delay: __uniConfig['async'].delay,
    timeout: __uniConfig['async'].timeout
  };
  if (__uniConfig['async']['loading']) {
    component.loading = {
      name: 'SystemAsyncLoading',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['loading']);
      }
    };
  }
  if (__uniConfig['async']['error']) {
    component.error = {
      name: 'SystemAsyncError',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['error']);
      }
    };
  }
  return component;
});
_vue.default.component('pages-order-list', function (resolve) {
  var component = {
    component: Promise.all(/*! require.ensure | pages-order-list */[__webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-order-checkout~pages-~d38250cb"), __webpack_require__.e("pages-address-edit~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-detail~page~59b4d8f3"), __webpack_require__.e("pages-address-list~pages-cart-cart~pages-index-index~pages-order-list~pages-order-logistics"), __webpack_require__.e("pages-index-index~pages-order-list"), __webpack_require__.e("pages-order-list")]).then((function () {
      return resolve(__webpack_require__(/*! ./src/pages/order/list.vue */ "p8Bx"));
    }).bind(null, __webpack_require__)).catch(__webpack_require__.oe),
    delay: __uniConfig['async'].delay,
    timeout: __uniConfig['async'].timeout
  };
  if (__uniConfig['async']['loading']) {
    component.loading = {
      name: 'SystemAsyncLoading',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['loading']);
      }
    };
  }
  if (__uniConfig['async']['error']) {
    component.error = {
      name: 'SystemAsyncError',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['error']);
      }
    };
  }
  return component;
});
_vue.default.component('pages-order-detail', function (resolve) {
  var component = {
    component: Promise.all(/*! require.ensure | pages-order-detail */[__webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-order-checkout~pages-~d38250cb"), __webpack_require__.e("pages-address-edit~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-detail~page~59b4d8f3"), __webpack_require__.e("pages-address-list~pages-goods-detail~pages-order-detail~pages-order-logistics"), __webpack_require__.e("pages-goods-detail~pages-order-checkout~pages-order-detail~pages-order-logistics"), __webpack_require__.e("pages-order-detail")]).then((function () {
      return resolve(__webpack_require__(/*! ./src/pages/order/detail.vue */ "Rn6I"));
    }).bind(null, __webpack_require__)).catch(__webpack_require__.oe),
    delay: __uniConfig['async'].delay,
    timeout: __uniConfig['async'].timeout
  };
  if (__uniConfig['async']['loading']) {
    component.loading = {
      name: 'SystemAsyncLoading',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['loading']);
      }
    };
  }
  if (__uniConfig['async']['error']) {
    component.error = {
      name: 'SystemAsyncError',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['error']);
      }
    };
  }
  return component;
});
_vue.default.component('pages-user-info', function (resolve) {
  var component = {
    component: Promise.all(/*! require.ensure | pages-user-info */[__webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-order-checkout~pages-~d38250cb"), __webpack_require__.e("pages-address-edit~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-detail~page~59b4d8f3"), __webpack_require__.e("pages-user-info")]).then((function () {
      return resolve(__webpack_require__(/*! ./src/pages/user/info.vue */ "QkVQ"));
    }).bind(null, __webpack_require__)).catch(__webpack_require__.oe),
    delay: __uniConfig['async'].delay,
    timeout: __uniConfig['async'].timeout
  };
  if (__uniConfig['async']['loading']) {
    component.loading = {
      name: 'SystemAsyncLoading',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['loading']);
      }
    };
  }
  if (__uniConfig['async']['error']) {
    component.error = {
      name: 'SystemAsyncError',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['error']);
      }
    };
  }
  return component;
});
_vue.default.component('pages-user-profile', function (resolve) {
  var component = {
    component: Promise.all(/*! require.ensure | pages-user-profile */[__webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-order-checkout~pages-~d38250cb"), __webpack_require__.e("pages-address-edit~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-detail~page~59b4d8f3"), __webpack_require__.e("pages-address-edit~pages-user-login~pages-user-profile~pages-user-register"), __webpack_require__.e("pages-user-profile")]).then((function () {
      return resolve(__webpack_require__(/*! ./src/pages/user/profile.vue */ "aBTK"));
    }).bind(null, __webpack_require__)).catch(__webpack_require__.oe),
    delay: __uniConfig['async'].delay,
    timeout: __uniConfig['async'].timeout
  };
  if (__uniConfig['async']['loading']) {
    component.loading = {
      name: 'SystemAsyncLoading',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['loading']);
      }
    };
  }
  if (__uniConfig['async']['error']) {
    component.error = {
      name: 'SystemAsyncError',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['error']);
      }
    };
  }
  return component;
});
_vue.default.component('pages-user-login', function (resolve) {
  var component = {
    component: Promise.all(/*! require.ensure | pages-user-login */[__webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-order-checkout~pages-~d38250cb"), __webpack_require__.e("pages-address-edit~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-detail~page~59b4d8f3"), __webpack_require__.e("pages-address-edit~pages-user-login~pages-user-profile~pages-user-register"), __webpack_require__.e("pages-user-login")]).then((function () {
      return resolve(__webpack_require__(/*! ./src/pages/user/login.vue */ "iUZK"));
    }).bind(null, __webpack_require__)).catch(__webpack_require__.oe),
    delay: __uniConfig['async'].delay,
    timeout: __uniConfig['async'].timeout
  };
  if (__uniConfig['async']['loading']) {
    component.loading = {
      name: 'SystemAsyncLoading',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['loading']);
      }
    };
  }
  if (__uniConfig['async']['error']) {
    component.error = {
      name: 'SystemAsyncError',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['error']);
      }
    };
  }
  return component;
});
_vue.default.component('pages-user-register', function (resolve) {
  var component = {
    component: Promise.all(/*! require.ensure | pages-user-register */[__webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-order-checkout~pages-~d38250cb"), __webpack_require__.e("pages-address-edit~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-detail~page~59b4d8f3"), __webpack_require__.e("pages-address-edit~pages-user-login~pages-user-profile~pages-user-register"), __webpack_require__.e("pages-user-register")]).then((function () {
      return resolve(__webpack_require__(/*! ./src/pages/user/register.vue */ "Y4Ej"));
    }).bind(null, __webpack_require__)).catch(__webpack_require__.oe),
    delay: __uniConfig['async'].delay,
    timeout: __uniConfig['async'].timeout
  };
  if (__uniConfig['async']['loading']) {
    component.loading = {
      name: 'SystemAsyncLoading',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['loading']);
      }
    };
  }
  if (__uniConfig['async']['error']) {
    component.error = {
      name: 'SystemAsyncError',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['error']);
      }
    };
  }
  return component;
});
_vue.default.component('pages-address-list', function (resolve) {
  var component = {
    component: Promise.all(/*! require.ensure | pages-address-list */[__webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-order-checkout~pages-~d38250cb"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~page~a72566e0"), __webpack_require__.e("pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-logistics"), __webpack_require__.e("pages-address-list~pages-cart-cart~pages-index-index~pages-order-list~pages-order-logistics"), __webpack_require__.e("pages-address-list~pages-goods-detail~pages-order-detail~pages-order-logistics"), __webpack_require__.e("pages-address-list")]).then((function () {
      return resolve(__webpack_require__(/*! ./src/pages/address/list.vue */ "JtM1"));
    }).bind(null, __webpack_require__)).catch(__webpack_require__.oe),
    delay: __uniConfig['async'].delay,
    timeout: __uniConfig['async'].timeout
  };
  if (__uniConfig['async']['loading']) {
    component.loading = {
      name: 'SystemAsyncLoading',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['loading']);
      }
    };
  }
  if (__uniConfig['async']['error']) {
    component.error = {
      name: 'SystemAsyncError',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['error']);
      }
    };
  }
  return component;
});
_vue.default.component('pages-address-edit', function (resolve) {
  var component = {
    component: Promise.all(/*! require.ensure | pages-address-edit */[__webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~aba5e4d9"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-order-checkout~pages-~d38250cb"), __webpack_require__.e("pages-address-edit~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-detail~page~59b4d8f3"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~page~a72566e0"), __webpack_require__.e("pages-address-edit~pages-user-login~pages-user-profile~pages-user-register"), __webpack_require__.e("pages-address-edit~pages-goods-detail"), __webpack_require__.e("pages-address-edit")]).then((function () {
      return resolve(__webpack_require__(/*! ./src/pages/address/edit.vue */ "hH17"));
    }).bind(null, __webpack_require__)).catch(__webpack_require__.oe),
    delay: __uniConfig['async'].delay,
    timeout: __uniConfig['async'].timeout
  };
  if (__uniConfig['async']['loading']) {
    component.loading = {
      name: 'SystemAsyncLoading',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['loading']);
      }
    };
  }
  if (__uniConfig['async']['error']) {
    component.error = {
      name: 'SystemAsyncError',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['error']);
      }
    };
  }
  return component;
});
_vue.default.component('pages-order-logistics', function (resolve) {
  var component = {
    component: Promise.all(/*! require.ensure | pages-order-logistics */[__webpack_require__.e("pages-address-edit~pages-address-list~pages-cart-cart~pages-goods-detail~pages-index-index~pages-ord~8703a764"), __webpack_require__.e("pages-address-edit~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-detail~page~59b4d8f3"), __webpack_require__.e("pages-address-edit~pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~page~a72566e0"), __webpack_require__.e("pages-address-list~pages-goods-detail~pages-index-index~pages-order-checkout~pages-order-logistics"), __webpack_require__.e("pages-address-list~pages-cart-cart~pages-index-index~pages-order-list~pages-order-logistics"), __webpack_require__.e("pages-address-list~pages-goods-detail~pages-order-detail~pages-order-logistics"), __webpack_require__.e("pages-goods-detail~pages-order-checkout~pages-order-detail~pages-order-logistics"), __webpack_require__.e("pages-order-logistics")]).then((function () {
      return resolve(__webpack_require__(/*! ./src/pages/order/logistics.vue */ "JOCa"));
    }).bind(null, __webpack_require__)).catch(__webpack_require__.oe),
    delay: __uniConfig['async'].delay,
    timeout: __uniConfig['async'].timeout
  };
  if (__uniConfig['async']['loading']) {
    component.loading = {
      name: 'SystemAsyncLoading',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['loading']);
      }
    };
  }
  if (__uniConfig['async']['error']) {
    component.error = {
      name: 'SystemAsyncError',
      render: function render(createElement) {
        return createElement(__uniConfig['async']['error']);
      }
    };
  }
  return component;
});
global.__uniRoutes = [{
  path: '/',
  alias: '/pages/index/index',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: Object.assign({
          isQuit: true,
          isEntry: true,
          isTabBar: true,
          tabBarIndex: 0
        }, __uniConfig.globalStyle, {
          "navigationBarTitleText": "商品列表",
          "enablePullDownRefresh": true,
          "backgroundColor": "#f5f7fa"
        })
      }, [createElement('pages-index-index', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    id: 1,
    name: 'pages-index-index',
    isNVue: false,
    maxWidth: 0,
    pagePath: 'pages/index/index',
    isQuit: true,
    isEntry: true,
    isTabBar: true,
    tabBarIndex: 0,
    windowTop: 44
  }
}, {
  path: '/pages/goods/detail',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: Object.assign({}, __uniConfig.globalStyle, {
          "navigationBarTitleText": "商品详情",
          "backgroundColor": "#f5f7fa"
        })
      }, [createElement('pages-goods-detail', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    name: 'pages-goods-detail',
    isNVue: false,
    maxWidth: 0,
    pagePath: 'pages/goods/detail',
    windowTop: 44
  }
}, {
  path: '/pages/cart/cart',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: Object.assign({
          isQuit: true,
          isTabBar: true,
          tabBarIndex: 1
        }, __uniConfig.globalStyle, {
          "navigationBarTitleText": "购物车",
          "enablePullDownRefresh": true,
          "backgroundColor": "#f5f7fa"
        })
      }, [createElement('pages-cart-cart', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    id: 2,
    name: 'pages-cart-cart',
    isNVue: false,
    maxWidth: 0,
    pagePath: 'pages/cart/cart',
    isQuit: true,
    isTabBar: true,
    tabBarIndex: 1,
    windowTop: 44
  }
}, {
  path: '/pages/order/checkout',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: Object.assign({}, __uniConfig.globalStyle, {
          "navigationBarTitleText": "确认订单",
          "backgroundColor": "#f5f7fa"
        })
      }, [createElement('pages-order-checkout', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    name: 'pages-order-checkout',
    isNVue: false,
    maxWidth: 0,
    pagePath: 'pages/order/checkout',
    windowTop: 44
  }
}, {
  path: '/pages/order/list',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: Object.assign({
          isQuit: true,
          isTabBar: true,
          tabBarIndex: 2
        }, __uniConfig.globalStyle, {
          "navigationBarTitleText": "我的订单",
          "enablePullDownRefresh": true,
          "backgroundColor": "#f5f7fa"
        })
      }, [createElement('pages-order-list', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    id: 3,
    name: 'pages-order-list',
    isNVue: false,
    maxWidth: 0,
    pagePath: 'pages/order/list',
    isQuit: true,
    isTabBar: true,
    tabBarIndex: 2,
    windowTop: 44
  }
}, {
  path: '/pages/order/detail',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: Object.assign({}, __uniConfig.globalStyle, {
          "navigationBarTitleText": "订单详情",
          "backgroundColor": "#f5f7fa"
        })
      }, [createElement('pages-order-detail', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    name: 'pages-order-detail',
    isNVue: false,
    maxWidth: 0,
    pagePath: 'pages/order/detail',
    windowTop: 44
  }
}, {
  path: '/pages/user/info',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: Object.assign({
          isQuit: true,
          isTabBar: true,
          tabBarIndex: 3
        }, __uniConfig.globalStyle, {
          "navigationBarTitleText": "个人中心",
          "navigationBarBackgroundColor": "#4A90D9",
          "navigationBarTextStyle": "white",
          "backgroundColor": "#f5f7fa"
        })
      }, [createElement('pages-user-info', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    id: 4,
    name: 'pages-user-info',
    isNVue: false,
    maxWidth: 0,
    pagePath: 'pages/user/info',
    isQuit: true,
    isTabBar: true,
    tabBarIndex: 3,
    windowTop: 44
  }
}, {
  path: '/pages/user/profile',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: Object.assign({}, __uniConfig.globalStyle, {
          "navigationBarTitleText": "编辑资料",
          "backgroundColor": "#f5f7fa"
        })
      }, [createElement('pages-user-profile', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    name: 'pages-user-profile',
    isNVue: false,
    maxWidth: 0,
    pagePath: 'pages/user/profile',
    windowTop: 44
  }
}, {
  path: '/pages/user/login',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: Object.assign({}, __uniConfig.globalStyle, {
          "navigationBarTitleText": "登录",
          "navigationBarBackgroundColor": "#ffffff",
          "navigationBarTextStyle": "black"
        })
      }, [createElement('pages-user-login', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    name: 'pages-user-login',
    isNVue: false,
    maxWidth: 0,
    pagePath: 'pages/user/login',
    windowTop: 44
  }
}, {
  path: '/pages/user/register',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: Object.assign({}, __uniConfig.globalStyle, {
          "navigationBarTitleText": "注册",
          "navigationBarBackgroundColor": "#ffffff",
          "navigationBarTextStyle": "black"
        })
      }, [createElement('pages-user-register', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    name: 'pages-user-register',
    isNVue: false,
    maxWidth: 0,
    pagePath: 'pages/user/register',
    windowTop: 44
  }
}, {
  path: '/pages/address/list',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: Object.assign({}, __uniConfig.globalStyle, {
          "navigationBarTitleText": "收货地址",
          "enablePullDownRefresh": true,
          "backgroundColor": "#f5f7fa"
        })
      }, [createElement('pages-address-list', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    name: 'pages-address-list',
    isNVue: false,
    maxWidth: 0,
    pagePath: 'pages/address/list',
    windowTop: 44
  }
}, {
  path: '/pages/address/edit',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: Object.assign({}, __uniConfig.globalStyle, {
          "navigationBarTitleText": "编辑地址",
          "backgroundColor": "#f5f7fa"
        })
      }, [createElement('pages-address-edit', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    name: 'pages-address-edit',
    isNVue: false,
    maxWidth: 0,
    pagePath: 'pages/address/edit',
    windowTop: 44
  }
}, {
  path: '/pages/order/logistics',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: Object.assign({}, __uniConfig.globalStyle, {
          "navigationBarTitleText": "物流详情",
          "backgroundColor": "#f5f7fa"
        })
      }, [createElement('pages-order-logistics', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    name: 'pages-order-logistics',
    isNVue: false,
    maxWidth: 0,
    pagePath: 'pages/order/logistics',
    windowTop: 44
  }
}, {
  path: '/choose-location',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: {
          navigationStyle: 'custom'
        }
      }, [createElement('system-choose-location', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    name: 'choose-location',
    pagePath: '/choose-location'
  }
}, {
  path: '/open-location',
  component: {
    render: function render(createElement) {
      return createElement('Page', {
        props: {
          navigationStyle: 'custom'
        }
      }, [createElement('system-open-location', {
        slot: 'page'
      })]);
    }
  },
  meta: {
    name: 'open-location',
    pagePath: '/open-location'
  }
}];
global.UniApp && new global.UniApp();
/* WEBPACK VAR INJECTION */}.call(this, __webpack_require__(/*! ./../node_modules/webpack/buildin/global.js */ "yLpj")))

/***/ }),

/***/ "yEaF":
/*!****************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/css-loader/dist/cjs.js??ref--9-oneOf-1-1!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-2!./node_modules/postcss-loader/src??ref--9-oneOf-1-3!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/sass-loader/dist/cjs.js??ref--9-oneOf-1-4!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-preprocess-loader??ref--9-oneOf-1-5!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/vue-loader/lib??vue-loader-options!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/webpack-scoped-loader!./node_modules/@dcloudio/vue-cli-plugin-uni/packages/wrap-loader??ref--18!./src/App.vue?vue&type=style&index=0&lang=scss& ***!
  \****************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

// Imports
var ___CSS_LOADER_API_IMPORT___ = __webpack_require__(/*! ../node_modules/css-loader/dist/runtime/api.js */ "JPst");
exports = ___CSS_LOADER_API_IMPORT___(false);
// Module
exports.push([module.i, "@charset \"UTF-8\";\n.u-line-1 {\n\n\n\n\n\n\n\n  display: -webkit-box !important;\n  overflow: hidden;\n  text-overflow: ellipsis;\n  word-break: break-all;\n  -webkit-line-clamp: 1;\n  -webkit-box-orient: vertical !important;\n}\n.u-line-2 {\n\n\n\n\n\n\n\n  display: -webkit-box !important;\n  overflow: hidden;\n  text-overflow: ellipsis;\n  word-break: break-all;\n  -webkit-line-clamp: 2;\n  -webkit-box-orient: vertical !important;\n}\n.u-line-3 {\n\n\n\n\n\n\n\n  display: -webkit-box !important;\n  overflow: hidden;\n  text-overflow: ellipsis;\n  word-break: break-all;\n  -webkit-line-clamp: 3;\n  -webkit-box-orient: vertical !important;\n}\n.u-line-4 {\n\n\n\n\n\n\n\n  display: -webkit-box !important;\n  overflow: hidden;\n  text-overflow: ellipsis;\n  word-break: break-all;\n  -webkit-line-clamp: 4;\n  -webkit-box-orient: vertical !important;\n}\n.u-line-5 {\n\n\n\n\n\n\n\n  display: -webkit-box !important;\n  overflow: hidden;\n  text-overflow: ellipsis;\n  word-break: break-all;\n  -webkit-line-clamp: 5;\n  -webkit-box-orient: vertical !important;\n}\n.u-border {\n  border-width: 0.5px !important;\n  border-color: #dadbde !important;\n  border-style: solid;\n}\n.u-border-top {\n  border-top-width: 0.5px !important;\n  border-color: #dadbde !important;\n  border-top-style: solid;\n}\n.u-border-left {\n  border-left-width: 0.5px !important;\n  border-color: #dadbde !important;\n  border-left-style: solid;\n}\n.u-border-right {\n  border-right-width: 0.5px !important;\n  border-color: #dadbde !important;\n  border-right-style: solid;\n}\n.u-border-bottom {\n  border-bottom-width: 0.5px !important;\n  border-color: #dadbde !important;\n  border-bottom-style: solid;\n}\n.u-border-top-bottom {\n  border-top-width: 0.5px !important;\n  border-bottom-width: 0.5px !important;\n  border-color: #dadbde !important;\n  border-top-style: solid;\n  border-bottom-style: solid;\n}\n.u-reset-button {\n  padding: 0;\n  background-color: transparent;\n\n  font-size: inherit;\n  line-height: inherit;\n  color: inherit;\n}\n.u-reset-button::after {\n  border: none;\n}\n.u-hover-class {\n  opacity: 0.7;\n}\n.u-primary-light {\n  color: #ecf5ff;\n}\n.u-warning-light {\n  color: #fdf6ec;\n}\n.u-success-light {\n  color: #f5fff0;\n}\n.u-error-light {\n  color: #fef0f0;\n}\n.u-info-light {\n  color: #f4f4f5;\n}\n.u-primary-light-bg {\n  background-color: #ecf5ff;\n}\n.u-warning-light-bg {\n  background-color: #fdf6ec;\n}\n.u-success-light-bg {\n  background-color: #f5fff0;\n}\n.u-error-light-bg {\n  background-color: #fef0f0;\n}\n.u-info-light-bg {\n  background-color: #f4f4f5;\n}\n.u-primary-dark {\n  color: #398ade;\n}\n.u-warning-dark {\n  color: #f1a532;\n}\n.u-success-dark {\n  color: #53c21d;\n}\n.u-error-dark {\n  color: #e45656;\n}\n.u-info-dark {\n  color: #767a82;\n}\n.u-primary-dark-bg {\n  background-color: #398ade;\n}\n.u-warning-dark-bg {\n  background-color: #f1a532;\n}\n.u-success-dark-bg {\n  background-color: #53c21d;\n}\n.u-error-dark-bg {\n  background-color: #e45656;\n}\n.u-info-dark-bg {\n  background-color: #767a82;\n}\n.u-primary-disabled {\n  color: #9acafc;\n}\n.u-warning-disabled {\n  color: #f9d39b;\n}\n.u-success-disabled {\n  color: #a9e08f;\n}\n.u-error-disabled {\n  color: #f7b2b2;\n}\n.u-info-disabled {\n  color: #c4c6c9;\n}\n.u-primary {\n  color: #3c9cff;\n}\n.u-warning {\n  color: #f9ae3d;\n}\n.u-success {\n  color: #5ac725;\n}\n.u-error {\n  color: #f56c6c;\n}\n.u-info {\n  color: #909399;\n}\n.u-primary-bg {\n  background-color: #3c9cff;\n}\n.u-warning-bg {\n  background-color: #f9ae3d;\n}\n.u-success-bg {\n  background-color: #5ac725;\n}\n.u-error-bg {\n  background-color: #f56c6c;\n}\n.u-info-bg {\n  background-color: #909399;\n}\n.u-main-color {\n  color: #303133;\n}\n.u-content-color {\n  color: #606266;\n}\n.u-tips-color {\n  color: #909193;\n}\n.u-light-color {\n  color: #c0c4cc;\n}\n.u-safe-area-inset-top {\n  padding-top: 0;\n  padding-top: constant(safe-area-inset-top);\n  padding-top: env(safe-area-inset-top);\n}\n.u-safe-area-inset-right {\n  padding-right: 0;\n  padding-right: constant(safe-area-inset-right);\n  padding-right: env(safe-area-inset-right);\n}\n.u-safe-area-inset-bottom {\n  padding-bottom: 0;\n  padding-bottom: constant(safe-area-inset-bottom);\n  padding-bottom: env(safe-area-inset-bottom);\n}\n.u-safe-area-inset-left {\n  padding-left: 0;\n  padding-left: constant(safe-area-inset-left);\n  padding-left: env(safe-area-inset-left);\n}\nuni-toast {\n  z-index: 10090;\n}\nuni-toast .uni-toast {\n  z-index: 10090;\n}\n::-webkit-scrollbar {\n  display: none;\n  width: 0 !important;\n  height: 0 !important;\n  -webkit-appearance: none;\n  background: transparent;\n}\n/* 全局样式 */\nuni-page-body {\n  background-color: #f5f7fa;\n  font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\n  color: #303133;\n  font-size: %?28?%;\n  line-height: 1.5;\n  box-sizing: border-box;\n}\nbody.?%PAGE?%{\n  background-color: #f5f7fa;\n}\nuni-view,\nuni-scroll-view,\nuni-text,\nuni-input,\nuni-textarea,\nuni-button {\n  box-sizing: border-box;\n}\n/* 滚动条美化 */\n::-webkit-scrollbar {\n  width: 0;\n  height: 0;\n}\n/* 消除按钮默认样式 */\nuni-button::after {\n  border: none;\n}", ""]);
// Exports
module.exports = exports;


/***/ })

/******/ });