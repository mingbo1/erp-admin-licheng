const path = require('path')

module.exports = {
  transpileDependencies: ['uview-ui'],
  devServer: {
    proxy: {
      '/addons': {
        target: 'http://erp.licheng.test',
        changeOrigin: true,
      },
    },
  },
}
