<template>
  <MediaDetailView
    media="game"
    :store="gamesStore"
    :on-status="gamesComposable.updateGameStatuses"
    :on-rate="guardarValoracion"
  >
    <template #meta="{ item }">
      <div
        v-if="item.developer || (item.developers && item.developers.length > 0)"
        class="game-developer-large"
      >
        <i class="fas fa-code" />
        <span>{{ t('game.by', { name: joinNames(item.developers) || item.developer }) }}</span>
      </div>

      <div class="game-metadata">
        <span
          v-if="item.publisher || (item.publishers && item.publishers.length > 0)"
          class="meta-pill"
        >
          <i class="fas fa-building" />
          {{ joinNames(item.publishers) || item.publisher }}
        </span>
        <span
          v-if="item.releaseDate || item.released"
          class="meta-pill"
        >
          <i class="fas fa-calendar" />
          {{ formatDate(item.releaseDate || item.released) }}
        </span>
        <span
          v-if="item.esrbRating || item.esrb_rating"
          class="meta-pill"
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
          class="meta-pill"
        >
          <i class="fas fa-star" />
          <span>{{ t('game.ratingOutOf', { n: item.rating }) }}</span>
        </div>
        <div
          v-if="item.ratings_count"
          class="meta-pill"
        >
          <i class="fas fa-users" />
          <span>{{ t('game.ratingsCount', { n: formatNumber(item.ratings_count) }) }}</span>
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
          {{ t('game.screenshots') }}
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
          {{ t('game.description') }}
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
          {{ t('game.links') }}
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
            {{ websiteName(website) }}
          </a>
        </div>
      </div>

      <div
        v-if="item.ratings_count || item.playtime || item.metacritic_score"
        class="game-additional-info"
      >
        <h2 class="section-title">
          <i class="fas fa-info-circle" />
          {{ t('game.additional') }}
        </h2>
        <div class="additional-info-content">
          <div
            v-if="item.ratings_count"
            class="info-item"
          >
            <strong>{{ t('game.ratingsCountLabel') }}</strong> {{ formatNumber(item.ratings_count) }}
          </div>
          <div
            v-if="item.playtime"
            class="info-item"
          >
            <strong>{{ t('game.playtimeLabel') }}</strong> {{ t('game.playtimeValue', { n: item.playtime }) }}
          </div>
          <div
            v-if="item.metacritic_score || item.metacriticScore"
            class="info-item"
          >
            <strong>{{ t('game.metacriticLabel') }}</strong> {{ item.metacritic_score || item.metacriticScore }}
          </div>
        </div>
      </div>
    </template>
  </MediaDetailView>
</template>

<script setup>
import MediaDetailView from '@/views/shared/MediaDetailView.vue';
import { useGamesStore } from '@/store/games';
import { useGames } from '@/composables/useGames';
import { sanitizeRich } from '@/utils/sanitize';
import { useI18n } from '@/composables/useI18n';
import { intlLocale } from '@/config/i18n';

const { t } = useI18n();

/**
 * Ficha de juego. El esqueleto —estados, cabecera, formulario de biblioteca,
 * modal y notas— vive en MediaDetailView, configurado desde mediaRegistry;
 * aquí queda lo propio de los juegos: plataformas y géneros como etiquetas, la
 * descripción de IGDB y la rejilla de capturas, que llega por `context`.
 */
const gamesStore = useGamesStore();
const gamesComposable = useGames();

/**
 * La valoración va por `updateGameRating`, la acción propia (`update_game_rating`): desde
 * el 2026-09-09 las cinco acciones de rating funcionan, comparten criterio y están
 * cubiertas por `backend/tests/Integration/RatingActionsTest.php`. `editUserGame` sigue
 * siendo el camino del modal de edición, que guarda varios campos a la vez.
 *
 * Y no se pasa por `useItemEdit` a propósito: instancia los cinco composables, así que
 * cada ficha levantaría los cinco stores de Pinia para guardar una valoración.
 */
const guardarValoracion = (id, valor) =>
  gamesComposable.updateGameRating(id, valor);

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
  return new Date(dateStr).toLocaleDateString(intlLocale(), { year: 'numeric', month: 'long', day: 'numeric' });
};

const formatNumber = (num) => num.toLocaleString(intlLocale());

// El nombre del enlace lo pone IGDB, que manda el tipo ya legible ("Steam",
// "GOG", "Bluesky"…). Aquí solo se traduce lo que es genérico: el resto son
// marcas y en ningún idioma se dicen de otra forma.
//
// Antes había un mapa de 18 entradas indexado por `website.category`, y estaba
// doblemente roto: IGDB retiró ese campo —así que los quince enlaces caían al
// rótulo genérico y se llamaban todos igual— y además **renumeró los tipos**,
// de modo que reutilizar el mapa contra el `id` nuevo habría etiquetado Epic
// como «Google+», GOG como «Tumblr» y Discord como «LinkedIn». Un rótulo
// genérico se ignora; uno falso se cree.
const TIPOS_TRADUCIBLES = {
  1: () => t('misc.officialSite'),   // Official Website
  2: () => t('game.communityWiki'),  // Community Wiki
  14: () => t('game.subreddit')      // Subreddit
};

/** El dominio, como último recurso: distingue un enlace de otro, que es lo mínimo. */
const hostName = (url) => {
  try {
    return new URL(url).hostname.replace(/^www\./, '');
  } catch {
    return url;
  }
};

const websiteName = (website) =>
  TIPOS_TRADUCIBLES[website?.type?.id]?.() || website?.type?.type || hostName(website?.url ?? '');

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
    color: var(--color-on-overlay);
    font-size: 4rem;
  }


  .game-developer-large {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    font-size: var(--font-size-md);
    color: var(--color-text-secondary);
    margin-bottom: spacing(xs);

    i { color: var(--color-card-game-accent); }

    @include responsive-below(md) {
      font-size: var(--font-size-base);
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

  .game-categories,
  .game-platforms {
    display: flex;
    align-items: flex-start;
    gap: spacing(xs);

    > i {
      color: var(--color-card-game-accent);
      margin-top: spacing(xs);
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
    font-size: var(--font-size-base);
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
    font-size: var(--font-size-sm);

    strong {
      margin-right: spacing(xs);
      color: var(--color-text-secondary);
    }
  }

  .library-section {
    border-top: 3px solid var(--color-card-game-accent);

    h2 {
      font-size: var(--font-size-xl);
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

