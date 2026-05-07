const { defineConfig } = require('@vue/cli-service')

// En build móvil (VUE_APP_MODE=mobile) los assets se cargan desde el protocolo
// capacitor:// — se necesita ruta relativa. En web se mantiene '/'.
const isMobile = process.env.VUE_APP_MODE === 'mobile'

module.exports = defineConfig({
  outputDir: 'dist',
  publicPath: isMobile ? './' : '/',
  devServer: {
    host: '0.0.0.0',
    port: 8080,
    client: {
      webSocketURL: 'auto://0.0.0.0:0/ws',
    }
    // Removed proxy configuration to avoid CORS header duplication
  }
})
