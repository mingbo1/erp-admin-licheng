<template>
  <view class="page">
    <!-- 地址列表 -->
    <view class="addr-list" v-if="list.length > 0">
      <view
        class="addr-card"
        v-for="item in list"
        :key="item.id"
        @click="selectAddress(item)"
        hover-class="card-hover"
      >
        <view class="addr-top">
          <text class="addr-name">{{ item.first_connect_name }}</text>
          <text class="addr-mobile">{{ item.first_connect_mobile }}</text>
          <u-tag v-if="item.is_first == 1" text="默认" type="primary" size="mini" plain class="default-tag" />
        </view>
        <text class="addr-detail">{{ item.connect_address }}</text>
        <view class="addr-actions">
          <view class="action-btn" @click.stop="editAddress(item)">
            <u-icon name="edit-pen" size="28" color="#909399" />
            <text class="action-text">编辑</text>
          </view>
          <view class="action-btn" @click.stop="deleteAddress(item)">
            <u-icon name="trash" size="28" color="#909399" />
            <text class="action-text">删除</text>
          </view>
          <view class="action-btn" v-if="item.is_first != 1" @click.stop="setDefault(item)">
            <u-icon name="checkmark-circle" size="28" color="#909399" />
            <text class="action-text">设为默认</text>
          </view>
        </view>
      </view>
    </view>

    <!-- 空状态 -->
    <u-empty mode="address" text="暂无收货地址" v-if="loaded && list.length === 0 && !loading"></u-empty>

    <!-- 加载中 -->
    <view class="loading-wrap" v-if="loading && list.length === 0">
      <u-skeleton rows="3" loading :title="false" />
    </view>

    <!-- 新增按钮 -->
    <view class="add-btn-wrap">
      <u-button type="primary" shape="circle" icon="plus" @click="addAddress">新增地址</u-button>
    </view>
  </view>
</template>

<script>
import { addressApi } from '../../api'

export default {
  data() {
    return {
      list: [],
      loading: false,
      loaded: false,
      selectMode: false,
    }
  },
  onLoad(options) {
    this.selectMode = options.select === '1'
  },
  onShow() {
    this.loadList()
  },
  onPullDownRefresh() {
    this.loadList().finally(() => uni.stopPullDownRefresh())
  },
  methods: {
    async loadList() {
      this.loading = true
      try {
        const res = await addressApi.index()
        this.list = res.list || []
        this.loaded = true
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
      } finally {
        this.loading = false
      }
    },
    selectAddress(item) {
      if (this.selectMode) {
        uni.$emit('selectAddress', item)
        uni.navigateBack()
      }
    },
    addAddress() {
      uni.navigateTo({ url: '/pages/address/edit' })
    },
    editAddress(item) {
      uni.navigateTo({ url: '/pages/address/edit?id=' + item.id })
    },
    deleteAddress(item) {
      uni.showModal({
        title: '提示',
        content: '确定删除该地址吗？',
        success: async (res) => {
          if (res.confirm) {
            try {
              await addressApi.delete(item.id)
              uni.showToast({ title: '已删除', icon: 'none' })
              this.loadList()
            } catch (e) {
              uni.showToast({ title: e, icon: 'none' })
            }
          }
        }
      })
    },
    async setDefault(item) {
      try {
        await addressApi.setDefault(item.id)
        uni.showToast({ title: '设置成功', icon: 'none' })
        this.loadList()
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
      }
    },
  },
}
</script>

<style scoped>
.page { min-height: 100vh; padding-bottom: 140rpx; background: #f5f7fa; }

.addr-list { padding: 20rpx; }
.addr-card {
  background: #fff;
  border-radius: 16rpx;
  padding: 28rpx 24rpx;
  margin-bottom: 16rpx;
  box-shadow: 0 2rpx 8rpx rgba(0,0,0,0.04);
  transition: transform 0.15s;
}
.card-hover { transform: scale(0.98); }
.addr-top { display: flex; align-items: center; gap: 12rpx; margin-bottom: 12rpx; }
.addr-name { font-size: 30rpx; font-weight: 600; color: #303133; }
.addr-mobile { font-size: 26rpx; color: #606266; }
.default-tag { margin-left: auto; }
.addr-detail { font-size: 26rpx; color: #606266; line-height: 1.5; }
.addr-actions { display: flex; gap: 32rpx; margin-top: 20rpx; padding-top: 16rpx; border-top: 1rpx solid #f5f7fa; }
.action-btn { display: flex; align-items: center; gap: 6rpx; }
.action-text { font-size: 24rpx; color: #909399; }

.loading-wrap { padding: 20rpx; }

.add-btn-wrap {
  position: fixed;
  bottom: env(safe-area-inset-bottom);
  left: 0; right: 0;
  padding: 20rpx 20rpx 40rpx;
}
</style>
