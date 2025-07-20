import { createRouter, createWebHistory } from 'vue-router';
import HomePage from '../components/HomePage.vue';
import BookSearch from '../components/Books/BookSearch.vue';
import MovieSearch from '../components/Movies/MovieSearch.vue';
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
    component: BookSearch
  },
  {
    path: '/movies',
    name: 'Movies',
    component: MovieSearch
  },
  {
    path: '/library',
    name: 'MyLibrary',
    component: () => import('../components/MyLibrary.vue')
  }
];

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes
});

export default router;