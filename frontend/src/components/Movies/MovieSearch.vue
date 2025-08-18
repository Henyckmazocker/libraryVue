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
    <div v-if="movieSearch.results.value && movieSearch.results.value.length" class="movie-list">
      <div v-for="result in movieSearch.results.value" :key="result.imdbID" class="movie-list-item-wrapper">
        <div class="movie-list-item" :class="{ expanded: selectedMovie && selectedMovie.imdbID === result.imdbID }" @click="toggleMovie(result.imdbID)">
          <img v-if="result.Poster && result.Poster !== 'N/A'" :src="result.Poster" alt="Poster" class="movie-list-poster" />
          <div class="movie-list-info">
            <span class="movie-list-title">{{ result.Title }} ({{ result.Year }})</span>
            <span v-if="selectedMovie && selectedMovie.imdbID === result.imdbID" class="accordion-arrow">
              <i class="fas fa-chevron-up"></i>
            </span>
            <span v-else class="accordion-arrow">
              <i class="fas fa-chevron-down"></i>
            </span>
          </div>
        </div>
        <transition name="accordion">
          <div v-if="selectedMovie && selectedMovie.imdbID === result.imdbID" class="movie-detail-below">
            <MovieDisplay :movie="selectedMovie" :allowedUserStatuses="allowedMovieStatusesList" />
          </div>
        </transition>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { useMovies } from '@/composables/useMovies';
import { useSearch } from '@/composables/useSearch';
import MovieDisplay from './MovieDisplay.vue';
import Logger from '@/utils/logger';

// Composables
const moviesComposable = useMovies();

// Configurar búsqueda con debouncing
const movieSearch = useSearch({
  debounceDelay: 500,
  minQueryLength: 2
});

// Estados locales
const selectedMovie = ref(null);
const errorMessage = ref("");

// Estados computados
const allowedMovieStatusesList = computed(() => {
  return Array.isArray(moviesComposable.allowedStatuses.value) ? moviesComposable.allowedStatuses.value : [];
});

const searchMovies = async () => {
  errorMessage.value = "";
  selectedMovie.value = null;
  
  if (!movieSearch.query.value.trim()) {
    errorMessage.value = "Introduce un título o palabra clave para buscar.";
    return;
  }
  
  try {
    const apiKey = 'f03583fd';
    const url = `https://www.omdbapi.com/?apikey=${apiKey}&s=${encodeURIComponent(movieSearch.query.value)}`;
    const response = await axios.get(url);
    
    if (response.data && response.data.Response === 'True') {
      movieSearch.results.value = response.data.Search;
      movieSearch.error.value = '';
    } else {
      errorMessage.value = response.data.Error || 'No se encontraron resultados.';
      movieSearch.results.value = [];
    }
  } catch (e) {
    errorMessage.value = 'Error al buscar las películas.';
    movieSearch.results.value = [];
  }
};

const toggleMovie = async (imdbID) => {
  if (selectedMovie.value && selectedMovie.value.imdbID === imdbID) {
    selectedMovie.value = null;
    return;
  }
  selectedMovie.value = null;
  
  try {
    const apiKey = 'f03583fd';
    const url = `https://www.omdbapi.com/?apikey=${apiKey}&i=${imdbID}`;
    const response = await axios.get(url);
    
    if (response.data && response.data.Response === 'True') {
      selectedMovie.value = response.data;
    } else {
      errorMessage.value = response.data.Error || 'No se pudo cargar la información de la película.';
    }
  } catch (e) {
    errorMessage.value = 'Error al cargar la información de la película.';
  }
};

onMounted(async () => {
  await moviesComposable.fetchAllowedStatuses();
  Logger.debug('Allowed movie statuses:', allowedMovieStatusesList.value);
});
</script>

<style>
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
  color: #e0e0e0;
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
  color: #e0e0e0;
  background-color: #2c2c2c;
  border: 1px solid #444;
  border-radius: 30px 0 0 30px;
  outline: none;
}
.movie-input::placeholder {
  color: #888;
}
.search-button {
  padding: 12px 24px;
  font-size: 1rem;
  color: #fff;
  background-color: #007bff;
  border: 1px solid #007bff;
  border-radius: 0 30px 30px 0;
  cursor: pointer;
}
.search-button:hover {
  background-color: #0056b3;
  border-color: #0056b3;
}
.error-message {
  color: #ff4d4f;
  background: rgba(255,77,79,0.1);
  padding: 10px 15px;
  border-radius: 12px;
  margin-bottom: 20px;
  width: 100%;
  text-align: center;
}
.movie-list {
  width: 100%;
  max-width: 600px;
  margin-top: 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.movie-list-item-wrapper {
  display: flex;
  flex-direction: column;
}
.movie-list-item {
  display: flex;
  align-items: center;
  background: #232323;
  border-radius: 10px;
  padding: 10px;
  cursor: pointer;
  transition: background 0.2s, box-shadow 0.2s;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
  border: 1px solid transparent;
}
.movie-list-poster {
  width: 50px;
  height: 75px;
  object-fit: cover;
  border-radius: 4px;
  margin-right: 16px;
  border: 1px solid #444;
}
.movie-detail-below {
  margin-left: 0;
  margin-top: 0;
  padding-left: 0;
  box-sizing: border-box;
  width: 100%;
  max-width: 600px;
}
.movie-detail-below .movie-result {
  margin-top: 0;
  border-top-left-radius: 0;
  border-top-right-radius: 0;
  border-top: none;
  background: #232323;
  display: flex;
  gap: 24px;
  width: 100%;
  max-width: 600px;
  margin-left: 0;
  box-sizing: border-box;
}
.movie-list-item-wrapper:not(:last-child) {
  margin-bottom: 10px;
}
.movie-list-item.expanded {
  background: #282c34;
  border: 1px solid #007bff;
  box-shadow: 0 2px 8px rgba(0,123,255,0.08);
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
}
.movie-list-info {
  display: flex;
  align-items: center;
  gap: 10px;
}
.accordion-arrow {
  font-size: 1.2rem;
  color: #88aaff;
  margin-left: 10px;
  user-select: none;
}
.accordion-enter-active, .accordion-leave-active {
  transition: max-height 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.3s;
}
.accordion-enter-from, .accordion-leave-to {
  max-height: 0;
  opacity: 0;
}
.accordion-enter-to, .accordion-leave-from {
  max-height: 600px;
  opacity: 1;
}
</style>
