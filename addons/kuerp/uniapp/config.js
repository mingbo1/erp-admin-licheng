// API 基础配置：H5 与小程序均直连后端域名（不走本地代理）
// 后端 CORS（application/config.php 的 cors_request_domain）需允许调用方来源，
// 当前已允许 localhost / 127.0.0.1；H5 若部署到其他域名，需把该域名加入 cors_request_domain。
// 小程序真机上线必须用 HTTPS 域名并在微信后台加入 request 合法域名；
// 当前 http://erp.licheng.test 仅用于本地调试（开发者工具需勾选"不校验合法域名"）。
// const apiDomain = 'http://erp.licheng.test'
const apiDomain = 'https://dyerp.boyuekeke.top'

export default {
  baseUrl: apiDomain,
  // 图片域名前缀（本地图片存储路径为相对路径，需拼接完整域名）
  imgBaseUrl: apiDomain,
}
