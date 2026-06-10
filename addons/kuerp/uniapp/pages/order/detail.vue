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
    <view class="section-card" v-if="order.receiver_name">
      <view class="address-row">
        <u-icon name="map" size="36" color="#4A90D9" />
        <view class="address-info">
          <view class="address-top">
            <text class="receiver-name">{{ order.receiver_name }}</text>
            <text class="receiver-mobile">{{ order.receiver_mobile }}</text>
          </view>
          <text class="receiver-address">{{ order.receiver_province }} {{ order.receiver_city }} {{ order.receiver_district }} {{ order.receiver_address }}</text>
        </view>
      </view>
    </view>

    <!-- 配送信息 -->
    <view class="section-card" v-if="order.delivery && order.delivery.length > 0">
      <view class="section-header">
        <view class="section-title">配送信息</view>
      </view>
      <u-divider half-width="20" :hairline="true"></u-divider>
      <view class="delivery-item" v-for="d in order.delivery" :key="d.id">
        <view class="delivery-top">
          <text class="delivery-type">{{ d.delivery_type == 'logistics' ? '物流配送' : '专业配送' }}</text>
          <u-button v-if="d.delivery_type == 'logistics'" type="primary" size="mini" plain hairline shape="circle" class="logistics-btn" @click="goLogistics(d.id)">查看物流</u-button>
        </view>
        <text class="delivery-no" v-if="d.delivery_no">单号: {{ d.delivery_no }}</text>
        <text class="delivery-company" v-if="d.express_company">{{ d.express_company }} {{ d.express_no }}</text>
        <text class="delivery-carrier" v-if="d.carrier_name">配送员: {{ d.carrier_name }} {{ d.carrier_mobile }}</text>
        <text class="delivery-time" v-if="d.shipped_time">发货时间: {{ formatTime(d.shipped_time) }}</text>
      </view>
    </view>

    <!-- 商品信息 -->
    <view class="section-card">
      <view class="section-header">
        <text class="section-title">商品信息</text>
      </view>
      <u-divider half-width="20" :hairline="true"></u-divider>
      <view class="goods-item" v-for="oi in (order.goods || [])" :key="oi.id">
        <view class="goods-icon">
          <image v-if="oi.goods_image" :src="$img(oi.goods_image)" mode="aspectFill" style="width:100%;height:100%;border-radius:8rpx;" />
          <u-icon v-else name="shopping-cart" size="28" color="#c0c4cc" />
        </view>
        <view class="goods-detail">
          <text class="goods-name">{{ oi.goods_name }}</text>
          <text class="goods-spec" v-if="oi.attr_name">规格: {{ oi.attr_name }}</text>
          <text class="goods-spec" v-if="oi.unit_name">单位: {{ oi.unit_name }}</text>
          <text class="goods-price">¥{{ oi.unit_price }} × {{ oi.quantity }}</text>
        </view>
        <text class="goods-amount">¥{{ oi.all_money }}</text>
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
          <text class="info-value">{{ order.order_no }}</text>
        </view>
        <view class="info-row">
          <text class="info-label">配送方式</text>
          <text class="info-value">{{ order.delivery_type == 'logistics' ? '物流配送' : '专业配送' }}</text>
        </view>
        <view class="info-row">
          <text class="info-label">商品金额</text>
          <text class="info-value price-value">¥{{ order.total_amount }}</text>
        </view>
        <view class="info-row" v-if="order.preferential > 0">
          <text class="info-label">优惠</text>
          <text class="info-value price-value">-¥{{ order.preferential }}</text>
        </view>
        <view class="info-row">
          <text class="info-label">实付金额</text>
          <text class="info-value price-value">¥{{ order.payable_amount || order.total_amount }}</text>
        </view>
        <view class="info-row">
          <text class="info-label">下单时间</text>
          <text class="info-value">{{ formatTime(order.createdtime) }}</text>
        </view>
        <view class="info-row" v-if="order.confirm_time">
          <text class="info-label">确认时间</text>
          <text class="info-value">{{ formatTime(order.confirm_time) }}</text>
        </view>
        <view class="info-row" v-if="order.complete_time">
          <text class="info-label">完成时间</text>
          <text class="info-value">{{ formatTime(order.complete_time) }}</text>
        </view>
        <view class="info-row" v-if="order.sales_code">
          <text class="info-label">销货单号</text>
          <text class="info-value">{{ order.sales_code }}</text>
        </view>
        <view class="info-row" v-if="order.remark">
          <text class="info-label">备注</text>
          <text class="info-value remark-value">{{ order.remark }}</text>
        </view>
      </view>
    </view>

    <!-- 操作 -->
    <view class="action-bar">
      <u-button type="info" plain hairline shape="circle" v-if="order.status === 'pending_confirm'" @click="cancelOrder">取消订单</u-button>
      <u-button type="success" shape="circle" v-if="order.status === 'shipped'" @click="confirmReceived">确认收货</u-button>
    </view>
  </view>

  <!-- 加载中 -->
  <view class="page" v-else>
    <u-skeleton rows="5" loading :title="false" />
  </view>
</template>

<script>
import { orderApi } from '../../api'

const STATUS_MAP = {
  pending_confirm: { text: '待确认', desc: '等待后台管理员确认订单', bg: 'linear-gradient(135deg, #E6A23C, #d4891c)', icon: 'clock' },
  confirmed: { text: '已确认', desc: '订单已确认，等待发货', bg: 'linear-gradient(135deg, #4A90D9, #357ABD)', icon: 'checkmark-circle' },
  shipped: { text: '已发货', desc: '商品已发出，请注意查收', bg: 'linear-gradient(135deg, #2c8cff, #1a6dcc)', icon: 'car' },
  received: { text: '已收货', desc: '商品已签收', bg: 'linear-gradient(135deg, #67C23A, #529b2e)', icon: 'checkmark-circle-fill' },
  completed: { text: '已完成', desc: '订单已完成', bg: 'linear-gradient(135deg, #67C23A, #529b2e)', icon: 'checkmark-circle-fill' },
  cancelled: { text: '已取消', desc: '订单已取消', bg: 'linear-gradient(135deg, #909399, #72767b)', icon: 'close-circle' },
  returned: { text: '已退货', desc: '订单已退货', bg: 'linear-gradient(135deg, #f56c6c, #d33b3b)', icon: 'close-circle' },
}

export default {
  data() {
    return { order: null, orderId: 0 }
  },
  computed: { statusInfo() { return STATUS_MAP[this.order && this.order.status] || {} } },
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
    goLogistics(deliveryId) {
      uni.navigateTo({ url: '/pages/order/logistics?delivery_id=' + deliveryId })
    },
    async cancelOrder() {
      try { await orderApi.cancel(this.orderId); uni.showToast({ title: '订单已取消', icon: 'none' }); this.loadDetail() }
      catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
    async confirmReceived() {
      uni.showModal({
        title: '确认收货', content: '是否确认已收到商品？',
        success: async (res) => {
          if (res.confirm) {
            try { await orderApi.received(this.orderId); uni.showToast({ title: '确认收货成功' }); this.loadDetail() }
            catch (e) { uni.showToast({ title: e, icon: 'none' }) }
          }
        }
      })
    },
  },
}
</script>

<style scoped>
.page { min-height: 100vh; background: #f5f7fa; padding-bottom: 40rpx; }

/* 状态头 */
.status-header { display: flex; align-items: center; padding: 40rpx 32rpx; color: #fff; }
.status-icon-wrap { width: 80rpx; height: 80rpx; display: flex; align-items: center; justify-content: center; margin-right: 20rpx; flex-shrink: 0; }
.status-content { display: flex; flex-direction: column; }
.status-title { font-size: 36rpx; font-weight: 700; }
.status-desc { font-size: 24rpx; opacity: 0.85; margin-top: 6rpx; }

.section-card { background: #fff; margin: 20rpx; border-radius: 16rpx; padding: 24rpx; }
.section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16rpx; }
.section-title { font-size: 28rpx; font-weight: 600; color: #303133; }
.logistics-btn { padding: 0 16rpx !important; font-size: 22rpx !important; height: 48rpx !important; line-height: 48rpx !important; width: 200rpx; }

/* 收货地址 */
.address-row { display: flex; gap: 16rpx; }
.address-info { flex: 1; }
.address-top { display: flex; align-items: center; gap: 12rpx; margin-bottom: 6rpx; }
.receiver-name { font-size: 30rpx; font-weight: 600; color: #303133; }
.receiver-mobile { font-size: 26rpx; color: #606266; }
.receiver-address { font-size: 26rpx; color: #909399; line-height: 1.5; }

/* 配送 */
.delivery-item { padding: 8rpx 0; }
.delivery-top { display: flex; align-items: center; justify-content: space-between; }
.delivery-type { font-size: 26rpx; font-weight: 500; color: #303133; display: block; }
.delivery-no, .delivery-company, .delivery-carrier, .delivery-time { font-size: 24rpx; color: #909399; display: block; margin-top: 4rpx; }

/* 商品 */
.goods-item { display: flex; align-items: center; padding: 16rpx 0; }
.goods-item + .goods-item { border-top: 1rpx solid #f5f7fa; }
.goods-icon { width: 64rpx; height: 64rpx; background: #f5f7fa; border-radius: 8rpx; display: flex; align-items: center; justify-content: center; margin-right: 16rpx; flex-shrink: 0; overflow: hidden; }
.goods-detail { flex: 1; }
.goods-name { font-size: 26rpx; color: #303133; display: block; }
.goods-spec { font-size: 22rpx; color: #c0c4cc; margin-top: 2rpx; display: block; }
.goods-price { font-size: 22rpx; color: #909399; margin-top: 4rpx; display: block; }
.goods-amount { font-size: 26rpx; color: #f56c6c; font-weight: 500; }

/* 订单信息 */
.info-list { }
.info-row { display: flex; justify-content: space-between; padding: 14rpx 0; }
.info-row + .info-row { border-top: 1rpx solid #f5f7fa; }
.info-label { font-size: 26rpx; color: #909399; flex-shrink: 0; }
.info-value { font-size: 26rpx; color: #303133; text-align: right; max-width: 60%; }
.price-value { color: #f56c6c; font-weight: 700; }
.remark-value { color: #606266; }

/* 操作 */
.action-bar { display: flex; justify-content: center; gap: 20rpx; padding: 20rpx; }
</style>
