import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import yaml from '@rollup/plugin-yaml'

// El proyecto se construye con Vue CLI (webpack); Vitest solo se usa para los
// tests, así que el alias '@' se replica aquí a mano en vez de leerlo de
// vue.config.js. Debe coincidir con el de jsconfig.json.
export default defineConfig({
  // El YAML de `src/locales` lo resuelve aquí su propio plugin: Vitest no pasa por
  // la cadena de loaders de webpack, y es lo que más fácil se pasa por alto.
  plugins: [vue(), yaml()],
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
