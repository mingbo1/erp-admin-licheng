<template>
  <view class="page">
    <!-- 发货信息 -->
    <view class="section-card" v-if="delivery">
      <view class="delivery-header">
        <u-icon :name="delivery.delivery_type == 'logistics' ? 'map' : 'car'" size="40" color="#4A90D9" />
        <view class="delivery-type">
          <text class="type-label">{{ delivery.delivery_type == 'logistics' ? '物流配送' : '专业配送' }}</text>
          <text class="type-desc" v-if="delivery.delivery_type == 'logistics'">{{ delivery.express_company }} {{ delivery.express_no }}</text>
          <text class="type-desc" v-else>{{ delivery.carrier_name }} {{ delivery.carrier_mobile }}</text>
        </view>
        <u-tag :text="delivery.status == 'shipped' ? '已发货' : '已签收'" :type="delivery.status == 'shipped' ? 'warning' : 'success'" size="mini" />
      </view>

      <u-divider half-width="20" :hairline="true"></u-divider>

      <view class="info-list">
        <view class="info-row" v-if="delivery.delivery_no">
          <text class="info-label">发货单号</text>
          <text class="info-value">{{ delivery.delivery_no }}</text>
        </view>
        <view class="info-row" v-if="delivery.express_company">
          <text class="info-label">快递公司</text>
          <text class="info-value">{{ delivery.express_company }}</text>
        </view>
        <view class="info-row" v-if="delivery.express_no">
          <text class="info-label">快递单号</text>
          <text class="info-value copy-text" @click="copy(delivery.express_no)">{{ delivery.express_no }} <u-icon name="copy" size="24" color="#4A90D9" /></text>
        </view>
        <view class="info-row" v-if="delivery.carrier_name">
          <text class="info-label">配送员</text>
          <text class="info-value">{{ delivery.carrier_name }}</text>
        </view>
        <view class="info-row" v-if="delivery.carrier_mobile">
          <text class="info-label">联系电话</text>
          <text class="info-value" @click="callPhone(delivery.carrier_mobile)">{{ delivery.carrier_mobile }} <u-icon name="phone" size="24" color="#4A90D9" /></text>
        </view>
        <view class="info-row" v-if="delivery.shipped_time">
          <text class="info-label">发货时间</text>
          <text class="info-value">{{ formatTime(delivery.shipped_time) }}</text>
        </view>
        <view class="info-row" v-if="delivery.remark">
          <text class="info-label">备注</text>
          <text class="info-value">{{ delivery.remark }}</text>
        </view>
      </view>
    </view>

    <!-- 物流轨迹 -->
    <view class="section-card" v-if="delivery && delivery.delivery_type == 'logistics'">
      <view class="section-header">
        <text class="section-title">物流轨迹</text>
      </view>
      <u-divider half-width="20" :hairline="true"></u-divider>

      <view class="trace-list" v-if="traces.length > 0">
        <view class="trace-item" v-for="(t, idx) in traces" :key="idx">
          <view class="trace-dot" :class="{ active: idx === 0 }"></view>
          <view class="trace-line" v-if="idx < traces.length - 1"></view>
          <view class="trace-content">
            <text class="trace-status">{{ t.status }}</text>
            <text class="trace-time">{{ t.time }}</text>
          </view>
        </view>
      </view>

      <u-empty mode="data" text="暂无物流轨迹" v-else-if="traces.length === 0" />
    </view>

    <!-- 专业配送 -->
    <view class="section-card" v-if="delivery && delivery.delivery_type == 'professional'">
      <view class="section-header">
        <text class="section-title">配送信息</text>
      </view>
      <u-divider half-width="20" :hairline="true"></u-divider>
      <view class="professional-info">
        <u-icon name="checkmark-circle" size="48" color="#67C23A" />
        <text class="professional-text">商品已通过专业配送发出，请留意收货</text>
      </view>
    </view>

    <!-- 加载中 -->
    <u-skeleton rows="4" loading v-if="!delivery && loading" :title="false" />

    <!-- 暂无 -->
    <u-empty mode="data" text="暂无物流信息" v-if="!delivery && !loading && loaded" />
  </view>
</template>

<script>
import { deliveryApi } from '../../api'

export default {
  data() {
    return {
      orderId: 0,
      delivery: null,
      traces: [],
      loading: false,
      loaded: false,
    }
  },
  onLoad(options) {
    this.orderId = Number(options.order_id) || 0
    if (this.orderId) this.loadDelivery()
  },
  methods: {
    async loadDelivery() {
      this.loading = true
      try {
        const res = await deliveryApi.index(this.orderId)
        this.delivery = res.delivery || null
        this.traces = res.traces || []
        this.loaded = true
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
        this.loaded = true
      } finally {
        this.loading = false
      }
    },
    formatTime(t) {
      if (!t) return ''
      const d = new Date(t * 1000)
      const pad = (n) => String(n).padStart(2, '0')
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
    },
    copy(text) {
      uni.setClipboardData({ data: text })
    },
    callPhone(phone) {
      uni.makePhoneCall({ phoneNumber: phone })
    },
  },
}
</script>

<style scoped>
.page { min-height: 100vh; background: #f5f7fa; padding-bottom: 40rpx; }

.section-card {
  background: #fff;
  margin: 20rpx;
  border-radius: 16rpx;
  padding: 24rpx;
}

.delivery-header { display: flex; align-items: center; gap: 16rpx; }
.delivery-type { flex: 1; }
.type-label { font-size: 28rpx; font-weight: 600; color: #303133; display: block; }
.type-desc { font-size: 24rpx; color: #909399; margin-top: 4rpx; display: block; }

.info-list { }
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 14rpx 0; }
.info-row + .info-row { border-top: 1rpx solid #f5f7fa; }
.info-label { font-size: 26rpx; color: #909399; flex-shrink: 0; }
.info-value { font-size: 26rpx; color: #303133; text-align: right; max-width: 60%; display: flex; align-items: center; gap: 8rpx; }
.copy-text { color: #4A90D9; }

.section-header { margin-bottom: 16rpx; }
.section-title { font-size: 28rpx; font-weight: 600; color: #303133; }

/* 物流轨迹时间线 */
.trace-list { position: relative; padding-left: 30rpx; }
.trace-item { position: relative; padding-bottom: 36rpx; }
.trace-dot {
  position: absolute; left: -22rpx; top: 6rpx;
  width: 14rpx; height: 14rpx;
  border-radius: 50%;
  background: #c0c4cc;
  z-index: 1;
}
.trace-dot.active { background: #4A90D9; width: 16rpx; height: 16rpx; left: -23rpx; }
.trace-line {
  position: absolute; left: -16rpx; top: 20rpx;
  width: 2rpx; bottom: 0;
  background: #e8e8e8;
}
.trace-content { }
.trace-status { font-size: 26rpx; color: #303133; display: block; }
.trace-time { font-size: 22rpx; color: #c0c4cc; margin-top: 4rpx; display: block; }

.professional-info { display: flex; flex-direction: column; align-items: center; gap: 16rpx; padding: 20rpx 0; }
.professional-text { font-size: 26rpx; color: #606266; text-align: center; }
</style>
