import config from '../config'

// token 引用，login 后设置
let token = uni.getStorageSync('token') || ''

export function setToken(val) {
  token = val
  if (val) {
    uni.setStorageSync('token', val)
  } else {
    uni.removeStorageSync('token')
  }
}

export function getToken() {
  if (!token) {
    token = uni.getStorageSync('token') || ''
  }
  return token
}

async function request(url, options = {}) {
  const method = options.method || 'GET'
  let data = options.data || {}

  // 自动附加 token
  const t = getToken()
  if (t) {
    data.token = t
  }

  return new Promise((resolve, reject) => {
    uni.request({
      url: config.baseUrl + url,
      method,
      data,
      header: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Token': t,
      },
      success: (res) => {
        if (res.data.code === 0) {
          resolve(res.data.data)
        } else {
          const msg = res.data.msg || '请求失败'
          // 未登录 → 触发 App 层重新登录
          if (msg === '请先登录') {
            setToken('')
            uni.showToast({ title: '登录已过期', icon: 'none' })
            // #ifdef MP-WEIXIN
            setTimeout(() => uni.reLaunch({ url: '/pages/user/wxlogin' }), 1000)
            // #endif
            // #ifndef MP-WEIXIN
            setTimeout(() => uni.reLaunch({ url: '/pages/user/login' }), 1000)
            // #endif
          }
          reject(msg)
        }
      },
      fail: () => {
        reject('网络异常，请稍后重试')
      },
    })
  })
}

export function get(url, data) {
  return request(url, { method: 'GET', data })
}

export function post(url, data) {
  return request(url, { method: 'POST', data })
}

export default request
