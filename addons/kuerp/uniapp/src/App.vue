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
          getApp().globalData.userInfo = res
          return
        } catch (e) {
          uni.removeStorageSync('token')
        }
      }
      getApp().globalData.userInfo = null
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
