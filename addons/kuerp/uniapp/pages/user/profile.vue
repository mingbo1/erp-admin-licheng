<template>
  <view class="page">
    <view class="form-card">
      <view class="form-header">基本资料</view>
      <u--form :model="form" ref="formRef">
        <u-form-item label="昵称" borderBottom :labelWidth="70">
          <u--input v-model="form.nickname" placeholder="请输入昵称" border="none" />
        </u-form-item>
        <u-form-item label="手机号" borderBottom :labelWidth="70">
          <u--input v-model="form.mobile" placeholder="请输入手机号" type="number" maxlength="11" border="none" />
        </u-form-item>
      </u--form>
    </view>

    <view class="save-btn-wrap">
      <u-button type="primary" shape="circle" @click="saveProfile">保存修改</u-button>
    </view>
  </view>
</template>

<script>
import { userApi } from '../../api'

export default {
  data() {
    return {
      nickname: '',
      mobile: '',
      form: { nickname: '', mobile: '' },
    }
  },
  onShow() {
    this.loadUserInfo()
  },
  methods: {
    async loadUserInfo() {
      try {
        const res = await userApi.info()
        const mu = res.mini_user || {}
        this.nickname = mu.nickname || ''
        this.mobile = mu.mobile || ''
        this.form.nickname = this.nickname
        this.form.mobile = this.mobile
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
      }
    },
    async saveProfile() {
      if (!this.form.nickname) {
        uni.showToast({ title: '请输入昵称', icon: 'none' })
        return
      }
      try {
        const data = {}
        if (this.form.nickname !== this.nickname) data.nickname = this.form.nickname
        if (this.form.mobile !== this.mobile) data.mobile = this.form.mobile
        if (Object.keys(data).length === 0) {
          uni.showToast({ title: '未修改', icon: 'none' })
          return
        }
        await userApi.update(data)
        uni.showToast({ title: '保存成功' })
        uni.navigateBack()
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
      }
    },
  },
}
</script>

<style scoped>
.page { min-height: 100vh; background: #f5f7fa; padding-top: 20rpx; }

.form-card {
  background: #fff;
  margin: 0 20rpx;
  border-radius: 16rpx;
  padding: 24rpx 32rpx;
}
.form-header {
  font-size: 28rpx;
  font-weight: 600;
  color: #303133;
  margin-bottom: 8rpx;
}

.save-btn-wrap {
  margin: 48rpx 20rpx 0;
}
</style>
