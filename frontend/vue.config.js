const { defineConfig } = require('@vue/cli-service')

module.exports = defineConfig({
  outputDir: 'dist',
  publicPath: '/',
  devServer: {
    host: '0.0.0.0',
    port: 8080,
    client: {
      webSocketURL: 'auto://0.0.0.0:0/ws',
    }
    // Removed proxy configuration to avoid CORS header duplication
  }
})
