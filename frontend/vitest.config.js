import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

// El proyecto se construye con Vue CLI (webpack); Vitest solo se usa para los
// tests, así que el alias '@' se replica aquí a mano en vez de leerlo de
// vue.config.js. Debe coincidir con el de jsconfig.json.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  test: {
    environment: 'jsdom',
    include: ['tests/unit/**/*.spec.js'],
    setupFiles: ['tests/unit/setup.js'],
    // Los bloques <style> de los SFC no se compilan: ningún test asserta sobre
    // estilos y así no hace falta resolver los @use de src/styles.
    css: false,
  },
})
