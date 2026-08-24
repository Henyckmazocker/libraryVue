<template>
  <MediaDetailView
    ref="detalle"
    media="series"
    :store="moviesStore"
  >
    <template #meta="{ item }">
      <div class="media-type-indicator is-series">
        <i class="fas fa-tv" />
        Serie de Televisión
      </div>

      <div
        v-if="item.totalSeasons"
        class="series-seasons-info"
      >
        <i class="fas fa-layer-group" />
        <span>{{ item.totalSeasons }} temporada{{ item.totalSeasons > 1 ? 's' : '' }}</span>
      </div>

      <div
        v-if="item.director"
        class="series-creator"
      >
        <i class="fas fa-tv" />
        <span>Creada por {{ item.director }}</span>
      </div>

      <div class="series-metadata">
        <span
          v-if="item.year"
          class="metadata-item"
        >
          <i class="fas fa-calendar" />
          {{ item.year }}
        </span>
        <span
          v-if="item.rated"
          class="metadata-item"
        >
          <i class="fas fa-certificate" />
          {{ item.rated }}
        </span>
        <span
          v-if="item.runtime"
          class="metadata-item"
        >
          <i class="fas fa-clock" />
          {{ item.runtime }} / ep.
        </span>
        <span
          v-if="item.country"
          class="metadata-item"
        >
          <i class="fas fa-globe" />
          {{ item.country }}
        </span>
      </div>

      <div
        v-if="item.language"
        class="series-language"
      >
        <i class="fas fa-language" />
        <span>{{ item.language }}</span>
      </div>

      <div class="series-ratings">
        <div
          v-if="item.imdbRating && item.imdbRating !== 'N/A'"
          class="rating-item"
        >
          <i class="fab fa-imdb" />
          <strong>IMDb:</strong> {{ item.imdbRating }}/10
          <span
            v-if="item.imdbVotes"
            class="votes"
          >({{ item.imdbVotes }} votos)</span>
        </div>
        <div
          v-if="item.metascore && item.metascore !== 'N/A'"
          class="rating-item"
        >
          <i class="fas fa-star" />
          <strong>Metascore:</strong> {{ item.metascore }}/100
        </div>
      </div>

      <div class="series-imdb-id">
        <strong>IMDb ID:</strong> {{ item.imdbID }}
      </div>

      <div
        v-if="item.genres && item.genres.length > 0"
        class="series-genres"
      >
        <i class="fas fa-tags" />
        <div class="genre-tags">
          <span
            v-for="(genre, i) in item.genres"
            :key="i"
            class="genre-tag"
          >{{ genre }}</span>
        </div>
      </div>
    </template>

    <template #extra="{ item, existing }">
      <div
        v-if="item.plot && item.plot !== 'N/A'"
        class="series-plot-section"
      >
        <h2 class="section-title">
          <i class="fas fa-align-left" /> Sinopsis
        </h2>
        <p class="series-plot-content">
          {{ item.plot }}
        </p>
      </div>

      <div
        v-if="item.actors || item.writer"
        class="series-crew-section"
      >
        <h2 class="section-title">
          <i class="fas fa-users" /> Equipo y Reparto
        </h2>
        <div class="crew-info">
          <div
            v-if="item.actors && item.actors !== 'N/A'"
            class="crew-item"
          >
            <strong><i class="fas fa-user-tie" /> Actores:</strong> {{ item.actors }}
          </div>
          <div
            v-if="item.writer && item.writer !== 'N/A'"
            class="crew-item"
          >
            <strong><i class="fas fa-pen" /> Guion:</strong> {{ item.writer }}
          </div>
        </div>
      </div>

      <div
        v-if="item.awards && item.awards !== 'N/A'"
        class="series-awards-section"
      >
        <h2 class="section-title">
          <i class="fas fa-trophy" /> Premios
        </h2>
        <p class="awards-content">
          {{ item.awards }}
        </p>
      </div>

      <div class="series-links-section">
        <h2 class="section-title">
          <i class="fas fa-external-link-alt" /> Ver en
        </h2>
        <a
          :href="`https://www.imdb.com/title/${item.imdbID}`"
          target="_blank"
          rel="noopener noreferrer"
          class="external-link"
        >
          <i class="fab fa-imdb" /> IMDb
        </a>
      </div>

      <!-- Lo irreductible de las series: el seguimiento por temporadas. -->
      <div
        v-if="item.totalSeasons && existing"
        class="season-tracker-section"
      >
        <SeriesSeasonTracker
          :imdb-id="item.imdbID"
          :total-seasons="item.totalSeasons"
          :progress="seasonProgress"
          :is-saving="isSavingSeason"
          @season-updated="(payload) => handleSeasonUpdated(item, payload)"
        />
      </div>

      <div class="section-divider" />
    </template>
  </MediaDetailView>
</template>

<script setup>
import { ref, watch } from 'vue';
import MediaDetailView from '@/views/shared/MediaDetailView.vue';
import SeriesSeasonTracker from '@/components/Movies/SeriesSeasonTracker.vue';
import { useMoviesStore } from '@/store/movies';
import { useMovies } from '@/composables/useMovies';
import Logger from '@/utils/logger';

/**
 * Ficha de serie. Comparte store con las películas —son la misma entidad en el
 * backend— pero tiene entrada propia en mediaRegistry: su ruta, su texto y su
 * filtro de estados ('dropped' en vez de 'abandoned'). Lo único que no puede
 * salir del genérico es el seguimiento por temporadas, que vive en
 * `useMovies` (trackSeriesSeason / getSeriesProgress) y va por el slot #extra.
 */
const moviesStore = useMoviesStore();
const moviesComposable = useMovies();
const detalle = ref(null);

const seasonProgress = ref({});
const isSavingSeason = ref(false);

const loadSeasonProgress = async (imdbId) => {
  if (!imdbId) return;
  try {
    const result = await moviesComposable.getSeriesProgress(imdbId);
    seasonProgress.value = result?.data || result || {};
  } catch (e) {
    Logger.warn('[SeriesDetailView] Could not load season progress:', e);
  }
};

const handleSeasonUpdated = async (series, { seasonNumber, status, dateViewed, personalRating, notes }) => {
  if (!series?.imdbID) return;
  isSavingSeason.value = true;
  try {
    await moviesComposable.trackSeriesSeason(series.imdbID, seasonNumber, {
      status, dateViewed, personalRating, notes,
    });
  } catch (e) {
    Logger.error('[SeriesDetailView] Error saving season:', e);
  } finally {
    isSavingSeason.value = false;
  }
};

// El progreso se carga cuando la serie está cargada y, de nuevo, cuando entra
// en la biblioteca: antes de eso el backend no tiene nada que devolver.
watch(() => detalle.value?.existing, (existe) => {
  if (existe) loadSeasonProgress(detalle.value?.item?.imdbID);
});

watch(() => detalle.value?.item?.imdbID, (imdbId) => {
  if (imdbId && detalle.value?.existing) loadSeasonProgress(imdbId);
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
      color: var(--color-card-movie-accent);
      border: 1px solid rgba(139, 92, 246, 0.3);
    }
  }

  .series-seasons-info {
    display: inline-flex;
    align-items: center;
    gap: spacing(2xs);
    color: var(--color-card-movie-accent);
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

    /* stylelint-disable-next-line color-no-hex -- IMDb: color de marca, drift intencional (styles.md) */
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

