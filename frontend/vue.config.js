// Sello de Augur (SDK web): versión y build_id, calculados por Node al arrancar serve/build y
// metidos en el bundle por el DefinePlugin de Vue CLI (lo recoge todo VUE_APP_*). El build_id
// cambia si cambia un fichero de src/ o public/ (sin src/augur/) o el package-lock.json.
const augurStamp = require('./src/augur/stamp.cjs')
process.env.VUE_APP_AUGUR_VERSION = augurStamp.clientVersion(__dirname)
process.env.VUE_APP_AUGUR_BUILD = augurStamp.buildId(__dirname)

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
