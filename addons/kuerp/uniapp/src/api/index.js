import { get, post } from './request'

// 用户
export const userApi = {
  login: (code) => post('/addons/kuerp/api.user/login', { code }),
  loginByPwd: (mobile, password) => post('/addons/kuerp/api.user/loginByPassword', { mobile, password }),
  register: (data) => post('/addons/kuerp/api.user/register', data),
  sendSmsCode: (mobile) => post('/addons/kuerp/api.user/sendSmsCode', { mobile }),
  info: () => get('/addons/kuerp/api.user/info'),
  update: (data) => post('/addons/kuerp/api.user/update', data),
}

// 轮播
export const bannerApi = {
  index: () => get('/addons/kuerp/api.banner/index'),
}

// 商品
export const goodsApi = {
  index: (params) => get('/addons/kuerp/api.goods/index', params),
  detail: (id) => get('/addons/kuerp/api.goods/detail', { id }),
  categories: () => get('/addons/kuerp/api.goods/categories'),
}

// 购物车
export const cartApi = {
  index: () => get('/addons/kuerp/api.cart/index'),
  add: (goods_id, quantity, attr_ids, goods_unit) => post('/addons/kuerp/api.cart/add', { goods_id, quantity, attr_ids, goods_unit }),
  edit: (id, quantity) => post('/addons/kuerp/api.cart/edit', { id, quantity }),
  delete: (id) => post('/addons/kuerp/api.cart/delete', { id }),
  clear: () => post('/addons/kuerp/api.cart/clear'),
  count: () => get('/addons/kuerp/api.cart/count'),
}

// 收货地址
export const addressApi = {
  index: () => get('/addons/kuerp/api.address/index'),
  add: (data) => post('/addons/kuerp/api.address/add', data),
  edit: (data) => post('/addons/kuerp/api.address/edit', data),
  delete: (id) => post('/addons/kuerp/api.address/delete', { id }),
  setDefault: (id) => post('/addons/kuerp/api.address/setDefault', { id }),
}

// 订单
export const orderApi = {
  add: (data) => post('/addons/kuerp/api.order/add', data),
  index: (params) => get('/addons/kuerp/api.order/index', params),
  detail: (id) => get('/addons/kuerp/api.order/detail', { id }),
  cancel: (id) => post('/addons/kuerp/api.order/cancel', { id }),
  received: (id) => post('/addons/kuerp/api.order/received', { id }),
  buyNow: (data) => post('/addons/kuerp/api.order/buyNow', data),
}

// 物流
export const deliveryApi = {
  index: (order_id) => get('/addons/kuerp/api.delivery/index', { order_id }),
}
