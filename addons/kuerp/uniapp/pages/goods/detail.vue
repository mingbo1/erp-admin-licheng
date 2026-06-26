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
        <text class="price-value">{{ formatPrice(currentPrice) }}</text>
        <text class="cost-price" v-if="currentSku && currentPrice != goods.show_price">¥{{ formatPrice(goods.show_price) }}</text>
      </view>
      <text class="goods-name">{{ goods.goods_name }}</text>
      <view class="tags-row">
        <u-tag v-if="goods.goods_code" :text="'#' + goods.goods_code" type="info" size="mini" plain />
        <u-tag v-if="currentStock != null" :text="'库存: ' + currentStock" :type="sellOut ? 'error' : 'warning'" size="mini" plain />
        <u-tag v-if="currentSku" :text="currentSku.attr_name" type="primary" size="mini" plain />
      </view>
    </view>

    <!-- 多规格选择（页面展示） -->
    <view class="section-card" v-if="attrGroups.length > 0">
      <view class="section-title">选择规格</view>
      <u-divider half-width="20" :hairline="true"></u-divider>
      <view class="attr-group" v-for="(group, gi) in attrGroups" :key="gi">
        <text class="attr-label">{{ group.group_name }}</text>
        <view class="attr-options">
          <text
            v-for="(opt, oi) in group.values"
            :key="oi"
            class="attr-tag"
            :class="{ selected: isAuxSelected(opt.id), disabled: isAuxDisabled(group, opt.id) }"
            @click="selectAux(group, opt)"
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
          :class="{ selected: selectedUnitIndex === ui }"
          @click="selectedUnitIndex = ui"
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
      <view class="cart-btn" @click="openSheet('cart')" v-if="canBuy">
        <text>加入订货单</text>
      </view>
      <view class="buy-btn" @click="openSheet('buyNow')" v-if="canBuy">
        <text>立即购买</text>
      </view>
      <view class="sold-out-btn" v-if="sellOut">
        <text>已售罄</text>
      </view>
      <view class="need-spec-btn" v-if="needSelectSpec" @click="openSheet('cart')">
        <text>请先选择规格</text>
      </view>
    </view>

    <!-- 底部弹窗（多规格组件：加入订货单 + 立即购买） -->
    <GoodsActionSheet
      v-model="showSheet"
      :goods="goods"
      :attrGroups="attrGroups"
      :attrs="attrs"
      :units="units"
      :showBuyNow="true"
      @specChange="onSheetSpecChange"
      @addToCart="onSheetAddToCart"
      @buyNow="onSheetBuyNow"
    />
  </view>

  <!-- 加载中 -->
  <view class="page" v-else>
    <u-skeleton rows="5" loading :title="false" />
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
      goods: null,
      units: [],
      attrGroups: [],       // 属性组 [{group_id, group_name, values: [{id, name}]}]
      attrs: [],             // 规格列表 [{id, attr_name, attr_ids, aux_value_ids, show_price, stock, ...}]
      selectedAuxIds: [],   // 选中的辅助属性值 ID（auxiliary.id）
      selectedUnitIndex: 0,
      content: '',
      goodsImages: [],
      goodsId: 0,
      showSheet: false,
      cartCount: 0,
    }
  },
  computed: {
    // 当前匹配的 SKU（根据选中的 aux_id 列表匹配 attrs）
    currentSku() {
      if (this.attrs.length === 0) return null
      if (this.selectedAuxIds.length === 0) return null
      const sorted = [...this.selectedAuxIds].sort((a, b) => a - b).join('&')
      return this.attrs.find(a => {
        if (!a.aux_value_ids || a.aux_value_ids.length === 0) return false
        const aSorted = [...a.aux_value_ids].sort((a, b) => a - b).join('&')
        return aSorted === sorted
      }) || null
    },
    // 当前价格：选中规格取 SKU 价格，未选规格取商品最低价
    currentPrice() {
      if (this.currentSku) return this.currentSku.show_price || 0
      return this.goods ? (this.goods.show_price || 0) : 0
    },
    // 当前库存：选中规格取 SKU 库存，未选规格取商品总库存
    currentStock() {
      if (this.currentSku) return this.currentSku.stock || 0
      return this.goods ? (this.goods.stock || 0) : 0
    },
    // 选中规格文本
    selectedSpecText() {
      const names = []
      for (const id of this.selectedAuxIds) {
        for (const group of this.attrGroups) {
          const val = group.values.find(v => v.id === id)
          if (val) { names.push(val.name); break }
        }
      }
      return names.length > 0 ? '已选: ' + names.join(' / ') : ''
    },
    // 是否已选全所有属性组
    allGroupsSelected() {
      if (this.attrGroups.length === 0) return true
      return this.selectedAuxIds.length >= this.attrGroups.length
    },
    // 是否已售罄（当前选中规格库存为 0 或无匹配 SKU）
    sellOut() {
      // 无多规格：看商品级库存
      if (this.attrs.length === 0) {
        return this.goods ? (this.goods.stock <= 0) : true
      }
      // 有多规格且已选全：看当前 SKU 库存
      if (this.allGroupsSelected && this.currentSku) {
        return this.currentSku.stock <= 0
      }
      // 有多规格未选全：不算售罄
      return false
    },
    // 需要先选择规格
    needSelectSpec() {
      return this.attrGroups.length > 0 && !this.allGroupsSelected
    },
    // 是否可以购买（已选全且有库存）
    canBuy() {
      if (this.needSelectSpec) return false
      return !this.sellOut
    },
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
        this.content = res.content || ''
        this.goodsImages = res.goods_images || []

        // 新格式多规格
        if (res.attr_groups && res.attr_groups.length > 0) {
          this.attrGroups = res.attr_groups
          this.attrs = res.attrs || []
          // 默认选中每个属性组的第一个值（有库存的优先）
          this.autoSelectFirstSpec()
        } else {
          this.attrGroups = []
          this.attrs = []
        }

        // 默认选中第一个单位
        if (this.units.length > 0) {
          this.selectedUnitIndex = 0
        }
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
      }
    },
    // 默认选中第一个有库存的规格
    autoSelectFirstSpec() {
      // 找第一个有库存的 SKU
      const skuWithStock = this.attrs.find(a => (a.stock || 0) > 0)
      const targetSku = skuWithStock || this.attrs[0]
      if (targetSku && targetSku.aux_value_ids && targetSku.aux_value_ids.length > 0) {
        this.selectedAuxIds = [...targetSku.aux_value_ids]
      } else {
        // 无 SKU 数据时，每个组选第一个值
        this.attrGroups.forEach(g => {
          if (g.values && g.values.length > 0) {
            this.selectedAuxIds.push(g.values[0].id)
          }
        })
      }
    },
    // 页面级规格选择（非弹窗）
    isAuxSelected(auxId) {
      return this.selectedAuxIds.includes(auxId)
    },
    isAuxDisabled(group, auxId) {
      if (this.attrs.length === 0) return false
      const testIds = this.selectedAuxIds.filter(id =>
        !(group.values || []).some(v => v.id === id)
      )
      testIds.push(auxId)
      const sorted = [...testIds].sort((a, b) => a - b).join('&')
      return !this.attrs.some(a => {
        if (!a.aux_value_ids || a.aux_value_ids.length === 0) return false
        return [...a.aux_value_ids].sort((a, b) => a - b).join('&') === sorted
      })
    },
    selectAux(group, opt) {
      if (!group || !group.values) return
      group.values.forEach(v => {
        const idx = this.selectedAuxIds.indexOf(v.id)
        if (idx >= 0) this.selectedAuxIds.splice(idx, 1)
      })
      this.selectedAuxIds.push(opt.id)
    },
    async loadCartCount() {
      try {
        const res = await cartApi.count()
        this.cartCount = res.count || 0
      } catch (_) {}
    },
    goCart() { uni.switchTab({ url: '/pages/cart/cart' }) },
    formatPrice(v) { return Number(v || 0).toFixed(2) },
    checkLogin() {
      if (!getToken()) {
        // #ifdef MP-WEIXIN
        uni.navigateTo({ url: '/pages/user/wxlogin' })
        // #endif
        // #ifndef MP-WEIXIN
        uni.navigateTo({ url: '/pages/user/login' })
        // #endif
        return false
      }
      return true
    },
    handleService() { uni.showToast({ title: '客服电话: 400-xxx-xxxx', icon: 'none' }) },
    // 打开弹窗
    openSheet(mode) {
      if (!this.checkLogin()) return
      if (this.needSelectSpec) {
        uni.showToast({ title: '请先选择规格', icon: 'none' })
        return
      }
      if (this.sellOut) {
        uni.showToast({ title: '该规格已售罄', icon: 'none' })
        return
      }
      this.showSheet = true
    },
    closeSheet() {
      this.showSheet = false
    },
    // 组件事件：规格变更 → 同步页面展示
    onSheetSpecChange(e) {
      this.selectedAuxIds = e.selectedAuxIds || []
      this.selectedUnitIndex = e.selectedUnitIdx || 0
    },
    // 组件事件：加入订货单
    async onSheetAddToCart(payload) {
      try {
        await cartApi.add(payload.goods_id, payload.quantity, payload.attr_ids, payload.goods_unit)
        this.closeSheet()
        this.cartCount++
        uni.showToast({ title: '已加入订货单', icon: 'none' })
      } catch (e) { uni.showToast({ title: e, icon: 'none' }) }
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

/* 规格属性 */
.attr-group { margin-bottom: 20rpx; }
.attr-group:last-child { margin-bottom: 0; }
.attr-label { font-size: 24rpx; color: #606266; display: block; margin-bottom: 10rpx; }
.attr-options { display: flex; flex-wrap: wrap; gap: 12rpx; }
.attr-tag {
  padding: 10rpx 24rpx; font-size: 24rpx; color: #606266;
  background: #f5f7fa; border-radius: 8rpx; border: 2rpx solid #f5f7fa;
}
.attr-tag.selected { color: #4A90D9; background: #ecf5ff; border-color: #4A90D9; }
.attr-tag.disabled { color: #c0c4cc; background: #f5f5f5; border-color: #f5f5f5; text-decoration: line-through; }
.unit-options { display: flex; flex-wrap: wrap; gap: 12rpx; }

/* 底部操作栏 */
.bottom-bar {
  position: fixed; bottom: 0; left: 0; right: 0; background: #fff;
  display: flex; align-items: center; padding: 0 24rpx; height: 100rpx;
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

.cart-btn, .buy-btn, .sold-out-btn, .need-spec-btn {
  flex: 1; height: 76rpx; line-height: 76rpx;
  text-align: center; font-size: 28rpx; font-weight: 500; color: #fff;
}
.cart-btn { background: #f5a623; border-radius: 38rpx 0 0 38rpx; margin-left: 20rpx; }
.buy-btn { background: #4A90D9; border-radius: 0 38rpx 38rpx 0; }
.sold-out-btn { background: #c0c4cc; border-radius: 38rpx; margin-left: 20rpx; }
.need-spec-btn { background: #909399; border-radius: 38rpx; margin-left: 20rpx; }

</style>
