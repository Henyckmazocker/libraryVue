<template>
  <div class="movie-search-container">
    <h1 class="title">Buscador de Películas (OMDb)</h1>
    <div class="input-group">
      <input type="text" class="movie-input" placeholder="Introduce el título o palabra clave" v-model="movieSearch.query.value" @keyup.enter="searchMovies" />
      <button @click="searchMovies" class="search-button">
        <i class="fas fa-search"></i>
      </button>
    </div>
    <div v-if="errorMessage || movieSearch.error.value" class="error-message">{{ errorMessage || movieSearch.error.value }}</div>
    
    <!-- Lista de resultados simplificada sin acordeón -->
    <div v-if="movieSearch.results.value && movieSearch.results.value.length" class="movie-list">
      <MovieListItem
        v-for="result in movieSearch.results.value"
        :key="result.imdbID"
        :movie="transformSearchResultToMovie(result)"
        :allowedStatuses="allowedUserStatusesList"
        @click="goToMovieDetail"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import MovieListItem from '@/components/Movies/MovieListItem.vue';
import { useMovies } from '@/composables/useMovies';
import { useSearch } from '@/composables/useSearch';
import { useLibraryNotifications } from '@/composables/useLibraryNotifications';
import Logger from '@/utils/logger';

// Composables
const router = useRouter();
const moviesComposable = useMovies();
const notifications = useLibraryNotifications();

// Configurar búsqueda con debouncing
const movieSearch = useSearch({
  debounceDelay: 500,
  minQueryLength: 2
});

// Estados locales
const errorMessage = ref("");

// Computed
const allowedUserStatusesList = computed(() => 
  Array.isArray(moviesComposable.allowedStatuses.value) ? moviesComposable.allowedStatuses.value : []
);

// Transformar resultado de búsqueda a formato de película
const transformSearchResultToMovie = (result) => {
  return {
    isbn: result.imdbID,
    imdbID: result.imdbID,
    title: result.Title,
    year: result.Year,
    coverUrl: result.Poster !== 'N/A' ? result.Poster : null,
    user_rating: 0,
    userStatuses: []
  };
};

// Navegación a página de detalle
const goToMovieDetail = (movie) => {
  if (!movie.imdbID) {
    Logger.warn('[MovieSearch] Movie has no IMDb ID, cannot navigate to detail');
    notifications.showError('Esta película no tiene IMDb ID disponible');
    return;
  }
  
  Logger.debug('[MovieSearch] Navigating to movie detail:', movie.imdbID);
  
  // Transformar datos básicos de la película
  const movieData = {
    isbn: movie.imdbID,
    imdbID: movie.imdbID,
    title: movie.Title,
    originalTitle: movie.Title,
    year: movie.Year,
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

const searchMovies = async () => {
  errorMessage.value = "";
  notifications.clearMessage();
  
  if (!movieSearch.query.value.trim()) {
    errorMessage.value = "Introduce un título o palabra clave para buscar.";
    return;
  }
  
  try {
    Logger.debug('[MovieSearch] Searching movies:', movieSearch.query.value);
    const apiKey = 'f03583fd';
    const url = `https://www.omdbapi.com/?apikey=${apiKey}&s=${encodeURIComponent(movieSearch.query.value)}`;
    const response = await axios.get(url);
    
    if (response.data && response.data.Response === 'True') {
      movieSearch.results.value = response.data.Search;
      movieSearch.error.value = '';
      Logger.debug(`[MovieSearch] Found ${response.data.Search.length} movies`);
    } else {
      errorMessage.value = response.data.Error || 'No se encontraron resultados.';
      movieSearch.results.value = [];
    }
  } catch (e) {
    Logger.error('[MovieSearch] Error searching movies:', e);
    errorMessage.value = 'Error al buscar las películas.';
    movieSearch.results.value = [];
  }
};

onMounted(async () => {
  await moviesComposable.fetchAllowedStatuses();
});
</script>

<style>
@import '@/assets/styles/variables.css';

.movie-search-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 30px;
  width: 100%;
  max-width: 600px;
  margin: auto;
}
.title {
  font-size: 2rem;
  color: var(--color-text);
  margin-bottom: 30px;
}
.input-group {
  display: flex;
  width: 100%;
  margin-bottom: 30px;
}
.movie-input {
  flex-grow: 1;
  padding: 12px 18px;
  font-size: 1rem;
  color: var(--color-text);
  background-color: var(--color-background-mute);
  border: 1px solid var(--color-border);
  border-radius: 30px 0 0 30px;
  outline: none;
}
.movie-input::placeholder {
  color: var(--color-text-muted);
}
.search-button {
  padding: 12px 24px;
  font-size: 1rem;
  color: var(--color-text-light);
  background-color: var(--color-primary);
  border: 1px solid var(--color-primary);
  border-radius: 0 30px 30px 0;
  cursor: pointer;
}
.search-button:hover {
  background-color: var(--color-primary-hover);
  border-color: var(--color-primary-hover);
}
.error-message,
.status-message {
  padding: 10px 15px;
  border-radius: 12px;
  margin-bottom: 20px;
  width: 100%;
  text-align: center;
  box-sizing: border-box;
}

.error-message {
  color: var(--color-error);
  background-color: var(--color-error-bg);
}

.status-message.success {
  color: var(--color-success);
  background-color: var(--color-success-bg);
}

.status-message.error {
  color: var(--color-error);
  background-color: var(--color-error-bg);
}
/* Lista de resultados simplificada */
.movie-list {
  width: 100%;
  max-width: 600px;
  margin-top: 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.movie-list-item {
  display: flex;
  align-items: center;
  background: var(--color-background-soft);
  border-radius: 10px;
  padding: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: var(--shadow-light);
  border: 1px solid transparent;
}

.movie-list-item:hover {
  background: var(--color-background-mute);
  border-color: var(--color-primary);
  box-shadow: var(--shadow-medium);
  transform: translateX(4px);
}

.movie-list-poster {
  width: 50px;
  height: 75px;
  object-fit: cover;
  border-radius: 4px;
  margin-right: 16px;
  border: 1px solid var(--color-border);
  flex-shrink: 0;
}

.movie-list-info {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.movie-list-title {
  color: var(--color-text);
  font-size: 1rem;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.movie-list-subtitle {
  color: var(--color-text-muted);
  font-size: 0.85rem;
}

.movie-list-arrow {
  font-size: 1.2rem;
  color: var(--color-primary-light);
  margin-left: 10px;
  flex-shrink: 0;
  transition: transform 0.2s ease;
}

.movie-list-item:hover .movie-list-arrow {
  transform: translateX(4px);
}
</style>
