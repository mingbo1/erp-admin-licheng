<template>
  <view class="page">

    <!-- 轮播图 -->
    <view class="banner-wrap" v-if="bannerList.length > 0">
      <u-swiper
        :list="bannerList"
        keyName="image"
        :height="230"
        :radius="16"
        :interval="3000"
        :autoplay="true"
        :circular="true"
        indicator
        indicatorMode="dot"
        indicatorActiveColor="#4A90D9"
      ></u-swiper>
    </view>

    <!-- 搜索栏 -->
    <view class="search-wrap">
      <u-search
        v-model="search"
        placeholder="搜索商品名称 / 编号"
        :show-action="false"
        bg-color="#fff"
        shape="round"
        @search="onSearch"
        @clear="onClear"
      ></u-search>
    </view>

    <!-- 分类切换 -->
    <scroll-view class="cate-scroll" scroll-x show-scrollbar="false">
      <view class="cate-inner">
        <view
          class="cate-item"
          :class="{ active: cateId === 0 }"
          @click="switchCate(0)"
        >全部</view>
        <view class="cate-item" v-for="c in categories" :key="c.id"
          :class="{ active: cateId === c.id }"
          @click="switchCate(c.id)"
        >{{ c.name }}</view>
      </view>
    </scroll-view>

    <!-- 商品列表 -->
    <view class="goods-grid" v-if="list.length > 0">
      <view
        class="goods-card"
        v-for="item in list"
        :key="item.id"
        @click="goDetail(item.id)"
        hover-class="card-hover"
      >
        <view class="card-img">
          <image v-if="item.goods_image" :src="$img(item.goods_image)" mode="aspectFill" style="width:100%;height:100%;border-radius:16rpx;" />
          <u-icon v-else name="shopping-cart" size="48" color="#c0c4cc" />
        </view>
        <view class="card-body">
          <text class="card-name u-line-2">{{ item.goods_name }}</text>
          <view class="card-spec" v-if="item.attr_name || item.unit_name">
            <text class="card-spec-text">{{ item.attr_name }}{{ item.attr_name && item.unit_name ? ' / ' : '' }}{{ item.unit_name }}</text>
          </view>
          <view class="card-bottom">
            <text class="card-price">¥{{ item.show_price }}</text>
            <view class="card-actions">
              <view class="card-add-btn" v-if="item.stock > 0" @tap.stop="addToCart(item)">
                <u-icon  name="plus" size="20" color="#fff" />
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
    <u-loadmore
      :status="hasMore ? 'loadmore' : 'nomore'"
      margin-top="24"
      margin-bottom="32"
      v-if="list.length > 0"
    />

    <!-- 底部弹窗 - 加入订货单 -->
    <u-popup :show="showSheet" mode="bottom" round="20" @close="closeSheet" :safe-area-inset-bottom="true">
      <view class="sheet-content" v-if="sheetItem">
        <view class="sheet-goods">
          <view class="sheet-goods-img">
            <image v-if="sheetItem.goods_image" :src="$img(sheetItem.goods_image)" mode="aspectFill" style="width:100%;height:100%;border-radius:12rpx;" />
            <u-icon v-else name="shopping-cart" size="48" color="#c0c4cc" />
          </view>
          <view class="sheet-goods-info">
            <text class="sheet-goods-name u-line-2">{{ sheetItem.goods_name }}</text>
            <text class="sheet-goods-price">¥{{ Number(sheetItem.show_price || 0).toFixed(2) }}</text>
            <text class="sheet-stock" v-if="sheetItem.stock != null">库存 {{ sheetItem.stock }}</text>
          </view>
        </view>
        <u-line />
        <view class="sheet-qty">
          <text class="sheet-qty-label">数量</text>
          <u-number-box v-model="quantity" :min="1" :max="999" :step="1"></u-number-box>
        </view>
        <view class="sheet-btn-wrap">
          <u-button type="primary" shape="circle" :throttle-time="1000" @click="confirmAdd">加入订货单</u-button>
        </view>
      </view>
    </u-popup>

  </view>
</template>

<script>
import { goodsApi, bannerApi, cartApi } from '../../api'
import { getToken } from '../../api/request'

let categoriesCache = null

export default {
  data() {
    return {
      list: [],
      categories: [],
      bannerList: [],
      cateId: 0,
      search: '',
      page: 1,
      loading: false,
      loaded: false,
      hasMore: true,
      showSheet: false,
      sheetItem: null,
      quantity: 1,
    }
  },
  onLoad() {
    this.loadBanner()
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
      } catch (_) {}
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
      } catch (_) {}
    },
    async loadData(append = false) {
      if (this.loading) return
      this.loading = true
      if (!append) {
        this.page = 1
        this.list = []
      }
      try {
        const res = await goodsApi.index({
          page: this.page,
          limit: 20,
          category_id: this.cateId || undefined,
          search: this.search || undefined,
        })
        if (append) {
          this.list = this.list.concat(res.list || [])
        } else {
          this.list = res.list || []
        }
        this.hasMore = this.list.length < (res.total || 0)
        this.loaded = true
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
      } finally {
        this.loading = false
      }
    },
    switchCate(id) {
      this.cateId = id
      this.loadData()
    },
    onSearch() { this.loadData() },
    onClear() { this.search = ''; this.loadData() },
    goDetail(id) { uni.navigateTo({ url: '/pages/goods/detail?id=' + id }) },
    addToCart(item) {
      if (!getToken()) {
        // #ifdef MP-WEIXIN
        uni.navigateTo({ url: '/pages/user/wxlogin' })
        // #endif
        // #ifndef MP-WEIXIN
        uni.navigateTo({ url: '/pages/user/login' })
        // #endif
        return
      }
      this.sheetItem = item
      this.quantity = 1
      this.showSheet = true
    },
    closeSheet() {
      this.showSheet = false
      this.sheetItem = null
      this.quantity = 1
    },
    async confirmAdd() {
      if (!this.sheetItem) return
      try {
        await cartApi.add(this.sheetItem.id, this.quantity, '', 0)
        this.closeSheet()
        uni.showToast({ title: '已加入订货单', icon: 'none' })
      } catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
  },
}
</script>

<style scoped>
.page { padding-bottom: 130rpx; min-height: 100vh; background: #f5f7fa; }

/* 轮播图 */
.banner-wrap { padding: 20rpx 20rpx 0; }

/* 搜索栏 */
.search-wrap { padding: 20rpx 20rpx 0; background: #f5f7fa; }

/* 分类 */
.cate-scroll { padding: 20rpx 20rpx; background: #f5f7fa; }
.cate-inner { display: flex; align-items: center; white-space: nowrap; gap: 12rpx; }
.cate-item {
  display: inline-flex;
  align-items: center;
  padding: 12rpx 28rpx;
  font-size: 26rpx;
  color: #606266;
  background: #fff;
  border-radius: 40rpx;
  white-space: nowrap;
  font-weight: 500;
  transition: all 0.2s;
}
.cate-item.active {
  color: #fff;
  background: linear-gradient(135deg, #4A90D9, #357ABD);
  font-weight: 600;
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
.goods-card:nth-child(odd) { margin-right: 4%; }
.card-hover { transform: scale(0.97); }
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
.card-name { font-size: 26rpx; color: #303133; font-weight: 500; line-height: 1.4; }
.card-spec { }
.card-spec-text { font-size: 22rpx; color: #c0c4cc; }

.card-bottom { display: flex; align-items: center; justify-content: space-between; margin-top: 4rpx; }
.card-price { font-size: 32rpx; color: #f56c6c; font-weight: 700; }
.card-actions { display: flex; align-items: center; }
.card-add-btn {
  width: 48rpx;
  height: 48rpx;
  background: linear-gradient(135deg, #4A90D9, #357ABD);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.card-soldout { font-size: 22rpx; color: #c0c4cc; }

/* 骨架屏 */
.skeleton-card-img {
  width: 100%; aspect-ratio: 1;
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
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* 弹窗 */
.sheet-content { padding: 24rpx 32rpx 48rpx; }
.sheet-goods { display: flex; align-items: center; margin-bottom: 24rpx; }
.sheet-goods-img {
  width: 140rpx; height: 140rpx;
  background: #f5f7fa; border-radius: 12rpx;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; margin-right: 20rpx; overflow: hidden;
}
.sheet-goods-info { flex: 1; }
.sheet-goods-name { font-size: 28rpx; color: #303133; font-weight: 500; display: block; }
.sheet-goods-price { font-size: 36rpx; color: #f56c6c; font-weight: 700; margin-top: 8rpx; display: block; }
.sheet-stock { font-size: 22rpx; color: #909399; margin-top: 6rpx; display: block; }
.sheet-qty { display: flex; align-items: center; justify-content: space-between; padding: 24rpx 0; }
.sheet-qty-label { font-size: 28rpx; color: #303133; font-weight: 500; }
.sheet-btn-wrap { margin: 10rpx 0; }
</style>
