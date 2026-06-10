<template>
  <view class="page">
    <!-- 收货地址 -->
    <view class="section-card" hover-class="card-hover" @click="selectAddress">
      <view class="address-area" v-if="address">
        <view class="address-top">
          <text class="address-name">{{ address.first_connect_name }}</text>
          <text class="address-mobile">{{ address.first_connect_mobile }}</text>
          <u-tag v-if="address.is_first == 1" text="默认" type="primary" size="mini" plain />
        </view>
        <text class="address-detail">{{ address.connect_address }}</text>
      </view>
      <view class="address-empty" v-else>
        <u-icon name="plus-circle" size="36" color="#c0c4cc" />
        <text class="address-empty-text">请添加收货地址</text>
      </view>
      <u-icon name="arrow-right" size="28" color="#c0c4cc" class="address-arrow" />
    </view>

    <!-- 商品清单 -->
    <view class="section-card">
      <view class="section-header">
        <text class="section-title">商品清单</text>
        <u-tag :text="itemCount + '件'" type="info" size="mini" plain />
      </view>
      <u-divider half-width="20" :hairline="true"></u-divider>
      <view class="goods-item" v-for="item in goodsList" :key="item.id || item.goods_id">
        <view class="goods-img">
          <image v-if="item.goods_image" :src="$img(item.goods_image)" mode="aspectFill" style="width:100%;height:100%;border-radius:8rpx;" />
          <u-icon v-else name="shopping-cart" size="32" color="#c0c4cc" />
        </view>
        <view class="goods-info">
          <text class="goods-name u-line-1">{{ item.goods_name || item.goods.goods_name }}</text>
          <text class="goods-spec" v-if="item.attr_name || item.unit_name">{{ item.unitName || item.unit_name }}{{ item.attr_name ? ' / ' + item.attr_name : '' }}</text>
          <text class="goods-price-tag">¥{{ item.unit_price || item.show_price || item.goods.sale_price }}</text>
        </view>
        <view class="goods-right">
          <text class="goods-qty">× {{ item.quantity }}</text>
          <text class="goods-total">¥{{ calcItemTotal(item) }}</text>
        </view>
      </view>

      <!-- 合计 -->
      <view class="total-row">
        <text>商品合计</text>
        <text class="total-price">¥{{ totalAmount }}</text>
      </view>
    </view>

    <!-- 备注 -->
    <view class="section-card">
      <view class="section-header">
        <text class="section-title">订单备注</text>
      </view>
      <u--textarea v-model="remark" placeholder="选填，如有特殊要求请在此备注" maxlength="200" count height="120" />
    </view>

    <!-- 提交栏 -->
    <view class="submit-bar safe-area-bottom">
      <view class="submit-total">
        <text class="submit-label">实付</text>
        <text class="submit-price">¥{{ totalAmount }}</text>
      </view>
      <u-button class="submit-btn" type="primary" shape="circle" :loading="submitting" :disabled="submitting || !address" @click="submitOrder">
        {{ submitting ? '提交中...' : '提交订单' }}
      </u-button>
    </view>
  </view>
</template>

<script>
import { cartApi, orderApi, addressApi } from '../../api'

export default {
  data() {
    return {
      goodsList: [],
      address: null,
      remark: '',
      submitting: false,
      // buyNow 模式
      directGoods: null,
    }
  },
  computed: {
    totalAmount() {
      return this.goodsList
        .reduce((s, i) => s + (i.unit_price || i.show_price || 0) * i.quantity, 0)
        .toFixed(2)
    },
    itemCount() {
      return this.goodsList.reduce((s, i) => s + i.quantity, 0)
    },
  },
  onLoad(options) {
    // buyNow 模式
    if (options.goods_id) {
      this.directGoods = {
        goods_id: Number(options.goods_id),
        quantity: Number(options.quantity) || 1,
        attr_ids: options.attr_ids || '',
        goods_unit: Number(options.goods_unit) || 0,
      }
    }
  },
  onShow() {
    this.loadAddress()
    // 来自地址选择
    uni.$on('selectAddress', (addr) => {
      this.address = addr
    })
    if (this.directGoods) {
      this.loadDirectGoods()
    } else {
      this.loadCart()
    }
  },
  onUnload() {
    uni.$off('selectAddress')
  },
  methods: {
    async loadAddress() {
      try {
        const res = await addressApi.index()
        const list = res.list || []
        const defaultAddr = list.find(a => a.is_first == 1) || list[0]
        if (defaultAddr) this.address = defaultAddr
      } catch (_) {}
    },
    async loadCart() {
      try {
        const res = await cartApi.index()
        const list = (res.list || []).filter(i => !i.sell_out)
        if (list.length === 0) {
          uni.showToast({ title: '购物车为空', icon: 'none' })
          setTimeout(() => uni.navigateBack(), 500)
          return
        }
        // 映射字段用于显示
        this.goodsList = list.map(item => ({
          ...item,
          goods_id: item.goods_id,
          goods_name: item.goods_name,
          goods_image: item.goods_image,
          quantity: item.quantity,
          unit_price: item.show_price || 0,
          unit_name: item.unit_name,
          attr_name: item.attr_name,
        }))
      } catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
    async loadDirectGoods() {
      try {
        const { goodsApi } = await import('../../api')
        const res = await goodsApi.detail(this.directGoods.goods_id)
        // 后端返回 { goods, units, attrs }，需要解包
        const goods = (res && res.goods) ? res.goods : res
        if (!goods || !goods.id) {
          uni.showToast({ title: '商品不存在', icon: 'none' })
          setTimeout(() => uni.navigateBack(), 500)
          return
        }
        // 解析单位名称
        let unitName = ''
        const unitId = this.directGoods.goods_unit
        if (unitId && res.units) {
          const unit = res.units.find(u => u.id == unitId)
          if (unit) unitName = unit.name
        }
        // 解析属性名称
        let attrName = ''
        const attrIds = this.directGoods.attr_ids
        if (attrIds && res.attrs) {
          const ids = attrIds.split('&').map(Number)
          const names = []
          res.attrs.forEach(group => {
            (group.values || []).forEach(v => {
              const vid = v.id || v.value_id
              if (ids.includes(vid)) names.push(v.name || v.value_name || v)
            })
          })
          attrName = names.join(' / ')
        }
        this.goodsList = [{
          goods_id: goods.id,
          goods_name: goods.goods_name,
          goods_image: goods.goods_image,
          quantity: this.directGoods.quantity,
          unit_price: goods.show_price || goods.market_sell_price || 0,
          goods_unit: unitId,
          attr_ids: attrIds,
          attr_name: attrName,
          unit_name: unitName,
        }]
      } catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
    selectAddress() {
      uni.navigateTo({ url: '/pages/address/list?select=1' })
    },
    calcItemTotal(item) {
      return ((item.unit_price || item.show_price || 0) * item.quantity).toFixed(2)
    },
    async submitOrder() {
      if (this.submitting) return
      if (!this.address) {
        uni.showToast({ title: '请选择收货地址', icon: 'none' })
        return
      }
      this.submitting = true
      try {
        // 构造商品列表数据
        const goods = this.directGoods ? [{
          goods_id: this.directGoods.goods_id,
          quantity: this.directGoods.quantity,
          attr_ids: this.directGoods.attr_ids,
          goods_unit: this.directGoods.goods_unit,
        }] : this.goodsList.map(item => ({
          goods_id: item.goods_id,
          quantity: item.quantity,
          attr_ids: item.attr_ids || '',
          goods_unit: item.goods_unit || 0,
        }))

        const res = await orderApi.add({
          address_id: this.address.id,
          remark: this.remark,
          goods: JSON.stringify(goods),
        })
        uni.showToast({ title: '下单成功' })
        setTimeout(() => {
          uni.redirectTo({ url: '/pages/order/detail?id=' + res.order.id })
        }, 500)
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
      } finally {
        this.submitting = false
      }
    },
  },
}
</script>

<style scoped>
.page { padding-bottom: 140rpx; min-height: 100vh; background: #f5f7fa; }
.card-hover { transform: scale(0.98); }

.section-card {
  background: #fff; margin: 20rpx; border-radius: 16rpx; padding: 24rpx; transition: transform 0.15s;
  position: relative;    /* 让内部 absolute 元素相对于此容器定位 */
  overflow: hidden;      /* 防止内容溢出圆角 */
}
.section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16rpx; }
.section-title { font-size: 28rpx; font-weight: 600; color: #303133; }

/* 地址 */
.address-area { position: relative; padding-right: 40rpx; }
.address-top { display: flex; align-items: center; gap: 12rpx; margin-bottom: 8rpx; }
.address-name { font-size: 30rpx; font-weight: 600; color: #303133; }
.address-mobile { font-size: 26rpx; color: #606266; }
.address-detail { font-size: 26rpx; color: #909399; line-height: 1.5; }
.address-empty { display: flex; align-items: center; gap: 12rpx; padding: 20rpx 0; }
.address-empty-text { font-size: 26rpx; color: #c0c4cc; }
.address-arrow { position: absolute; right: 0; top: 50%; transform: translateY(-50%); }

/* 商品 */
.goods-item { display: flex; align-items: center; padding: 16rpx 0; }
.goods-item + .goods-item { border-top: 1rpx solid #f5f7fa; }
.goods-img {
  width: 72rpx; height: 72rpx; background: #f5f7fa; border-radius: 8rpx;
  display: flex; align-items: center; justify-content: center;
  margin-right: 16rpx; flex-shrink: 0; overflow: hidden;
}
.goods-info { flex: 1; }
.goods-name { font-size: 26rpx; color: #303133; display: block; }
.goods-spec { font-size: 22rpx; color: #c0c4cc; margin-top: 4rpx; display: block; }
.goods-price-tag { font-size: 22rpx; color: #909399; margin-top: 4rpx; display: block; }
.goods-right { text-align: right; margin-left: 16rpx; }
.goods-qty { font-size: 24rpx; color: #909399; display: block; }
.goods-total { font-size: 26rpx; color: #303133; font-weight: 500; margin-top: 4rpx; display: block; }

.total-row {
  display: flex; justify-content: space-between; padding-top: 16rpx; margin-top: 8rpx;
  border-top: 2rpx solid #f5f7fa; font-size: 28rpx; color: #303133; font-weight: 600;
}
.total-row .total-price { color: #f56c6c; font-size: 32rpx; }

/* 提交栏 */
.submit-bar {
  position: fixed; bottom: 0; left: 0; right: 0; background: #fff;
  display: flex; align-items: center; justify-content: space-between;
  padding: 16rpx 24rpx; border-top: 1rpx solid #f2f2f2;
}
.submit-total { display: flex; align-items: baseline; gap: 8rpx; width: 350rpx; }
.submit-label { font-size: 26rpx; color: #606266; }
.submit-price { font-size: 36rpx; color: #f56c6c; font-weight: 700; }
.submit-btn { width: 400rpx; }
</style>
