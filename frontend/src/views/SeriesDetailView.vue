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
import { ref, onMounted, computed, watch, toRaw } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import LibraryMovieItem from '@/components/Movies/LibraryMovieItem.vue';
import SeriesSeasonTracker from '@/components/Movies/SeriesSeasonTracker.vue';
import EditItemModal from '@/components/EditItemModal.vue';
import { useMovies } from '@/composables/useMovies';
import { useAuth } from '@/composables/useAuth';
import { useUIStore } from '@/store/ui';
import Logger from '@/utils/logger';

const route  = useRoute();
const router = useRouter();
const { isAuthenticated } = useAuth();
const moviesComposable    = useMovies();
const uiStore             = useUIStore();

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

const handleEditItem = async () => {
  // Ensure movies are loaded in the store before opening the modal.
  if (moviesComposable.movies.value.length === 0) {
    await moviesComposable.fetchMovies();
  }

  const storeSeries = existingSeries.value ? toRaw(existingSeries.value) : null;

  const itemForModal = storeSeries
    ? {
        ...series.value,
        user_rating: storeSeries.user_rating ?? null,
        userStatuses: Array.isArray(storeSeries.userStatuses) ? [...storeSeries.userStatuses] : [],
        ownershipFormat: storeSeries.ownershipFormat ?? storeSeries.ownership_format ?? null,
        ownership_format: storeSeries.ownership_format ?? storeSeries.ownershipFormat ?? null,
        ownership_format_id: storeSeries.ownershipFormat?.id ?? storeSeries.ownership_format?.id ?? null,
        tags: storeSeries.tags ?? null,
        isbn: storeSeries.isbn ?? series.value?.isbn,
        imdbID: storeSeries.imdbID ?? series.value?.imdbID,
      }
    : series.value;

  editModal.value = { isVisible: true, item: itemForModal, itemType: 'movie' };
};

const closeEditModal = () => {
  editModal.value = { isVisible: false, item: null, itemType: 'movie' };
};

const handleModalSaved = async (updatedItem) => {
  closeEditModal();
  if (series.value && updatedItem) {
    series.value = {
      ...series.value,
      ...updatedItem,
      user_rating: updatedItem.user_rating,
      userStatuses: updatedItem.userStatuses || series.value.userStatuses
    };
    // Actualizar en el store local también
    const seriesInStore = moviesComposable.findMovieByTMDBId(
      series.value.imdbID || series.value.isbn
    );
    if (seriesInStore) Object.assign(seriesInStore, updatedItem);
  }
  uiStore.showSuccess('Serie actualizada correctamente');
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
    fetchSeriesDetails(route.params.imdbId)
      .then(() => _mergeExistingSeriesData())
      .catch(() => {});
  } else {
    await fetchSeriesDetails(route.params.imdbId);
  }

  // Merge datos de biblioteca
  _mergeExistingSeriesData();

  await loadSeasonProgress();
};

const _mergeExistingSeriesData = () => {
  if (!existingSeries.value || !series.value) return;
  series.value = {
    ...series.value,
    user_rating:  existingSeries.value.user_rating,
    userStatuses: existingSeries.value.userStatuses || [],
    ownershipFormat: existingSeries.value.ownershipFormat ?? existingSeries.value.ownership_format ?? null,
    ownership_format: existingSeries.value.ownership_format ?? existingSeries.value.ownershipFormat ?? null,
    ownership_format_id: existingSeries.value.ownership_format_id ?? existingSeries.value.ownershipFormat?.id ?? null,
    tags: existingSeries.value.tags ?? null,
  };
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

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/detail-view' as *;

// Series comparte identidad visual con Movies (variant 'movie')
.series-detail-view {
  @include detail-view-page('movie', 'series');

  .series-plot-section,
  .series-crew-section,
  .series-awards-section,
  .series-links-section,
  .season-tracker-section,
  .library-form-section {
    @include detail-section-card;
  }

  .series-poster-large {
    flex-shrink: 0;
    width: 220px;
  }

  .series-main-info {
    flex: 1;
    min-width: 0;
  }

  .media-type-indicator {
    display: inline-flex;
    align-items: center;
    gap: spacing(2xs);
    padding: spacing(3xs) spacing(sm);
    border-radius: radius(full);
    font-size: 0.82rem;
    font-weight: 600;
    margin-bottom: spacing(sm);

    &.is-series {
      background: rgba(139, 92, 246, 0.15);
      color: #a78bfa;
      border: 1px solid rgba(139, 92, 246, 0.3);
    }
  }

  .series-seasons-info {
    display: inline-flex;
    align-items: center;
    gap: spacing(2xs);
    color: #a78bfa;
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: spacing(xs);
  }

  .series-creator {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    color: var(--color-text-secondary);
    font-size: 0.9rem;
    margin-bottom: spacing(xs);

    i { color: var(--color-card-movie-accent); }
  }

  .series-metadata {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(sm);
    margin: spacing(sm) 0;
  }

  .series-language {
    display: flex;
    align-items: center;
    gap: spacing(2xs);
    font-size: 0.88rem;
    color: var(--color-text-secondary);
    margin-bottom: spacing(xs);
  }

  .series-ratings {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
    margin: spacing(xs) 0;
  }

  .rating-item {
    display: flex;
    align-items: center;
    gap: spacing(2xs);
    font-size: 0.9rem;

    i { color: #f5c518; }

    .votes {
      color: var(--color-text-secondary);
      font-size: 0.8rem;
    }
  }

  .series-imdb-id {
    font-size: 0.82rem;
    color: var(--color-text-secondary);
  }

  .series-genres {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    flex-wrap: wrap;
    margin-top: spacing(xs);

    > i {
      color: var(--color-card-movie-accent);
      flex-shrink: 0;
    }
  }

  .genre-tags {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(2xs);
  }

  .series-plot-content {
    line-height: 1.7;
    color: var(--color-text);
  }

  .crew-info {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
  }

  .crew-item {
    font-size: 0.92rem;
  }

  .awards-content {
    font-size: 0.92rem;
    color: var(--color-text);
  }

  .poster-placeholder {
    background: rgba(139, 92, 246, 0.15);
    border: 2px dashed rgba(139, 92, 246, 0.3);
    color: rgba(139, 92, 246, 0.4);
    font-size: 3rem;
  }

  @include responsive-below(md) {
    .series-poster-large,
    .poster-placeholder {
      width: 100%;
      max-width: 250px;
      margin: 0 auto;
    }
  }
}
</style>

