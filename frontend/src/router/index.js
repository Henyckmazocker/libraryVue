import { createRouter, createWebHistory } from 'vue-router';

// Importar eagerly las vistas de detalle para transiciones instantáneas
import BookDetailView from '../views/BookDetailView.vue';
import MovieDetailView from '../views/MovieDetailView.vue';
import GameDetailView from '../views/GameDetailView.vue';

const routes = [
  {
    path: '/',
    name: 'Home',
    component: () => import('../components/HomePage.vue')
  },
  {
    path: '/books',
    name: 'Books',
    component: () => import('../components/Books/BookSearch.vue')
  },
  {
    path: '/movies',
    name: 'Movies',
    component: () => import('../components/Movies/MovieSearch.vue')
  },
  {
    path: '/games',
    name: 'Games',
    component: () => import('../components/Games/GameSearch.vue')
  },
  {
    path: '/library',
    name: 'MyLibrary',
    component: () => import('../components/MyLibrary.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/dashboard',
    name: 'UnifiedDashboard',
    component: () => import('../components/Dashboard/UnifiedDashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/dashboard/books',
    redirect: '/dashboard?tab=books'
  },
  {
    path: '/dashboard/movies',
    redirect: '/dashboard?tab=movies'
  },
  {
    path: '/dashboard/games',
    redirect: '/dashboard?tab=games'
  },
  {
    path: '/books/:isbn',
    name: 'BookDetail',
    component: BookDetailView,
    props: true,
    meta: { requiresAuth: true }
  },
  {
    path: '/movies/:imdbId',
    name: 'MovieDetail',
    component: MovieDetailView,
    props: true,
    meta: { requiresAuth: true }
  },
  {
    path: '/games/:gameId',
    name: 'GameDetail',
    component: GameDetailView,
    props: true,
    meta: { requiresAuth: true }
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('../views/NotFoundView.vue')
  }
];

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes
});

// Guard de autenticación — redirige a Home si la ruta requiere auth y no hay sesión
router.beforeEach(async (to, from, next) => {
  if (to.meta.requiresAuth) {
    // Import dinámico para evitar dependencia circular
    const { useAuthStore } = require('../store/auth.js');
    const authStore = useAuthStore();

    // Si aún no se ha verificado la sesión, esperar a que se complete
    if (!authStore.isAuthenticated && !authStore._authChecked) {
      await authStore.initializeAuth();
    }

    if (!authStore.isLoggedIn) {
      return next({ name: 'Home' });
    }
  }
  next();
});

// Asegurar que el tema se aplique después de cada navegación
router.afterEach(() => {
  // Reaplica el tema al DOM después de la navegación
  const theme = localStorage.getItem('theme') || 'light';
  if (theme === 'dark') {
    document.documentElement.classList.add('app-dark');
  } else {
    document.documentElement.classList.remove('app-dark');
  }
});

export default router;