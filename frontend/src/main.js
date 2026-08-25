import '@/assets/styles/index.scss';
import 'primeicons/primeicons.css';
// FontAwesome local (antes venía de cdnjs): sin red, en la app Capacitor, el CDN dejaba la
// interfaz sin iconos. Solo se importan las familias en uso — no hay ni un `far` en el proyecto.
import '@fortawesome/fontawesome-free/css/fontawesome.min.css';
import '@fortawesome/fontawesome-free/css/solid.min.css';
import '@fortawesome/fontawesome-free/css/brands.min.css';
import { createApp } from 'vue';
import App from './App.vue';
import { createPinia } from 'pinia';
import router from './router';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import MultiSelect from 'primevue/multiselect';
import Tooltip from 'primevue/tooltip';
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
app.use(ToastService);
app.component('MultiSelect', MultiSelect);
// `v-tooltip` es una DIRECTIVA, no un componente, así que no basta con importar
// PrimeVue: hay que registrarla. Sin esto, los tres componentes de Social que la
// usan (`FriendsList:46`, `FriendRequests`, `UserSearchResult:30`) sueltan
// «Failed to resolve directive: tooltip» y se quedan sin tooltip. Llevaba así
// desde el 2026-05-13, y no se notó porque un warning de consola no rompe nada:
// los botones funcionan, solo falta el texto al pasar por encima.
app.directive('tooltip', Tooltip);

if (router) {
  app.use(router);
}

app.mount('#app');
