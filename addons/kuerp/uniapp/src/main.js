import Vue from 'vue'
import App from './App'
import uView from 'uview-ui'
import config from './config'

Vue.use(uView)

Vue.config.productionTip = false

// 全局图片地址格式化：相对路径自动拼接域名
Vue.prototype.$img = function (url) {
  if (!url) return ''
  if (/^https?:\/\//.test(url)) return url
  return (config.imgBaseUrl || '') + url
}

App.mpType = 'app'

const app = new Vue({
  ...App,
})
app.$mount()
