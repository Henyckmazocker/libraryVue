/**
 * Design Tokens — Single source of truth para colores y escalas.
 *
 * Consumido por:
 *  - frontend/src/config/primevue-preset.js → preset de PrimeVue (Lara)
 *  - frontend/src/assets/styles/tokens/*.scss → CSS variables (mantener sincronizado manualmente)
 *
 * IMPORTANTE: Si cambias un valor aquí, actualiza también el archivo SCSS correspondiente.
 * (Una integración build-time se podría hacer en el futuro, ahora se mantiene sincronización manual).
 */

export const palette = {
  // Paleta principal (verde teal)
  primary: {
    50:  '#e8f2f1',
    100: '#c4ddd8',
    200: '#a3cbc1',
    300: '#7ab3a8',
    400: '#5a9e92',
    500: '#1D4E4A', // Principal
    600: '#194541',
    700: '#153a37',
    800: '#0f2a27',
    900: '#0a1d1b',
    950: '#051211',
  },
  // Escalas neutras / semánticas
  // OJO: `secondary` es aquí un color de FONDO — primevue-preset.js lo usa como
  // `colorScheme.light.highlight.background`, con primary[500] encima (5.30). No es el
  // mismo rol que `--color-secondary` de tokens/_colors.scss, que es un color de primer
  // plano (el spinner de App.vue:58) y por eso va más oscuro en claro. Divergen a propósito.
  secondary: '#A3CBC1',
  tertiary:  '#6F5D4F',
  accent:    '#E2CBBF',
  highlight: '#DCA1B0',

  // Estado
  success: '#4CAF50',
  warning: '#FFA726',
  error:   '#EF5350',
  info:    '#42A5F5',
};

export default { palette };
