<template>
  <view class="page">
    <!-- 选择客户 -->
    <view class="section-card">
      <view class="section-header">
        <text class="section-title">下单客户</text>
        <view class="section-action" @click="showCustomerPicker = true" v-if="!customerFixed">
          {{ customer ? '切换' : '请选择' }}
          <u-icon name="arrow-right" size="24" color="#2979ff" />
        </view>
      </view>
      <view class="customer-info" v-if="customer" @click="customerFixed ? null : showCustomerPicker = true">
        <text class="customer-name">{{ customer.customer_name }}</text>
        <text class="customer-contact" v-if="customer.customer_mobile">{{ customer.customer_mobile }}</text>
      </view>
      <view class="customer-empty" v-else @click="showCustomerPicker = true">
        <u-icon name="account" size="32" color="#c0c4cc" />
        <text class="empty-text">请选择下单客户</text>
        <u-icon name="arrow-right" size="24" color="#c0c4cc" />
      </view>
    </view>

    <!-- 收货地址 -->
    <view class="section-card">
      <view class="section-header">
        <text class="section-title">收货地址</text>
      
      </view>
      <view class="address-area" v-if="address">
        <view class="address-info" @click="showAddressPicker = true">
          <view class="address-top">
            <text class="address-name">{{ address.first_connect_name || customer.connet_name }}</text>
            <text class="address-mobile">{{ address.first_connect_mobile || customer.customer_mobile }}</text>
          </view>
          <text class="address-detail u-line-2">{{ address.connect_address || customer.getgoods_address || '点击修改地址' }}</text>
        </view>
        <u-icon name="arrow-right" size="28" color="#c0c4cc" class="address-arrow" @click="showAddressPicker = true" />
      </view>
      <!-- 手动编辑地址 -->
      <view class="address-edit" v-if="showAddressEdit">
        <view class="address-edit-form">
          <view class="edit-field">
            <text class="edit-label">收货人</text>
            <u--input v-model="editReceiverName" placeholder="请输入收货人姓名" border="none" clearable inputClass="edit-input" />
          </view>
          <view class="edit-field">
            <text class="edit-label">手机号</text>
            <u--input v-model="editReceiverMobile" placeholder="请输入收货人手机" border="none" clearable type="number" inputClass="edit-input" />
          </view>
          <view class="edit-field">
            <text class="edit-label">收货地址</text>
            <u--textarea v-model="editReceiverAddress" placeholder="如：广东省 深圳市 南山区 XX路XX号" border="none" height="80" count inputClass="edit-textarea" />
          </view>
        </view>
        <view class="address-edit-actions">
          <u-button type="primary" shape="circle" @click="confirmManualAddress" class="edit-confirm-btn">确认地址</u-button>
          <u-button type="default" shape="circle" @click="showAddressEdit = false" class="edit-cancel-btn">取消</u-button>
        </view>
      </view>
      <view class="address-edit-toggle" v-if="customer && !showAddressEdit">
        <text class="edit-link" @click="openManualAddress">手动填写收货信息</text>
      </view>
      <view class="address-empty" v-if="!customer">
        <text class="empty-text" style="color:#c0c4cc;">请先选择客户</text>
      </view>
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
      <u-button class="submit-btn" type="primary" shape="circle" :loading="submitting" :disabled="submitting || !customer" @click="submitOrder">
        {{ submitting ? '提交中...' : '提交订单' }}
      </u-button>
    </view>

    <!-- 客户选择弹窗 -->
    <u-popup :show="showCustomerPicker" mode="bottom" round="16" @close="showCustomerPicker = false">
      <view class="picker-wrap">
        <view class="picker-header">
          <text class="picker-title">选择客户</text>
          <u-icon name="close" size="32" @click="showCustomerPicker = false" />
        </view>
        <view class="picker-search">
          <u-search v-model="customerKeyword" placeholder="搜索客户名称/手机号" :showAction="false" @search="searchCustomers" @clear="loadCustomers" />
        </view>
        <scroll-view scroll-y class="picker-list">
          <view class="picker-item" v-for="c in customerList" :key="c.id"
                :class="{ 'picker-item-active': customer && customer.id === c.id }"
                @click="selectCustomer(c)">
            <view class="picker-item-info">
              <text class="picker-item-name">{{ c.customer_name }}</text>
              <text class="picker-item-detail">{{ c.connet_name || '' }}{{ c.customer_mobile ? ' / ' + c.customer_mobile : '' }}</text>
              <text class="picker-item-addr u-line-1" v-if="c.getgoods_address">{{ c.getgoods_address }}</text>
            </view>
            <u-tag v-if="c.is_own" text="我的" type="primary" size="mini" plain />
            <u-icon v-if="customer && customer.id === c.id" name="checkbox-mark" size="28" color="#2979ff" />
          </view>
          <view class="picker-empty" v-if="customerList.length === 0">
            <text>未找到客户</text>
          </view>
        </scroll-view>
        <view class="picker-footer">
          <u-button type="primary" shape="circle" @click="openAddCustomer">新增客户</u-button>
        </view>
      </view>
    </u-popup>

    <!-- 新增客户弹窗 -->
    <u-popup :show="showAddCustomer" mode="bottom" round="16" @close="showAddCustomer = false">
      <view class="picker-wrap">
        <view class="picker-header">
          <text class="picker-title">新增客户</text>
          <u-icon name="close" size="32" @click="showAddCustomer = false" />
        </view>
        <view class="add-form">
          <u--input v-model="addCustomerName" placeholder="客户名称（必填）" border="bottom" clearable />
          <u--input v-model="addCustomerMobile" placeholder="客户手机" border="bottom" clearable type="number" />
          <u--input v-model="addCustomerContact" placeholder="联系人" border="bottom" clearable />
          <u--textarea v-model="addCustomerAddress" placeholder="收货地址" height="80" count />
          <u-button type="primary" shape="circle" :loading="addingCustomer" @click="submitAddCustomer">确认添加</u-button>
        </view>
      </view>
    </u-popup>

    <!-- 地址选择弹窗 -->
    <u-popup :show="showAddressPicker" mode="bottom" round="16" @close="showAddressPicker = false">
      <view class="picker-wrap">
        <view class="picker-header">
          <text class="picker-title">选择收货地址</text>
          <u-icon name="close" size="32" @click="showAddressPicker = false" />
        </view>
        <scroll-view scroll-y class="picker-list">
          <view class="picker-item" v-for="a in addressList" :key="a.id"
                :class="{ 'picker-item-active': address && address.id === a.id }"
                @click="selectAddress(a)">
            <view class="picker-item-info">
              <view class="address-top">
                <text class="picker-item-name">{{ a.first_connect_name }}</text>
                <text>{{ a.first_connect_mobile }}</text>
              </view>
              <text class="picker-item-addr u-line-1">{{ a.connect_address }}</text>
            </view>
            <u-icon v-if="address && address.id === a.id" name="checkbox-mark" size="28" color="#2979ff" />
          </view>
          <view class="picker-empty" v-if="addressList.length === 0">
            <text>暂无收货地址，请手动填写</text>
          </view>
        </scroll-view>
        <view class="picker-footer">
          <u-button type="primary" shape="circle" @click="showAddressPicker = false; openManualAddress()">手动填写地址</u-button>
        </view>
      </view>
    </u-popup>
  </view>
</template>

<script>
import { cartApi, orderApi, customerApi, userApi } from '../../api'

export default {
  data() {
    return {
      goodsList: [],
      customer: null,
      address: null,
      remark: '',
      submitting: false,
      directGoods: null,
      cartIds: '',
      customerFixed: false,

      // 客户选择
      showCustomerPicker: false,
      customerList: [],
      customerKeyword: '',

      // 新增客户
      showAddCustomer: false,
      addCustomerName: '',
      addCustomerMobile: '',
      addCustomerContact: '',
      addCustomerAddress: '',
      addingCustomer: false,

      // 地址选择
      showAddressPicker: false,
      addressList: [],

      // 手动地址
      showAddressEdit: false,
      editReceiverName: '',
      editReceiverMobile: '',
      editReceiverAddress: '',
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
    if (options.goods_id) {
      this.directGoods = {
        goods_id: Number(options.goods_id),
        quantity: Number(options.quantity) || 1,
        attr_ids: decodeURIComponent(options.attr_ids || ''),
        goods_unit: Number(options.goods_unit) || 0,
        attr_name: decodeURIComponent(options.attr_name || ''),
      }
    }
    if (options.ids) {
      this.cartIds = options.ids
    }
  },
  onShow() {
    if (this.directGoods) {
      this.loadDirectGoods()
    } else {
      this.loadCart()
    }
    this.loadCustomers()
    this.checkUserType()
  },
  methods: {
    // ========== 客户 ==========
    async checkUserType() {
      try {
        const res = await userApi.info()
        const miniUser = (res && res.mini_user) ? res.mini_user : null
        // 用户类型是客户类型,不可切换
        if (miniUser && miniUser.user_type ==='customer') {
          this.customerFixed = true
        }
      } catch (_) {}
    },
    async loadCustomers(keyword) {
      try {
        const res = await customerApi.list(keyword || '')
        this.customerList = res.list || []
        // 自动选中自己的客户
        if (!this.customer) {
          const own = this.customerList.find(c => c.is_own)
          if (own) {
            this.selectCustomer(own)
          }
        }
      } catch (_) {}
    },
    searchCustomers() {
      this.loadCustomers(this.customerKeyword)
    },
    selectCustomer(c) {
      this.customer = c
      this.showCustomerPicker = false
      // 加载该客户的地址
      this.loadAddresses(c.id)
      // 用客户默认信息填充地址
      this.address = {
        first_connect_name: c.connet_name || c.customer_name,
        first_connect_mobile: c.customer_mobile || '',
        connect_address: c.getgoods_address || '',
        is_first: 1,
      }
      this.showAddressEdit = false
    },
    openAddCustomer() {
      this.showCustomerPicker = false
      this.showAddCustomer = true
      this.addCustomerName = ''
      this.addCustomerMobile = ''
      this.addCustomerContact = ''
      this.addCustomerAddress = ''
    },
    async submitAddCustomer() {
      if (!this.addCustomerName) {
        uni.showToast({ title: '请输入客户名称', icon: 'none' })
        return
      }
      this.addingCustomer = true
      try {
        const res = await customerApi.add({
          customer_name: this.addCustomerName,
          customer_mobile: this.addCustomerMobile,
          connet_name: this.addCustomerContact || this.addCustomerName,
          getgoods_address: this.addCustomerAddress,
        })
        this.showAddCustomer = false
        this.addingCustomer = false
        // 选中新客户
        if (res.customer) {
          this.customer = res.customer
          this.address = {
            first_connect_name: res.customer.connet_name || res.customer.customer_name,
            first_connect_mobile: res.customer.customer_mobile || '',
            connect_address: res.customer.getgoods_address || '',
            is_first: 1,
          }
          this.addressList = []
        }
        this.loadCustomers()
      } catch (e) {
        uni.showToast({ title: typeof e === 'string' ? e : '添加失败', icon: 'none' })
      } finally {
        this.addingCustomer = false
      }
    },

    // ========== 地址 ==========
    async loadAddresses(customerId) {
      try {
        const res = await customerApi.addresses(customerId || (this.customer ? this.customer.id : 0))
        this.addressList = res.list || []
        if (res.customer && !this.customer) {
          this.customer = res.customer
        }
        const def = this.addressList.find(a => a.is_first == 1) || this.addressList[0]
        if (def) {
          this.address = def
        }
      } catch (_) {}
    },
    selectAddress(a) {
      this.address = a
      this.showAddressPicker = false
      this.showAddressEdit = false
    },
    openManualAddress() {
      this.editReceiverName = this.address ? (this.address.first_connect_name || '') : ''
      this.editReceiverMobile = this.address ? (this.address.first_connect_mobile || '') : ''
      this.editReceiverAddress = this.address ? (this.address.connect_address || '') : ''
      this.showAddressEdit = true
    },
    async confirmManualAddress() {
      if (!this.editReceiverName || !this.editReceiverAddress) {
        uni.showToast({ title: '请填写收货人和地址', icon: 'none' })
        return
      }
      // 保存到客户地址库
      if (this.customer && this.customer.id) {
        try {
          const res = await customerApi.saveAddress({
            customer_id: this.customer.id,
            first_connect_name: this.editReceiverName,
            first_connect_mobile: this.editReceiverMobile,
            connect_address: this.editReceiverAddress,
          })
          if (res && res.address) {
            this.address = res.address
            // 同步更新到地址列表（去重替换）
            const idx = this.addressList.findIndex(a => a.id === res.address.id)
            if (idx >= 0) {
              this.addressList.splice(idx, 1, res.address)
            } else {
              this.addressList.unshift(res.address)
            }
          }
        } catch (e) {
          // 保存失败时降级为本地地址
          this.address = {
            first_connect_name: this.editReceiverName,
            first_connect_mobile: this.editReceiverMobile,
            connect_address: this.editReceiverAddress,
            is_first: 0,
          }
        }
      } else {
        // 无客户时仅本地使用
        this.address = {
          first_connect_name: this.editReceiverName,
          first_connect_mobile: this.editReceiverMobile,
          connect_address: this.editReceiverAddress,
          is_first: 0,
        }
      }
      this.showAddressEdit = false
      uni.showToast({ title: '地址已更新', icon: 'success' })
    },

    // ========== 商品 ==========
    async loadCart() {
      try {
        const res = await cartApi.index({ ids: this.cartIds })
        const list = (res.list || []).filter(i => !i.sell_out)
        if (list.length === 0) {
          uni.showToast({ title: '购物车为空', icon: 'none' })
          setTimeout(() => uni.navigateBack(), 500)
          return
        }
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
        const goods = (res && res.goods) ? res.goods : res
        if (!goods || !goods.id) {
          uni.showToast({ title: '商品不存在', icon: 'none' })
          setTimeout(() => uni.navigateBack(), 500)
          return
        }
        let unitName = ''
        const unitId = this.directGoods.goods_unit
        if (unitId && res.units) {
          const unit = res.units.find(u => u.id == unitId)
          if (unit) unitName = unit.name
        }
        let attrName = this.directGoods.attr_name || ''
        const attrIds = this.directGoods.attr_ids
        if (!attrName && attrIds) {
          const ids = ('' + attrIds).split('&').map(Number)
          const names = []
          const source = res.attr_groups || res.attrs || []
          source.forEach(group => {
            (group.values || []).forEach(v => {
              const vid = v.attribute_id || v.id
              if (ids.includes(vid)) names.push(v.value_name || v.name || v)
            })
          })
          attrName = names.join(' / ')
        }
        let skuPrice = goods.show_price || goods.market_sell_price || 0
        let skuStock = goods.stock || 0
        if (attrIds && res.attrs && res.attrs.length > 0) {
          const sortedIds = ('' + attrIds).split('&').map(Number).sort((a, b) => a - b).join('&')
          const matched = res.attrs.find(a => {
            if (!a.aux_value_ids || a.aux_value_ids.length === 0) return false
            const aSorted = [...a.aux_value_ids].sort((a, b) => a - b).join('&')
            return aSorted === sortedIds
          })
          if (matched) {
            skuPrice = matched.show_price || skuPrice
            skuStock = matched.stock || 0
          }
        }
        this.goodsList = [{
          goods_id: goods.id,
          goods_name: goods.goods_name,
          goods_image: goods.goods_image,
          quantity: this.directGoods.quantity,
          unit_price: skuPrice,
          goods_unit: unitId,
          attr_ids: attrIds,
          attr_name: attrName,
          unit_name: unitName,
          stock: skuStock,
        }]
      } catch (e) { uni.showToast({ title: e, icon: 'none' }) }
    },
    calcItemTotal(item) {
      return ((item.unit_price || item.show_price || 0) * item.quantity).toFixed(2)
    },

    // ========== 提交订单 ==========
    async submitOrder() {
      if (this.submitting) return
      if (!this.customer) {
        uni.showToast({ title: '请选择客户', icon: 'none' })
        return
      }
      if (!this.address) {
        uni.showToast({ title: '请填写收货地址', icon: 'none' })
        return
      }
      this.submitting = true
      try {
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

        // 构造下单参数
        const orderData = {
          customer_id: this.customer.id,
          remark: this.remark,
          goods: JSON.stringify(goods),
        }
        // 地址：优先用 address_id，否则手动传
        if (this.address.id && this.address.id > 0) {
          orderData.address_id = this.address.id
        }
        orderData.receiver_name = this.address.first_connect_name || ''
        orderData.receiver_mobile = this.address.first_connect_mobile || ''
        orderData.receiver_address = this.address.connect_address || ''

        const res = await orderApi.add(orderData)
        uni.showToast({ title: '下单成功' })
        setTimeout(() => {
          uni.redirectTo({ url: '/pages/order/detail?id=' + res.order.id })
        }, 500)
      } catch (e) {
        uni.showToast({ title: typeof e === 'string' ? e : '提交失败', icon: 'none' })
      } finally {
        this.submitting = false
      }
    },
  },
}
</script>

<style scoped>
.page { padding-bottom: 140rpx; min-height: 100vh; background: #f5f7fa; }

.section-card {
  background: #fff; margin: 20rpx; border-radius: 16rpx; padding: 24rpx;
  overflow: hidden;
}
.section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16rpx; }
.section-title { font-size: 28rpx; font-weight: 600; color: #303133; }
.section-action { font-size: 24rpx; color: #2979ff; display: flex; align-items: center; gap: 4rpx; height: 100%; }

/* 客户 */
.customer-info { display: flex; align-items: center; gap: 16rpx; }
.customer-name { font-size: 30rpx; font-weight: 600; color: #303133; }
.customer-contact { font-size: 26rpx; color: #909399; }
.customer-empty { display: flex; align-items: center; gap: 12rpx; padding: 8rpx 0; }
.empty-text { font-size: 26rpx; color: #c0c4cc; flex: 1; }

/* 地址 */
.address-area { display: flex; align-items: center; }
.address-info { flex: 1; min-width: 0; }
.address-top { display: flex; align-items: center; gap: 12rpx; margin-bottom: 8rpx; }
.address-name { font-size: 30rpx; font-weight: 600; color: #303133; flex-shrink: 0; }
.address-mobile { font-size: 26rpx; color: #606266; flex-shrink: 0; }
.address-detail { font-size: 26rpx; color: #909399; line-height: 1.5; }
.address-empty { padding: 8rpx 0; }
.address-arrow { flex-shrink: 0; margin-left: 16rpx; }
.address-edit { padding-top: 16rpx; }
.address-edit-form {
  background: #f8f9fc; border-radius: 12rpx; padding: 8rpx 16rpx;
}
.edit-field {
  display: flex; align-items: center; min-height: 88rpx;
  border-bottom: 1rpx solid #eee;
}
.edit-field:last-of-type { border-bottom: none; }
.edit-label {
  font-size: 26rpx; color: #606266; width: 120rpx; flex-shrink: 0;
  font-weight: 500;
}
.edit-field .edit-input { flex: 1; }
.edit-field .edit-textarea { flex: 1; }
.address-edit-actions {
  display: flex; gap: 16rpx; margin-top: 20rpx;
}
.edit-confirm-btn { flex: 1; }
.edit-cancel-btn { flex: 1; }
.address-edit-toggle { padding-top: 12rpx; }
.edit-link { font-size: 24rpx; color: #2979ff; }

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

/* 弹窗 */
.picker-wrap { max-height: 70vh; display: flex; flex-direction: column; padding: 24rpx 24rpx 0; }
.picker-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16rpx; }
.picker-title { font-size: 32rpx; font-weight: 600; color: #303133; }
.picker-search { margin-bottom: 16rpx; }
.picker-list { max-height: 50vh; }
.picker-item {
  display: flex; align-items: center; padding: 20rpx 0;
  border-bottom: 1rpx solid #f5f7fa;
}
.picker-item-active { background: #f0f7ff; margin: 0 -24rpx; padding: 20rpx 24rpx; }
.picker-item-info { flex: 1; min-width: 0; }
.picker-item-name { font-size: 28rpx; font-weight: 500; color: #303133; display: block; }
.picker-item-detail { font-size: 24rpx; color: #909399; margin-top: 4rpx; display: block; }
.picker-item-addr { font-size: 22rpx; color: #c0c4cc; margin-top: 4rpx; }
.picker-empty { text-align: center; padding: 40rpx 0; color: #c0c4cc; font-size: 26rpx; }
.picker-footer { padding: 20rpx 0; }

.add-form { padding-top: 12rpx; }
.add-form > * { margin-bottom: 16rpx; }

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
