<template>
  <view class="page">
    <!-- 状态标签 -->
    <u-sticky bg-color="#fff" :offset-top="0">
      <u-tabs
        :list="tabs"
        :current="tabIndex"
        @click="switchTab"
        keyName="label"
        :scrollable="true"
      ></u-tabs>
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
        <view class="card-header">
          <view class="header-left">
            <view class="header-top-row">
              <text class="order-no">{{ item.purchase_code }}</text>
              <text class="item-num" v-if="item.items">共{{ item.items.length }}件</text>
            </view>
            <text class="order-time">{{ formatTime(item.createtime) }}</text>
          </view>
          <view class="status-tag" :class="'status-' + getStatusInfo(item.display_status).css">
            <view class="status-dot"></view>
            <text>{{ getStatusInfo(item.display_status).text }}</text>
          </view>
        </view>

        <!-- 商品 -->
        <view class="card-goods" v-if="item.items && item.items.length > 0">
          <view class="goods-row" v-for="(oi, idx) in item.items.slice(0, 3)" :key="idx">
            <image
              class="goods-thumb"
              :src="getImgUrl(oi.goods_image)"
              mode="aspectFill"
              v-if="oi.goods_image"
            />
            <text class="goods-name u-line-1">{{ oi.goods_note || oi.goods_code }}</text>
            <text class="goods-qty">×{{ oi.number }}</text>
          </view>
          <view class="goods-more" v-if="item.items.length > 3">
            <text>等 {{ item.items.length }} 件商品</text>
          </view>
        </view>

        <view class="card-footer">
          <view class="footer-left">
            <view class="amount-area">
              <text class="amount-label">金额</text>
              <text class="amount-value">¥{{ item.all_money }}</text>
            </view>
          </view>
          <view class="footer-actions">
            <u-button
              type="info" size="small" plain hairline shape="circle"
              v-if="canCancel(item)"
              @tap.stop="handleCancel(item)"
            >取消订单</u-button>
            <u-button
              type="primary" size="small" plain hairline shape="circle"
              @tap.stop="goDetail(item.id)"
            >查看详情</u-button>
          </view>
        </view>
      </view>
    </view>

    <!-- 骨架屏 -->
    <view class="order-list" v-if="loading && list.length === 0">
      <view class="order-card" v-for="i in [1, 2, 3]" :key="i">
        <view class="skeleton-header"><view class="skeleton-line" style="width:55%;"></view><view class="skeleton-line" style="width:18%;"></view></view>
        <view class="skeleton-goods"><view class="skeleton-line"></view><view class="skeleton-line"></view></view>
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
import config from '../../config'

// display_status 映射
const DISPLAY_MAP = {
  pending_review: { text: '待审核', css: 'pending' },
  pending_ship: { text: '待发货', css: 'confirmed' },
  partial_shipped: { text: '部分发货', css: 'shipped' },
  shipped: { text: '已发货', css: 'shipped' },
  cancelled: { text: '已取消', css: 'cancelled' },
}

export default {
  data() {
    return {
      DISPLAY_MAP,
      list: [],
      page: 1,
      loaded: false,
      loading: true,
      hasMore: true,
      activeTab: 'all',
      requestVersion: 0,
      tabs: [
        { key: 'all', label: '全部' },
        { key: 'pending_review', label: '待审核' },
        { key: 'pending_ship', label: '待发货' },
        { key: 'partial_shipped', label: '部分发货' },
        { key: 'shipped', label: '已发货' },
        { key: 'cancelled', label: '已取消' },
      ],
    }
  },
  computed: {
    tabIndex() {
      const idx = this.tabs.findIndex(t => t.key === this.activeTab)
      return idx >= 0 ? idx : 0
    }
  },
  onShow() { this.page = 1; this.loadOrders() },
  onPullDownRefresh() { this.page = 1; this.loadOrders().finally(() => uni.stopPullDownRefresh()) },
  onReachBottom() { if (!this.loading && this.hasMore) { this.page++; this.loadOrders(true) } },
  methods: {
    async loadOrders(append = false) {
      // 追加加载时防重复，但切换状态时不阻塞（允许覆盖旧请求）
      if (append && this.loading) return

      if (!append) {
        this.requestVersion++
      }
      const reqId = this.requestVersion

      this.loading = true
      try {
        const res = await orderApi.index({ page: this.page, limit: 20, status: this.activeTab || undefined })
        // 竞态保护：忽略过时响应
        if (reqId !== this.requestVersion) return
        if (append) { this.list = this.list.concat(res.list || []) } else { this.list = res.list || [] }
        this.hasMore = this.list.length < (res.total || 0)
        this.loaded = true
      } catch (e) {
        if (reqId !== this.requestVersion) return
        uni.showToast({ title: e, icon: 'none' })
      } finally {
        if (reqId === this.requestVersion) {
          this.loading = false
        }
      }
    },
    switchTab(e) {
      const idx = typeof e === 'number' ? e : e.index
      const tab = this.tabs[idx]
      if (!tab) return
      this.activeTab = tab.key
      this.page = 1
      this.loadOrders()
    },
    canCancel(item) {
      return item && item.display_status === 'pending_review'
    },
    getStatusInfo(status) {
      return DISPLAY_MAP[status] || { text: '待审核', css: '' }
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
    goDetail(id) { uni.navigateTo({ url: '/pages/order/detail?id=' + id }) },
    handleCancel(item) {
      uni.showModal({
        title: '取消订单', content: `确定取消订单「${item.purchase_code}」吗？`,
        success: (res) => { if (res.confirm) this.cancelOrder(item.id) }
      })
    },
    async cancelOrder(id) {
      try { await orderApi.cancel(id); uni.showToast({ title: '订单已取消', icon: 'none' }); this.page = 1; this.loadOrders() }
      catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
  },
}
</script>

<style scoped>
.page { padding-bottom: 130rpx; min-height: 100vh; background: #f5f7fa; }

.order-list { padding: 20rpx; }
.order-card { background: #fff; border-radius: 16rpx; padding: 24rpx; margin-bottom: 16rpx; }
.card-hover { transform: scale(0.98); }

.card-header { display: flex; justify-content: space-between; align-items: flex-start; }
.header-left { display: flex; flex-direction: column; flex: 1; margin-right: 12rpx; }
.header-left > * + * { margin-top: 6rpx; }
.header-top-row { display: flex; align-items: center; }
.header-top-row > * + * { margin-left: 12rpx; }
.order-no { font-size: 24rpx; color: #303133; font-weight: 600; }
.item-num { font-size: 20rpx; color: #c0c4cc; }
.order-time { font-size: 22rpx; color: #c0c4cc; }

.status-tag { display: flex; align-items: center; padding: 6rpx 16rpx; border-radius: 20rpx; font-size: 22rpx; font-weight: 500; flex-shrink: 0; }
.status-tag > * + * { margin-left: 6rpx; }
.status-dot { width: 10rpx; height: 10rpx; border-radius: 50%; }
.status-pending { background: #fdf6ec; color: #e6a23c; }
.status-pending .status-dot { background: #e6a23c; }
.status-confirmed { background: #ecf5ff; color: #4A90D9; }
.status-confirmed .status-dot { background: #4A90D9; }
.status-cancelled { background: #f4f4f5; color: #909399; }
.status-cancelled .status-dot { background: #909399; }
.status-shipped { background: #e8f4fd; color: #2c8cff; }
.status-shipped .status-dot { background: #2c8cff; }

.card-goods { margin: 16rpx 0; padding: 12rpx 16rpx; background: #fafbfc; border-radius: 8rpx; }
.goods-row { display: flex; align-items: center; padding: 6rpx 0; }
.goods-row + .goods-row { border-top: 1rpx solid #f5f7fa; }
.goods-thumb { width: 64rpx; height: 64rpx; border-radius: 8rpx; flex-shrink: 0; margin-right: 12rpx; background: #f0f0f0; }
.goods-name { font-size: 24rpx; color: #303133; flex: 1; margin-right: 16rpx; }
.goods-qty { font-size: 22rpx; color: #909399; flex-shrink: 0; }
.goods-more { padding: 8rpx 0 0; font-size: 20rpx; color: #c0c4cc; text-align: center; }

.card-footer { display: flex; justify-content: space-between; align-items: center; }
.footer-left { display: flex; flex-direction: column; }
.footer-left > * + * { margin-top: 4rpx; }
.amount-area { display: flex; align-items: baseline; }
.amount-area > * + * { margin-left: 6rpx; }
.amount-label { font-size: 22rpx; color: #909399; }
.amount-value { font-size: 30rpx; color: #f56c6c; font-weight: 700; }
.footer-actions { display: flex; }
.footer-actions > * + * { margin-left: 12rpx; }

.skeleton-header { display: flex; justify-content: space-between; margin-bottom: 20rpx; }
.skeleton-goods { display: flex; margin: 20rpx 0; }
.skeleton-goods > * + * { margin-left: 12rpx; }
.skeleton-goods .skeleton-line:first-child { width: 48rpx; height: 48rpx; border-radius: 6rpx; flex-shrink: 0; }
.skeleton-goods .skeleton-line:last-child { flex: 1; height: 48rpx; }
.skeleton-footer { margin-top: 16rpx; }
.skeleton-line { height: 24rpx; background: #f0f0f0; border-radius: 12rpx; }
</style>
