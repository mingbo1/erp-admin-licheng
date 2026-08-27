const path = require('path')

module.exports = {
  transpileDependencies: ['uview-ui'],
  // 注：接口/图片不再走本地代理 —— config.js 中 baseUrl / imgBaseUrl 已直连后端域名，
  // 依赖后端 CORS（application/config.php cors_request_domain 允许 localhost）。
  // 若将来改成相对路径模式（baseUrl 为空），需在此配置代理到后端，
  // 注意 erp.licheng.test 同时解析到 ::1(IPv6) 与 127.0.0.1，Node 默认走 IPv6 会导致 ECONNREFUSED。
  devServer: {},
}
