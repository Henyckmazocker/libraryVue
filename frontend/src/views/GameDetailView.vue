<template>
  <MediaDetailView
    media="game"
    :store="gamesStore"
  >
    <template #meta="{ item }">
      <div
        v-if="item.developer || (item.developers && item.developers.length > 0)"
        class="game-developer-large"
      >
        <i class="fas fa-code" />
        <span>por {{ joinNames(item.developers) || item.developer }}</span>
      </div>

      <div class="game-metadata">
        <span
          v-if="item.publisher || (item.publishers && item.publishers.length > 0)"
          class="metadata-item"
        >
          <i class="fas fa-building" />
          {{ joinNames(item.publishers) || item.publisher }}
        </span>
        <span
          v-if="item.releaseDate || item.released"
          class="metadata-item"
        >
          <i class="fas fa-calendar" />
          {{ formatDate(item.releaseDate || item.released) }}
        </span>
        <span
          v-if="item.esrbRating || item.esrb_rating"
          class="metadata-item"
        >
          <i class="fas fa-certificate" />
          {{ typeof item.esrb_rating === 'object' ? item.esrb_rating.name : (item.esrbRating || item.esrb_rating) }}
        </span>
      </div>

      <div
        v-if="item.rating || item.ratings_count"
        class="game-ratings"
      >
        <div
          v-if="item.rating"
          class="rating-display"
        >
          <i class="fas fa-star" />
          <span>{{ item.rating }} / 5</span>
        </div>
        <div
          v-if="item.ratings_count"
          class="rating-count"
        >
          <i class="fas fa-users" />
          <span>{{ formatNumber(item.ratings_count) }} valoraciones</span>
        </div>
      </div>

      <div
        v-if="genresArray(item).length > 0"
        class="game-categories"
      >
        <i class="fas fa-tags" />
        <div class="category-tags">
          <span
            v-for="genre in genresArray(item)"
            :key="genre"
            class="category-tag"
          >
            {{ genre }}
          </span>
        </div>
      </div>

      <div
        v-if="platformsArray(item).length > 0"
        class="game-platforms"
      >
        <i class="fas fa-gamepad" />
        <div class="platform-tags">
          <span
            v-for="platform in platformsArray(item)"
            :key="platform"
            class="platform-tag"
          >
            <i :class="platformIcon(platform)" />
            {{ platform }}
          </span>
        </div>
      </div>
    </template>

    <template #extra="{ item, context }">
      <!-- Capturas: llegan del enriquecimiento de IGDB, no del ítem. -->
      <div
        v-if="(context.screenshots || []).length > 0"
        class="screenshots-section"
      >
        <h2 class="section-title">
          <i class="fas fa-images" />
          Capturas de Pantalla
        </h2>
        <div class="screenshots-grid">
          <img
            v-for="(screenshot, index) in context.screenshots.slice(0, 6)"
            :key="index"
            :src="screenshot.image"
            :alt="`Screenshot ${index + 1}`"
            class="screenshot-thumb"
            loading="lazy"
            decoding="async"
          >
        </div>
      </div>

      <div
        v-if="item.description || item.description_raw"
        class="game-description-section"
      >
        <h2 class="section-title">
          <i class="fas fa-align-left" />
          Descripción
        </h2>
        <!-- eslint-disable vue/no-v-html -- saneado con utils/sanitize.js -->
        <div
          class="game-description-content"
          v-html="sanitizeRich(item.description || item.description_raw)"
        />
        <!-- eslint-enable vue/no-v-html -->
      </div>

      <div
        v-if="item.websites && item.websites.length > 0"
        class="game-links-section"
      >
        <h2 class="section-title">
          <i class="fas fa-external-link-alt" />
          Enlaces Externos
        </h2>
        <div class="external-links">
          <a
            v-for="website in item.websites"
            :key="website.url"
            :href="website.url"
            target="_blank"
            rel="noopener noreferrer"
            class="external-link"
          >
            <i class="fas fa-link" />
            {{ websiteName(website.category) }}
          </a>
        </div>
      </div>

      <div
        v-if="item.ratings_count || item.playtime || item.metacritic_score"
        class="game-additional-info"
      >
        <h2 class="section-title">
          <i class="fas fa-info-circle" />
          Información Adicional
        </h2>
        <div class="additional-info-content">
          <div
            v-if="item.ratings_count"
            class="info-item"
          >
            <strong>Número de valoraciones:</strong> {{ formatNumber(item.ratings_count) }}
          </div>
          <div
            v-if="item.playtime"
            class="info-item"
          >
            <strong>Tiempo de juego promedio:</strong> {{ item.playtime }} horas
          </div>
          <div
            v-if="item.metacritic_score || item.metacriticScore"
            class="info-item"
          >
            <strong>Puntuación Metacritic:</strong> {{ item.metacritic_score || item.metacriticScore }}
          </div>
        </div>
      </div>
    </template>
  </MediaDetailView>
</template>

<script setup>
import MediaDetailView from '@/views/shared/MediaDetailView.vue';
import { useGamesStore } from '@/store/games';
import { sanitizeRich } from '@/utils/sanitize';

/**
 * Ficha de juego. El esqueleto —estados, cabecera, formulario de biblioteca,
 * modal y notas— vive en MediaDetailView, configurado desde mediaRegistry;
 * aquí queda lo propio de los juegos: plataformas y géneros como etiquetas, la
 * descripción de IGDB y la rejilla de capturas, que llega por `context`.
 */
const gamesStore = useGamesStore();

const joinNames = (value) => (Array.isArray(value)
  ? value.map(v => (typeof v === 'string' ? v : v.name)).filter(Boolean).join(', ')
  : '');

const asText = (value) => {
  if (typeof value === 'string') return value;
  if (Array.isArray(value)) return value.map(v => v.name || v).join(', ');
  return '';
};

const genresArray = (game) => {
  const text = asText(game?.genres);
  return text ? text.split(', ').filter(Boolean) : [];
};

const platformsArray = (game) => {
  const platforms = game?.platforms;
  const text = typeof platforms === 'string'
    ? platforms
    : (Array.isArray(platforms)
      ? platforms.map(p => (typeof p === 'string' ? p : p.platform?.name || p.name)).join(', ')
      : '');
  return text ? text.split(', ').filter(Boolean) : [];
};

const platformIcon = (platform) => {
  const name = platform.toLowerCase();
  if (name.includes('playstation') || name.includes('ps')) return 'fab fa-playstation';
  if (name.includes('xbox')) return 'fab fa-xbox';
  if (name.includes('nintendo') || name.includes('switch')) return 'fas fa-gamepad';
  if (name.includes('pc') || name.includes('windows')) return 'fab fa-windows';
  if (name.includes('linux')) return 'fab fa-linux';
  if (name.includes('mac')) return 'fab fa-apple';
  if (name.includes('android') || name.includes('ios')) return 'fas fa-mobile-alt';
  return 'fas fa-gamepad';
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
};

const formatNumber = (num) => num.toLocaleString('es-ES');

const websiteName = (category) => ({
  1: 'Sitio Oficial', 2: 'Wikia', 3: 'Wikipedia', 4: 'Facebook', 5: 'Twitter',
  6: 'Twitch', 8: 'Instagram', 9: 'YouTube', 10: 'iPhone', 11: 'iPad',
  12: 'Android', 13: 'Steam', 14: 'Reddit', 15: 'Discord', 16: 'Google+',
  17: 'Tumblr', 18: 'LinkedIn'
}[category] || 'Ver enlace');

</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/detail-view' as *;

.game-detail-view {
  @include detail-view-page('game');

  .screenshots-section,
  .game-description-section,
  .game-links-section,
  .game-additional-info,
  .library-section {
    @include detail-section-card;
  }

  .game-cover-large {
    flex-shrink: 0;
    width: 280px;
  }

  .cover-placeholder {
    aspect-ratio: 3 / 4;
    background: linear-gradient(135deg, var(--color-card-movie-accent) 0%, var(--color-card-movie-accent) 100%);
    border: none;
    color: white;
    font-size: 4rem;
  }

  .game-main-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: spacing(sm);
  }

  .game-developer-large {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    font-size: 1.1rem;
    color: var(--color-text-secondary);
    margin-bottom: spacing(xs);

    i { color: var(--color-card-game-accent); }

    @include responsive-below(md) {
      font-size: 1rem;
    }
  }

  .game-metadata {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(xs);
    margin-bottom: spacing(sm);

    @include responsive-below(md) {
      gap: spacing(2xs);
    }
  }

  .game-ratings {
    display: flex;
    gap: spacing(sm);
    flex-wrap: wrap;
    margin-bottom: spacing(xs);
  }

  .rating-display,
  .rating-count {
    display: flex;
    align-items: center;
    gap: spacing(2xs);
    padding: spacing(xs) spacing(md);
    background: var(--color-background-soft);
    border-radius: radius(sm);
    font-size: 0.95rem;

    i { color: var(--color-card-game-accent); }
  }

  .game-categories,
  .game-platforms {
    display: flex;
    align-items: flex-start;
    gap: spacing(xs);

    > i {
      color: var(--color-card-game-accent);
      margin-top: 6px;
      flex-shrink: 0;
    }
  }

  .category-tags,
  .platform-tags {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(xs);
  }

  .screenshots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: spacing(sm);

    @include responsive-below(md) {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  .screenshot-thumb {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: radius(md);
    cursor: pointer;
    transition: transform transition(fast);
    box-shadow: shadow(sm);

    &:hover { transform: scale(1.05); }

    @include responsive-below(md) {
      height: 100px;
    }
  }

  .game-description-content {
    line-height: 1.8;
    color: var(--color-text);
    font-size: 1rem;
    text-align: justify;
  }

  .additional-info-content {
    display: flex;
    flex-direction: column;
    gap: spacing(xs);
  }

  .info-item {
    padding: spacing(xs) spacing(sm);
    background: var(--color-background-soft);
    border-radius: radius(sm);
    font-size: 0.9rem;

    strong {
      margin-right: spacing(xs);
      color: var(--color-text-secondary);
    }
  }

  .library-section {
    border-top: 3px solid var(--color-card-game-accent);

    h2 {
      font-size: 1.5rem;
      color: var(--color-heading);
      margin-bottom: spacing(lg);
    }
  }

  @include responsive-below(md) {
    .game-cover-large {
      width: 100%;
      max-width: 250px;
      margin: 0 auto;
    }
  }
}
</style>

