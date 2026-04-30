<template>
  <div class="series-detail-view">
    <!-- Botón de volver -->
    <button @click="goBack" class="back-button">
      <i class="fas fa-arrow-left"></i>
      <span>Volver a búsqueda</span>
    </button>

    <!-- Estado de carga -->
    <div v-if="isLoading" class="loading-state">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Cargando información de la serie...</p>
    </div>

    <!-- Mensaje de error -->
    <div v-else-if="error" class="error-state">
      <i class="fas fa-exclamation-circle"></i>
      <p>{{ error }}</p>
      <button @click="goBack" class="action-button">Volver a búsqueda</button>
    </div>

    <!-- Detalle de la serie -->
    <div v-else-if="series" class="series-detail-content">
      <!-- Cabecera -->
      <div class="series-header">
        <div class="series-poster-large">
          <img v-if="series.coverUrl" :src="series.coverUrl" :alt="series.title" class="poster-image-large" />
          <div v-else class="poster-placeholder">
            <i class="fas fa-tv"></i>
          </div>
        </div>

        <div class="series-main-info">
          <h1 class="series-title-large">{{ series.title }}</h1>

          <!-- Badge: Serie de Televisión -->
          <div class="media-type-indicator is-series">
            <i class="fas fa-tv"></i>
            Serie de Televisión
          </div>

          <!-- Temporadas -->
          <div v-if="series.totalSeasons" class="series-seasons-info">
            <i class="fas fa-layer-group"></i>
            <span>{{ series.totalSeasons }} temporada{{ series.totalSeasons > 1 ? 's' : '' }}</span>
          </div>

          <div v-if="series.director" class="series-creator">
            <i class="fas fa-tv"></i>
            <span>Creada por {{ series.director }}</span>
          </div>

          <div class="series-metadata">
            <span v-if="series.year" class="metadata-item">
              <i class="fas fa-calendar"></i>
              {{ series.year }}
            </span>
            <span v-if="series.rated" class="metadata-item">
              <i class="fas fa-certificate"></i>
              {{ series.rated }}
            </span>
            <span v-if="series.runtime" class="metadata-item">
              <i class="fas fa-clock"></i>
              {{ series.runtime }} / ep.
            </span>
            <span v-if="series.country" class="metadata-item">
              <i class="fas fa-globe"></i>
              {{ series.country }}
            </span>
          </div>

          <div v-if="series.language" class="series-language">
            <i class="fas fa-language"></i>
            <span>{{ series.language }}</span>
          </div>

          <!-- Ratings externos -->
          <div class="series-ratings">
            <div v-if="series.imdbRating && series.imdbRating !== 'N/A'" class="rating-item">
              <i class="fab fa-imdb"></i>
              <strong>IMDb:</strong> {{ series.imdbRating }}/10
              <span v-if="series.imdbVotes" class="votes">({{ series.imdbVotes }} votos)</span>
            </div>
            <div v-if="series.metascore && series.metascore !== 'N/A'" class="rating-item">
              <i class="fas fa-star"></i>
              <strong>Metascore:</strong> {{ series.metascore }}/100
            </div>
          </div>

          <div class="series-imdb-id">
            <strong>IMDb ID:</strong> {{ series.imdbID }}
          </div>

          <!-- Géneros -->
          <div v-if="series.genres && series.genres.length > 0" class="series-genres">
            <i class="fas fa-tags"></i>
            <div class="genre-tags">
              <span v-for="(genre, i) in series.genres" :key="i" class="genre-tag">{{ genre }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Sinopsis -->
      <div v-if="series.plot && series.plot !== 'N/A'" class="series-plot-section">
        <h2 class="section-title"><i class="fas fa-align-left"></i> Sinopsis</h2>
        <p class="series-plot-content">{{ series.plot }}</p>
      </div>

      <!-- Equipo y reparto -->
      <div v-if="series.actors || series.writer" class="series-crew-section">
        <h2 class="section-title"><i class="fas fa-users"></i> Equipo y Reparto</h2>
        <div class="crew-info">
          <div v-if="series.actors && series.actors !== 'N/A'" class="crew-item">
            <strong><i class="fas fa-user-tie"></i> Actores:</strong> {{ series.actors }}
          </div>
          <div v-if="series.writer && series.writer !== 'N/A'" class="crew-item">
            <strong><i class="fas fa-pen"></i> Guion:</strong> {{ series.writer }}
          </div>
        </div>
      </div>

      <!-- Premios -->
      <div v-if="series.awards && series.awards !== 'N/A'" class="series-awards-section">
        <h2 class="section-title"><i class="fas fa-trophy"></i> Premios</h2>
        <p class="awards-content">{{ series.awards }}</p>
      </div>

      <!-- Enlace externo -->
      <div class="series-links-section">
        <h2 class="section-title"><i class="fas fa-external-link-alt"></i> Ver en</h2>
        <a :href="`https://www.imdb.com/title/${series.imdbID}`" target="_blank" rel="noopener noreferrer" class="external-link">
          <i class="fab fa-imdb"></i> IMDb
        </a>
      </div>

      <div class="section-divider"></div>

      <!-- ══ Seguimiento de temporadas ══ -->
      <div v-if="series.totalSeasons && existingSeries" class="season-tracker-section">
        <SeriesSeasonTracker
          :imdb-id="series.imdbID"
          :total-seasons="series.totalSeasons"
          :progress="seasonProgress"
          :is-saving="isSavingSeason"
          @season-updated="handleSeasonUpdated"
        />
      </div>

      <div class="section-divider"></div>

      <!-- Formulario de biblioteca -->
      <div class="library-form-section">
        <h2 class="section-title">
          <i :class="['fas', existingSeries ? 'fa-edit' : 'fa-save']"></i>
          {{ existingSeries ? 'Editar en Mi Biblioteca' : 'Añadir a Mi Biblioteca' }}
        </h2>
        <LibraryMovieItem
          ref="librarySeriesItemRef"
          :movie="series"
          :allowedUserStatuses="allowedStatuses"
          :editable="!!existingSeries"
          @delete-movie="handleDeleteSeries"
          @save-movie="handleSaveSeries"
          @edit-item="handleEditItem"
        />
      </div>
    </div>

    <!-- Estado vacío -->
    <div v-else class="empty-state">
      <i class="fas fa-tv"></i>
      <p>No se encontró información de la serie</p>
      <button @click="goBack" class="action-button">Volver a búsqueda</button>
    </div>

    <!-- Edit Modal -->
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
import SeriesSeasonTracker from '@/components/Movies/SeriesSeasonTracker.vue';
import EditItemModal from '@/components/EditItemModal.vue';
import { useMovies } from '@/composables/useMovies';
import { useAuth } from '@/composables/useAuth';
import Logger from '@/utils/logger';

const route  = useRoute();
const router = useRouter();
const { isAuthenticated } = useAuth();
const moviesComposable    = useMovies();

// ── Estado ──────────────────────────────────────────────────
const series   = ref((history.state && history.state.movie) ? history.state.movie : null);
const isLoading = ref(!series.value);
const error     = ref(null);
const librarySeriesItemRef = ref(null);
const editModal = ref({ isVisible: false, item: null, itemType: 'movie' });

// Tracking de temporadas
const seasonProgress = ref({});
const isSavingSeason = ref(false);

// ── Computed ─────────────────────────────────────────────────
const allowedStatuses = computed(() => {
  const all = Array.isArray(moviesComposable.allowedStatuses.value)
    ? moviesComposable.allowedStatuses.value : [];
  // Series: excluir 'abandoned' (se usa 'dropped')
  return all.filter(s => s !== 'abandoned');
});

const existingSeries = computed(() => {
  if (!series.value?.imdbID) return null;
  return moviesComposable.findMovieByTMDBId(series.value.imdbID);
});

// ── Helpers ──────────────────────────────────────────────────
const goBack = () => {
  if (window.history.length > 1) router.back();
  else router.push({ name: 'Movies' });
};

const transformSeriesData = (omdbData) => {
  if (!omdbData) return null;
  const genres = omdbData.Genre && omdbData.Genre !== 'N/A'
    ? omdbData.Genre.split(', ').map(g => g.trim()) : [];
  return {
    isbn:          omdbData.imdbID,
    imdbID:        omdbData.imdbID,
    title:         omdbData.Title,
    originalTitle: omdbData.Title,
    director:      omdbData.Director !== 'N/A' ? omdbData.Director : null,
    author:        omdbData.Director !== 'N/A' ? omdbData.Director : null,
    year:          omdbData.Year,
    coverUrl:      omdbData.Poster !== 'N/A' ? omdbData.Poster : null,
    user_rating:   0,
    userStatuses:  [],
    itemType:      'movie',
    genres,
    plot:          omdbData.Plot,
    imdbRating:    omdbData.imdbRating,
    imdbVotes:     omdbData.imdbVotes,
    metascore:     omdbData.Metascore,
    rated:         omdbData.Rated,
    runtime:       omdbData.Runtime,
    language:      omdbData.Language,
    country:       omdbData.Country,
    awards:        omdbData.Awards,
    actors:        omdbData.Actors,
    writer:        omdbData.Writer,
    type:          'series',
    media_type:    'series',
    totalSeasons:  omdbData.totalSeasons && omdbData.totalSeasons !== 'N/A'
      ? parseInt(omdbData.totalSeasons) : null,
    ratings:       omdbData.Ratings || [],
  };
};

const fetchSeriesDetails = async (imdbId) => {
  const isBackground = !!series.value;
  if (!isBackground) isLoading.value = true;
  error.value = null;
  try {
    const apiKey = process.env.VUE_APP_OMDB_API_KEY;
    const url = `https://www.omdbapi.com/?apikey=${apiKey}&i=${imdbId}&plot=full`;
    const response = await axios.get(url);
    if (response.data && response.data.Response === 'True') {
      series.value = transformSeriesData(response.data);
    } else {
      error.value = response.data.Error || 'No se encontró información de la serie.';
    }
  } catch (err) {
    error.value = 'No se pudo obtener información de la serie.';
    Logger.error('[SeriesDetailView] Error:', err);
  } finally {
    if (!isBackground) isLoading.value = false;
  }
};

const loadSeasonProgress = async () => {
  if (!series.value?.imdbID || !existingSeries.value) return;
  try {
    const result = await moviesComposable.getSeriesProgress(series.value.imdbID);
    if (result.success) seasonProgress.value = result.data || {};
  } catch (e) {
    Logger.warn('[SeriesDetailView] Could not load season progress:', e);
  }
};

// ── Handlers ─────────────────────────────────────────────────
const handleSeasonUpdated = async ({ seasonNumber, status, dateViewed, personalRating, notes }) => {
  if (!series.value?.imdbID) return;
  isSavingSeason.value = true;
  try {
    await moviesComposable.trackSeriesSeason(series.value.imdbID, seasonNumber, {
      status, dateViewed, personalRating, notes,
    });
  } catch (e) {
    Logger.error('[SeriesDetailView] Error saving season:', e);
  } finally {
    isSavingSeason.value = false;
  }
};

const handleDeleteSeries = async () => {};

const handleSaveSeries = async (data) => {
  try {
    const result = await moviesComposable.addMovie(data.movie, data.statuses);
    if (result.success) {
      if (librarySeriesItemRef.value) librarySeriesItemRef.value.setSaveSuccess();
      if (series.value) series.value.userStatuses = data.statuses;
      // Cargar progreso de temporadas después de añadir
      await loadSeasonProgress();
    } else {
      if (librarySeriesItemRef.value) librarySeriesItemRef.value.setSaveError(result.message);
    }
  } catch (e) {
    Logger.error('[SeriesDetailView] Error saving series:', e);
  }
};

const handleEditItem = () => {
  editModal.value = { isVisible: true, item: series.value, itemType: 'movie' };
};

const closeEditModal = () => {
  editModal.value = { isVisible: false, item: null, itemType: 'movie' };
};

const handleModalSaved = async (updatedItem) => {
  closeEditModal();
  if (series.value && updatedItem) {
    series.value = { ...series.value, ...updatedItem };
  }
  setTimeout(() => moviesComposable.fetchMovies().catch(() => {}), 500);
};

// ── Lifecycle ─────────────────────────────────────────────────
const loadData = async () => {
  const hasEager = !!series.value;

  await Promise.all([
    moviesComposable.movies.value.length === 0 ? moviesComposable.fetchMovies() : Promise.resolve(),
    allowedStatuses.value.length === 0 ? moviesComposable.fetchAllowedStatuses() : Promise.resolve(),
  ]);

  if (hasEager) {
    fetchSeriesDetails(route.params.imdbId).catch(() => {});
  } else {
    await fetchSeriesDetails(route.params.imdbId);
  }

  // Merge datos de biblioteca
  if (existingSeries.value && series.value) {
    series.value = {
      ...series.value,
      user_rating:  existingSeries.value.user_rating,
      userStatuses: existingSeries.value.userStatuses || [],
    };
  }

  await loadSeasonProgress();
};

onMounted(async () => {
  if (isAuthenticated.value) await loadData();
});

watch(isAuthenticated, async (val) => {
  if (val && !series.value) await loadData();
});

// Recargar progreso cuando la serie se añade a la biblioteca
watch(existingSeries, async (val) => {
  if (val) await loadSeasonProgress();
});
</script>

<style scoped>
.series-detail-view {
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
.back-button:hover { background-color: var(--color-background-soft); }

.loading-state, .error-state, .empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--color-text-muted);
}
.loading-state i, .error-state i, .empty-state i { font-size: 3rem; margin-bottom: 16px; display: block; }

.series-detail-content { }

/* ── Cabecera ── */
.series-header {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 2rem;
  margin-bottom: 2rem;
}
@media (max-width: 640px) { .series-header { grid-template-columns: 1fr; } }

.series-poster-large { width: 100%; }
.poster-image-large  { width: 100%; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
.poster-placeholder {
  width: 100%; aspect-ratio: 2/3;
  background: rgba(139, 92, 246, 0.15);
  border: 2px dashed rgba(139, 92, 246, 0.3);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 3rem; color: rgba(139,92,246,0.4);
}

.series-title-large { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.75rem; }

.media-type-indicator {
  display: inline-flex; align-items: center; gap: 0.4rem;
  padding: 0.3rem 0.8rem;
  border-radius: 20px;
  font-size: 0.82rem;
  font-weight: 600;
  margin-bottom: 0.75rem;
}
.media-type-indicator.is-series {
  background: rgba(139, 92, 246, 0.15);
  color: #a78bfa;
  border: 1px solid rgba(139, 92, 246, 0.3);
}

.series-seasons-info {
  display: inline-flex; align-items: center; gap: 0.4rem;
  color: #a78bfa; font-weight: 600; font-size: 0.95rem;
  margin-bottom: 0.5rem;
}

.series-creator {
  display: flex; align-items: center; gap: 0.5rem;
  color: var(--text-color-secondary, #aaa);
  font-size: 0.9rem; margin-bottom: 0.5rem;
}

.series-metadata { display: flex; flex-wrap: wrap; gap: 0.75rem; margin: 0.75rem 0; }
.metadata-item { display: flex; align-items: center; gap: 0.35rem; font-size: 0.88rem; color: var(--text-color-secondary, #aaa); }

.series-language { display: flex; align-items: center; gap: 0.4rem; font-size: 0.88rem; color: var(--text-color-secondary,#aaa); margin-bottom: 0.5rem; }

.series-ratings { display: flex; flex-direction: column; gap: 0.3rem; margin: 0.5rem 0; }
.rating-item { display: flex; align-items: center; gap: 0.4rem; font-size: 0.9rem; }
.votes { color: var(--text-color-secondary, #aaa); font-size: 0.8rem; }

.series-imdb-id { font-size: 0.82rem; color: var(--text-color-secondary, #aaa); margin: 0.3rem 0; }

.series-genres { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem; }
.genre-tags { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.genre-tag {
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 12px;
  padding: 0.2rem 0.6rem;
  font-size: 0.8rem;
}

/* ── Secciones ── */
.section-title {
  display: flex; align-items: center; gap: 0.5rem;
  font-size: 1.1rem; font-weight: 600;
  margin-bottom: 0.75rem;
  padding-bottom: 0.4rem;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.series-plot-section, .series-crew-section, .series-awards-section,
.series-links-section, .season-tracker-section, .library-form-section {
  margin-bottom: 2rem;
}

.series-plot-content { line-height: 1.7; color: var(--text-color, #fff); }

.crew-info { display: flex; flex-direction: column; gap: 0.4rem; }
.crew-item { font-size: 0.92rem; }

.awards-content { font-size: 0.92rem; color: var(--text-color, #fff); }

.external-link {
  display: inline-flex; align-items: center; gap: 0.4rem;
  padding: 0.45rem 1rem;
  background: rgba(255,193,7,0.1);
  color: #fbbf24;
  border: 1px solid rgba(255,193,7,0.25);
  border-radius: 8px;
  text-decoration: none;
  font-size: 0.9rem;
  transition: background 0.2s;
}
.external-link:hover { background: rgba(255,193,7,0.2); }

.section-divider {
  height: 1px;
  background: rgba(255,255,255,0.06);
  margin: 2rem 0;
}

.action-button {
  padding: 0.6rem 1.2rem;
  background: var(--primary-color, #1D4E4A);
  color: #fff;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: opacity 0.2s;
}
.action-button:hover { opacity: 0.85; }
</style>
