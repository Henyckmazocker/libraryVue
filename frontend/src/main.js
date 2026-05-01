import '@/assets/styles/index.scss';
import 'primeicons/primeicons.css';
import { createApp } from 'vue';
import App from './App.vue';
import { createPinia } from 'pinia';
import router from './router';
import PrimeVue from 'primevue/config';
import MultiSelect from 'primevue/multiselect';
import { CustomPreset } from '@/config/primevue-preset';

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

app.mount('#app');
