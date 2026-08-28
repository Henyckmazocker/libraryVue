import { createRouter, createWebHistory, createWebHashHistory } from 'vue-router';

// Las vistas de detalle van en diferido con `webpackPrefetch`: el navegador se baja su chunk en
// tiempo ocioso, después del arranque, así que la transición sigue siendo instantánea sin cobrar
// ~1,6 MB en el bundle inicial de la home. Ver `VideoDetailView` más abajo, que ya iba así.

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
    path: '/albums',
    name: 'Albums',
    component: () => import('../components/Albums/AlbumSearch.vue')
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
    path: '/dashboard/albums',
    redirect: '/dashboard?tab=albums'
  },
  {
    path: '/dashboard/videos',
    redirect: '/dashboard?tab=videos'
  },
  {
    path: '/profile',
    name: 'UserProfile',
    component: () => import(/* webpackPrefetch: true, webpackChunkName: "detail-profile" */
      '../views/UserProfileView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/books/:isbn',
    name: 'BookDetail',
    component: () => import(/* webpackPrefetch: true, webpackChunkName: "detail-book" */
      '../views/BookDetailView.vue'),
    props: true,
    meta: { requiresAuth: true }
  },
  {
    path: '/movies/:imdbId',
    name: 'MovieDetail',
    component: () => import(/* webpackPrefetch: true, webpackChunkName: "detail-movie" */
      '../views/MovieDetailView.vue'),
    props: true,
    meta: { requiresAuth: true }
  },
  {
    path: '/series/:imdbId',
    name: 'SeriesDetail',
    component: () => import(/* webpackPrefetch: true, webpackChunkName: "detail-series" */
      '../views/SeriesDetailView.vue'),
    props: true,
    meta: { requiresAuth: true }
  },
  {
    path: '/games/:gameId',
    name: 'GameDetail',
    component: () => import(/* webpackPrefetch: true, webpackChunkName: "detail-game" */
      '../views/GameDetailView.vue'),
    props: true,
    meta: { requiresAuth: true }
  },
  {
    path: '/albums/:albumId',
    name: 'AlbumDetail',
    component: () => import(/* webpackPrefetch: true, webpackChunkName: "detail-album" */
      '../views/AlbumDetailView.vue'),
    props: true,
    meta: { requiresAuth: true }
  },
  {
    path: '/videos',
    name: 'Videos',
    component: () => import('../components/Videos/VideoSearch.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/videos/:youtubeId',
    name: 'VideoDetail',
    component: () => import('../views/VideoDetailView.vue'),
    props: true,
    meta: { requiresAuth: true }
  },
  {
    path: '/friends',
    name: 'Friends',
    component: () => import('../views/FriendsView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/lists',
    name: 'Lists',
    component: () => import('../views/ListsView.vue'),
    // Sin `requiresAuth` la vista es accesible sin sesión y `get_my_lists`
    // devuelve un 401 que no espera: las once acciones de listas llevan `Auth`,
    // incluidas las de lectura.
    meta: { requiresAuth: true }
  },
  {
    path: '/lists/:listId',
    name: 'ListDetail',
    component: () => import('../views/ListDetailView.vue'),
    props: true,
    meta: { requiresAuth: true }
  },
  {
    path: '/inbox',
    name: 'Inbox',
    component: () => import('../views/InboxView.vue'),
    // Sin `requiresAuth` la vista es accesible sin sesión y `get_inbox`
    // devuelve un 401 que no espera.
    meta: { requiresAuth: true }
  },
  {
    path: '/user/:username',
    name: 'PublicProfile',
    component: () => import('../views/PublicProfileView.vue'),
    props: true,
    meta: { requiresAuth: true }
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('../views/NotFoundView.vue')
  }
];

// En build móvil (Capacitor) se usa hash history — no hay servidor que maneje rutas SPA.
const isMobile = process.env.VUE_APP_MODE === 'mobile'

const router = createRouter({
  history: isMobile ? createWebHashHistory() : createWebHistory(process.env.BASE_URL),
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