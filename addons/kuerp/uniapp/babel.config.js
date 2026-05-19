const webpack = require('webpack')

const plugins = []

if (process.env.UNI_OPT_TREESHAKINGNG) {
  plugins.push(require('@dcloudio/vue-cli-plugin-uni-optimize/packages/babel-plugin-uni-api/index.js'))
}

process.UNI_LIBRARIES = process.UNI_LIBRARIES || ['@dcloudio/uni-ui']
process.UNI_LIBRARIES.forEach(libraryName => {
  plugins.push([
    'import',
    {
      'libraryName': libraryName,
      'customName': (name) => {
        return `${libraryName}/lib/${name}/${name}`
      }
    }
  ])
})

module.exports = {
  presets: [
    [
      '@vue/app',
      {
        modules: webpack.version[0] > 4 ? 'auto' : 'commonjs',
        useBuiltIns: process.env.UNI_PLATFORM === 'h5' ? 'usage' : 'entry'
      }
    ]
  ],
  plugins
}
