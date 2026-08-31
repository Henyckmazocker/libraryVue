const { defineConfig } = require('@vue/cli-service')

// En build móvil (VUE_APP_MODE=mobile) los assets se cargan desde el protocolo
// capacitor:// — se necesita ruta relativa. En web se mantiene '/'.
const isMobile = process.env.VUE_APP_MODE === 'mobile'

module.exports = defineConfig({
  // Los catálogos de idioma son YAML y se compilan a un objeto EN BUILD: al
  // navegador no viaja ningún parser. Va por `chainWebpack` y no por una lista de
  // plugins porque este repo es Vue CLI 5 sobre webpack, no Vite; Vitest resuelve
  // el mismo fichero por su propia cadena, en `vitest.config.js`.
  chainWebpack: (config) => {
    config.module
      .rule('yaml')
      .test(/\.ya?ml$/)
      .type('json')
      .use('yaml-loader')
      .loader('yaml-loader')
      .options({ asJSON: true });
  },
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
