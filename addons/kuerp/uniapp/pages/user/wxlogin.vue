<template>
  <view class="page">
    <view class="login-wrap">
      <view class="logo-area">
        <u-icon name="account" size="80" color="#4A90D9" />
        <text class="logo-title">欢迎回来</text>
        <text class="logo-desc">请输入账号密码登录</text>
      </view>

      <view class="form-area">
        <u--form>
          <u-form-item label="账号" borderBottom :label-width="70">
            <u--input v-model="username" placeholder="请输入手机号/账号" border="none" />
          </u-form-item>
          <u-form-item label="密码" borderBottom :label-width="70">
            <u--input v-model="password" placeholder="请输入密码" password border="none" />
          </u-form-item>
        </u--form>
      </view>

      <view class="login-btn-wrap">
        <u-button type="primary" shape="circle" :loading="loading" :disabled="loading" @click="handleLogin">
          登 录
        </u-button>
      </view>

      <view class="tip-area">
        <text class="tip-text">账号由管理员在后台分配，如有疑问请联系管理员</text>
      </view>
    </view>
  </view>
</template>

<script>
import { userApi } from '@/api/index.js'
import { setToken } from '@/api/request.js'

export default {
  data() {
    return {
      username: '',
      password: '',
      loading: false,
    }
  },
  methods: {
    async handleLogin() {
      if (!this.username.trim()) {
        uni.showToast({ title: '请输入账号', icon: 'none' })
        return
      }
      if (!this.password || this.password.length < 6) {
        uni.showToast({ title: '密码至少6位', icon: 'none' })
        return
      }
      if (this.loading) return

      this.loading = true
      try {
        const res = await userApi.loginByPwd(this.username.trim(), this.password)
        if (res && res.userinfo) {
          setToken(res.userinfo.token || '')
          const app = getApp()
          if (app) {
            app.globalData.userInfo = res.userinfo
          }
          uni.showToast({ title: '登录成功', icon: 'success' })
          setTimeout(() => {
            uni.switchTab({ url: '/pages/index/index' })
          }, 800)
        } else {
          uni.showToast({ title: '登录失败，请重试', icon: 'none' })
        }
      } catch (e) {
        uni.showToast({ title: typeof e === 'string' ? e : '登录失败', icon: 'none' })
      } finally {
        this.loading = false
      }
    },
  },
}
</script>

<style scoped>
.page {
  min-height: 100vh;
  background: #fff;
  display: flex;
  flex-direction: column;
}
.login-wrap {
  flex: 1;
  padding: 160rpx 48rpx 60rpx;
  display: flex;
  flex-direction: column;
}
.logo-area {
  text-align: center;
  margin-bottom: 80rpx;
}
.logo-title {
  font-size: 44rpx;
  font-weight: 700;
  color: #303133;
  display: block;
  margin-top: 24rpx;
}
.logo-desc {
  font-size: 26rpx;
  color: #909399;
  margin-top: 12rpx;
  display: block;
}

.form-area {
  margin-bottom: 48rpx;
}

.login-btn-wrap {
  margin-bottom: 32rpx;
}

.tip-area {
  text-align: center;
  padding: 0 20rpx;
}
.tip-text {
  font-size: 22rpx;
  color: #c0c4cc;
}
</style>
