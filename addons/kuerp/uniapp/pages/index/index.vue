<template>
  <view class="page">

    <!-- 轮播图 -->
    <view class="banner-wrap" v-if="bannerList.length > 0">
      <u-swiper :list="bannerList" keyName="image" :height="230" :radius="16" :interval="3000" :autoplay="true"
        :circular="true" indicator indicatorMode="dot" indicatorActiveColor="#4A90D9"></u-swiper>
    </view>

    <!-- 中部导航栏（金刚区） -->
    <view class="nav-wrap" v-if="navList.length > 0">
      <view class="nav-item" v-for="(item, index) in navList" :key="item.id"
        :style="{ animationDelay: (index * 0.05) + 's' }"
        @click="goNav(item)" hover-class="nav-item-hover">
        <view class="nav-icon">
          <image v-if="item.image" :src="item.image" mode="aspectFit" class="nav-icon-img" />
          <u-icon v-else name="grid" size="46" color="#4A90D9" />
        </view>
        <text class="nav-title">{{ item.title }}</text>
      </view>
    </view>

    <!-- 搜索栏 -->
    <view class="search-wrap">
      <u-search v-model="search" placeholder="搜索商品名称 / 编号" :show-action="false" bg-color="#fff" shape="round"
        @search="onSearch" @clear="onClear"></u-search>
    </view>

    <!-- 分类切换 -->
    <view class="tabs-scroll">
      <u-tabs :list="tabList" :current="cateIndex" @change="switchCate" keyName="name" :scrollable="true"></u-tabs>

    </view>


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
    <u-empty mode="search" text="暂无商品" v-if="loaded && list.length === 0 && !loading"></u-empty>

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
import { goodsApi, bannerApi, navApi, cartApi } from '../../api'
import { getToken } from '../../api/request'

let categoriesCache = null

export default {
  components: { GoodsActionSheet },
  data() {
    return {
      list: [],
      categories: [],
      bannerList: [],
      navList: [],
      cateId: 0,
      search: '',
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
    cateIndex() {
      const idx = this.tabList.findIndex(t => t.id === this.cateId)
      return idx >= 0 ? idx : 0
    },
    sheetAttrGroups() { return (this.sheetDetail && this.sheetDetail.attr_groups) || [] },
    sheetAttrs() { return (this.sheetDetail && this.sheetDetail.attrs) || [] },
    sheetUnits() { return (this.sheetDetail && this.sheetDetail.units) || [] },
  },
  onLoad() {
    this.loadBanner()
    this.loadNav()
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
    async loadBanner() {
      try {
        const res = await bannerApi.index()
        this.bannerList = (res.list || []).map(i => ({
          ...i,
          image: this.$img(i.image),
        }))
      } catch (_) { }
    },
    async loadNav() {
      try {
        const res = await navApi.index()
        this.navList = (res.list || []).map(i => ({
          ...i,
          image: this.$img(i.image),
        }))
      } catch (_) { }
    },
    async loadCategories() {
      if (categoriesCache) {
        this.categories = categoriesCache
        return
      }
      try {
        const res = await goodsApi.categories()
        this.categories = res.list || []
        categoriesCache = this.categories
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
      if (this.search) params.keyword = this.search
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
    switchCate(e) {
      const idx = typeof e === 'number' ? e : e.index
      const tab = this.tabList[idx]
      if (!tab) return
      this.cateId = tab.id
      this.loadData()
    },
    onSearch() { this.loadData() },
    onClear() { this.search = ''; this.loadData() },
    goDetail(id) { uni.navigateTo({ url: '/pages/goods/detail?id=' + id }) },
    // 中部导航跳转（tabBar 页用 switchTab，其余用 navigateTo）
    goNav(item) {
      if (item.link_type === 'none' || !item.link_url) return
      let url = item.link_url
      if (url.indexOf('/') !== 0) url = '/' + url
      const tabbarPaths = ['/pages/index/index', '/pages/cart/cart', '/pages/order/list', '/pages/user/info']
      if (tabbarPaths.indexOf(url) >= 0) {
        uni.switchTab({ url })
      } else {
        uni.navigateTo({ url })
      }
    },
    async addToCart(item) {
      if (!getToken()) {
        // #ifdef MP-WEIXIN
        uni.navigateTo({ url: '/pages/user/login' })
        // #endif
        // #ifndef MP-WEIXIN
        uni.navigateTo({ url: '/pages/user/login' })
        // #endif
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

/* 轮播图 */
.banner-wrap {
  padding: 20rpx 20rpx 0;
}

/* 中部导航栏（金刚区，4个一行，超出换行） */
.nav-wrap {
  display: flex;
  flex-wrap: wrap;
  margin: 20rpx 20rpx 0;
  padding: 28rpx 8rpx 12rpx;
  background: #fff;
  border-radius: 24rpx;
  box-shadow: 0 6rpx 20rpx rgba(74, 144, 217, 0.06);
}

.nav-item {
  width: 25%;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8rpx 0 18rpx;
  border-radius: 18rpx;
  animation: nav-fade 0.5s ease backwards;
}

.nav-item-hover {
  background: #f2f7fe;
  transform: scale(0.94);
}

.nav-icon {
  width: 92rpx;
  height: 92rpx;
  border-radius: 26rpx;
  background: linear-gradient(150deg, #eef5ff 0%, #e6eefb 100%);
  border: 1rpx solid #e3ecf9;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 14rpx;
  overflow: hidden;
}

.nav-icon-img {
  width: 74rpx;
  height: 74rpx;
}

.nav-title {
  font-size: 24rpx;
  color: #3a4252;
  font-weight: 500;
  max-width: 96%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: center;
}

@keyframes nav-fade {
  from {
    opacity: 0;
    transform: translateY(20rpx);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* 搜索栏 */
.search-wrap {
  padding: 20rpx 20rpx 0;
  background: #f5f7fa;
}

.tabs-scroll {
  background: #fff;
  margin: 20rpx 0;
}

/* 商品网格 - 两列 */
.goods-grid {
  display: flex;
  flex-wrap: wrap;
  padding: 0 16rpx;
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
