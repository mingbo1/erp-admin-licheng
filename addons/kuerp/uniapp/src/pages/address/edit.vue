<template>
  <view class="page">
    <view class="form-card">
      <u--form>
        <u-form-item label="联系人" borderBottom :labelWidth="80" required>
          <u--input v-model="form.name" placeholder="请输入收货人姓名" border="none" maxlength="30" />
        </u-form-item>
        <u-form-item label="手机号" borderBottom :labelWidth="80" required>
          <u--input v-model="form.mobile" placeholder="请输入收货人手机号" type="number" maxlength="11" border="none" />
        </u-form-item>
        <u-form-item label="所在地区" borderBottom :labelWidth="80" @click="openRegionPicker">
          <view class="region-value" v-if="form.regionText">{{ form.regionText }}</view>
          <text v-else class="region-placeholder">请选择省市区</text>
          <u-icon name="arrow-right" size="24" color="#c0c4cc" />
        </u-form-item>
        <u-form-item label="详细地址" borderBottom :labelWidth="80" required>
          <u--input v-model="form.address" placeholder="请输入街道、门牌号等" border="none" maxlength="100" />
        </u-form-item>
        <u-form-item label="设为默认" borderBottom :labelWidth="80">
          <u-switch v-model="form.isDefault" slot="right" />
        </u-form-item>
      </u--form>
    </view>

    <view class="save-btn-wrap">
      <u-button type="primary" shape="circle" :loading="submitting" :disabled="submitting" @click="save">
        {{ submitting ? '保存中...' : '保存' }}
      </u-button>
    </view>

    <!-- 省市区选择器 -->
    <u-picker
      ref="picker"
      :show="showRegion"
      :columns="regionColumns"
      keyName="label"
      @change="onRegionChange"
      @confirm="regionConfirm"
      @cancel="showRegion = false"
      @close="showRegion = false"
    />
  </view>
</template>

<script>
import { addressApi } from '../../api'
import { PROVINCES, CITIES, DISTRICTS } from '../../lib/area'

export default {
  data() {
    return {
      addressId: 0,
      form: { name: '', mobile: '', regionText: '', province: '', city: '', district: '', address: '', isDefault: false },
      showRegion: false,
      submitting: false,
      regionColumns: [],
    }
  },
  onLoad(options) {
    this.addressId = Number(options.id) || 0
    this.buildRegionColumns()
    if (this.addressId) this.loadAddress()
  },
  methods: {
    buildRegionColumns(selectedProvince, selectedCity) {
      const pList = PROVINCES.map(p => ({ label: p }))

      let province = selectedProvince || PROVINCES[0]
      if (!PROVINCES.includes(province)) province = PROVINCES[0]
      const cityList = (CITIES[province] || []).map(c => ({ label: c }))

      // 区县列：根据选中的城市获取
      let districtList = []
      let firstCity = selectedCity || (cityList[0] ? cityList[0].label : '')
      if (firstCity && DISTRICTS[firstCity]) {
        districtList = DISTRICTS[firstCity].map(d => ({ label: d }))
      }

      this.regionColumns = [pList, cityList, districtList]
    },
    openRegionPicker() {
      const province = this.form.province || PROVINCES[0]
      const city = this.form.city || ''
      this.buildRegionColumns(province, city)
      this.showRegion = true
    },
    onRegionChange(e) {
      const getLabel = (v) => (v && typeof v === 'object') ? v.label : v
      const items = e.value || []
      if (!items.length) return

      if (e.columnIndex === 0) {
        // 省份改变 → 更新城市列和区县列
        const provinceLabel = getLabel(items[0]) || PROVINCES[0]
        const cityList = (CITIES[provinceLabel] || []).map(c => ({ label: c }))
        let districtList = []
        if (cityList.length) {
          const firstCity = cityList[0].label
          districtList = (DISTRICTS[firstCity] || []).map(d => ({ label: d }))
        }

        this.$set(this.regionColumns, 1, cityList)
        this.$set(this.regionColumns, 2, districtList)
        this.$nextTick(() => {
          if (this.$refs.picker) {
            this.$refs.picker.setColumnValues(1, cityList)
            this.$refs.picker.setColumnValues(2, districtList)
          }
        })
      } else if (e.columnIndex === 1) {
        // 城市改变 → 更新区县列
        const cityLabel = getLabel(items[1]) || ''
        const districtList = (DISTRICTS[cityLabel] || []).map(d => ({ label: d }))
        this.$set(this.regionColumns, 2, districtList)
        this.$nextTick(() => {
          if (this.$refs.picker) {
            this.$refs.picker.setColumnValues(2, districtList)
          }
        })
      }
    },
    async loadAddress() {
      try {
        const res = await addressApi.index()
        const addr = (res.list || []).find(a => a.id == this.addressId)
        if (addr) {
          this.form.name = addr.first_connect_name || ''
          this.form.mobile = addr.first_connect_mobile || ''
          this.form.address = addr.connect_address || ''
          this.form.isDefault = addr.is_first == 1

          // 从 connect_address 中解析省市区
          const parts = (addr.connect_address || '').split(/\s+/).filter(Boolean)
          if (parts.length >= 1) this.form.province = parts[0]
          if (parts.length >= 2) this.form.city = parts[1]
          if (parts.length >= 3) this.form.district = parts[2]
          this.form.regionText = parts.slice(0, 3).join(' ')
        }
      } catch (e) {
        uni.showToast({ title: e, icon: 'none' })
      }
    },
    regionConfirm(e) {
      // e.value = 选中项对象数组, e.values = 全部列数据(2D), 优先用 e.value
      const getLabel = (v) => (v && typeof v === 'object') ? v.label : v
      const items = e.value || e.values || []
      const labels = items.map(v => getLabel(v)).filter(Boolean)

      this.form.province = labels[0] || ''
      this.form.city = labels[1] || ''
      this.form.district = labels[2] || ''
      this.form.regionText = [this.form.province, this.form.city, this.form.district].filter(Boolean).join(' ')
      this.showRegion = false
    },
    async save() {
      if (!this.form.name) {
        uni.showToast({ title: '请输入联系人', icon: 'none' })
        return
      }
      if (!this.form.mobile || !/^1\d{10}$/.test(this.form.mobile)) {
        uni.showToast({ title: '请输入正确手机号', icon: 'none' })
        return
      }
      if (!this.form.address) {
        uni.showToast({ title: '请输入详细地址', icon: 'none' })
        return
      }
      this.submitting = true
      try {
        const data = {
          name: this.form.name,
          mobile: this.form.mobile,
          address: this.form.address,
          is_default: this.form.isDefault ? 1 : 0,
        }
        if (this.form.province) data.province = this.form.province
        if (this.form.city) data.city = this.form.city
        if (this.form.district) data.district = this.form.district

        if (this.addressId) {
          data.id = this.addressId
          await addressApi.edit(data)
          uni.showToast({ title: '编辑成功' })
        } else {
          await addressApi.add(data)
          uni.showToast({ title: '新增成功' })
        }
        setTimeout(() => uni.navigateBack(), 500)
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
.page { min-height: 100vh; padding-top: 20rpx; background: #f5f7fa; }

.form-card {
  background: #fff;
  margin: 0 20rpx;
  border-radius: 16rpx;
  padding: 12rpx 32rpx;
}

.region-value { font-size: 28rpx; color: #303133; flex: 1; }
.region-placeholder { font-size: 28rpx; color: #c0c4cc; flex: 1; }

.save-btn-wrap { margin: 60rpx 20rpx 0; }
</style>
