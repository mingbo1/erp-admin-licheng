<template>
  <view class="page">
    <view class="login-wrap">
      <view class="logo-area">
        <u-icon name="account" size="80" color="#4A90D9" />
        <text class="logo-title">欢迎回来</text>
        <text class="logo-desc">请输入您的账号信息登录</text>
      </view>

      <view class="form-area">
        <u--form>
          <u-form-item label="手机号" borderBottom :label-width="70">
            <u--input v-model="mobile" placeholder="请输入手机号" type="number" maxlength="11" border="none" />
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
import { userApi } from '../../api'
import { setToken } from '../../api/request'

export default {
  data() {
    return {
      mobile: '',
      password: '',
      loading: false,
    }
  },
  methods: {
    async handleLogin() {
      if (!this.mobile || !/^1\d{10}$/.test(this.mobile)) {
        uni.showToast({ title: '请输入正确手机号', icon: 'none' })
        return
      }
      if (!this.password || this.password.length < 6) {
        uni.showToast({ title: '密码至少6位', icon: 'none' })
        return
      }
      if (this.loading) return
      this.loading = true
      try {
        const res = await userApi.loginByPwd(this.mobile, this.password)
        setToken(res.userinfo.token)
        getApp().globalData.userInfo = res.userinfo
        uni.showToast({ title: '登录成功' })
        setTimeout(() => uni.switchTab({ url: '/pages/index/index' }), 800)
      } catch (e) {
        uni.showToast({ title: e || '登录失败', icon: 'none' })
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
  padding: 100rpx 48rpx 60rpx;
  display: flex;
  flex-direction: column;
}
.logo-area { text-align: center; margin-bottom: 80rpx; }
.logo-title { font-size: 40rpx; font-weight: 700; color: #303133; display: block; margin-top: 20rpx; }
.logo-desc { font-size: 26rpx; color: #909399; margin-top: 12rpx; display: block; }

.form-area { margin-bottom: 48rpx; }

.login-btn-wrap { margin-bottom: 32rpx; }

.tip-area { text-align: center; padding: 0 20rpx; }
.tip-text { font-size: 22rpx; color: #c0c4cc; }
</style>
