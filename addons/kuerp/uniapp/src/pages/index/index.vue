<template>
  <view class="page">

    <!-- 轮播图 -->
    <view class="banner-wrap" v-if="bannerList.length > 0">
      <u-swiper
        :list="bannerList"
        keyName="url"
        :height="320"
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
    <view class="goods-list" v-if="list.length > 0">
      <view
        class="goods-item"
        v-for="item in list"
        :key="item.id"
        @click="goDetail(item.id)"
        hover-class="item-hover"
      >
        <view class="goods-img">
          <image v-if="item.goods_image" :src="$img(item.goods_image)" mode="aspectFill" style="width:100%;height:100%;border-radius:12rpx;" />
          <u-icon v-else name="shopping-cart" size="48" color="#c0c4cc" />
        </view>
        <view class="goods-content">
          <text class="goods-name u-line-2">{{ item.goods_name }}</text>
          <view class="goods-meta" v-if="item.attr_name || item.unit_name">
            <text class="goods-spec">{{ item.attr_name }}{{ item.attr_name && item.unit_name ? ' / ' : '' }}{{ item.unit_name }}</text>
          </view>
          <view class="goods-bottom">
            <view class="price-area">
              <text class="goods-price">¥{{ item.show_price }}</text>
            </view>
            <view class="goods-actions">
              <u-icon v-if="item.stock > 0" name="plus" size="28" color="#fff" class="cart-add-btn" />
              <u-tag v-if="item.stock <= 0" text="已售罄" type="error" size="mini" />
            </view>
          </view>
        </view>
      </view>
    </view>

    <!-- 骨架屏 -->
    <view class="goods-list" v-if="loading && list.length === 0">
      <view class="goods-item" v-for="i in 5" :key="i">
        <view class="skeleton-img"></view>
        <view class="goods-content">
          <view class="skeleton-line" style="width:70%;"></view>
          <view class="skeleton-line" style="width:40%;margin-top:12rpx;"></view>
          <view class="skeleton-line" style="width:30%;margin-top:12rpx;"></view>
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

  </view>
</template>

<script>
import { goodsApi, bannerApi } from '../../api'

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
        this.bannerList = res.list || []
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

/* 商品列表 */
.goods-list { padding: 0 20rpx; }
.goods-item {
  display: flex;
  align-items: center;
  background: #fff;
  border-radius: 16rpx;
  padding: 24rpx;
  margin-bottom: 16rpx;
  transition: transform 0.15s;
}
.item-hover { transform: scale(0.98); }
.goods-img {
  width: 180rpx;
  height: 180rpx;
  background: #f5f7fa;
  border-radius: 16rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-right: 20rpx;
  overflow: hidden;
}
.goods-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12rpx;
  min-height: 120rpx;
}
.goods-name { font-size: 28rpx; color: #303133; font-weight: 500; line-height: 1.4; }
.goods-meta { }
.goods-spec { font-size: 22rpx; color: #c0c4cc; }

.goods-bottom { display: flex; align-items: center; justify-content: space-between; margin-top: auto; }
.price-area { display: flex; align-items: baseline; gap: 4rpx; }
.goods-price { font-size: 36rpx; color: #f56c6c; font-weight: 700; }
.goods-unit { font-size: 22rpx; color: #909399; }
.goods-actions { display: flex; align-items: center; }
.cart-add-btn {
  width: 52rpx;
  height: 52rpx;
  background: linear-gradient(135deg, #4A90D9, #357ABD);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* 骨架屏 */
.skeleton-img {
  width: 180rpx; height: 180rpx;
  background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 16rpx;
  flex-shrink: 0;
  margin-right: 20rpx;
}
.skeleton-line {
  height: 28rpx;
  background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 14rpx;
}
@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
