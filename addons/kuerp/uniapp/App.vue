<script>
import { userApi } from './api'

export default {
  globalData: {
    userInfo: null
  },
  onLaunch() {
    this.autoLogin()
  },
  methods: {
    async autoLogin() {
      const token = uni.getStorageSync('token')
      if (token) {
        try {
          const res = await userApi.info()
          this.setGlobalData('userInfo', res)
          return
        } catch (e) {
          uni.removeStorageSync('token')
        }
      }
      this.setGlobalData('userInfo', null)
    },
    // 安全设置 globalData，兼容小程序 onLaunch 阶段 getApp() 不可用的场景
    setGlobalData(key, value) {
      // Vue2 自定义 option，始终可用
      if (this.$options.globalData) {
        this.$options.globalData[key] = value
      }
      // 同步到微信 App globalData（供页面中 getApp().globalData 使用）
      const app = getApp()
      if (app && app.globalData) {
        app.globalData[key] = value
      }
    },
  }
}
</script>

<style lang="scss">
@import 'uview-ui/index.scss';

/* 全局样式 */
page {
  background-color: #f5f7fa;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  color: #303133;
  font-size: 28rpx;
  line-height: 1.5;
  box-sizing: border-box;
}

view,
scroll-view,
text,
input,
textarea,
button {
  box-sizing: border-box;
}

/* 滚动条美化 */
::-webkit-scrollbar {
  width: 0;
  height: 0;
}

/* 消除按钮默认样式 */
button::after {
  border: none;
}
</style>
