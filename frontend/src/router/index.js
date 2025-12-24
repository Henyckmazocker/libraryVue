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
  },
  {
    path: '/libraryx',
    name: 'LibraryX',
    component: () => import('../components/LibraryX.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/dashboard/books',
    name: 'BooksDashboard',
    component: () => import('../components/Dashboard/BooksDashboard.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/dashboard/movies',
    name: 'MoviesDashboard',
    component: () => import('../components/Dashboard/MoviesDashboard.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/books/:isbn',
    name: 'BookDetail',
    component: () => import('../views/BookDetailView.vue'),
    props: true
  },
  {
    path: '/movies/:imdbId',
    name: 'MovieDetail',
    component: () => import('../views/MovieDetailView.vue'),
    props: true
  }
];

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes
});

export default router;