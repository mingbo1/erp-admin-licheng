<template>
  <view class="page" v-if="goods">
    <!-- 商品图片轮播 -->
    <swiper class="goods-swiper" indicator-dots indicator-active-color="#4A90D9" circular>
      <swiper-item v-for="(img, i) in goodsImages" :key="i">
        <image :src="$img(img)" mode="aspectFill" style="width:100%;height:100%;" />
      </swiper-item>
      <swiper-item v-if="(!goodsImages || goodsImages.length === 0) && goods.goods_image">
        <image :src="$img(goods.goods_image)" mode="aspectFill" style="width:100%;height:100%;" />
      </swiper-item>
      <swiper-item v-if="!goods.goods_image && (!goodsImages || goodsImages.length === 0)">
        <view class="no-img"><u-icon name="shopping-cart" size="120" color="#dcdfe6" /></view>
      </swiper-item>
    </swiper>

    <!-- 商品信息 -->
    <view class="info-section">
      <view class="price-row">
        <text class="price-symbol">¥</text>
        <text class="price-value">{{ formatPrice(goods.show_price) }}</text>
        <text class="cost-price" v-if="goods.market_sell_price && goods.market_sell_price != goods.show_price">¥{{ goods.market_sell_price }}</text>
      </view>
      <text class="goods-name">{{ goods.goods_name }}</text>
      <view class="tags-row">
        <u-tag v-if="goods.goods_code" :text="'#' + goods.goods_code" type="info" size="mini" plain />
        <u-tag v-if="goods.stock != null" :text="'库存: ' + goods.stock" type="warning" size="mini" plain />
      </view>
    </view>

    <!-- 属性选择 -->
    <view class="section-card" v-if="attrs && attrs.length > 0">
      <view class="section-title">选择规格</view>
      <u-divider half-width="20" :hairline="true"></u-divider>
      <view class="attr-group" v-for="(group, gi) in attrGroups" :key="gi">
        <text class="attr-label">{{ group.name }}</text>
        <view class="attr-options">
          <text
            v-for="(opt, oi) in group.values"
            :key="oi"
            class="attr-tag"
            :class="{ selected: selectedAttrs[gi] === oi }"
            @click="selectAttr(gi, oi)"
          >{{ opt.name }}</text>
        </view>
      </view>
    </view>

    <!-- 多单位选择 -->
    <view class="section-card" v-if="units && units.length > 1">
      <view class="section-title">选择单位</view>
      <u-divider half-width="20" :hairline="true"></u-divider>
      <view class="unit-options">
        <text
          v-for="(u, ui) in units"
          :key="ui"
          class="attr-tag"
          :class="{ selected: selectedUnit === ui }"
          @click="selectedUnit = ui"
        >{{ u.name }}</text>
      </view>
    </view>

    <!-- 商品详情（富文本） -->
    <view class="section-card" v-if="content">
      <view class="section-title">商品详情</view>
      <u-divider half-width="20" :hairline="true"></u-divider>
      <view class="rich-content">
        <u-parse :content="content" :no-data="true"></u-parse>
      </view>
    </view>

    <!-- 备注 -->
    <view class="section-card" v-if="goods.note">
      <view class="section-title">备注</view>
      <u-divider half-width="20" :hairline="true"></u-divider>
      <text class="remark-text">{{ goods.note }}</text>
    </view>

    <!-- 底部操作栏 -->
    <view class="bottom-bar safe-area-bottom">
      <view class="action-btn" @click="goCart">
        <u-icon name="shopping-cart" size="40" color="#606266" />
        <text class="action-label">订货单</text>
        <view class="cart-badge" v-if="cartCount > 0">{{ cartCount > 99 ? '99+' : cartCount }}</view>
      </view>
      <view class="action-btn" @click="handleService">
        <u-icon name="chat" size="40" color="#606266" />
        <text class="action-label">客服</text>
      </view>
      <view class="cart-btn" @click="showSheet = true" v-if="!goods.sell_out">
        <text>加入订货单</text>
      </view>
      <view class="buy-btn" @click="buyNow" v-if="!goods.sell_out">
        <text>立即购买</text>
      </view>
      <view class="sold-out-btn" v-else>
        <text>已售罄</text>
      </view>
    </view>

    <!-- 遮罩 + 底部弹窗 -->
    <u-popup :show="showSheet" mode="bottom" round="20" @close="closeSheet" :safe-area-inset-bottom="true">
      <view class="sheet-content">
        <view class="sheet-goods">
          <view class="sheet-goods-img">
            <image v-if="goods.goods_image" :src="$img(goods.goods_image)" mode="aspectFill" style="width:100%;height:100%;border-radius:12rpx;" />
            <u-icon v-else name="shopping-cart" size="48" color="#c0c4cc" />
          </view>
          <view class="sheet-goods-info">
            <text class="sheet-goods-name">{{ goods.goods_name }}</text>
            <text class="sheet-goods-price">¥{{ formatPrice(goods.show_price) }}</text>
            <text class="sheet-stock" v-if="goods.stock != null">库存 {{ goods.stock }}</text>
          </view>
        </view>

        <!-- 弹窗内属性 -->
        <view class="attr-group" v-for="(group, gi) in attrGroups" :key="'s' + gi">
          <text class="attr-label">{{ group.name }}</text>
          <view class="attr-options">
            <text
              v-for="(opt, oi) in group.values"
              :key="oi"
              class="attr-tag"
              :class="{ selected: selectedAttrs[gi] === oi }"
              @click="selectAttr(gi, oi)"
            >{{ opt.name }}</text>
          </view>
        </view>

        <!-- 弹窗内单位 -->
        <view class="attr-group" v-if="units && units.length > 1">
          <text class="attr-label">单位</text>
          <view class="attr-options">
            <text
              v-for="(u, ui) in units"
              :key="ui"
              class="attr-tag"
              :class="{ selected: selectedUnit === ui }"
              @click="selectedUnit = ui"
            >{{ u.name }}</text>
          </view>
        </view>

        <u-line />
        <view class="sheet-qty">
          <text class="sheet-qty-label">数量</text>
          <u-number-box v-model="quantity" :min="1" :max="999" :step="1"></u-number-box>
        </view>
        <view class="sheet-btn-wrap">
          <u-button type="primary" shape="circle" :throttle-time="1000" @click="addCart">加入订货单</u-button>
        </view>
      </view>
    </u-popup>
  </view>

  <!-- 加载中 -->
  <view class="page" v-else>
    <u-skeleton rows="5" loading :title="false" />
  </view>
</template>

<script>
import { goodsApi, cartApi } from '../../api'
import { getToken } from '../../api/request'

export default {
  data() {
    return {
      goods: null,
      units: [],
      attrs: [],
      content: '',
      goodsImages: [],
      quantity: 1,
      goodsId: 0,
      showSheet: false,
      attrGroups: [],
      selectedAttrs: [],
      selectedUnit: 0,
      cartCount: 0,
    }
  },
  onLoad(options) {
    this.goodsId = Number(options.id) || 0
    if (this.goodsId) this.loadDetail()
  },
  onShow() {
    this.loadCartCount()
  },
  methods: {
    async loadDetail() {
      try {
        const res = await goodsApi.detail(this.goodsId)
        this.goods = res.goods || res
        this.units = res.units || []
        this.attrs = res.attrs || []
        this.content = res.content || (res.goods && res.goods.content) || ''
        this.goodsImages = res.goods_images || []

        // 解析属性分组
        if (this.attrs.length > 0) {
          this.attrGroups = this.attrs.map(a => ({
            name: a.name || a.attr_name || '',
            values: (a.values || []).map(v => ({
              id: v.id || v.value_id || v,
              name: v.name || v.value_name || v,
            }))
          }))
          this.selectedAttrs = this.attrGroups.map(() => -1)
        }
        // 默认选中第一个单位
        if (this.units.length > 0) {
          this.selectedUnit = 0
        }
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
      }
    },
    async loadCartCount() {
      try {
        const res = await cartApi.count()
        this.cartCount = res.count || 0
      } catch (_) {}
    },
    selectAttr(gi, oi) {
      this.selectedAttrs[gi] = this.selectedAttrs[gi] === oi ? -1 : oi
    },
    getAttrIds() {
      const ids = []
      for (let gi = 0; gi < this.attrGroups.length; gi++) {
        const oi = this.selectedAttrs[gi]
        if (oi >= 0 && this.attrGroups[gi].values[oi]) {
          ids.push(this.attrGroups[gi].values[oi].id)
        }
      }
      return ids.join('&')
    },
    getAttrName() {
      const names = []
      for (let gi = 0; gi < this.attrGroups.length; gi++) {
        const oi = this.selectedAttrs[gi]
        if (oi >= 0 && this.attrGroups[gi].values[oi]) {
          names.push(this.attrGroups[gi].values[oi].name)
        }
      }
      return names.join('/')
    },
    getGoodsUnit() {
      if (this.units.length > 0 && this.selectedUnit >= 0) {
        const unit = this.units[this.selectedUnit]
        return unit.id || 0
      }
      return 0
    },
    goCart() { uni.switchTab({ url: '/pages/cart/cart' }) },
    formatPrice(v) { return Number(v || 0).toFixed(2) },
    closeSheet() { this.showSheet = false; this.quantity = 1 },
    checkLogin() {
      if (!getToken()) { uni.navigateTo({ url: '/pages/user/login' }); return false }
      return true
    },
    handleService() { uni.showToast({ title: '客服电话: 400-xxx-xxxx', icon: 'none' }) },
    async addCart() {
      if (!this.checkLogin()) return
      try {
        await cartApi.add(this.goodsId, this.quantity, this.getAttrIds(), this.getGoodsUnit())
        this.closeSheet()
        this.cartCount++
        uni.showToast({ title: '已加入订货单', icon: 'none' })
      } catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
    async buyNow() {
      if (!this.checkLogin()) return
      const attrIds = this.getAttrIds()
      const goodsUnit = this.getGoodsUnit()
      uni.navigateTo({
        url: '/pages/order/checkout?goods_id=' + this.goodsId + '&quantity=' + this.quantity + '&attr_ids=' + attrIds + '&goods_unit=' + goodsUnit
      })
    },
  },
}
</script>

<style scoped>
.page { min-height: 100vh; padding-bottom: 120rpx; background: #f5f7fa; }

/* 图片轮播 */
.goods-swiper { height: 520rpx; background: #f5f7fa; }
.no-img { height: 100%; display: flex; align-items: center; justify-content: center; }

/* 信息区 */
.info-section { background: #fff; padding: 28rpx 24rpx; margin-bottom: 16rpx; }
.price-row { display: flex; align-items: baseline; gap: 12rpx; margin-bottom: 16rpx; }
.price-symbol { font-size: 28rpx; color: #f56c6c; font-weight: 700; }
.price-value { font-size: 48rpx; color: #f56c6c; font-weight: 700; }
.cost-price { font-size: 24rpx; color: #c0c4cc; text-decoration: line-through; }
.goods-name { font-size: 32rpx; color: #303133; font-weight: 600; line-height: 1.5; }
.tags-row { display: flex; flex-wrap: wrap; gap: 12rpx; margin-top: 20rpx; }

/* 卡片通用 */
.section-card { background: #fff; margin-bottom: 16rpx; padding: 24rpx; }
.section-title { font-size: 28rpx; font-weight: 600; color: #303133; margin-bottom: 16rpx; }
.remark-text { font-size: 26rpx; color: #606266; line-height: 1.6; }

/* 富文本 */
.rich-content { line-height: 1.8; font-size: 26rpx; color: #303133; overflow: hidden; }
.rich-content >>> img { max-width: 100%; height: auto; display: block; }

/* 属性 */
.attr-group { margin-bottom: 20rpx; }
.attr-group:last-child { margin-bottom: 0; }
.attr-label { font-size: 24rpx; color: #606266; display: block; margin-bottom: 10rpx; }
.attr-options { display: flex; flex-wrap: wrap; gap: 12rpx; }
.attr-tag {
  padding: 10rpx 24rpx;
  font-size: 24rpx;
  color: #606266;
  background: #f5f7fa;
  border-radius: 8rpx;
  border: 2rpx solid #f5f7fa;
}
.attr-tag.selected { color: #4A90D9; background: #ecf5ff; border-color: #4A90D9; }
.unit-options { display: flex; flex-wrap: wrap; gap: 12rpx; }

/* 底部操作栏 */
.bottom-bar {
  position: fixed; bottom: 0; left: 0; right: 0;
  background: #fff; display: flex; align-items: center;
  padding: 0 24rpx; height: 100rpx;
  border-top: 1rpx solid #f2f2f2; z-index: 10;
}
.action-btn {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  width: 96rpx; height: 100%; flex-shrink: 0; position: relative;
}
.action-label { font-size: 20rpx; color: #909399; margin-top: 4rpx; }
.cart-badge {
  position: absolute; top: 8rpx; right: 4rpx;
  min-width: 28rpx; height: 28rpx; padding: 0 6rpx;
  background: #f56c6c; color: #fff; font-size: 18rpx;
  border-radius: 14rpx; text-align: center; line-height: 28rpx;
}

.cart-btn, .buy-btn, .sold-out-btn {
  flex: 1; height: 76rpx; line-height: 76rpx;
  text-align: center; font-size: 28rpx; font-weight: 500; color: #fff;
}
.cart-btn { background: #f5a623; border-radius: 38rpx 0 0 38rpx; margin-left: 20rpx; }
.buy-btn { background: #4A90D9; border-radius: 0 38rpx 38rpx 0; }
.sold-out-btn { background: #c0c4cc; border-radius: 38rpx; margin-left: 20rpx; }

/* 弹窗内容 */
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
