<template>
  <view class="page">
    <!-- 购物车列表 -->
    <view class="cart-content" v-if="list.length > 0">
      <view class="cart-item" v-for="item in list" :key="item.id">
        <u-checkbox :checked="item.checked" shape="square" active-color="#4A90D9" @change="(val) => item.checked = val" />
        <view class="item-img">
          <image v-if="item.goods_image" :src="$img(item.goods_image)" mode="aspectFill" style="width:100%;height:100%;border-radius:12rpx;" />
          <u-icon v-else name="shopping-cart" size="40" color="#c0c4cc" />
        </view>
        <view class="item-content">
          <view class="item-top">
            <text class="item-name u-line-1">{{ item.goods_name || '未知商品' }}</text>
          </view>
          <view class="item-spec" v-if="item.attr_name || item.unit_name">
            <text class="item-spec-text">{{ item.attr_name }}{{ item.attr_name && item.unit_name ? ' / ' : '' }}{{ item.unit_name }}</text>
          </view>
          <view class="item-bottom">
            <u-number-box v-model="item.quantity" :min="1" :max="999" @change="(e) => onQtyChange(item, e)"></u-number-box>
            <view class="item-subtotal">
              <text class="subtotal-label">小计</text>
              <text class="subtotal-price">¥{{ (item.show_price * item.quantity).toFixed(2) }}</text>
            </view>
          </view>
        </view>
        <view class="item-del" @click.stop="removeItem(item.id)">
          <u-icon name="trash" size="32" color="#c0c4cc" />
        </view>
      </view>

      <!-- 售罄提示 -->
      <view class="sold-out-bar" v-if="hasSellOut">
        <u-icon name="info-circle" size="24" color="#e6a23c" />
        <text class="sold-out-text">部分商品已售罄，请移除以继续结算</text>
      </view>

      <!-- 清空 -->
      <view class="clear-bar" @click="clearCart">
        <u-icon name="trash" size="28" color="#909399" />
        <text class="clear-text">清空订货单</text>
      </view>
    </view>

    <!-- 空状态 -->
    <u-empty mode="car" text="订货单是空的" v-else>
      <u-button type="primary" shape="circle" size="medium" slot="bottom" @click="goIndex">去逛逛</u-button>
    </u-empty>

    <!-- 底部结算栏 -->
    <view class="bottom-bar safe-area-bottom" v-if="list.length > 0">
      <view class="select-all-area" @click="toggleSelectAll">
        <u-checkbox-group
          v-model="selectedIds"
          @change="toggleSelectAll"
        >

        <u-checkbox  :checked="isAllSelected" shape="square" active-color="#4A90D9" />
        </u-checkbox-group>

        
        <text class="select-all-text">全选</text>
      </view>
      <view class="total-area">
        <text class="total-label">合计</text>
        <text class="total-price">¥{{ totalAmount }}</text>
      </view>
      <view class="btn-checkout" :class="{ 'btn-disabled': selectedIds.length === 0 }" @click="checkout">
        <text>去结算</text>
        <view class="checkout-badge">{{ selectedIds.length }}</view>
      </view>
    </view>

  </view>
</template>

<script>
import { cartApi } from '../../api'
export default {
  data() {
    return {
      list: [],
    }
  },
  computed: {
    selectedIds() {
      return this.list.filter(i => i.checked).map(i => i.id)
    },
    isAllSelected() {
      return this.list.length > 0 && this.list.every(i => i.checked)
    },
    totalAmount() {
      return this.list
        .filter(i => i.checked)
        .reduce((s, i) => s + ((i.show_price || 0) * i.quantity), 0)
        .toFixed(2)
    },
    hasSellOut() {
      return this.list.some(i => i.sell_out)
    },
  },
  onShow() { this.loadCart() },
  onPullDownRefresh() { this.loadCart().finally(() => uni.stopPullDownRefresh()) },
  methods: {
    async loadCart() {
      try {
        const res = await cartApi.index()
        this.list = (res.list || []).filter(i => !i.sell_out).map(i => ({ ...i, checked: true }))
      } catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
    toggleSelectAll() {
      const val = !this.isAllSelected
      this.list.forEach(i => { i.checked = val })
    },
    async onQtyChange(item, e) {
      try { await cartApi.edit(item.id, e.value) } catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
    async removeItem(id) {
      try {
        await cartApi.delete(id)
        this.list = this.list.filter(i => i.id !== id)
      } catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
    async clearCart() {
      uni.showModal({
        title: '提示', content: '确定清空订货单吗？',
        success: async (res) => {
          if (res.confirm) {
            try { await cartApi.clear(); this.list = []; uni.showToast({ title: '已清空', icon: 'none' }) }
            catch (e) { uni.showToast({ title: e, icon: 'none' }) }
          }
        }
      })
    },
    checkout() {
      if (this.selectedIds.length === 0) {
        uni.showToast({ title: '请选择商品', icon: 'none' })
        return
      }
      uni.navigateTo({
        url: '/pages/order/checkout?ids=' + this.selectedIds.join(',')
      })
    },
    goIndex() { uni.switchTab({ url: '/pages/index/index' }) },
  },
}
</script>

<style scoped>
.page { padding-bottom: 180rpx; min-height: 100vh; background: #f5f7fa; }
.cart-content { padding: 20rpx; }
.cart-item {
  display: flex; align-items: center; background: #fff;
  border-radius: 16rpx; padding: 24rpx; margin-bottom: 16rpx; position: relative;
}
.cart-item .u-checkbox { margin: 0 8rpx 0 0; flex-shrink: 0; }
.item-img {
  width: 130rpx; height: 130rpx; background: #f5f7fa;
  border-radius: 12rpx; display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; margin-right: 20rpx; overflow: hidden;
}
.item-content { flex: 1; min-height: 100rpx; }
.item-top { display: flex; justify-content: space-between; align-items: flex-start; }
.item-name { font-size: 26rpx; color: #303133; font-weight: 500; flex: 1; }
.item-price { font-size: 28rpx; color: #f56c6c; font-weight: 600; margin-left: 16rpx; }
.item-spec { margin: 6rpx 0; }
.item-spec-text { font-size: 22rpx; color: #c0c4cc; }
.item-bottom { display: flex; align-items: center; justify-content: space-between; margin-top: 16rpx; }
.item-subtotal { display: flex; align-items: baseline; gap: 6rpx; }
.subtotal-label { font-size: 22rpx; color: #909399; }
.subtotal-price { font-size: 26rpx; color: #f56c6c; font-weight: 600; }
.item-del { position: absolute; top: 16rpx; right: 16rpx; padding: 8rpx; }

.sold-out-bar { display: flex; align-items: center; justify-content: center; gap: 8rpx; padding: 16rpx; }
.sold-out-text { font-size: 24rpx; color: #e6a23c; }

.clear-bar { display: flex; align-items: center; justify-content: center; gap: 8rpx; padding: 24rpx; }
.clear-text { font-size: 24rpx; color: #909399; }

.bottom-bar {
  position: fixed; bottom: 90rpx; left: 0; right: 0;
  background: #fff; display: flex; align-items: center; justify-content: space-between;
  padding: 16rpx 24rpx; border-top: 1rpx solid #f2f2f2;
}
.select-all-area { display: flex; align-items: center; gap: 8rpx; padding: 8rpx 0; flex-shrink: 0; }
.select-all-text { font-size: 26rpx; color: #606266; }
.total-area { display: flex; align-items: center; gap: 8rpx; }
.total-label { font-size: 26rpx; color: #606266; }
.total-price { font-size: 40rpx; color: #f56c6c; font-weight: 700; }
.btn-checkout { display: flex; align-items: center; gap: 8rpx; padding: 20rpx 48rpx; background: #4A90D9; color: #fff; border-radius: 44rpx; font-size: 28rpx; font-weight: 500; }
.btn-disabled { opacity: 0.5; }
.checkout-badge { background: rgba(255,255,255,0.2); border-radius: 20rpx; padding: 2rpx 14rpx; font-size: 22rpx; }
</style>
