<template>
  <MediaDetailView
    ref="detalle"
    media="series"
    :store="moviesStore"
    :on-status="moviesComposable.updateMovieStatuses"
    :on-rate="guardarValoracion"
    @show-seasons="abrirTemporadas"
  >
    <template #meta="{ item }">
      <div class="media-type-indicator is-series">
        <i class="fas fa-tv" />
        {{ t('series.tvShow') }}
      </div>

      <div
        v-if="item.totalSeasons"
        class="series-seasons-info"
      >
        <i class="fas fa-layer-group" />
        <span>{{ t('movie.seasons', { n: item.totalSeasons }) }}</span>
      </div>

      <div
        v-if="item.director"
        class="series-creator"
      >
        <i class="fas fa-tv" />
        <span>{{ t('series.createdBy', { name: item.director }) }}</span>
      </div>

      <div class="series-metadata">
        <span
          v-if="item.year"
          class="meta-pill"
        >
          <i class="fas fa-calendar" />
          {{ item.year }}
        </span>
        <span
          v-if="item.rated"
          class="meta-pill"
        >
          <i class="fas fa-certificate" />
          {{ item.rated }}
        </span>
        <span
          v-if="item.runtime"
          class="meta-pill"
        >
          <i class="fas fa-clock" />
          {{ t('series.runtimePerEpisode', { n: item.runtime }) }}
        </span>
        <span
          v-if="item.country"
          class="meta-pill"
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
          class="meta-pill"
        >
          <i class="fab fa-imdb" />
          <strong>{{ t('movie.imdb') }}</strong> {{ t('movie.imdbScore', { n: item.imdbRating }) }}
          <span
            v-if="item.imdbVotes"
            class="votes"
          >{{ t('movie.votes', { n: item.imdbVotes }) }}</span>
        </div>
        <div
          v-if="item.metascore && item.metascore !== 'N/A'"
          class="meta-pill"
        >
          <i class="fas fa-star" />
          <strong>{{ t('movie.metascore') }}</strong> {{ t('movie.metaScore', { n: item.metascore }) }}
        </div>
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

    <!-- El id de IMDb se copia, no se lee: baja al plegable del final. -->
    <template #technical="{ item }">
      <div class="meta-identifier">
        <strong>{{ t('movie.imdbId') }}</strong> {{ item.imdbID }}
      </div>
    </template>

    <template #extra="{ item, existing }">
      <div
        v-if="item.plot && item.plot !== 'N/A'"
        class="series-plot-section"
      >
        <h2 class="section-title">
          <i class="fas fa-align-left" /> {{ t('movie.plot') }}
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
          <i class="fas fa-users" /> {{ t('movie.crew') }}
        </h2>
        <div class="crew-info">
          <div
            v-if="item.actors && item.actors !== 'N/A'"
            class="crew-item"
          >
            <strong><i class="fas fa-user-tie" /> {{ t('movie.actors') }}</strong> {{ item.actors }}
          </div>
          <div
            v-if="item.writer && item.writer !== 'N/A'"
            class="crew-item"
          >
            <strong><i class="fas fa-pen" /> {{ t('movie.writer') }}</strong> {{ item.writer }}
          </div>
        </div>
      </div>

      <div
        v-if="item.awards && item.awards !== 'N/A'"
        class="series-awards-section"
      >
        <h2 class="section-title">
          <i class="fas fa-trophy" /> {{ t('series.awards') }}
        </h2>
        <p class="awards-content">
          {{ item.awards }}
        </p>
      </div>

      <div class="series-links-section">
        <h2 class="section-title">
          <i class="fas fa-external-link-alt" /> {{ t('series.viewOn') }}
        </h2>
        <a
          :href="`https://www.imdb.com/title/${item.imdbID}`"
          target="_blank"
          rel="noopener noreferrer"
          class="external-link"
        >
          <i class="fab fa-imdb" /> {{ t('brand.imdb') }}
        </a>
      </div>

      <!-- Lo irreductible de las series: el seguimiento por temporadas. 514 líneas
           que ocupaban el final de la ficha para algo que se toca de vez en cuando,
           y que ahora se abre desde el botón del panel.

           El modal va DENTRO del slot y no como hermano de `MediaDetailView` porque
           esta vista tiene raíz única: un segundo nodo raíz —aunque sea un
           `<Teleport>`— la convierte en fragment, y el `<Transition mode="out-in">`
           de `App.vue` no puede animar un fragment; su transición de salida no
           termina nunca y la app se queda en blanco. Como el modal se teletransporta
           al `body`, dónde se declare dentro del árbol da igual. -->
      <BaseModal
        v-if="temporadas.isVisible && existing"
        v-model="temporadas.isVisible"
        :title="t('seasons.title')"
        size="lg"
        accent="var(--color-card-movie-accent)"
      >
        <SeriesSeasonTracker
          :imdb-id="item.imdbID"
          :total-seasons="item.totalSeasons"
          :progress="seasonProgress"
          :is-saving="isSavingSeason"
          @season-updated="(payload) => handleSeasonUpdated(item, payload)"
        />
      </BaseModal>

      <div class="section-divider" />
    </template>
  </MediaDetailView>
</template>

<script setup>
import { ref, watch } from 'vue';
import MediaDetailView from '@/views/shared/MediaDetailView.vue';
import SeriesSeasonTracker from '@/components/Movies/SeriesSeasonTracker.vue';
import BaseModal from '@/components/common/BaseModal.vue';
import { useMoviesStore } from '@/store/movies';
import { useMovies } from '@/composables/useMovies';
import Logger from '@/utils/logger';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

/**
 * Ficha de serie. Comparte store con las películas —son la misma entidad en el
 * backend— pero tiene entrada propia en mediaRegistry: su ruta, su texto y su
 * filtro de estados ('dropped' en vez de 'abandoned'). Lo único que no puede
 * salir del genérico es el seguimiento por temporadas, que vive en
 * `useMovies` (trackSeriesSeason / getSeriesProgress) y va por el slot #extra.
 */
const moviesStore = useMoviesStore();
const moviesComposable = useMovies();

/**
 * La valoración se guarda por donde la guarda el modal de edición, que acaba en este
 * mismo `editUserMovie` (`useItemEdit.js:26` solo despacha por medio). NO se
 * usa `updateMovieRating`: esas cinco acciones no las llama nadie y dos están rotas.
 *
 * Y no se pasa por `useItemEdit` a propósito: instancia los cinco composables, así que
 * cada ficha levantaría los cinco stores de Pinia para guardar una valoración.
 */
const guardarValoracion = (id, valor) =>
  moviesComposable.editUserMovie(id, null, { personalRating: valor });
const detalle = ref(null);

const temporadas = ref({ isVisible: false });

const abrirTemporadas = () => {
  Logger.debug('[SeriesDetailView] Showing season tracker');
  temporadas.value = { isVisible: true };
};

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
  .library-section {
    @include detail-section-card;
  }

  .series-poster-large {
    flex-shrink: 0;
    width: 220px;
  }


  .media-type-indicator {
    display: inline-flex;
    align-items: center;
    gap: spacing(2xs);
    padding: spacing(3xs) spacing(sm);
    border-radius: radius(full);
    font-size: var(--font-size-sm);
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
    font-size: var(--font-size-base);
    margin-bottom: spacing(xs);
  }

  .series-creator {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    color: var(--color-text-secondary);
    font-size: var(--font-size-sm);
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
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
    margin-bottom: spacing(xs);
  }

  .series-ratings {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
    margin: spacing(xs) 0;
  }

  // Como en la ficha de película: la nota es una pastilla más, con el oro de IMDb
  // ganando al acento del medio.
  .series-ratings .meta-pill {
    .votes {
      color: var(--color-text-secondary);
      font-size: var(--font-size-xs);
    }

    /* stylelint-disable-next-line color-no-hex -- IMDb: color de marca, drift intencional (styles.md) */
    i { color: #f5c518; }
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
    font-size: var(--font-size-sm);
  }

  .awards-content {
    font-size: var(--font-size-sm);
    color: var(--color-text);
  }

  .poster-placeholder {
    background: rgba(139, 92, 246, 0.15);
    border: 2px dashed rgba(139, 92, 246, 0.3);
    color: rgba(139, 92, 246, 0.4);
    font-size: var(--font-size-4xl);
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

