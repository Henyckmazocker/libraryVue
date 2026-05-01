/**
 * PrimeVue Preset — Construido a partir de los design tokens.
 *
 * Single source of truth: frontend/src/config/design-tokens.js
 * Las CSS variables equivalentes viven en frontend/src/assets/styles/tokens/.
 */

import { definePreset } from '@primevue/themes';
import Lara from '@primevue/themes/lara';
import { palette } from './design-tokens';

export const CustomPreset = definePreset(Lara, {
  semantic: {
    primary: {
      50:  palette.primary[50],
      100: palette.primary[100],
      200: palette.primary[200],
      300: palette.primary[300],
      400: palette.primary[400],
      500: palette.primary[500],
      600: palette.primary[600],
      700: palette.primary[700],
      800: palette.primary[800],
      900: palette.primary[900],
      950: palette.primary[950],
    },
    colorScheme: {
      light: {
        primary: {
          color: palette.primary[500],
          inverseColor: '#ffffff',
          hoverColor: '#2a5e5a',
          activeColor: palette.primary[800],
        },
        highlight: {
          background: palette.secondary,
          focusBackground: palette.primary[300],
          color: palette.primary[500],
          focusColor: palette.primary[500],
        },
      },
      dark: {
        primary: {
          color: palette.secondary,
          inverseColor: palette.primary[500],
          hoverColor: palette.primary[100],
          activeColor: palette.primary[300],
        },
        highlight: {
          background: 'rgba(163, 203, 193, .16)',
          focusBackground: 'rgba(163, 203, 193, .24)',
          color: 'rgba(255, 255, 255, .87)',
          focusColor: 'rgba(255, 255, 255, .87)',
        },
      },
    },
  },
});

export default CustomPreset;
