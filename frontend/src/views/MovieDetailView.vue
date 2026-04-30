<template>
  <div class="movie-detail-view">
      <!-- Botón de volver -->
      <button @click="goBack" class="back-button">
        <i class="fas fa-arrow-left"></i>
        <span>Volver a búsqueda</span>
      </button>

      <!-- Estado de carga -->
      <div v-if="isLoading" class="loading-state">
        <i class="fas fa-spinner fa-spin"></i>
        <p>Cargando información de la película...</p>
      </div>

      <!-- Mensaje de error -->
      <div v-else-if="error" class="error-state">
        <i class="fas fa-exclamation-circle"></i>
        <p>{{ error }}</p>
        <button @click="goBack" class="action-button">Volver a búsqueda</button>
      </div>

      <!-- Detalles de la película -->
      <div v-else-if="movie" class="movie-detail-content">
        <!-- Cabecera principal con póster y datos -->
        <div class="movie-header">
          <div class="movie-poster-large">
            <img v-if="movie.coverUrl" :src="movie.coverUrl" :alt="movie.title" class="poster-image-large" />
            <div v-else class="poster-placeholder">
              <i class="fas fa-film"></i>
            </div>
          </div>
          
          <div class="movie-main-info">
            <h1 class="movie-title-large">{{ movie.title }}</h1>

            <!-- Badge tipo: Serie o Película -->
            <div class="media-type-indicator" :class="movie.type === 'series' ? 'is-series' : 'is-movie'">
              <i :class="movie.type === 'series' ? 'fas fa-tv' : 'fas fa-film'"></i>
              {{ movie.type === 'series' ? 'Serie de Televisión' : 'Película' }}
            </div>

            <!-- Info exclusiva de series: temporadas -->
            <div v-if="movie.type === 'series' && movie.totalSeasons" class="series-seasons-info">
              <i class="fas fa-layer-group"></i>
              <span>{{ movie.totalSeasons }} temporada{{ movie.totalSeasons > 1 ? 's' : '' }}</span>
            </div>
            
            <div v-if="movie.director" class="movie-director-large">
              <i :class="movie.type === 'series' ? 'fas fa-tv' : 'fas fa-video'"></i>
              <span>{{ movie.type === 'series' ? 'Creada por' : 'Dirigida por' }} {{ movie.director }}</span>
            </div>
            
            <div class="movie-metadata">
              <span v-if="movie.year" class="metadata-item">
                <i class="fas fa-calendar"></i>
                {{ movie.year }}
              </span>
              <span v-if="movie.rated" class="metadata-item">
                <i class="fas fa-certificate"></i>
                {{ movie.rated }}
              </span>
              <span v-if="movie.runtime" class="metadata-item">
                <i class="fas fa-clock"></i>
                {{ movie.type === 'series' ? movie.runtime + ' / ep.' : movie.runtime }}
              </span>
              <span v-if="movie.country" class="metadata-item">
                <i class="fas fa-globe"></i>
                {{ movie.country }}
              </span>
            </div>
            
            <div v-if="movie.language" class="movie-language">
              <i class="fas fa-language"></i>
              <span>{{ movie.language }}</span>
            </div>
            
            <div class="movie-ratings">
              <div v-if="movie.imdbRating && movie.imdbRating !== 'N/A'" class="rating-item">
                <i class="fab fa-imdb"></i>
                <strong>IMDb:</strong> {{ movie.imdbRating }}/10
                <span v-if="movie.imdbVotes" class="votes">({{ movie.imdbVotes }} votos)</span>
              </div>
              <div v-if="movie.metascore && movie.metascore !== 'N/A'" class="rating-item">
                <i class="fas fa-star"></i>
                <strong>Metascore:</strong> {{ movie.metascore }}/100
              </div>
              <div v-if="movie.ratings && movie.ratings.length > 0" class="additional-ratings">
                <div v-for="(rating, index) in movie.ratings" :key="index" class="rating-item">
                  <i class="fas fa-star-half-alt"></i>
                  <strong>{{ rating.Source }}:</strong> {{ rating.Value }}
                </div>
              </div>
            </div>
            
            <div class="movie-imdb-id">
              <strong>IMDb ID:</strong> {{ movie.imdbID }}
            </div>
            
            <!-- Géneros -->
            <div v-if="movie.genres && movie.genres.length > 0" class="movie-genres">
              <i class="fas fa-tags"></i>
              <div class="genre-tags">
                <span v-for="(genre, index) in movie.genres" :key="index" class="genre-tag">
                  {{ genre }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Sinopsis -->
        <div v-if="movie.plot && movie.plot !== 'N/A'" class="movie-plot-section">
          <h2 class="section-title">
            <i class="fas fa-align-left"></i>
            Sinopsis
          </h2>
          <p class="movie-plot-content">{{ movie.plot }}</p>
        </div>

        <!-- Equipo y reparto -->
        <div v-if="movie.actors || movie.writer" class="movie-crew-section">
          <h2 class="section-title">
            <i class="fas fa-users"></i>
            Equipo y Reparto
          </h2>
          <div class="crew-info">
            <div v-if="movie.actors && movie.actors !== 'N/A'" class="crew-item">
              <strong><i class="fas fa-user-tie"></i> Actores:</strong> {{ movie.actors }}
            </div>
            <div v-if="movie.writer && movie.writer !== 'N/A'" class="crew-item">
              <strong><i class="fas fa-pen"></i> Guion:</strong> {{ movie.writer }}
            </div>
          </div>
        </div>

        <!-- Información adicional -->
        <div v-if="movie.awards && movie.awards !== 'N/A'" class="movie-awards-section">
          <h2 class="section-title">
            <i class="fas fa-trophy"></i>
            Premios y Nominaciones
          </h2>
          <p class="awards-content">{{ movie.awards }}</p>
        </div>

        <!-- Producción y Lanzamiento (solo películas) -->
        <div v-if="movie.type !== 'series' && (movie.production || movie.boxOffice || movie.released || movie.dvd || movie.website)" class="movie-production-section">
          <h2 class="section-title">
            <i class="fas fa-building"></i>
            Producción y Lanzamiento
          </h2>
          <div class="production-info">
            <div v-if="movie.production && movie.production !== 'N/A'" class="production-item">
              <strong><i class="fas fa-industry"></i> Productora:</strong> {{ movie.production }}
            </div>
            <div v-if="movie.boxOffice && movie.boxOffice !== 'N/A'" class="production-item">
              <strong><i class="fas fa-dollar-sign"></i> Recaudación:</strong> {{ movie.boxOffice }}
            </div>
            <div v-if="movie.released && movie.released !== 'N/A'" class="production-item">
              <strong><i class="fas fa-calendar-day"></i> Estreno:</strong> {{ movie.released }}
            </div>
            <div v-if="movie.dvd && movie.dvd !== 'N/A'" class="production-item">
              <strong><i class="fas fa-compact-disc"></i> DVD:</strong> {{ movie.dvd }}
            </div>
            <div v-if="movie.website && movie.website !== 'N/A'" class="production-item">
              <strong><i class="fas fa-link"></i> Sitio Web:</strong> 
              <a :href="movie.website" target="_blank" rel="noopener noreferrer">{{ movie.website }}</a>
            </div>
            <div v-if="movie.type && movie.type !== 'N/A'" class="production-item">
              <strong><i class="fas fa-film"></i> Tipo:</strong> {{ movie.type }}
            </div>
          </div>
        </div>

        <!-- Enlace a IMDb -->
        <div class="movie-links-section">
          <h2 class="section-title">
            <i class="fas fa-external-link-alt"></i>
            Enlaces Externos
          </h2>
          <div class="external-links">
            <a :href="`https://www.imdb.com/title/${movie.imdbID}`" target="_blank" rel="noopener noreferrer" class="external-link">
              <i class="fab fa-imdb"></i>
              Ver en IMDb
            </a>
          </div>
        </div>

        <!-- Separador visual -->
        <div class="section-divider"></div>

        <!-- Formulario de biblioteca -->
        <div class="library-form-section">
          <h2 class="section-title">
            <i :class="['fas', existingMovie ? 'fa-edit' : 'fa-save']"></i>
            {{ existingMovie ? 'Editar en Mi Biblioteca' : 'Añadir a Mi Biblioteca' }}
          </h2>
          <LibraryMovieItem
            ref="libraryMovieItemRef"
            :movie="movie"
            :allowedUserStatuses="allowedStatuses"
            :editable="!!existingMovie"
            @delete-movie="handleDeleteMovie"
            @save-movie="handleSaveMovie"
            @edit-item="handleEditItem"
          />
        </div>
      </div>

      <!-- Estado inicial sin película -->
      <div v-else class="empty-state">
        <i class="fas fa-film"></i>
        <p>No se encontró información de la película</p>
        <button @click="goBack" class="action-button">Volver a búsqueda</button>
      </div>

      <!-- Edit Item Modal -->
      <EditItemModal
        v-if="editModal.isVisible"
        :item="editModal.item"
        :item-type="editModal.itemType"
        :allowed-statuses="allowedStatuses"
        :is-visible="editModal.isVisible"
        @close="closeEditModal"
        @saved="handleModalSaved"
      />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import LibraryMovieItem from '@/components/Movies/LibraryMovieItem.vue';
import EditItemModal from '@/components/EditItemModal.vue';
import { useMovies } from '@/composables/useMovies';
import { useAuth } from '@/composables/useAuth';
import Logger from '@/utils/logger';

const route = useRoute();
const router = useRouter();
const { isAuthenticated } = useAuth();
const moviesComposable = useMovies();

// Estados
const movie = ref((history.state && history.state.movie) ? history.state.movie : null);
// Si venimos con datos en el router state, no mostrar spinner (transición seamless)
const isLoading = ref(!movie.value);
const error = ref(null);
const libraryMovieItemRef = ref(null);
const editModal = ref({
  isVisible: false,
  item: null,
  itemType: 'movie'
});

// Computed
const allowedStatuses = computed(() => {
  const all = Array.isArray(moviesComposable.allowedStatuses.value)
    ? moviesComposable.allowedStatuses.value : [];
  const mediaType = movie.value?.type || movie.value?.mediaType || movie.value?.media_type || 'movie';
  if (mediaType === 'series') {
    // Series: excluir 'abandoned' (usar 'dropped')
    return all.filter(s => s !== 'abandoned');
  }
  // Película: excluir estados exclusivos de series
  return all.filter(s => !['watching', 'on-hold', 'dropped'].includes(s));
});

const existingMovie = computed(() => {
  if (!movie.value?.imdbID) return null;
  return moviesComposable.findMovieByTMDBId(movie.value.imdbID);
});

// Métodos
const goBack = () => {
  if (window.history.length > 1) {
    router.back();
  } else {
    router.push({ name: 'Movies' });
  }
};

const transformMovieData = (omdbMovie) => {
  if (!omdbMovie) return null;
  
  // Procesar géneros: convertir string separado por comas a array
  const processedGenres = omdbMovie.Genre && omdbMovie.Genre !== 'N/A' 
    ? omdbMovie.Genre.split(', ').map(g => g.trim()) 
    : [];
  
  return {
    isbn: omdbMovie.imdbID,
    imdbID: omdbMovie.imdbID,
    title: omdbMovie.Title,
    originalTitle: omdbMovie.Title,
    director: omdbMovie.Director !== 'N/A' ? omdbMovie.Director : null,
    author: omdbMovie.Director !== 'N/A' ? omdbMovie.Director : null,
    year: omdbMovie.Year,
    coverUrl: omdbMovie.Poster !== 'N/A' ? omdbMovie.Poster : null,
    user_rating: 0,
    userStatuses: [],
    itemType: 'movie',
    // Datos básicos de OMDb
    genres: processedGenres,
    plot: omdbMovie.Plot,
    imdbRating: omdbMovie.imdbRating,
    imdbVotes: omdbMovie.imdbVotes,
    metascore: omdbMovie.Metascore,
    rated: omdbMovie.Rated,
    runtime: omdbMovie.Runtime,
    language: omdbMovie.Language,
    country: omdbMovie.Country,
    awards: omdbMovie.Awards,
    actors: omdbMovie.Actors,
    writer: omdbMovie.Writer,
    production: omdbMovie.Production,
    boxOffice: omdbMovie.BoxOffice,
    // Campos adicionales
    released: omdbMovie.Released,
    dvd: omdbMovie.DVD,
    website: omdbMovie.Website,
    type: omdbMovie.Type ? omdbMovie.Type.toLowerCase() : 'movie',
    totalSeasons: omdbMovie.totalSeasons && omdbMovie.totalSeasons !== 'N/A'
      ? parseInt(omdbMovie.totalSeasons) : null,
    ratings: omdbMovie.Ratings || []
  };
};

const fetchMovieDetails = async (imdbId) => {
  // Solo mostrar spinner si no hay datos previos (evitar flash en enrichment)
  const isBackgroundEnrichment = !!movie.value;
  if (!isBackgroundEnrichment) {
    isLoading.value = true;
  }
  error.value = null;

  try {
    Logger.debug(`[MovieDetailView] Fetching details for IMDb ID: ${imdbId}`);
    
    const apiKey = process.env.VUE_APP_OMDB_API_KEY;
    const url = `https://www.omdbapi.com/?apikey=${apiKey}&i=${imdbId}&plot=full`;
    const response = await axios.get(url);
    
    if (response.data && response.data.Response === 'True') {
      movie.value = transformMovieData(response.data);
      Logger.debug(`[MovieDetailView] Movie loaded successfully:`, movie.value.title);
    } else {
      error.value = response.data.Error || 'No se encontró información de la película.';
      Logger.error(`[MovieDetailView] OMDb API error:`, response.data.Error);
    }
  } catch (err) {
    error.value = 'No se pudo obtener información de la película. Verifica el IMDb ID.';
    Logger.error(`[MovieDetailView] Error fetching movie details:`, err);
  } finally {
    if (!isBackgroundEnrichment) {
      isLoading.value = false;
    }
  }
};

const handleDeleteMovie = async () => {
};

const handleSaveMovie = async (data) => {
  try {
    Logger.debug('[MovieDetailView] Saving movie to library:', data);
    
    const result = await moviesComposable.addMovie(data.movie, data.statuses);
    
    if (result.success) {
      // Llamar al método de éxito del componente hijo
      if (libraryMovieItemRef.value) {
        libraryMovieItemRef.value.setSaveSuccess();
      }
      // Actualizar la película local con los datos guardados
      if (movie.value) {
        movie.value.userStatuses = data.statuses;
      }
    } else {
      // Llamar al método de error del componente hijo
      if (libraryMovieItemRef.value) {
        libraryMovieItemRef.value.setSaveError();
      }
    }
  } catch (err) {
    Logger.error('[MovieDetailView] Error saving movie:', err);
    // Llamar al método de error del componente hijo
    if (libraryMovieItemRef.value) {
      libraryMovieItemRef.value.setSaveError();
    }
  }
};

const handleEditItem = async (movieData) => {
  Logger.debug('[MovieDetailView] Opening edit modal for movie:', movieData);
  
  editModal.value = {
    isVisible: true,
    item: movie.value,
    itemType: 'movie'
  };
};

const closeEditModal = () => {
  editModal.value = {
    isVisible: false,
    item: null,
    itemType: 'movie'
  };
};

const handleModalSaved = async (updatedItem) => {
  Logger.debug('[MovieDetailView] Movie saved from modal, updating local data', updatedItem);
  
  // Cerrar el modal
  closeEditModal();
  
  try {
    // Actualizar inmediatamente con datos del evento (optimista)
    if (movie.value && updatedItem) {
      movie.value = {
        ...movie.value,
        ...updatedItem,
        user_rating: updatedItem.user_rating,
        userStatuses: updatedItem.userStatuses
      };
    }
    
    // Actualizar en el store local de movies también
    const movieInStore = moviesComposable.findMovieByTMDBId(movie.value.imdbID || movie.value.tmdbId);
    if (movieInStore) {
      Object.assign(movieInStore, updatedItem);
    }
    
    // Llamar al método de éxito del componente hijo
    if (libraryMovieItemRef.value) {
      libraryMovieItemRef.value.setEditSuccess();
    }
    
    Logger.info('[MovieDetailView] Movie data updated successfully');
    
    // Opcional: Recargar en segundo plano para sincronizar (sin bloquear UI)
    setTimeout(() => {
      moviesComposable.fetchMovies().catch(err => {
        Logger.error('[MovieDetailView] Background refresh failed:', err);
      });
    }, 500);
  } catch (err) {
    Logger.error('[MovieDetailView] Error updating movie data:', err);
    if (libraryMovieItemRef.value) {
      libraryMovieItemRef.value.setEditError();
    }
  }
};

// Helper function to load movie data
const loadMovieData = async () => {
  Logger.debug('[MovieDetailView] Loading movie data');

  // Datos ya cargados eagerly desde history.state, o via route.state
  const hasEagerData = !!movie.value;

  if (hasEagerData || (route.state && route.state.movie)) {
    if (!hasEagerData && route.state.movie) {
      movie.value = route.state.movie;
    }
    isLoading.value = false;
    Logger.debug('[MovieDetailView] Using pre-loaded movie data (seamless)');

    // Cargar datos de biblioteca en segundo plano
    await Promise.all([
      moviesComposable.movies.value.length === 0 ? moviesComposable.fetchMovies() : Promise.resolve(),
      allowedStatuses.value.length === 0 ? moviesComposable.fetchAllowedStatuses() : Promise.resolve()
    ]);

    // Enriquecer con datos completos de la API en segundo plano (sin mostrar spinner)
    fetchMovieDetails(route.params.imdbId)
      .then(() => _mergeExistingMovieData())
      .catch(err =>
        Logger.warn('[MovieDetailView] Background enrichment failed:', err)
      );
  } else {
    // Sin state: acceso directo por URL — mostrar spinner
    await Promise.all([
      moviesComposable.movies.value.length === 0 ? moviesComposable.fetchMovies() : Promise.resolve(),
      allowedStatuses.value.length === 0 ? moviesComposable.fetchAllowedStatuses() : Promise.resolve()
    ]);
    await fetchMovieDetails(route.params.imdbId);
  }

  _mergeExistingMovieData();
};

const _mergeExistingMovieData = () => {
  if (!existingMovie.value || !movie.value) return;

  Logger.debug('[MovieDetailView] Merging with existing movie data');
  movie.value = {
    ...movie.value,
    user_rating: existingMovie.value.user_rating,
    userStatuses: existingMovie.value.userStatuses || []
  };
};

// Lifecycle
onMounted(async () => {
  Logger.debug('[MovieDetailView] Component mounted');
  
  // Wait for authentication before loading data
  if (isAuthenticated.value) {
    await loadMovieData();
  }
});

// Watch for authentication changes
watch(isAuthenticated, async (newValue) => {
  if (newValue && !movie.value) {
    Logger.debug('[MovieDetailView] User authenticated, loading movie data...');
    await loadMovieData();
  }
});
</script>

<style scoped>
.movie-detail-view {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background-color: var(--color-background-mute);
  color: var(--color-text);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.95rem;
  transition: all 0.2s ease;
  margin-bottom: 20px;
}

.back-button:hover {
  background-color: var(--color-background-soft);
  border-color: var(--color-border-hover);
}

.back-button i {
  font-size: 1rem;
}

.page-title {
  font-size: 1.8rem;
  color: var(--color-heading);
  margin-bottom: 20px;
  font-weight: 600;
}

.movie-detail-content {
  animation: fadeIn 0.3s ease-in;
}

/* Cabecera de la película */
.movie-header {
  display: flex;
  gap: 30px;
  margin-bottom: 40px;
  padding: 30px;
  background: var(--color-background-mute);
  border-radius: 16px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.movie-poster-large {
  flex-shrink: 0;
  width: 220px;
}

.poster-image-large {
  width: 100%;
  height: auto;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  border: 1px solid var(--color-border);
}

.poster-placeholder {
  width: 220px;
  height: 330px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-background-soft);
  border-radius: 12px;
  border: 2px dashed var(--color-border);
}

.poster-placeholder i {
  font-size: 4rem;
  color: var(--color-text-muted);
}

.movie-main-info {
  flex: 1;
  min-width: 0;
}

.movie-title-large {
  font-size: 2.2rem;
  font-weight: 700;
  color: var(--color-heading);
  margin: 0 0 15px 0;
  line-height: 1.3;
}

.movie-director-large {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.2rem;
  color: var(--color-text-secondary);
  margin-bottom: 20px;
}

.movie-director-large i {
  color: var(--color-primary);
}

.media-type-indicator {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-bottom: 12px;
}

.media-type-indicator.is-series {
  background: rgba(139, 92, 246, 0.15);
  color: #a78bfa;
  border: 1px solid rgba(139, 92, 246, 0.35);
}

.media-type-indicator.is-movie {
  background: rgba(29, 78, 74, 0.15);
  color: #4ade80;
  border: 1px solid rgba(29, 78, 74, 0.35);
}

.series-seasons-info {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 1rem;
  color: #a78bfa;
  margin-bottom: 16px;
  font-weight: 500;
}

.movie-metadata {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  margin-bottom: 15px;
}

.metadata-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: var(--color-background-soft);
  border-radius: 6px;
  font-size: 0.9rem;
  color: var(--color-text);
}

.metadata-item i {
  color: var(--color-primary);
  font-size: 0.85rem;
}

.movie-language {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  color: var(--color-text-secondary);
  font-size: 0.95rem;
}

.movie-language i {
  color: var(--color-primary);
}

.movie-ratings {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 15px 0;
  padding: 15px;
  background: var(--color-background-soft);
  border-radius: 8px;
}

.additional-ratings {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  padding-top: 10px;
  border-top: 1px solid var(--color-border);
}

.rating-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.95rem;
}

.rating-item i {
  font-size: 1.2rem;
  color: #f5c518;
}

.rating-item .votes {
  color: var(--color-text-muted);
  font-size: 0.85rem;
  margin-left: 5px;
}

.movie-imdb-id {
  margin-bottom: 15px;
  padding: 10px 15px;
  background: var(--color-background-soft);
  border-left: 3px solid var(--color-primary);
  border-radius: 4px;
  font-size: 0.9rem;
  font-family: 'Courier New', monospace;
}

.movie-genres {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-top: 15px;
}

.movie-genres i {
  color: var(--color-primary);
  margin-top: 6px;
  flex-shrink: 0;
}

.genre-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.genre-tag {
  padding: 4px 12px;
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
  color: white;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 500;
}

/* Secciones */
.section-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.4rem;
  font-weight: 600;
  color: var(--color-heading);
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 2px solid var(--color-border);
}

.section-title i {
  color: var(--color-primary);
}

.movie-plot-section,
.movie-crew-section,
.movie-awards-section,
.movie-production-section,
.movie-links-section,
.library-form-section {
  margin-bottom: 35px;
  padding: 25px;
  background: var(--color-background-mute);
  border-radius: 12px;
}

.movie-plot-content {
  line-height: 1.8;
  color: var(--color-text);
  font-size: 1rem;
  text-align: justify;
}

.crew-info,
.production-info {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.crew-item,
.production-item {
  padding: 10px 15px;
  background: var(--color-background-soft);
  border-radius: 6px;
  line-height: 1.6;
}

.production-item a {
  color: var(--color-primary);
  text-decoration: none;
}

.production-item a:hover {
  text-decoration: underline;
}

.crew-item i,
.production-item i {
  color: var(--color-primary);
  margin-right: 5px;
}

.awards-content {
  line-height: 1.8;
  color: var(--color-text);
  font-size: 1rem;
}

/* Enlaces externos */
.external-links {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.external-link {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  background: var(--color-background-soft);
  color: var(--color-text);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  text-decoration: none;
  font-size: 0.95rem;
  transition: all 0.2s ease;
  max-width: fit-content;
}

.external-link:hover {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
  transform: translateX(4px);
}

.external-link i {
  font-size: 1.1rem;
}

/* Separador */
.section-divider {
  height: 2px;
  background: linear-gradient(90deg, transparent, var(--color-border), transparent);
  margin: 40px 0;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Estados de carga, error y vacío */
.loading-state,
.error-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  gap: 16px;
}

.loading-state i,
.error-state i,
.empty-state i {
  font-size: 3rem;
  color: var(--color-text-mute);
}

.loading-state i {
  color: var(--color-primary);
}

.error-state i {
  color: #ff6b6b;
}

.loading-state p,
.error-state p,
.empty-state p {
  font-size: 1.1rem;
  color: var(--color-text-mute);
  margin: 0;
}

.action-button {
  padding: 10px 20px;
  background-color: var(--color-primary);
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1rem;
  transition: background-color 0.2s ease;
}

.action-button:hover {
  background-color: var(--color-primary-dark);
}

/* Responsive */
@media (max-width: 768px) {
  .movie-detail-view {
    padding: 15px;
  }

  .back-button {
    font-size: 0.9rem;
    padding: 8px 12px;
  }
  
  .movie-header {
    flex-direction: column;
    padding: 20px;
    gap: 20px;
  }
  
  .movie-poster-large,
  .poster-placeholder {
    width: 100%;
    max-width: 250px;
    margin: 0 auto;
  }
  
  .movie-title-large {
    font-size: 1.6rem;
  }
  
  .movie-director-large {
    font-size: 1rem;
  }
  
  .movie-metadata {
    gap: 8px;
  }
  
  .metadata-item {
    font-size: 0.85rem;
    padding: 5px 10px;
  }
  
  .movie-ratings {
    flex-direction: column;
    gap: 10px;
    padding: 12px;
  }
  
  .movie-plot-section,
  .movie-crew-section,
  .movie-awards-section,
  .movie-production-section,
  .movie-links-section,
  .library-form-section {
    padding: 15px;
    margin-bottom: 25px;
  }
  
  .section-title {
    font-size: 1.2rem;
  }
  
  .genre-tag {
    font-size: 0.8rem;
    padding: 4px 10px;
  }
}
</style>
