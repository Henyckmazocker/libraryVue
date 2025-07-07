import { createRouter, createWebHistory } from 'vue-router';
import HomePage from '../components/HomePage.vue';
import HelloWorld from '../components/HelloWorld.vue';
import MovieSearch from '../components/MovieSearch.vue';
// We will create MyLibrary.vue in the next step
// import MyLibrary from '../components/MyLibrary.vue'; 

const routes = [
  {
    path: '/',
    name: 'Home',
    component: HomePage
  },
  {
    path: '/books',
    name: 'Books',
    component: HelloWorld
  },
  {
    path: '/movies',
    name: 'Movies',
    component: MovieSearch
  },
  {
    path: '/library',
    name: 'MyLibrary',
    // component: MyLibrary // Will uncomment when MyLibrary.vue is created
    // For now, let's use a placeholder or even HelloWorld to test routing
    component: () => import('../components/MyLibrary.vue') // Lazy load MyLibrary
  }
];

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes
});

export default router;