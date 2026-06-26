// API 基础配置
// H5 端使用空字符串（通过 vue.config.js proxy 代理到后端，避免跨域）
// 小程序端需要完整域名（无 proxy，直接请求后端）
// 正式上线需改为 HTTPS 域名并在微信后台配置合法域名
const isMpWeixin = process.env.UNI_PLATFORM === 'mp-weixin'

export default {
  // baseUrl: isMpWeixin ? 'http://erp.licheng.test' : '',
  // 图片域名前缀（本地图片存储路径为相对路径，需拼接完整域名）
  // imgBaseUrl: 'http://erp.licheng.test',
  
  baseUrl: isMpWeixin ? 'https://dyerp.shengbokj.com/' : '',
  // 图片域名前缀（本地图片存储路径为相对路径，需拼接完整域名）
  imgBaseUrl: 'https://dyerp.shengbokj.com/',
}
