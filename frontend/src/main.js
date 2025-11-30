import 'primeicons/primeicons.css';
import { createApp } from 'vue';
import App from './App.vue';
import { createPinia } from 'pinia';
import router from './router';
import PrimeVue from 'primevue/config';
import MultiSelect from 'primevue/multiselect';
import { definePreset } from '@primevue/themes';
import Lara from '@primevue/themes/lara';

// Preset personalizado con los colores principales
const CustomPreset = definePreset(Lara, {
  semantic: {
    primary: {
      50: '#e8f2f1',
      100: '#c4ddd8',
      200: '#a3cbc1',
      300: '#7ab3a8',
      400: '#5a9e92',
      500: '#1D4E4A',  // Color principal
      600: '#194541',
      700: '#153a37',
      800: '#0f2a27',
      900: '#0a1d1b',
      950: '#051211'
    },
    colorScheme: {
      light: {
        primary: {
          color: '#1D4E4A',
          inverseColor: '#ffffff',
          hoverColor: '#2a5e5a',
          activeColor: '#0f2a27'
        },
        highlight: {
          background: '#A3CBC1',
          focusBackground: '#7ab3a8',
          color: '#1D4E4A',
          focusColor: '#1D4E4A'
        }
      },
      dark: {
        primary: {
          color: '#A3CBC1',
          inverseColor: '#1D4E4A',
          hoverColor: '#c4ddd8',
          activeColor: '#7ab3a8'
        },
        highlight: {
          background: 'rgba(163, 203, 193, .16)',
          focusBackground: 'rgba(163, 203, 193, .24)',
          color: 'rgba(255,255,255,.87)',
          focusColor: 'rgba(255,255,255,.87)'
        }
      }
    }
  }
});

const app = createApp(App);
app.use(createPinia());
app.use(PrimeVue, {
        ripple: true,
        theme: {
            preset: CustomPreset,
            options: {
                prefix: 'p',
                cssLayer: false,
                darkModeSelector: '.app-dark',
              },
        },
    }
  );
app.component('MultiSelect', MultiSelect);

if (router) {
  app.use(router);
}

// Si necesitas usar Vuex, importa y usa aquí. Actualmente solo Pinia está en uso.

app.mount('#app');