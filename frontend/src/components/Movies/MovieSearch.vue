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
    <div :class="['status-message', notifications.statusType.value]" aria-live="polite" style="min-height: 2.5em;">
      <span v-if="notifications.statusMessage.value">{{ notifications.statusMessage.value }}</span>
    </div>
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
            <LibraryMovieItem 
              v-if="allowedMovieStatusesList.length > 0"
              :movie="transformMovieData(selectedMovie)" 
              :allowedUserStatuses="allowedMovieStatusesList" 
              :editable="true"
              :readonly="false"
              @delete-movie="handleDeleteMovie"
              @update-rating="handleUpdateRating"
              @update-statuses="handleUpdateStatuses"
              @save-movie="handleSaveMovie"
              @edit-item="handleEditItem"
            />
            <div v-else class="loading-statuses">
              Cargando estados disponibles...
            </div>
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
import { useLibraryNotifications } from '@/composables/useLibraryNotifications';
import LibraryMovieItem from './LibraryMovieItem.vue';

// Composables
const moviesComposable = useMovies();
const notifications = useLibraryNotifications();

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

// Función para transformar datos de OMDb al formato esperado por LibraryMovieItem
const transformMovieData = (omdbMovie) => {
  if (!omdbMovie) return null;
  
  // Procesar géneros: convertir string separado por comas a array
  const processedGenres = omdbMovie.Genre && omdbMovie.Genre !== 'N/A' 
    ? omdbMovie.Genre.split(', ').map(g => g.trim()) 
    : [];
  
  const movieData = {
    isbn: omdbMovie.imdbID, // Usamos imdbID como ISBN para consistencia
    imdbID: omdbMovie.imdbID,
    title: omdbMovie.Title,
    originalTitle: omdbMovie.Title,
    director: omdbMovie.Director !== 'N/A' ? omdbMovie.Director : null,
    author: omdbMovie.Director !== 'N/A' ? omdbMovie.Director : null, // Para consistencia
    year: omdbMovie.Year,
    coverUrl: omdbMovie.Poster !== 'N/A' ? omdbMovie.Poster : null,
    user_rating: 0, // Nuevo, sin rating del usuario
    userStatuses: [], // Nuevo, sin estados del usuario
    itemType: 'movie',
    // Datos adicionales de OMDb procesados
    genres: processedGenres, // Array de géneros procesados
    plot: omdbMovie.Plot,
    imdbRating: omdbMovie.imdbRating
  };
  
  // Debug: mostrar géneros extraídos
  if (processedGenres.length > 0) {
    console.log(`Extracted genres for "${omdbMovie.Title}":`, processedGenres);
  }
  
  return movieData;
};

// Manejadores de eventos para LibraryMovieItem
const handleDeleteMovie = async () => {
  // En búsqueda no deberíamos tener películas guardadas para eliminar
  console.warn('Delete movie called from search - this should not happen');
};

const handleUpdateRating = async (data) => {
  await moviesComposable.updateMovieRating(data.isbn, data.rating);
};

const handleUpdateStatuses = async (data) => {
  await moviesComposable.updateMovieStatuses(data.isbn, data.statuses);
};

const handleSaveMovie = async (data) => {
  try {
    const result = await moviesComposable.addMovie(data.movie, data.statuses);
    if (result.success) {
      notifications.showSuccess("Película guardada correctamente en tu biblioteca");
      // Cerrar el acordeón después de guardar exitosamente
      selectedMovie.value = null;
    } else {
      notifications.showError("Error al guardar la película: " + (result.message || 'Error desconocido'));
    }
  } catch (error) {
    notifications.showError("Error al guardar la película: " + (error.message || error));
  }
};

const handleEditItem = (movie, itemType) => {
  // Aquí podríamos abrir el modal de edición si fuera necesario
  console.log('Edit item from search:', { movie, itemType });
};

const searchMovies = async () => {
  errorMessage.value = "";
  notifications.clearMessage();
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
      console.log(`Loaded detailed movie data for "${response.data.Title}"`);
      console.log('Raw OMDB genre data:', response.data.Genre);
    } else {
      errorMessage.value = response.data.Error || 'No se pudo cargar la información de la película.';
    }
  } catch (e) {
    errorMessage.value = 'Error al cargar la información de la película.';
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
  background: var(--color-background-soft);
  border-radius: 10px;
  padding: 10px;
  cursor: pointer;
  transition: var(--transition-fast);
  box-shadow: var(--shadow-light);
  border: 1px solid transparent;
}
.movie-list-poster {
  width: 50px;
  height: 75px;
  object-fit: cover;
  border-radius: 4px;
  margin-right: 16px;
  border: 1px solid var(--color-border);
}
.movie-detail-below {
  margin-left: 0;
  margin-top: 0;
  padding-left: 0;
  box-sizing: border-box;
  width: 100%;
  max-width: 600px;
}

/* Estilos específicos para LibraryMovieItem en contexto de búsqueda */
.movie-detail-below .library-movie-item-container {
  margin-top: 0;
  border-top-left-radius: 0;
  border-top-right-radius: 0;
  border-top: none;
  background: var(--color-background-soft);
  width: 100%;
  max-width: 600px;
  margin-left: 0;
  box-sizing: border-box;
}

/* Ajustar el layout para que se vea bien en el acordeón */
.movie-detail-below .movie-details {
  gap: 16px;
}

.movie-detail-below .cover-image {
  width: 120px;
  height: auto;
}
.movie-list-item-wrapper:not(:last-child) {
  margin-bottom: 10px;
}
.movie-list-item.expanded {
  background: var(--color-background-mute);
  border: 1px solid var(--color-primary-light);
  box-shadow: var(--shadow-medium);
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

.loading-statuses {
  padding: 20px;
  text-align: center;
  color: var(--color-text-secondary);
  background: var(--color-background-soft);
}
</style>
