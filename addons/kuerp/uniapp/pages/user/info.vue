<template>
  <view class="page">
    <!-- 用户头像卡片 -->
    <view class="profile-header" @click="handleProfileClick">
      <!-- 未登录 -->
      <template v-if="!isLogin">
        <u-avatar icon="account" bg-color="rgba(255,255,255,0.3)" size="80" icon-color="#fff"></u-avatar>
        <view class="profile-info">
          <text class="profile-name">请登录</text>
          <text class="profile-mobile">点击登录，体验更多功能</text>
        </view>
        <u-icon name="arrow-right" size="28" color="rgba(255,255,255,0.6)" />
      </template>
      <!-- 已登录 -->
      <template v-else>
        <u-avatar v-if="avatarUrl" :src="$img(avatarUrl)" bg-color="#4A90D9" size="80" fontSize="36"></u-avatar>
        <u-avatar v-else :text="avatarText" bg-color="#4A90D9" size="80" fontSize="36"></u-avatar>
        <view class="profile-info">
          <text class="profile-name">{{ nickname || '未设置昵称' }}</text>
          <text class="profile-mobile">{{ mobileText }}</text>
        </view>
        <u-icon name="arrow-right" size="28" color="rgba(255,255,255,0.6)" />
      </template>
    </view>

    <!-- 功能入口 -->
    <view class="section-card">
      <u-cell-group :border="false">
        <u-cell icon="order" title="我的订单" isLink :border="false" @click="goOrders"></u-cell>
        <u-cell icon="shopping-cart" title="订货单" isLink :border="false" @click="goCart"></u-cell>
        <!-- <u-cell icon="map" title="收货地址" isLink :border="false" @click="goAddress"></u-cell> -->
        <u-cell icon="edit-pen" title="编辑资料" isLink :border="false" @click="goProfile"></u-cell>
      </u-cell-group>
    </view>

    <!-- 退出 / 登录 -->
    <view class="logout-btn" v-if="isLogin">
      <u-button type="info" plain hairline shape="circle" @click="handleLogout">退出登录</u-button>
    </view>

  </view>
</template>

<script>
import { userApi } from '../../api'
import { setToken } from '../../api/request'
export default {
  data() {
    return { nickname: '', mobile: '', isLogin: false, avatarUrl: '' }
  },
  computed: {
    avatarText() { return (this.nickname || '?').charAt(0).toUpperCase() },
    mobileText() {
      if (this.mobile) return this.mobile.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2')
      return '未绑定手机'
    },
  },
  onShow() { this.loadUserInfo() },
  methods: {
    async loadUserInfo() {
      try {
        const res = await userApi.info()
        if (res && res.mini_user) {
          const mu = res.mini_user || {}
          this.nickname = mu.nickname || ''
          this.mobile = mu.mobile || ''
          this.isLogin = true
          this.avatarUrl = mu.avatar || ''
        } else {
          this.isLogin = false
        }
      } catch (e) {
        this.isLogin = false
      }
    },
    // 点击头像区域：未登录 → 跳转登录页；已登录 → 编辑资料
    handleProfileClick() {
      if (!this.isLogin) {
        // #ifdef MP-WEIXIN
        uni.navigateTo({ url: '/pages/user/login' })
        // #endif
        // #ifndef MP-WEIXIN
        uni.navigateTo({ url: '/pages/user/login' })
        // #endif
        return
      }
      uni.navigateTo({ url: '/pages/user/profile' })
    },
    goOrders() { uni.switchTab({ url: '/pages/order/list' }) },
    goCart() { uni.switchTab({ url: '/pages/cart/cart' }) },
    goAddress() { uni.navigateTo({ url: '/pages/address/list' }) },
    goProfile() { uni.navigateTo({ url: '/pages/user/profile' }) },
    handleLogout() {
      uni.showModal({
        title: '提示', content: '确定退出登录吗？',
        success: (res) => {
          if (res.confirm) {
            setToken('')
            this.isLogin = false
            this.nickname = ''
            this.mobile = ''
            this.avatarUrl = ''
            const app = getApp()
            if (app && app.globalData) { app.globalData.userInfo = null }
            // #ifdef MP-WEIXIN
            uni.reLaunch({ url: '/pages/user/login' })
            // #endif
            // #ifndef MP-WEIXIN
            uni.reLaunch({ url: '/pages/user/login' })
            // #endif
          }
        }
      })
    },
  },
}
</script>

<style scoped>
.page { padding-bottom: 130rpx; min-height: 100vh; background: #f5f7fa; }

.profile-header {
  display: flex; align-items: center; padding: 48rpx 32rpx;
  background: linear-gradient(135deg, #4A90D9, #357ABD);
}
.profile-info { flex: 1; margin-left: 24rpx; }
.profile-name { font-size: 34rpx; font-weight: 600; color: #fff; display: block; }
.profile-mobile { font-size: 24rpx; color: rgba(255,255,255,0.8); margin-top: 6rpx; display: block; }

.section-card { background: #fff; margin: 20rpx; border-radius: 16rpx; overflow: hidden; }

.logout-btn { margin: 0 20rpx; }
</style>
