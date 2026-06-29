<template>
  <view class="page" v-if="order">
    <!-- 状态头 -->
    <view class="status-header" :style="{ background: statusInfo.bg }">
      <view class="status-icon-wrap">
        <u-icon :name="statusInfo.icon" size="56" color="#fff" />
      </view>
      <view class="status-content">
        <text class="status-title">{{ statusInfo.text }}</text>
        <text class="status-desc">{{ statusInfo.desc }}</text>
      </view>
    </view>

    <!-- 收货信息 -->
    <view class="section-card" v-if="order.receiving_address">
      <view class="address-row">
        <u-icon name="map" size="36" color="#4A90D9" />
        <view class="address-info">
          <text class="receiver-address">{{ order.receiving_address }}</text>
        </view>
      </view>
    </view>

    <!-- 商品信息 -->
    <view class="section-card">
      <view class="section-header">
        <text class="section-title">商品信息</text>
      </view>
      <u-divider half-width="20" :hairline="true"></u-divider>
      <view class="goods-item" v-for="oi in (order.items || [])" :key="oi.id">
        <image
          class="goods-thumb"
          :src="getImgUrl(oi.goods_image)"
          mode="aspectFill"
          v-if="oi.goods_image"
        />
        <view class="goods-info">
          <text class="goods-name u-line-1">{{ oi.goods_note || oi.goods_code }}</text>
          <view class="goods-meta">
            <text class="goods-price">¥{{ oi.unit_price }}</text>
            <text class="goods-qty">× {{ oi.number }}</text>
            <text class="goods-subtotal">¥{{ oi.all_money }}</text>
          </view>
        </view>
      </view>
      <view class="total-row">
        <text>商品合计</text>
        <text class="total-price">¥{{ order.all_money }}</text>
      </view>
    </view>

    <!-- 订单信息 -->
    <view class="section-card">
      <view class="section-header">
        <text class="section-title">订单信息</text>
      </view>
      <u-divider half-width="20" :hairline="true"></u-divider>
      <view class="info-list">
        <view class="info-row">
          <text class="info-label">订单编号</text>
          <text class="info-value">{{ order.purchase_code }}</text>
        </view>
        <view class="info-row" v-if="order.source">
          <text class="info-label">订单来源</text>
          <text class="info-value">{{ order.source === 'miniapp' ? '小程序端' : '后台' }}</text>
        </view>
        <!-- 已发货: 显示关联销货单 -->
        <view class="info-row" v-for="s in (order.sales_list || [])" :key="s.purchase_code">
          <text class="info-label">关联销货单</text>
          <text class="info-value" style="color:#2979ff;">{{ s.purchase_code }}</text>
        </view>
        <view class="info-row">
          <text class="info-label">订单金额</text>
          <text class="info-value price-value">¥{{ order.all_money }}</text>
        </view>
        <view class="info-row">
          <text class="info-label">创建时间</text>
          <text class="info-value">{{ formatTime(order.createtime) }}</text>
        </view>
        <view class="info-row" v-if="order.examine_time">
          <text class="info-label">审核时间</text>
          <text class="info-value">{{ formatTime(order.examine_time) }}</text>
        </view>
        <view class="info-row" v-if="order.note">
          <text class="info-label">备注</text>
          <text class="info-value remark-value">{{ order.note }}</text>
        </view>
      </view>
    </view>

    <!-- 操作 -->
    <view class="action-bar" v-if="canCancel">
      <u-button type="info" plain hairline shape="circle" @click="cancelOrder">取消订单</u-button>
    </view>
  </view>

  <!-- 加载中 -->
  <view class="page" v-else>
    <u-skeleton rows="5" loading :title="false" />
  </view>
</template>

<script>
import { orderApi } from '../../api'
import config from '../../config'

const STATUS_MAP = {
  pending_review: { text: '待审核', desc: '订单已提交，等待后台审核', bg: '#F5A623', icon: 'clock' },
  pending_ship: { text: '待发货', desc: '订单已审核，仓库备货中', bg: 'linear-gradient(135deg, #4A90D9, #357ABD)', icon: 'checkmark-circle' },
  partial_shipped: { text: '部分发货', desc: '部分商品已发出', bg: 'linear-gradient(135deg, #67C23A, #529b2e)', icon: 'car' },
  shipped: { text: '已发货', desc: '商品已全部发出', bg: 'linear-gradient(135deg, #2c8cff, #1a6dcc)', icon: 'car' },
  cancelled: { text: '已取消', desc: '订单已取消', bg: 'linear-gradient(135deg, #909399, #72767b)', icon: 'close-circle' },
}

export default {
  data() {
    return { order: null, orderId: 0 }
  },
  computed: {
    statusInfo() {
      const st = this.order ? this.order.display_status : ''
      return STATUS_MAP[st] || STATUS_MAP['pending_review']
    },
    canCancel() {
      return this.order && this.order.display_status === 'pending_review'
    },
  },
  onLoad(options) { this.orderId = Number(options.id) || 0; if (this.orderId) this.loadDetail() },
  onShow() { if (this.orderId) this.loadDetail() },
  methods: {
    async loadDetail() {
      try {
        const res = await orderApi.detail(this.orderId)
        this.order = res.order || res
      } catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
    formatTime(t) {
      if (!t) return ''
      const d = new Date(t * 1000)
      const pad = (n) => String(n).padStart(2, '0')
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
    },
    getImgUrl(path) {
      if (!path) return ''
      if (path.startsWith('http')) return path
      return config.imgBaseUrl + path
    },
    async cancelOrder() {
      try {
        await orderApi.cancel(this.orderId)
        uni.showToast({ title: '订单已取消', icon: 'none' })
        this.loadDetail()
      } catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
  },
}
</script>

<style scoped>
.page { min-height: 100vh; background: #f5f7fa; padding-bottom: 40rpx; }

.status-header {
  display: flex; align-items: center; padding: 60rpx 32rpx 40rpx; color: #fff;
}
.status-icon-wrap { margin-right: 24rpx; }
.status-title { font-size: 36rpx; font-weight: 700; display: block; }
.status-desc { font-size: 24rpx; opacity: 0.85; margin-top: 8rpx; display: block; }

.section-card { background: #fff; margin: 20rpx; border-radius: 16rpx; padding: 24rpx; }
.section-title { font-size: 28rpx; font-weight: 600; color: #303133; }

.address-row { display: flex; align-items: flex-start; gap: 16rpx; }
.address-info { flex: 1; }
.receiver-address { font-size: 26rpx; color: #606266; line-height: 1.6; }

.goods-item { display: flex; align-items: flex-start; padding: 12rpx 0; }
.goods-item + .goods-item { border-top: 1rpx solid #f5f7fa; }
.goods-thumb { width: 120rpx; height: 120rpx; border-radius: 8rpx; flex-shrink: 0; margin-right: 16rpx; background: #f0f0f0; }
.goods-info { flex: 1; min-width: 0; }
.goods-name { font-size: 26rpx; color: #303133; display: block; margin-bottom: 8rpx; }
.goods-meta { display: flex; gap: 16rpx; font-size: 24rpx; color: #909399; }
.goods-subtotal { color: #303133; font-weight: 500; margin-left: auto; }

.total-row {
  display: flex; justify-content: space-between; padding-top: 16rpx; margin-top: 8rpx;
  border-top: 2rpx solid #f5f7fa; font-size: 28rpx; font-weight: 600; color: #303133;
}
.total-price { color: #f56c6c; font-size: 32rpx; }

.info-list { margin-top: 8rpx; }
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 10rpx 0; }
.info-label { font-size: 24rpx; color: #909399; flex-shrink: 0; }
.info-value { font-size: 24rpx; color: #303133; text-align: right; }
.price-value { color: #f56c6c; font-weight: 500; }
.remark-value { max-width: 400rpx; }

.action-bar { display: flex; justify-content: center; padding: 32rpx 20rpx; gap: 20rpx; }
</style>
