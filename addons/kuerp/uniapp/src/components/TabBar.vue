<template>
  <view class="bottom-nav safe-area-bottom">
    <view
      class="nav-item"
      v-for="item in tabs"
      :key="item.key"
      :class="{ active: active === item.key }"
      @click="goPage(item.key)"
    >
      <view class="nav-icon-wrap">
        <u-icon :name="item.icon" :size="30" :color="active === item.key ? $u.color.primary : '#909399'" />
      </view>
      <text class="nav-label">{{ item.label }}</text>
    </view>
  </view>
</template>

<script>
export default {
  props: {
    active: { type: String, default: 'index' },
  },
  data() {
    return {
      tabs: [
        { key: 'index', label: '首页', icon: 'home', url: '/pages/index/index' },
        { key: 'cart', label: '订货单', icon: 'shopping-cart', url: '/pages/cart/cart' },
        { key: 'order', label: '订单', icon: 'order', url: '/pages/order/list' },
        { key: 'user', label: '我的', icon: 'account', url: '/pages/user/info' },
      ],
    }
  },
  methods: {
    goPage(key) {
      if (key === this.active) return
      const tab = this.tabs.find((t) => t.key === key)
      if (tab) uni.reLaunch({ url: tab.url })
    },
  },
}
</script>

<style scoped>
.bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 100rpx;
  background: #fff;
  display: flex;
  align-items: center;
  border-top: 1rpx solid #f2f2f2;
  z-index: 999;
  padding-bottom: env(safe-area-inset-bottom);
}
.nav-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  padding-bottom: 8rpx;
}
.nav-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 40rpx;
}
.nav-label {
  font-size: 22rpx;
  color: #909399;
  margin-top: 4rpx;
  line-height: 1;
  transition: color 0.2s;
}
.nav-item.active .nav-label {
  color: #4A90D9;
  font-weight: 500;
}
</style>
