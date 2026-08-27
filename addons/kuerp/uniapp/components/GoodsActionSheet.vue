<template>
  <u-popup
    :show="value"
    mode="bottom"
    round="20"
    @close="$emit('input', false)"
    :safe-area-inset-bottom="true"
  >
    <view class="sheet-content" v-if="goods">
      <!-- 商品信息 -->
      <view class="sheet-goods">
        <view class="sheet-goods-img">
          <image
            v-if="goods.goods_image"
            :src="$img(goods.goods_image)"
            mode="aspectFill"
            style="width:100%;height:100%;border-radius:12rpx;"
          />
          <u-icon v-else name="shopping-cart" size="48" color="#c0c4cc" />
        </view>
        <view class="sheet-goods-info">
          <text class="sheet-goods-name u-line-2">{{ goods.goods_name }}</text>
          <text class="sheet-goods-price">¥{{ formatPrice(currentPrice) }}</text>
          <text class="sheet-stock" :class="{ 'stock-zero': sellOut }">
            {{ sellOut ? '库存不足' : '库存 ' + currentStock }}
          </text>
          <text class="sheet-selected-spec" v-if="selectedSpecText">{{ selectedSpecText }}</text>
        </view>
      </view>

      <!-- 规格选择 -->
      <view class="sheet-attr" v-if="attrGroups.length > 0">
        <view class="attr-group" v-for="(group, gi) in attrGroups" :key="gi">
          <text class="attr-label">{{ group.group_name }}</text>
          <view class="attr-options">
            <text
              v-for="(opt, oi) in group.values"
              :key="oi"
              class="attr-tag"
              :class="{ selected: isSelected(opt.id), disabled: isDisabled(group, opt.id) }"
              @click="selectAux(group, opt)"
            >{{ opt.name }}</text>
          </view>
        </view>
      </view>

      <!-- 单位选择 -->
      <view class="sheet-attr" v-if="units.length > 1">
        <text class="attr-label">单位</text>
        <view class="attr-options">
          <text
            v-for="(u, ui) in units"
            :key="ui"
            class="attr-tag"
            :class="{ selected: selectedUnitIdx === ui }"
            @click="selectedUnitIdx = ui"
          >{{ u.name }}</text>
        </view>
      </view>

      <u-line />
      <view class="sheet-qty">
        <text class="sheet-qty-label">数量</text>
        <u-number-box v-model="quantity" :min="1" :max="maxQty" :step="1"></u-number-box>
      </view>

      <!-- 底部双按钮：加入订货单 + 立即购买 -->
      <view class="sheet-btns">
        <view
          class="sheet-btn cart-btn"
          :class="{ 'btn-disabled': !canBuy }"
          @click="handleCart"
        >加入订货单</view>
        <view
          v-if="showBuyNow"
          class="sheet-btn buy-btn"
          :class="{ 'btn-disabled': !canBuy }"
          @click="handleBuyNow"
        >立即购买</view>
      </view>
    </view>
  </u-popup>
</template>

<script>
export default {
  props: {
    value:       { type: Boolean, default: false },        // v-model
    goods:       { type: Object,  default: null },          // 商品信息
    attrGroups:  { type: Array,   default: () => [] },      // 属性组
    attrs:       { type: Array,   default: () => [] },      // 规格列表
    units:       { type: Array,   default: () => [] },      // 单位列表
    showBuyNow:  { type: Boolean, default: true },          // 是否显示"立即购买"
  },
  data() {
    return {
      selectedAuxIds:  [],
      selectedUnitIdx: 0,
      quantity: 1,
      initialised: false,
    }
  },
  computed: {
    maxQty() {
      return Math.min(this.currentStock || 999, 999)
    },
    // 当前匹配的 SKU
    currentSku() {
      if (this.attrs.length === 0 || this.selectedAuxIds.length === 0) return null
      const sorted = [...this.selectedAuxIds].sort((a, b) => a - b).join('&')
      return this.attrs.find(a => {
        if (!a.aux_value_ids || a.aux_value_ids.length === 0) return false
        return [...a.aux_value_ids].sort((a, b) => a - b).join('&') === sorted
      }) || null
    },
    currentPrice() {
      if (this.currentSku) return this.currentSku.show_price || 0
      return this.goods ? (this.goods.show_price || 0) : 0
    },
    currentStock() {
      if (this.currentSku) return this.currentSku.stock || 0
      return this.goods ? (this.goods.stock || 0) : 0
    },
    selectedSpecText() {
      if (this.attrGroups.length === 0) return ''
      const names = []
      for (const id of this.selectedAuxIds) {
        for (const g of this.attrGroups) {
          const v = (g.values || []).find(v => v.id === id)
          if (v) { names.push(v.name); break }
        }
      }
      return names.length > 0 ? '已选: ' + names.join(' / ') : ''
    },
    allSelected() {
      if (this.attrGroups.length === 0) return true
      return this.selectedAuxIds.length >= this.attrGroups.length
    },
    sellOut() {
      if (this.attrs.length === 0) return this.goods ? (this.goods.stock <= 0) : true
      if (this.allSelected && this.currentSku) return this.currentSku.stock <= 0
      return false
    },
    canBuy() {
      if (this.attrGroups.length > 0 && !this.allSelected) return false
      return !this.sellOut
    },
  },
  watch: {
    value(val) {
      if (val) {
        // 每次打开弹窗重置数量，并重新初始化规格选择
        this.quantity = 1
        this.initialised = false
        this.selectedAuxIds = []
        this.selectedUnitIdx = 0
        this.initSelection()
      }
    },
    attrs: {
      immediate: true,
      handler(val) {
        if (val && val.length > 0 && !this.initialised) {
          this.initSelection()
        }
      },
    },
  },
  methods: {
    initSelection() {
      // 规格数据未就绪时暂不标记完成，等 attrs watcher 触发后重试
      // （index 页面先开弹窗再异步加载数据，首次调用时 attrs 为空）
      if (this.attrs.length === 0) {
        this.emitSpecChange()
        return
      }
      if (this.selectedAuxIds.length === 0) {
        const skuWithStock = this.attrs.find(a => (a.stock || 0) > 0)
        const target = skuWithStock || this.attrs[0]
        if (target && target.aux_value_ids && target.aux_value_ids.length > 0) {
          this.selectedAuxIds = [...target.aux_value_ids]
        }
      }
      if (this.units.length > 0) {
        this.selectedUnitIdx = 0
      }
      this.initialised = true
      this.emitSpecChange()
    },
    emitSpecChange() {
      this.$emit('specChange', {
        selectedAuxIds:  [...this.selectedAuxIds],
        selectedUnitIdx: this.selectedUnitIdx,
        currentSku:      this.currentSku,
        currentPrice:    this.currentPrice,
        currentStock:    this.currentStock,
      })
    },
    formatPrice(v) { return Number(v || 0).toFixed(2) },
    isSelected(auxId) {
      return this.selectedAuxIds.includes(auxId)
    },
    isDisabled(group, auxId) {
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
      this.emitSpecChange()
    },
    getSortedAuxIdsStr() {
      return [...this.selectedAuxIds].sort((a, b) => a - b).join('&')
    },
    getAttrName() {
      const names = []
      for (const id of this.selectedAuxIds) {
        for (const g of this.attrGroups) {
          const v = (g.values || []).find(v => v.id === id)
          if (v) { names.push(v.name); break }
        }
      }
      return names.join(' / ')
    },
    getGoodsUnit() {
      if (this.units.length > 0) {
        return (this.units[this.selectedUnitIdx] || {}).id || 0
      }
      return 0
    },
    buildPayload() {
      return {
        goods_id: this.goods ? this.goods.id : 0,
        quantity: this.quantity,
        attr_ids: this.attrGroups.length > 0 ? this.getSortedAuxIdsStr() : '',
        goods_unit: this.getGoodsUnit(),
        attr_name: this.getAttrName(),
      }
    },
    handleCart() {
      if (!this.canBuy) {
        if (this.sellOut) {
          uni.showToast({ title: '该规格已售罄', icon: 'none' })
        } else {
          uni.showToast({ title: '请先选择规格', icon: 'none' })
        }
        return
      }
      this.$emit('addToCart', this.buildPayload())
    },
    handleBuyNow() {
      if (!this.canBuy) {
        if (this.sellOut) {
          uni.showToast({ title: '该规格已售罄', icon: 'none' })
        } else {
          uni.showToast({ title: '请先选择规格', icon: 'none' })
        }
        return
      }
      this.$emit('buyNow', this.buildPayload())
    },
  },
}
</script>

<style scoped>
.sheet-content { padding: 24rpx 32rpx 48rpx; }
.sheet-goods { display: flex; align-items: flex-start; margin-bottom: 24rpx; }
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
.sheet-stock.stock-zero { color: #f56c6c; }
.sheet-selected-spec { font-size: 22rpx; color: #4A90D9; margin-top: 4rpx; display: block; }

/* 规格选择 */
.sheet-attr { margin-bottom: 20rpx; }
.attr-group { margin-bottom: 16rpx; }
.attr-group:last-child { margin-bottom: 0; }
.attr-label { font-size: 24rpx; color: #606266; display: block; margin-bottom: 10rpx; }
.attr-options { display: flex; flex-wrap: wrap; gap: 12rpx; }
.attr-tag {
  padding: 10rpx 24rpx; font-size: 24rpx; color: #606266;
  background: #f5f7fa; border-radius: 8rpx; border: 2rpx solid #f5f7fa;
}
.attr-tag.selected { color: #4A90D9; background: #ecf5ff; border-color: #4A90D9; }
.attr-tag.disabled { color: #c0c4cc; background: #f5f5f5; border-color: #f5f5f5; text-decoration: line-through; }

/* 数量 */
.sheet-qty { display: flex; align-items: center; justify-content: space-between; padding: 24rpx 0; }
.sheet-qty-label { font-size: 28rpx; color: #303133; font-weight: 500; }

/* 底部双按钮 */
.sheet-btns { display: flex; gap: 20rpx; margin: 16rpx 0 10rpx; }
.sheet-btn {
  flex: 1; height: 76rpx; line-height: 76rpx;
  text-align: center; font-size: 28rpx; font-weight: 500; color: #fff;
  border-radius: 38rpx;
}
.cart-btn { background: #f5a623; }
.buy-btn  { background: #4A90D9; }
.btn-disabled { opacity: 0.5; pointer-events: none; }
</style>
