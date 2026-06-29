<template>
  <view class="page">
    <view class="register-wrap">
      <view class="logo-area">
        <u-icon name="edit-pen" size="64" color="#4A90D9" />
        <text class="logo-title">创建账号</text>
        <text class="logo-desc">注册即享快速下单体验</text>
      </view>

      <view class="form-area">
        <u--form>
          <u-form-item label="昵称" borderBottom :label-width="70">
            <u--input v-model="nickname" placeholder="输入昵称（选填）" border="none" />
          </u-form-item>
          <u-form-item label="手机" borderBottom :label-width="70">
            <u--input v-model="mobile" placeholder="输入手机号" type="number" maxlength="11" border="none" />
          </u-form-item>
          <u-form-item label="验证码" borderBottom :label-width="70">
            <template slot="right">
              <u-button
                type="primary"
                size="mini"
                :plain="true"
                :disabled="codeSending"
                @click="handleSendCode"
              >{{ codeSending ? `${codeCountdown}s` : '获取验证码' }}</u-button>
            </template>
            <u--input v-model="smsCode" placeholder="输入验证码" type="number" maxlength="6" border="none" />
          </u-form-item>
          <u-form-item label="密码" borderBottom :label-width="70">
            <u--input v-model="password" placeholder="设置密码（至少6位）" password border="none" />
          </u-form-item>
          <u-form-item label="确认" borderBottom :label-width="70">
            <u--input v-model="confirmPwd" placeholder="再次输入密码" password border="none" />
          </u-form-item>
        </u--form>
      </view>

      <view class="register-btn-wrap">
        <u-button type="primary" shape="circle" :loading="loading" :disabled="loading" @click="handleRegister">
          注 册
        </u-button>
      </view>

      <view class="links">
        <text class="link" @click="goLogin">已有账号？去登录</text>
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
      nickname: '',
      mobile: '',
      smsCode: '',
      password: '',
      confirmPwd: '',
      loading: false,
      codeSending: false,
      codeCountdown: 60,
    }
  },
  methods: {
    async handleSendCode() {
      if (!this.mobile || !/^1\d{10}$/.test(this.mobile)) {
        uni.showToast({ title: '请输入正确手机号', icon: 'none' })
        return
      }
      if (this.codeSending) return
      this.codeSending = true
      try {
        const res = await userApi.sendSmsCode(this.mobile)
        uni.showToast({ title: '验证码已发送' + (res.code ? `(${res.code})` : ''), icon: 'none' })
        this.codeCountdown = 60
        const timer = setInterval(() => {
          this.codeCountdown--
          if (this.codeCountdown <= 0) {
            clearInterval(timer)
            this.codeSending = false
          }
        }, 1000)
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
        this.codeSending = false
      }
    },
    async handleRegister() {
      if (!this.nickname) {
        this.nickname = '用户' + this.mobile.substr(-4)
      }
      if (!this.mobile || !/^1\d{10}$/.test(this.mobile)) {
        uni.showToast({ title: '请输入正确手机号', icon: 'none' })
        return
      }
      if (!this.smsCode || this.smsCode.length < 6) {
        uni.showToast({ title: '请输入验证码', icon: 'none' })
        return
      }
      if (!this.password || this.password.length < 6) {
        uni.showToast({ title: '密码至少6位', icon: 'none' })
        return
      }
      if (this.password !== this.confirmPwd) {
        uni.showToast({ title: '两次密码输入不一致', icon: 'none' })
        return
      }
      if (this.loading) return
      this.loading = true
      try {
        const res = await userApi.register({
          mobile: this.mobile,
          password: this.password,
          sms_code: this.smsCode,
          nickname: this.nickname,
        })
        setToken(res.userinfo.token)
        getApp().globalData.userInfo = res.userinfo
        uni.showToast({ title: '注册成功' })
        setTimeout(() => uni.switchTab({ url: '/pages/index/index' }), 800)
      } catch (e) {
        uni.showToast({ title: e || '注册失败', icon: 'none' })
      } finally {
        this.loading = false
      }
    },
    goLogin() {
    // #ifdef MP-WEIXIN
			uni.navigateTo({
				url: '/pages/user/login'
			})
			// #endif
			// #ifndef MP-WEIXIN
			uni.navigateTo({
				url: '/pages/user/login'
			})
			// #endif
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
.register-wrap {
  flex: 1;
  padding: 60rpx 48rpx 60rpx;
  display: flex;
  flex-direction: column;
}
.logo-area { text-align: center; margin-bottom: 60rpx; }
.logo-title { font-size: 36rpx; font-weight: 700; color: #303133; display: block; margin-top: 16rpx; }
.logo-desc { font-size: 26rpx; color: #909399; margin-top: 10rpx; display: block; }

.form-area { margin-bottom: 40rpx; }

.register-btn-wrap { margin-bottom: 24rpx; }

.links { text-align: center; }
.link { font-size: 26rpx; color: #4A90D9; }
</style>
