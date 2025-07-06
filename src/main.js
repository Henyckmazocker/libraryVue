import { createApp } from 'vue'
import App from './App.vue'
import { createPinia } from 'pinia'
import router from './router'
import store from './store'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const app = createApp(App);
app.use(createPinia());

if (router) {
  app.use(router);
}

if (store) {
  app.use(store);
}

app.mount('#app');
