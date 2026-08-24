<template>
  <MediaDetailView
    media="video"
    :store="videosStore"
  >
    <!-- Botón de reproducción superpuesto a la miniatura. -->
    <template #cover-overlay="{ item }">
      <a
        v-if="youtubeIdOf(item)"
        :href="`https://www.youtube.com/watch?v=${youtubeIdOf(item)}`"
        target="_blank"
        rel="noopener noreferrer"
        class="youtube-play-btn"
        title="Ver en YouTube"
        aria-label="Ver este vídeo en YouTube"
      >
        <i
          class="fab fa-youtube"
          aria-hidden="true"
        />
      </a>
    </template>

    <template #meta="{ item }">
      <div
        v-if="item.channel_name || item.channelName"
        class="video-channel-large"
      >
        <i class="fas fa-tv" />
        <span>{{ item.channel_name || item.channelName }}</span>
      </div>

      <div class="video-metadata">
        <span
          v-if="item.duration"
          class="metadata-item"
        >
          <i class="fas fa-clock" />
          {{ item.duration }}
        </span>
        <span
          v-if="publishedYear(item)"
          class="metadata-item"
        >
          <i class="fas fa-calendar" />
          {{ publishedYear(item) }}
        </span>
        <span
          v-if="item.view_count || item.viewCount"
          class="metadata-item"
        >
          <i class="fas fa-eye" />
          {{ formatCount(item.view_count || item.viewCount) }} vistas
        </span>
        <span
          v-if="item.like_count || item.likeCount"
          class="metadata-item"
        >
          <i class="fas fa-thumbs-up" />
          {{ formatCount(item.like_count || item.likeCount) }}
        </span>
      </div>

      <div
        v-if="categoriesArray(item).length > 0"
        class="video-categories"
      >
        <i class="fas fa-tags" />
        <div class="category-tags">
          <span
            v-for="cat in categoriesArray(item)"
            :key="cat"
            class="category-tag"
          >
            {{ cat }}
          </span>
        </div>
      </div>

      <div
        v-if="youtubeIdOf(item)"
        class="video-links"
      >
        <a
          :href="`https://www.youtube.com/watch?v=${youtubeIdOf(item)}`"
          target="_blank"
          rel="noopener noreferrer"
          class="youtube-link"
        >
          <i class="fab fa-youtube" />
          Ver en YouTube
        </a>
      </div>
    </template>

    <template #extra="{ item }">
      <div
        v-if="item.description"
        class="video-description-section"
      >
        <h2 class="section-title">
          <i class="fas fa-align-left" />
          Descripción
        </h2>
        <p class="video-description">
          {{ truncateDescription(item.description, showFullDesc ? 9999 : 300) }}
        </p>
        <button
          v-if="item.description.length > 300"
          class="toggle-desc-btn"
          @click="showFullDesc = !showFullDesc"
        >
          {{ showFullDesc ? 'Mostrar menos' : 'Mostrar más' }}
        </button>
      </div>
    </template>
  </MediaDetailView>
</template>

<script setup>
import { ref } from 'vue';
import MediaDetailView from '@/views/shared/MediaDetailView.vue';
import { useVideosStore } from '@/store/videos';

/**
 * Ficha de vídeo. El esqueleto —estados, cabecera, formulario de biblioteca,
 * modal y notas— vive en MediaDetailView, configurado desde mediaRegistry;
 * aquí queda solo lo que es de los vídeos: la columna de datos, la descripción
 * plegable y el botón de reproducción sobre la miniatura.
 */
const videosStore = useVideosStore();
const showFullDesc = ref(false);

const youtubeIdOf = (video) => video?.youtube_id || video?.youtubeId;

const publishedYear = (video) => {
  const dateStr = video?.published_at || video?.publishedAt;
  if (!dateStr) return null;
  return new Date(dateStr).getFullYear();
};

const categoriesArray = (video) => {
  const cats = video?.categories;
  if (!cats) return [];
  if (Array.isArray(cats)) return cats;
  if (typeof cats === 'string') {
    try { return JSON.parse(cats); } catch { return []; }
  }
  return [];
};

function formatCount(num) {
  if (!num) return '0';
  if (num >= 1_000_000) return (num / 1_000_000).toFixed(1) + 'M';
  if (num >= 1_000) return (num / 1_000).toFixed(1) + 'K';
  return num.toString();
}

function truncateDescription(text, maxLen) {
  if (!text) return '';
  if (text.length <= maxLen) return text;
  return text.slice(0, maxLen) + '...';
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/detail-view' as *;

.video-detail-view {
  @include detail-view-page('video');

  .video-description-section,
  .library-section,
  .notes-section {
    @include detail-section-card;
  }

  // ── Cover wrapper ────────────────────────────────────────────────────────
  .video-cover-large {
    position: relative; // CRITICAL: contiene el youtube-play-btn inset:0
    flex-shrink: 0;
    width: 320px;

    @include responsive-below(md) {
      width: 100%;
      max-width: 480px;
      margin: 0 auto;
    }
  }

  .cover-placeholder {
    aspect-ratio: 16 / 9;
    background: linear-gradient(135deg, var(--color-card-video-bg) 0%, var(--color-card-video-border) 100%);
    border: none;

    i {
      font-size: 4rem;
      color: var(--color-card-video-accent);
    }
  }

  // Botón play superpuesto sobre la miniatura
  .youtube-play-btn {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: rgba(255, 255, 255, 0.9);
    background: rgba(192, 57, 43, 0.3);
    border-radius: radius(md);
    opacity: 0;
    transition: opacity transition(fast);
    text-decoration: none;

    &:hover { opacity: 1; }
  }

  // ── Info principal ────────────────────────────────────────────────────────
  .video-main-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: spacing(sm);
  }

  .video-channel-large {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    font-size: 1.1rem;
    color: var(--color-text-secondary);

    i { color: var(--color-card-video-accent); }

    @include responsive-below(md) { font-size: 1rem; }
  }

  .video-metadata {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(sm);
    margin-bottom: spacing(xs);

    @include responsive-below(md) { gap: spacing(2xs); }
  }

  .video-categories {
    display: flex;
    align-items: flex-start;
    gap: spacing(xs);

    i { color: var(--color-card-video-accent); margin-top: 4px; }
  }

  .category-tags {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(2xs);
  }

  .video-links {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(sm);
    margin-top: spacing(xs);
  }

  .youtube-link {
    display: inline-flex;
    align-items: center;
    gap: spacing(xs);
    color: var(--color-card-video-accent);
    text-decoration: none;
    font-weight: 600;
    padding: spacing(xs) spacing(sm);
    border: 1px solid var(--color-card-video-accent);
    border-radius: radius(md);
    transition: background transition(fast);

    &:hover { background: rgba(192, 57, 43, 0.15); }
  }

  // ── Descripción ───────────────────────────────────────────────────────────
  .video-description {
    color: var(--color-text-secondary);
    line-height: 1.7;
    white-space: pre-wrap;
    font-size: 0.9rem;
  }

  .toggle-desc-btn {
    background: none;
    border: none;
    color: var(--color-card-video-accent);
    cursor: pointer;
    font-size: 0.85rem;
    padding: spacing(xs) 0;
    font-weight: 600;

    &:hover { text-decoration: underline; }
  }
}
</style>
