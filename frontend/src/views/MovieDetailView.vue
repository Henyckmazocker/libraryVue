<template>
  <MediaDetailView
    media="movie"
    :store="moviesStore"
  >
    <template #meta="{ item }">
      <!-- Badge tipo: Serie o Película -->
      <div
        class="media-type-indicator"
        :class="item.type === 'series' ? 'is-series' : 'is-movie'"
      >
        <i :class="item.type === 'series' ? 'fas fa-tv' : 'fas fa-film'" />
        {{ item.type === 'series' ? 'Serie de Televisión' : 'Película' }}
      </div>

      <div
        v-if="item.type === 'series' && item.totalSeasons"
        class="series-seasons-info"
      >
        <i class="fas fa-layer-group" />
        <span>{{ item.totalSeasons }} temporada{{ item.totalSeasons > 1 ? 's' : '' }}</span>
      </div>

      <div
        v-if="item.director"
        class="movie-director-large"
      >
        <i :class="item.type === 'series' ? 'fas fa-tv' : 'fas fa-video'" />
        <span>{{ item.type === 'series' ? 'Creada por' : 'Dirigida por' }} {{ item.director }}</span>
      </div>

      <div class="movie-metadata">
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
          {{ item.type === 'series' ? item.runtime + ' / ep.' : item.runtime }}
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
        class="movie-language"
      >
        <i class="fas fa-language" />
        <span>{{ item.language }}</span>
      </div>

      <div class="movie-ratings">
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
        <div
          v-if="item.ratings && item.ratings.length > 0"
          class="additional-ratings"
        >
          <div
            v-for="(rating, index) in item.ratings"
            :key="index"
            class="rating-item"
          >
            <i class="fas fa-star-half-alt" />
            <strong>{{ rating.Source }}:</strong> {{ rating.Value }}
          </div>
        </div>
      </div>

      <div class="movie-imdb-id">
        <strong>IMDb ID:</strong> {{ item.imdbID }}
      </div>

      <div
        v-if="item.genres && item.genres.length > 0"
        class="movie-genres"
      >
        <i class="fas fa-tags" />
        <div class="genre-tags">
          <span
            v-for="(genre, index) in item.genres"
            :key="index"
            class="genre-tag"
          >
            {{ genre }}
          </span>
        </div>
      </div>
    </template>

    <template #extra="{ item }">
      <div
        v-if="item.plot && item.plot !== 'N/A'"
        class="movie-plot-section"
      >
        <h2 class="section-title">
          <i class="fas fa-align-left" />
          Sinopsis
        </h2>
        <p class="movie-plot-content">
          {{ item.plot }}
        </p>
      </div>

      <div
        v-if="item.actors || item.writer"
        class="movie-crew-section"
      >
        <h2 class="section-title">
          <i class="fas fa-users" />
          Equipo y Reparto
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
        class="movie-awards-section"
      >
        <h2 class="section-title">
          <i class="fas fa-trophy" />
          Premios y Nominaciones
        </h2>
        <p class="awards-content">
          {{ item.awards }}
        </p>
      </div>

      <!-- Producción y Lanzamiento (solo películas) -->
      <div
        v-if="item.type !== 'series' && (item.production || item.boxOffice || item.released || item.dvd || item.website)"
        class="movie-production-section"
      >
        <h2 class="section-title">
          <i class="fas fa-building" />
          Producción y Lanzamiento
        </h2>
        <div class="production-info">
          <div
            v-if="item.production && item.production !== 'N/A'"
            class="production-item"
          >
            <strong><i class="fas fa-industry" /> Productora:</strong> {{ item.production }}
          </div>
          <div
            v-if="item.boxOffice && item.boxOffice !== 'N/A'"
            class="production-item"
          >
            <strong><i class="fas fa-dollar-sign" /> Recaudación:</strong> {{ item.boxOffice }}
          </div>
          <div
            v-if="item.released && item.released !== 'N/A'"
            class="production-item"
          >
            <strong><i class="fas fa-calendar-day" /> Estreno:</strong> {{ item.released }}
          </div>
          <div
            v-if="item.dvd && item.dvd !== 'N/A'"
            class="production-item"
          >
            <strong><i class="fas fa-compact-disc" /> DVD:</strong> {{ item.dvd }}
          </div>
          <div
            v-if="item.website && item.website !== 'N/A'"
            class="production-item"
          >
            <strong><i class="fas fa-link" /> Sitio Web:</strong>
            <a
              :href="item.website"
              target="_blank"
              rel="noopener noreferrer"
            >{{ item.website }}</a>
          </div>
          <div
            v-if="item.type && item.type !== 'N/A'"
            class="production-item"
          >
            <strong><i class="fas fa-film" /> Tipo:</strong> {{ item.type }}
          </div>
        </div>
      </div>

      <div class="movie-links-section">
        <h2 class="section-title">
          <i class="fas fa-external-link-alt" />
          Enlaces Externos
        </h2>
        <div class="external-links">
          <a
            :href="`https://www.imdb.com/title/${item.imdbID}`"
            target="_blank"
            rel="noopener noreferrer"
            class="external-link"
          >
            <i class="fab fa-imdb" />
            Ver en IMDb
          </a>
        </div>
      </div>
    </template>
  </MediaDetailView>
</template>

<script setup>
import MediaDetailView from '@/views/shared/MediaDetailView.vue';
import { useMoviesStore } from '@/store/movies';

/**
 * Ficha de película. El esqueleto —estados, cabecera, formulario de biblioteca,
 * modal y ciclo de vida— vive en MediaDetailView, configurado desde
 * mediaRegistry; aquí queda lo propio del cine: el badge de tipo, las
 * valoraciones de IMDb y Metascore, el reparto, los premios y la producción.
 */
const moviesStore = useMoviesStore();
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/detail-view' as *;

.movie-detail-view {
  @include detail-view-page('movie');

  .movie-plot-section,
  .movie-crew-section,
  .movie-awards-section,
  .movie-production-section,
  .movie-links-section,
  .library-form-section {
    @include detail-section-card;
  }

  .page-title {
    font-size: 1.8rem;
    color: var(--color-heading);
    margin-bottom: spacing(md);
    font-weight: 600;
  }

  .movie-poster-large {
    flex-shrink: 0;
    width: 220px;
  }

  .movie-main-info {
    flex: 1;
    min-width: 0;
  }

  .movie-director-large {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    font-size: 1.2rem;
    color: var(--color-text-secondary);
    margin-bottom: spacing(md);

    i { color: var(--color-card-movie-accent); }

    @include responsive-below(md) {
      font-size: 1rem;
    }
  }

  .media-type-indicator {
    display: inline-flex;
    align-items: center;
    gap: spacing(2xs);
    padding: spacing(3xs) spacing(sm);
    border-radius: radius(sm);
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    margin-bottom: spacing(sm);

    &.is-series {
      background: rgba(139, 92, 246, 0.15);
      color: var(--color-card-movie-accent);
      border: 1px solid rgba(139, 92, 246, 0.35);
    }

    &.is-movie {
      background: rgba(29, 78, 74, 0.15);
      color: var(--color-card-game-accent);
      border: 1px solid rgba(29, 78, 74, 0.35);
    }
  }

  .series-seasons-info {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    font-size: 1rem;
    color: var(--color-card-movie-accent);
    margin-bottom: spacing(md);
    font-weight: 500;
  }

  .movie-metadata {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(sm);
    margin-bottom: spacing(sm);

    @include responsive-below(md) {
      gap: spacing(2xs);
    }
  }

  .movie-language {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    margin-bottom: spacing(sm);
    color: var(--color-text-secondary);
    font-size: 0.95rem;

    i { color: var(--color-card-movie-accent); }
  }

  .movie-ratings {
    display: flex;
    flex-direction: column;
    gap: spacing(sm);
    margin: spacing(sm) 0;
    padding: spacing(sm);
    background: var(--color-background-soft);
    border-radius: radius(md);

    @include responsive-below(md) {
      flex-direction: column;
      gap: spacing(xs);
      padding: spacing(xs);
    }
  }

  .additional-ratings {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(sm);
    padding-top: spacing(xs);
    border-top: 1px solid var(--color-border);
  }

  .rating-item {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    font-size: 0.95rem;

    i {
      font-size: 1.2rem;
      /* stylelint-disable-next-line color-no-hex -- IMDb: color de marca, drift intencional (styles.md) */
      color: #f5c518;
    }

    .votes {
      color: var(--color-text-muted);
      font-size: 0.85rem;
      margin-left: 5px;
    }
  }

  .movie-genres {
    display: flex;
    align-items: flex-start;
    gap: spacing(xs);
    margin-top: spacing(sm);

    > i {
      color: var(--color-card-movie-accent);
      margin-top: 6px;
      flex-shrink: 0;
    }
  }

  .genre-tags {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(xs);
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
    gap: spacing(sm);
  }

  .crew-item,
  .production-item {
    padding: spacing(xs) spacing(md);
    background: var(--color-background-soft);
    border-radius: radius(sm);
    line-height: 1.6;

    i {
      color: var(--color-card-movie-accent);
      margin-right: 5px;
    }
  }

  .production-item a {
    color: var(--color-primary);
    text-decoration: none;

    &:hover { text-decoration: underline; }
  }

  .awards-content {
    line-height: 1.8;
    color: var(--color-text);
    font-size: 1rem;
  }

  .poster-placeholder {
    width: 220px;
    height: 330px;
  }

  @include responsive-below(md) {
    .movie-poster-large,
    .poster-placeholder {
      width: 100%;
      max-width: 250px;
      height: auto;
      margin: 0 auto;
    }
  }
}
</style>

