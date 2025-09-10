import 'primeicons/primeicons.css';
import './assets/embed-variables.css';
import { createApp } from 'vue';
import App from './App.vue';
import { createPinia } from 'pinia';
import router from './router';
import PrimeVue from 'primevue/config';
import MultiSelect from 'primevue/multiselect';
import { definePreset } from '@primevue/themes';
import Lara from '@primevue/themes/lara';

const app = createApp(App);
app.use(createPinia());
app.use(PrimeVue, {
        ripple: true,
        theme: {
            preset: definePreset(Lara, {}),
            options: {
                prefix: 'p',
                cssLayer: false,
                darkMode: true,
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