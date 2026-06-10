<template>
  <view class="page">
    <!-- 状态标签 -->
    <u-sticky bg-color="#f5f7fa" :offset-top="0">
      <scroll-view class="tabs-scroll" scroll-x show-scrollbar="false">
        <view class="tabs-inner">
          <view
            v-for="t in tabs"
            :key="t.key"
            class="tab-item"
            :class="{ active: activeTab === t.key }"
            @click="switchTab(t.key)"
          >
            <text>{{ t.label }}</text>
          </view>
        </view>
      </scroll-view>
    </u-sticky>

    <!-- 订单列表 -->
    <view class="order-list" v-if="list.length > 0">
      <view
        class="order-card"
        v-for="item in list"
        :key="item.id"
        @click="goDetail(item.id)"
        hover-class="card-hover"
      >
        <!-- 顶部 -->
        <view class="card-header">
          <view class="header-left">
            <view class="header-top-row">
              <text class="order-no">{{ item.order_no }}</text>
              <text class="item-num" v-if="item.goods">共{{ item.goods.length }}件</text>
            </view>
            <text class="order-time">{{ formatTime(item.createdtime) }}</text>
          </view>
          <view class="status-tag" :class="'status-' + item.status">
            <view class="status-dot"></view>
            <text>{{ statusText(item.status) }}</text>
          </view>
        </view>

        <!-- 商品摘要 -->
        <view class="card-goods" v-if="item.goods && item.goods.length > 0">
          <view class="goods-row" v-for="(oi, idx) in item.goods.slice(0, 3)" :key="oi.id">
            <view class="goods-index">{{ idx + 1 }}</view>
            <text class="goods-name u-line-1">{{ oi.goods_name }}</text>
            <text class="goods-qty">×{{ oi.quantity }}</text>
          </view>
          <view class="goods-more" v-if="item.goods.length > 3">
            <text>等共 {{ item.goods.length }} 件商品</text>
          </view>
        </view>

        <!-- 底部 -->
        <view class="card-footer">
          <view class="amount-area">
            <text class="amount-label">实付</text>
            <text class="amount-value">¥{{ item.payable_amount || item.total_amount }}</text>
          </view>
          <view class="footer-actions">
            <u-button
              type="info" size="mini" plain hairline shape="circle"
              v-if="item.status === 'pending_confirm'"
              @tap.stop="handleCancel(item)"
            >取消订单</u-button>
            <u-button
              type="primary" size="mini" plain hairline shape="circle"
              v-if="item.status === 'shipped'"
              @tap.stop="confirmReceived(item)"
            >确认收货</u-button>
            <u-button
              type="primary" size="mini" plain hairline shape="circle"
              v-if="item.status === 'confirmed'"
              @tap.stop="goDetail(item.id)"
            >查看详情</u-button>
            <u-button
              type="success" size="mini" plain hairline shape="circle"
              v-if="item.status === 'completed' || item.status === 'received'"
              @tap.stop="goDetail(item.id)"
            >查看详情</u-button>
          </view>
        </view>
      </view>
    </view>

    <!-- 骨架屏 -->
    <view class="order-list" v-if="loading && list.length === 0">
      <view class="order-card" v-for="i in 3" :key="i">
        <view class="skeleton-header"><view class="skeleton-line" style="width:55%;"></view><view class="skeleton-line" style="width:18%;"></view></view>
        <view class="skeleton-goods"><view class="skeleton-line" style="width:82%;"></view><view class="skeleton-line" style="width:58%;margin-top:12rpx;"></view></view>
        <view class="skeleton-footer"><view class="skeleton-line" style="width:30%;"></view></view>
      </view>
    </view>

    <!-- 空状态 -->
    <u-empty mode="order" text="暂无订单" v-if="loaded && list.length === 0 && !loading"></u-empty>

    <!-- 加载更多 -->
    <u-loadmore :status="hasMore ? 'loadmore' : 'nomore'" margin-top="24" margin-bottom="32" v-if="list.length > 0" />

  </view>
</template>

<script>
import { orderApi } from '../../api'

const STATUS_MAP = {
  pending_confirm: { text: '待确认' },
  confirmed: { text: '已确认' },
  shipped: { text: '已发货' },
  received: { text: '已收货' },
  completed: { text: '已完成' },
  cancelled: { text: '已取消' },
  returned: { text: '已退货' },
}

export default {
  data() {
    return {
      list: [],
      page: 1,
      loaded: false,
      loading: false,
      hasMore: true,
      activeTab: '',
      tabs: [
        { key: '', label: '全部' },
        { key: 'pending_confirm', label: '待确认' },
        { key: 'confirmed', label: '已确认' },
        { key: 'shipped', label: '已发货' },
        { key: 'completed', label: '已完成' },
        { key: 'cancelled', label: '已取消' },
      ],
    }
  },
  onShow() { this.page = 1; this.loadOrders() },
  onPullDownRefresh() { this.page = 1; this.loadOrders().finally(() => uni.stopPullDownRefresh()) },
  onReachBottom() { if (!this.loading && this.hasMore) { this.page++; this.loadOrders(true) } },
  methods: {
    async loadOrders(append = false) {
      if (this.loading) return
      this.loading = true
      try {
        const res = await orderApi.index({ page: this.page, limit: 20, status: this.activeTab || undefined })
        if (append) { this.list = this.list.concat(res.list || []) } else { this.list = res.list || [] }
        this.hasMore = this.list.length < (res.total || 0)
        this.loaded = true
      } catch (e) { uni.showToast({ title: e, icon: 'none' }) } finally { this.loading = false }
    },
    switchTab(key) { this.activeTab = key; this.page = 1; this.loadOrders() },
    statusText(s) { return (STATUS_MAP[s] || {}).text || s },
    formatTime(t) {
      if (!t) return ''
      const d = new Date(t * 1000)
      const pad = (n) => String(n).padStart(2, '0')
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
    },
    goDetail(id) { uni.navigateTo({ url: '/pages/order/detail?id=' + id }) },
    handleCancel(item) {
      uni.showModal({
        title: '取消订单', content: `确定取消订单「${item.order_no}」吗？`,
        success: (res) => { if (res.confirm) this.cancelOrder(item.id) }
      })
    },
    async cancelOrder(id) {
      try { await orderApi.cancel(id); uni.showToast({ title: '订单已取消', icon: 'none' }); this.page = 1; this.loadOrders() }
      catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
    confirmReceived(item) {
      uni.showModal({
        title: '确认收货', content: '是否确认已收到商品？',
        success: async (res) => {
          if (res.confirm) {
            try { await orderApi.received(item.id); uni.showToast({ title: '确认收货成功', icon: 'none' }); this.page = 1; this.loadOrders() }
            catch (e) { uni.showToast({ title: e, icon: 'none' }) }
          }
        }
      })
    },
  },
}
</script>

<style scoped>
.page { padding-bottom: 130rpx; min-height: 100vh; background: #f5f7fa; }

.tabs-scroll { padding: 20rpx 20rpx 0; white-space: nowrap; background: #f5f7fa; }
.tabs-inner { display: flex; gap: 16rpx; padding-bottom: 12rpx; }
.tab-item {
  display: inline-flex; align-items: center; gap: 6rpx;
  padding: 14rpx 32rpx; font-size: 26rpx; color: #606266;
  background: #fff; border-radius: 32rpx; transition: all 0.2s; flex-shrink: 0;
}
.tab-item.active { color: #fff; background: #4A90D9; font-weight: 500; box-shadow: 0 4rpx 12rpx rgba(74,144,217,0.3); }

.order-list { padding: 20rpx; }
.order-card { background: #fff; border-radius: 16rpx; padding: 24rpx; margin-bottom: 16rpx; box-shadow: 0 2rpx 8rpx rgba(0,0,0,0.04); transition: transform 0.15s; }
.card-hover { transform: scale(0.98); }

.card-header { display: flex; justify-content: space-between; align-items: flex-start; }
.header-left { display: flex; flex-direction: column; gap: 6rpx; flex: 1; margin-right: 12rpx; }
.header-top-row { display: flex; align-items: center; gap: 12rpx; }
.order-no { font-size: 24rpx; color: #303133; font-weight: 600; }
.item-num { font-size: 20rpx; color: #c0c4cc; }
.order-time { font-size: 22rpx; color: #c0c4cc; }

.status-tag { display: flex; align-items: center; gap: 6rpx; padding: 6rpx 16rpx; border-radius: 20rpx; font-size: 22rpx; font-weight: 500; flex-shrink: 0; }
.status-dot { width: 10rpx; height: 10rpx; border-radius: 50%; }
.status-pending_confirm { background: #fdf6ec; color: #e6a23c; }
.status-pending_confirm .status-dot { background: #e6a23c; }
.status-confirmed { background: #ecf5ff; color: #4A90D9; }
.status-confirmed .status-dot { background: #4A90D9; }
.status-shipped { background: #e8f4fd; color: #2c8cff; }
.status-shipped .status-dot { background: #2c8cff; }
.status-received { background: #f0f9eb; color: #67c23a; }
.status-received .status-dot { background: #67c23a; }
.status-completed { background: #f0f9eb; color: #67c23a; }
.status-completed .status-dot { background: #67c23a; }
.status-cancelled { background: #f4f4f5; color: #909399; }
.status-cancelled .status-dot { background: #909399; }
.status-returned { background: #fef0f0; color: #f56c6c; }
.status-returned .status-dot { background: #f56c6c; }

.card-goods { margin: 16rpx 0; padding: 12rpx 16rpx; background: #fafbfc; border-radius: 8rpx; }
.goods-row { display: flex; align-items: center; padding: 6rpx 0; }
.goods-row + .goods-row { border-top: 1rpx solid #f5f7fa; }
.goods-index { width: 28rpx; height: 28rpx; display: flex; align-items: center; justify-content: center; font-size: 18rpx; color: #c0c4cc; flex-shrink: 0; margin-right: 8rpx; }
.goods-name { font-size: 24rpx; color: #303133; flex: 1; margin-right: 16rpx; }
.goods-qty { font-size: 22rpx; color: #909399; flex-shrink: 0; }
.goods-more { padding: 8rpx 0 0; font-size: 20rpx; color: #c0c4cc; text-align: center; }

.card-footer { display: flex; justify-content: space-between; align-items: center; }
.amount-area { display: flex; align-items: baseline; gap: 6rpx; }
.amount-label { font-size: 22rpx; color: #909399; }
.amount-value { font-size: 30rpx; color: #f56c6c; font-weight: 700; }
.footer-actions { display: flex; gap: 12rpx; }

.skeleton-header { display: flex; justify-content: space-between; margin-bottom: 20rpx; }
.skeleton-goods { margin: 20rpx 0; }
.skeleton-footer { margin-top: 16rpx; }
.skeleton-line { height: 24rpx; background: linear-gradient(90deg,#f0f0f0 25%,#e8e8e8 50%,#f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 12rpx; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
</style>
