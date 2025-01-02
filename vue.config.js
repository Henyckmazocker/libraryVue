const { defineConfig } = require('@vue/cli-service')
module.exports = defineConfig({
  outputDir: '../build',
  publicPath: '/',
  devServer: {
    headers: { "Access-Control-Allow-Origin": "*" },
    https: false,
    port: 8080,
    host: 'localhost', /* its your Vue.app url */
    proxy: {
      '/admin/*': {
        target: 'http://localhost:8888', /* its your API-server url */
        ws: true,
        changeOrigin: true
      }
    }
  }
})
