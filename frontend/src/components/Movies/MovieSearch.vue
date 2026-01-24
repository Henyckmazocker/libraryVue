<template>
  <div class="movie-search-container">
    <!-- Search Section -->
    <GenericSearch :config="searchConfig" />
    <!-- Trending Movies Section -->
    <TrendingCarousel
      :items="trendingMovies"
      :is-loading="isLoadingTrending"
      :error="errorTrending"
      type="movies"
      :item-component="MovieCarouselItem"
      title="Películas Populares"
      subtitle="Las películas más populares en nuestra comunidad"
      @item-click="handleTrendingClick"
    />

  </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import GenericSearch from '@/components/shared/GenericSearch.vue';
import TrendingCarousel from '@/components/TrendingCarousel.vue';
import MovieCarouselItem from '@/components/Movies/MovieCarouselItem.vue';
import { useMovies } from '@/composables/useMovies';
import { useTrending } from '@/composables/useTrending';
import { useAuthStore } from '@/store/auth';
import { useMoviesStore } from '@/store/movies';
import { useUIStore } from '@/store/ui';
import { storeToRefs } from 'pinia';
import Logger from '@/utils/logger';

// Router
const router = useRouter();

// Composables & Stores
const moviesComposable = useMovies();
const authStore = useAuthStore();
const moviesStore = useMoviesStore();
const uiStore = useUIStore();
const { isAuthenticated } = storeToRefs(authStore);
const { 
  trendingMovies, 
  isLoadingMovies: isLoadingTrending, 
  errorMovies: errorTrending,
  fetchTrendingMovies 
} = useTrending();

// Cargar películas del usuario y trending al montar (solo si está autenticado)
onMounted(async () => {
  if (isAuthenticated.value) {
    // Cargar películas de la biblioteca para poder verificar qué items tiene el usuario
    if (moviesStore.movies.length === 0) {
      await moviesStore.fetchMovies();
    }
    // Cargar trending movies
    fetchTrendingMovies(10, 90); // 10 películas, últimos 90 días
  }
});

// Cargar películas cuando se autentique
watch(isAuthenticated, async (newValue) => {
  if (newValue) {
    // Cargar biblioteca del usuario
    if (moviesStore.movies.length === 0) {
      Logger.debug('[MovieSearch] User authenticated, fetching user movies...');
      await moviesStore.fetchMovies();
    }
    // Cargar trending movies
    if (trendingMovies.value.length === 0) {
      Logger.debug('[MovieSearch] Fetching trending movies...');
      fetchTrendingMovies(10, 90);
    }
  }
});

// Función para detectar si es IMDb ID o búsqueda por título
const detectSearchType = (query) => {
  const cleanQuery = query.trim();
  // IMDb ID tiene formato: tt1234567 (tt seguido de al menos 7 dígitos)
  const isIMDbID = /^tt\d{7,}$/i.test(cleanQuery);
  
  return {
    type: isIMDbID ? 'direct' : 'title',
    isDirect: isIMDbID
  };
};

// Handler de búsqueda para películas
const searchMovies = async (query, searchType) => {
  if (searchType === 'title') {
    try {
      Logger.debug('[MovieSearch] Searching movies:', query);
      const apiKey = 'f03583fd';
      const url = `https://www.omdbapi.com/?apikey=${apiKey}&s=${encodeURIComponent(query)}`;
      const response = await axios.get(url);
      
      if (response.data && response.data.Response === 'True') {
        Logger.debug(`[MovieSearch] Found ${response.data.Search.length} movies`);
        return response.data.Search;
      } else {
        throw new Error(response.data.Error || 'No se encontraron resultados.');
      }
    } catch (error) {
      Logger.error('[MovieSearch] Error searching movies:', error);
      throw error;
    }
  }
  
  return [];
};

// Transformar resultado de búsqueda
const transformResult = (result) => {
  return {
    isbn: result.imdbID,
    imdbID: result.imdbID,
    title: result.Title,
    Title: result.Title,
    year: result.Year,
    Year: result.Year,
    coverUrl: result.Poster !== 'N/A' ? result.Poster : null,
    Poster: result.Poster,
    user_rating: 0,
    userStatuses: []
  };
};

// Navegación a detalle
const navigateToDetail = (router, movie) => {
  if (!movie.imdbID) {
    Logger.warn('[MovieSearch] Movie has no IMDb ID, cannot navigate to detail');
    uiStore.showError('Esta película no tiene IMDb ID disponible');
    return;
  }
  
  Logger.debug('[MovieSearch] Navigating to movie detail:', movie.imdbID);
  
  const movieData = {
    isbn: movie.imdbID,
    imdbID: movie.imdbID,
    title: movie.Title || movie.title,
    originalTitle: movie.Title || movie.title,
    year: movie.Year || movie.year,
    coverUrl: movie.Poster !== 'N/A' ? movie.Poster : null,
    user_rating: 0,
    userStatuses: [],
    itemType: 'movie'
  };
  
  router.push({
    name: 'MovieDetail',
    params: { imdbId: movie.imdbID },
    state: { movie: movieData }
  });
};

// Handler para clicks en trending
const handleTrendingClick = (movie) => {
  Logger.debug('Trending movie clicked:', movie);
  
  // Navegar a detalle de la película trending
  // El backend devuelve 'isbn' (que es el IMDb ID)
  const movieData = {
    isbn: movie.isbn,
    imdbID: movie.isbn,
    title: movie.title,
    originalTitle: movie.original_title || movie.title,
    year: movie.year || '',
    coverUrl: movie.coverUrl,
    user_rating: movie.avg_rating || 0,
    userStatuses: [],
    itemType: 'movie'
  };
  
  router.push({
    name: 'MovieDetail',
    params: { imdbId: movie.isbn },
    state: { movie: movieData }
  });
};

// Obtener clave única del resultado
const getResultKey = (result) => {
  return result.imdbID || `movie-${Date.now()}-${Math.random()}`;
};

// Cargar estados permitidos
const fetchAllowedStatuses = async () => {
  await moviesComposable.fetchAllowedStatuses();
  return Array.isArray(moviesComposable.allowedStatuses.value) 
    ? moviesComposable.allowedStatuses.value 
    : [];
};

// Configuración del componente genérico
const searchConfig = computed(() => ({
  title: 'Buscador de Películas (OMDb)',
  inputs: [
    {
      type: 'auto',
      placeholder: 'Buscar por título o IMDb ID (ej: tt1234567)...',
      buttonText: '',
      idField: 'imdbID',
      emptyMessage: 'Introduce un título o IMDb ID para buscar.',
      errorMessage: 'Error al buscar la película.'
    }
  ],
  carouselItemComponent: MovieCarouselItem,
  itemProp: 'movie',
  searchHandler: searchMovies,
  transformResult: transformResult,
  navigateToDetail: navigateToDetail,
  getResultKey: getResultKey,
  fetchAllowedStatuses: fetchAllowedStatuses,
  detectSearchType: detectSearchType
}));
</script>

<style scoped>
.movie-search-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 40px;
}
</style>
