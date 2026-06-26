<template>
  <view class="page">
    <!-- 头像区域 -->
    <view class="avatar-section">
      <view class="avatar-wrap" @click="changeAvatar">
        <image v-if="avatarUrl" class="avatar-img" :src="$img(avatarUrl)" mode="aspectFill" />
        <view v-else class="avatar-placeholder">
          <u-icon name="camera" size="48" color="#ccc" />
        </view>
        <view class="avatar-mask">
          <u-icon name="camera" size="28" color="#fff" />
          <text class="mask-text">更换头像</text>
        </view>
      </view>
    </view>

    <!-- 表单 -->
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
      <u-button type="primary" shape="circle" :loading="saving" @click="saveProfile">保存修改</u-button>
    </view>
  </view>
</template>

<script>
import { userApi } from '../../api'

export default {
  data() {
    return {
      avatarUrl: '',          // 当前显示的头像URL
      originalAvatar: '',     // 加载时的原始头像URL（用于判断是否修改）
      nickname: '',
      mobile: '',
      form: { nickname: '', mobile: '' },
      saving: false,
    }
  },
  onShow() {
    this.loadUserInfo()
  },
  methods: {
    async loadUserInfo() {
      try {
        const res = await userApi.info()
        const mu = res.mini_user || res || {}
        this.nickname = mu.nickname || ''
        this.mobile = mu.mobile || ''
        this.avatarUrl = mu.avatar || ''
        this.originalAvatar = this.avatarUrl
        this.form.nickname = this.nickname
        this.form.mobile = this.mobile
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
      }
    },

    // 更换头像
    changeAvatar() {
      uni.chooseImage({
        count: 1,
        sizeType: ['compressed'],
        sourceType: ['album', 'camera'],
        success: async (res) => {
          const tempFilePath = res.tempFilePaths[0]
          if (!tempFilePath) return

          // 先展示本地图片
          this.avatarUrl = tempFilePath
          uni.showLoading({ title: '上传中...' })

          try {
            const uploadRes = await userApi.updateImage(tempFilePath)

              console.log(uploadRes,'上传成功')
            const url = uploadRes.url
            if (url) {
              console.log(url,'上传成功')
              this.avatarUrl = url
              uni.hideLoading()
              uni.showToast({ title: '头像已更新', icon: 'success' })
            } else {
              throw new Error('上传返回异常')
            }
          } catch (e) {
            uni.hideLoading()
            // 上传失败，回退到原始头像
            this.avatarUrl = this.originalAvatar
            uni.showToast({ title: '头像上传失败，请重试', icon: 'none' })
          }
        }
      })
    },

    async saveProfile() {
      if (!this.form.nickname) {
        uni.showToast({ title: '请输入昵称', icon: 'none' })
        return
      }
      if (this.saving) return
      this.saving = true

      try {
        const data = {}
        if (this.form.nickname !== this.nickname) data.nickname = this.form.nickname
        if (this.form.mobile !== this.mobile) data.mobile = this.form.mobile
        // 头像：上传成功且与原始不同时传给后端
        if (this.avatarUrl && this.avatarUrl !== this.originalAvatar) {
          data.avatar = this.avatarUrl
        }

        if (Object.keys(data).length === 0) {
          uni.showToast({ title: '未修改', icon: 'none' })
          return
        }

        await userApi.update(data)
        uni.showToast({ title: '保存成功' })
        setTimeout(() => uni.navigateBack(), 800)
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
      } finally {
        this.saving = false
      }
    },
  },
}
</script>

<style scoped>
.page { min-height: 100vh; background: #f5f7fa; padding-top: 20rpx; }

/* 头像区域 */
.avatar-section {
  display: flex;
  justify-content: center;
  padding: 48rpx 0 32rpx;
}
.avatar-wrap {
  position: relative;
  width: 160rpx;
  height: 160rpx;
  border-radius: 50%;
  overflow: hidden;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.12);
}
.avatar-img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
}
.avatar-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f0f0f0;
  border-radius: 50%;
}
.avatar-mask {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 60rpx;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.mask-text {
  font-size: 20rpx;
  color: #fff;
  line-height: 1.2;
}

/* 表单 */
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
