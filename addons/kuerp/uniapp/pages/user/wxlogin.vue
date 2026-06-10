<template>
	<view class="wxlogin-page">
	
		<view class="content">
			<!-- 头像选择区域 -->
			<view class="avatar-section">
				<button class="avatar-btn" open-type="chooseAvatar" @chooseavatar="onChooseAvatar">
					<image v-if="avatarUrl" class="avatar-img" :src="avatarUrl" mode="aspectFill"></image>
					<view v-else class="avatar-placeholder">
						<u-icon name="camera" size="48" color="#ccc"></u-icon>
					</view>
				</button>
				<text class="avatar-tip">点击选择头像</text>
			</view>

			<!-- 昵称输入 -->
			<view class="nickname-section">
				<text class="label">昵称</text>
				<input
					type="nickname"
					class="nickname-input"
					v-model="nickname"
					placeholder="请输入昵称"
					maxlength="50"
				/>
			</view>

			<!-- 权限说明 -->
			<view class="permission-tip">
				<text class="tip-title">申请获取以下权限</text>
				<text class="tip-desc">获取你的昵称、头像</text>
			</view>

			<!-- 底部按钮 -->
			<view class="btn-group">
				<u-button
					shape="circle"
					class="btn-allow"
					type="primary"
					:loading="loading"
					:custom-style="{ backgroundColor: themeColor, borderColor: themeColor }"
					@click="handleAllow"
				>允许</u-button>
				<u-button
					shape="circle"
					class="btn-refuse"
					@click="handleRefuse"
				>暂不登录</u-button>
			</view>

			<!-- 账号密码登录入口 -->
			<view class="other-login">
				<text class="link" @click="toAccountLogin">账密登录</text>
			</view>
		</view>
	</view>
</template>

<script>
import { userApi } from '@/api/index.js'
import { setToken } from '@/api/request.js'

export default {
	data() {
		return {
			avatarUrl: '',
			avatarFilePath: '',     // chooseAvatar 返回的临时路径，用于上传
			nickname: '',
			loading: false,
			themeColor: '#07c160',  // 微信绿色
			hasAccount: false       // 静默登录是否已有账号
		}
	},
	onLoad() {
		this.silentCheck()
	},
	methods: {
		// ========== 步骤1：静默检查 ==========
		// 用 wx.login 获取 code 发给后端，检查是否已有账号
		async silentCheck() {
			try {
				const code = await this.getWxCode()
				const res = await userApi.wxLogin({ code })

				if (res && res.userinfo) {
					// 已有账号：直接登录
					this.saveLogin(res.userinfo)
					uni.showToast({ title: '登录成功', icon: 'success' })
					setTimeout(() => this.goHome(), 800)
				} else {
					// 新用户：等待填写头像和昵称
					this.hasAccount = false
				}
			} catch (e) {
				// 静默检查失败（例如首次访问），正常流程，不需要提示
				this.hasAccount = false
			}
		},

		// ========== 步骤2：用户授权登录 ==========
		async handleAllow() {
			if (this.loading) return

			if (!this.avatarUrl) {
				uni.showToast({ title: '请选择头像', icon: 'none' })
				return
			}
			if (!this.nickname.trim()) {
				uni.showToast({ title: '请填写昵称', icon: 'none' })
				return
			}

			this.loading = true
			try {
				// 如果有新的临时头像文件，先上传
				let avatarUrl = this.avatarUrl
				if (this.avatarFilePath) {
					try {
						const uploadRes = await userApi.uploadAvatar(this.avatarFilePath)
						avatarUrl = uploadRes.fullurl || uploadRes.url || avatarUrl
					} catch (e) {
						// 上传失败就用原始路径（可能是 base64 或已有 URL）
						console.warn('头像上传失败，使用原始路径', e)
					}
				}

				// 重新获取 code
				const code = await this.getWxCode()

				// 调用登录接口
				const res = await userApi.wxLogin({
					code,
					nickname: this.nickname.trim(),
					avatar: avatarUrl
				})

				if (res && res.userinfo) {
					this.saveLogin(res.userinfo)
					uni.showToast({ title: '登录成功', icon: 'success' })
					setTimeout(() => this.goHome(), 800)
				} else {
					uni.showToast({ title: '登录失败，请重试', icon: 'none' })
				}
			} catch (e) {
				console.error('登录失败:', e)
				uni.showToast({ title: typeof e === 'string' ? e : '登录失败', icon: 'none' })
			} finally {
				this.loading = false
			}
		},

		// ========== 辅助方法 ==========

		// 获取微信登录 code
		getWxCode() {
			return new Promise((resolve, reject) => {
				// #ifdef MP-WEIXIN
				wx.login({
					success: (res) => {
						if (res.code) {
							resolve(res.code)
						} else {
							reject('获取微信登录凭证失败')
						}
					},
					fail: () => reject('微信登录失败')
				})
				// #endif
				// #ifndef MP-WEIXIN
				reject('非微信小程序环境')
				// #endif
			})
		},

		// 保存登录态
		saveLogin(userinfo) {
			const token = userinfo.token || ''
			if (token) {
				setToken(token)
			}
			// 保存用户信息到 globalData 供全局使用
			const app = getApp()
			if (app) {
				app.globalData.userInfo = userinfo
			}
		},

		// 跳转到首页
		goHome() {
			uni.switchTab({
				url: '/pages/index/index'
			})
		},

		// 选择头像回调
		onChooseAvatar(e) {
			const { avatarUrl } = e.detail
			if (avatarUrl) {
				this.avatarUrl = avatarUrl
				this.avatarFilePath = avatarUrl  // 保存临时路径用于上传
			}
		},

		// 拒绝授权
		handleRefuse() {
			uni.navigateBack({
				fail: () => this.goHome()
			})
		},

		// 跳转账密登录
		toAccountLogin() {
			// #ifdef MP-WEIXIN
			uni.navigateTo({
				url: '/pages/user/wxlogin'
			})
			// #endif
			// #ifndef MP-WEIXIN
			uni.navigateTo({
				url: '/pages/user/login'
			})
			// #endif
		}
	}
}
</script>

<style lang="scss" scoped>
.wxlogin-page {
	min-height: 100vh;
	background-color: #fff;
}

.content {
	padding: 120rpx 60rpx 0;
}

// 头像区域
.avatar-section {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding-bottom: 40rpx;

	.avatar-btn {
		width: 180rpx;
		height: 180rpx;
		padding: 0;
		margin: 0;
		background: #f0f0f0;
		border: none;
		border-radius: 50%;
		overflow: hidden;
		box-shadow: 3px 3px 10px rgba(0, 0, 0, 0.15);
		line-height: 1;
		display: flex;
		align-items: center;
		justify-content: center;

		&::after {
			border: none;
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
	}

	.avatar-tip {
		margin-top: 20rpx;
		font-size: 24rpx;
		color: #999;
	}
}

// 昵称输入
.nickname-section {
	display: flex;
	align-items: center;
	height: 100rpx;
	border-bottom: 1rpx solid #f0f0f0;
	padding: 0 20rpx;

	.label {
		width: 45%;
		font-size: 28rpx;
		color: #333;
		margin-right: 20rpx;
		flex-shrink: 0;
		text-align: right;
	}

	.nickname-input {
		width: 50%;
		font-size: 28rpx;
		color: #333;
		text-align: left;
	}
}

// 权限提示
.permission-tip {
	padding: 50rpx 0 40rpx;
	text-align: center;

	.tip-title {
		display: block;
		font-size: 28rpx;
		color: #333;
		margin-bottom: 10rpx;
	}

	.tip-desc {
		display: block;
		font-size: 24rpx;
		color: #999;
	}
}

// 按钮组
.btn-group {
	.btn-allow {
		margin-bottom: 20rpx;
	}

	.btn-refuse {
		background: #f5f5f5;
		color: #666;
		border-color: #f5f5f5;
	}
}

// 其他登录方式
.other-login {
	text-align: center;
	margin-top: 60rpx;

	.link {
		font-size: 26rpx;
		color: #07c160;
	}
}
</style>
