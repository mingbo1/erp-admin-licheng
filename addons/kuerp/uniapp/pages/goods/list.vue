<template>
  <view class="page">

    <!-- 搜索栏 -->
    <view class="search-bar">
      <u-search v-model="keyword" placeholder="搜索商品名称 / 编号" :show-action="true" action-text="搜索"
        shape="round" bg-color="#fff" :clearabled="true" @search="onSearch" @custom="onSearch" @clear="onClear"></u-search>
    </view>

    <!-- 分类筛选 -->
    <scroll-view scroll-x class="cate-scroll" :show-scrollbar="false" v-if="tabList.length > 0">
      <view class="cate-chips">
        <view v-for="c in tabList" :key="c.id" class="cate-chip" :class="{ active: c.id === cateId }"
          @click="switchCate(c.id)">{{ c.name }}</view>
      </view>
    </scroll-view>

    <!-- 商品列表 -->
    <view class="goods-grid" v-if="list.length > 0">
      <view class="goods-card" v-for="item in list" :key="item.id" @click="goDetail(item.id)" hover-class="card-hover">
        <view class="card-img">
          <image v-if="item.goods_image" :src="$img(item.goods_image)" mode="aspectFill"
            style="width:100%;height:100%;border-radius:16rpx;" />
          <u-icon v-else name="shopping-cart" size="48" color="#c0c4cc" />
        </view>
        <view class="card-body">
          <text class="card-name u-line-2">{{ item.goods_name }}</text>
          <view class="card-spec" v-if="item.attr_name || item.unit_name">
            <text class="card-spec-text">{{ item.attr_name }}{{ item.attr_name && item.unit_name ? ' / ' : '' }}{{
              item.unit_name }}</text>
          </view>
          <view class="card-bottom">
            <text class="card-price">¥{{ item.show_price }}</text>
            <view class="card-actions">
              <view class="card-add-btn" v-if="item.stock > 0" @tap.stop="addToCart(item)">
                <u-icon name="plus" size="20" color="#fff" />
              </view>
              <text v-if="item.stock <= 0" class="card-soldout">已售罄</text>
            </view>
          </view>
        </view>
      </view>
    </view>

    <!-- 骨架屏 -->
    <view class="goods-grid" v-if="loading && list.length === 0">
      <view class="goods-card" v-for="i in 6" :key="i" :style="i % 2 ? 'margin-right:4%' : ''">
        <view class="skeleton-card-img"></view>
        <view class="card-body">
          <view class="skeleton-line" style="width:80%;"></view>
          <view class="skeleton-line" style="width:50%;margin-top:10rpx;"></view>
          <view class="skeleton-line" style="width:40%;margin-top:10rpx;"></view>
        </view>
      </view>
    </view>

    <!-- 空状态 -->
    <u-empty mode="search" :text="emptyText" v-if="loaded && list.length === 0 && !loading"></u-empty>

    <!-- 加载更多 -->
    <u-loadmore :status="hasMore ? 'loadmore' : 'nomore'" margin-top="24" margin-bottom="32" v-if="list.length > 0" />

    <!-- 底部弹窗 - 加入订货单（多规格组件） -->
    <GoodsActionSheet v-model="showSheet" :goods="sheetItem" :attrGroups="sheetAttrGroups" :attrs="sheetAttrs"
      :units="sheetUnits" :showBuyNow="true" @addToCart="onSheetAddToCart" @input="closeSheet"
      @buyNow="onSheetBuyNow" />

  </view>
</template>

<script>
import GoodsActionSheet from '../../components/GoodsActionSheet.vue'
import { goodsApi, cartApi } from '../../api'
import { getToken } from '../../api/request'

export default {
  components: { GoodsActionSheet },
  data() {
    return {
      list: [],
      categories: [],
      cateId: 0,
      keyword: '',
      page: 1,
      loading: false,
      loaded: false,
      hasMore: true,
      showSheet: false,
      sheetItem: null,           // 列表商品
      sheetDetail: null,         // 详情数据（attr_groups, attrs, units）
      requestVersion: 0,          // 请求版本号，防止竞态
    }
  },
  computed: {
    tabList() {
      return [{ id: 0, name: '全部' }, ...this.categories]
    },
    sheetAttrGroups() { return (this.sheetDetail && this.sheetDetail.attr_groups) || [] },
    sheetAttrs() { return (this.sheetDetail && this.sheetDetail.attrs) || [] },
    sheetUnits() { return (this.sheetDetail && this.sheetDetail.units) || [] },
    emptyText() {
      if (this.keyword) return '未找到相关商品'
      return this.cateId > 0 ? '该分类暂无商品' : '暂无商品'
    },
  },
  onLoad(options) {
    // 支持 ?category_id=XX 直接定位到某分类
    const cateId = Number(options.category_id || options.categoryId || 0)
    this.cateId = cateId > 0 ? cateId : 0
    this.loadCategories()
    this.loadData()
  },
  onPullDownRefresh() {
    this.page = 1
    this.list = []
    this.loadData().finally(() => uni.stopPullDownRefresh())
  },
  onReachBottom() {
    if (!this.loading && this.hasMore) {
      this.page++
      this.loadData(true)
    }
  },
  methods: {
    async loadCategories() {
      try {
        const res = await goodsApi.categories()
        this.categories = res.list || []
        // 定位分类时，将导航栏标题设置为分类名
        if (this.cateId > 0) {
          const cur = this.tabList.find(t => t.id === this.cateId)
          if (cur && cur.name) uni.setNavigationBarTitle({ title: cur.name })
        }
      } catch (_) { }
    },
    async loadData(append = false) {
      // 追加加载时防重复，但切换分类/搜索时不阻塞（允许覆盖旧请求）
      if (append && this.loading) return

      if (!append) {
        this.requestVersion++
      }
      const reqId = this.requestVersion

      this.loading = true
      if (!append) {
        this.page = 1
        this.list = []
      }
      const params = { page: this.page, limit: 20 }
      if (this.cateId) params.category_id = this.cateId
      if (this.keyword) params.keyword = this.keyword
      try {
        const res = await goodsApi.index(params)
        // 竞态保护：忽略过时响应
        if (reqId !== this.requestVersion) return
        if (append) {
          this.list = this.list.concat(res.list || [])
        } else {
          this.list = res.list || []
        }
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
    switchCate(id) {
      if (id === this.cateId) return
      this.cateId = id
      // 切分类时同步导航栏标题
      const cur = this.tabList.find(t => t.id === id)
      if (cur && cur.name && cur.name !== '全部') {
        uni.setNavigationBarTitle({ title: cur.name })
      } else {
        uni.setNavigationBarTitle({ title: '商品列表' })
      }
      this.loadData()
    },
    onSearch() { this.loadData() },
    onClear() { this.keyword = ''; this.loadData() },
    goDetail(id) { uni.navigateTo({ url: '/pages/goods/detail?id=' + id }) },
    async addToCart(item) {
      if (!getToken()) {
        uni.navigateTo({ url: '/pages/user/login' })
        return
      }
      // 先加载规格数据，再打开弹窗，确保打开时默认规格已就绪
      this.sheetItem = item
      this.sheetDetail = null
      try {
        const res = await goodsApi.detail(item.id)
        this.sheetDetail = res
        this.showSheet = true
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
      }
    },
    // 组件事件：加入订货单
    async onSheetAddToCart(payload) {
      try {
        await cartApi.add(payload.goods_id, payload.quantity, payload.attr_ids, payload.goods_unit)
        this.closeSheet()
        uni.showToast({ title: '已加入订货单', icon: 'none' })
      } catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
    closeSheet() {
      this.showSheet = false
      this.sheetItem = null
      this.sheetDetail = null
    },
    // 组件事件：立即购买
    onSheetBuyNow(payload) {
      this.closeSheet()
      uni.navigateTo({
        url: '/pages/order/checkout?goods_id=' + payload.goods_id +
          '&quantity=' + payload.quantity +
          '&attr_ids=' + encodeURIComponent(payload.attr_ids) +
          '&goods_unit=' + payload.goods_unit +
          '&attr_name=' + encodeURIComponent(payload.attr_name)
      })
    },
  },
}
</script>

<style scoped>
.page {
  padding-bottom: 130rpx;
  min-height: 100vh;
  background: #f5f7fa;
}

/* 搜索栏（吸顶） */
.search-bar {
  position: sticky;
  top: 0;
  z-index: 10;
  padding: 20rpx 20rpx 16rpx;
  background: #f5f7fa;
}

/* 分类筛选 chips */
.cate-scroll {
  background: #fff;
  white-space: nowrap;
}

.cate-chips {
  display: inline-flex;
  padding: 20rpx 24rpx;
}

.cate-chip {
  flex-shrink: 0;
  margin-right: 16rpx;
  padding: 10rpx 28rpx;
  font-size: 24rpx;
  color: #606266;
  background: #f5f7fa;
  border-radius: 30rpx;
  transition: all 0.2s;
}

.cate-chip.active {
  color: #fff;
  background: linear-gradient(135deg, #4A90D9, #357ABD);
  font-weight: 500;
}

/* 商品网格 - 两列 */
.goods-grid {
  display: flex;
  flex-wrap: wrap;
  padding: 20rpx 16rpx 0;
}

.goods-card {
  width: 48%;
  margin-bottom: 16rpx;
  background: #fff;
  border-radius: 20rpx;
  overflow: hidden;
  transition: transform 0.15s;
}

.goods-card:nth-child(odd) {
  margin-right: 4%;
}

.card-hover {
  transform: scale(0.97);
}

.card-img {
  width: 100%;
  aspect-ratio: 1;
  background: #f5f7fa;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.card-body {
  padding: 16rpx 16rpx 20rpx;
  display: flex;
  flex-direction: column;
  gap: 8rpx;
}

.card-name {
  font-size: 26rpx;
  color: #303133;
  font-weight: 500;
  line-height: 1.4;
}

.card-spec {}

.card-spec-text {
  font-size: 22rpx;
  color: #c0c4cc;
}

.card-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 4rpx;
}

.card-price {
  font-size: 32rpx;
  color: #f56c6c;
  font-weight: 700;
}

.card-actions {
  display: flex;
  align-items: center;
}

.card-add-btn {
  width: 48rpx;
  height: 48rpx;
  background: linear-gradient(135deg, #4A90D9, #357ABD);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.card-soldout {
  font-size: 22rpx;
  color: #c0c4cc;
}

/* 骨架屏 */
.skeleton-card-img {
  width: 100%;
  aspect-ratio: 1;
  background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

.skeleton-line {
  height: 26rpx;
  background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 13rpx;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }

  100% {
    background-position: -200% 0;
  }
}
</style>
