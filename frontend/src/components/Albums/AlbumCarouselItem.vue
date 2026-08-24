<template>
  <button
    type="button"
    class="album-carousel-item"
    @click="handleClick"
  >
    <div class="album-cover-wrapper">
      <img
        v-if="album.cover_url || album.coverUrl"
        :src="album.cover_url || album.coverUrl"
        :alt="album.title || album.name"
        class="album-cover"
        width="160"
        height="160"
        loading="lazy"
        decoding="async"
      >
      <div
        v-else
        class="album-cover-placeholder"
      >
        <i class="fas fa-music" />
      </div>

      <!-- Year badge -->
      <div
        v-if="releaseYear"
        class="year-badge"
      >
        {{ releaseYear }}
      </div>

      <!-- User rating badge -->
      <div
        v-if="album.user_rating && album.user_rating > 0"
        class="rating-badge"
      >
        <i class="fas fa-star" />
        <span>{{ album.user_rating }}</span>
      </div>

      <!-- In library badge -->
      <div
        v-if="isInLibrary"
        class="library-badge"
        title="En tu biblioteca"
      >
        <i
          class="fas fa-bookmark"
          aria-hidden="true"
        />
        <span class="u-sr-only">En tu biblioteca</span>
      </div>

      <!-- Album type badge -->
      <div
        v-if="albumType"
        class="type-badge"
        :title="albumType"
      >
        <i
          :class="albumTypeIcon"
          aria-hidden="true"
        />
        <span class="u-sr-only">Tipo: {{ albumType }}</span>
      </div>
    </div>

    <div class="album-info">
      <h3 class="album-title">
        {{ truncateText(album.title || album.name, 40) }}
      </h3>
      <p
        v-if="artistName"
        class="album-artist"
      >
        {{ truncateText(artistName, 30) }}
      </p>
      <div
        v-if="album.popularity"
        class="album-popularity"
      >
        <span class="popularity-label">Popularidad:</span>
        <span class="popularity-bar">
          <span
            class="popularity-fill"
            :style="{ width: album.popularity + '%' }"
          />
        </span>
      </div>
    </div>
  </button>
</template>

<script setup>
import { computed } from 'vue';
import { useAlbumsStore } from '@/store/albums';

const props = defineProps({
  album: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['click']);

const albumsStore = useAlbumsStore();

const isInLibrary = computed(() => {
  if (typeof props.album.is_in_user_library !== 'undefined') {
    return props.album.is_in_user_library === 1 || props.album.is_in_user_library === true;
  }
  const spotifyId = props.album.spotify_id || props.album.spotifyId;
  return spotifyId ? !!albumsStore.getAlbumBySpotifyId(spotifyId) : false;
});

const releaseYear = computed(() => {
  const dateStr = props.album.release_date || props.album.releaseDate;
  if (!dateStr) return null;
  return new Date(dateStr).getFullYear();
});

const artistName = computed(() => {
  return props.album.artist || props.album.artists?.[0]?.name || null;
});

const albumType = computed(() => {
  const type = props.album.album_type || props.album.albumType;
  if (!type) return null;
  const labels = { album: 'Álbum', single: 'Single', ep: 'EP', compilation: 'Compilación' };
  return labels[type.toLowerCase()] || type;
});

const albumTypeIcon = computed(() => {
  const type = (props.album.album_type || props.album.albumType || '').toLowerCase();
  switch (type) {
    case 'single': return 'fas fa-compact-disc';
    case 'ep': return 'fas fa-record-vinyl';
    case 'compilation': return 'fas fa-layer-group';
    default: return 'fas fa-music';
  }
});

function truncateText(text, maxLength) {
  if (!text) return '';
  return text.length > maxLength ? text.slice(0, maxLength) + '…' : text;
}

function handleClick() {
  emit('click', props.album);
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.album-carousel-item {
  @include button-reset;
  cursor: pointer;
  width: 160px;
  flex-shrink: 0;
  border-radius: 10px;
  overflow: hidden;
  background: var(--color-background-card);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.album-carousel-item:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}

.album-cover-wrapper {
  position: relative;
  width: 160px;
  height: 160px;
  overflow: hidden;
  background: var(--color-background-mute);
}

.album-cover {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.album-cover-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-hover));
  color: rgba(255, 255, 255, 0.4);
  font-size: 3rem;
}

.year-badge {
  position: absolute;
  bottom: 6px;
  left: 6px;
  background: var(--color-overlay-strong);
  color: var(--color-on-overlay);
  font-size: 0.68rem;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: 600;
}

.rating-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  background: var(--color-overlay-strong);
  color: var(--color-rating-star);
  font-size: 0.7rem;
  padding: 2px 6px;
  border-radius: 4px;
  display: flex;
  align-items: center;
  gap: 3px;
}

.library-badge {
  position: absolute;
  top: 6px;
  left: 6px;
  background: var(--color-overlay-strong);
  color: var(--color-on-overlay);
  font-size: 0.75rem;
  padding: 3px 6px;
  border-radius: 4px;
}

.type-badge {
  position: absolute;
  bottom: 6px;
  right: 6px;
  background: rgba(0, 0, 0, 0.6);
  color: rgba(255, 255, 255, 0.8);
  font-size: 0.7rem;
  padding: 2px 5px;
  border-radius: 4px;
}

.album-info {
  padding: 8px 10px;
}

.album-title {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0 0 3px;
  line-height: 1.3;
}

.album-artist {
  font-size: 0.74rem;
  color: var(--color-text-secondary);
  margin: 0 0 5px;
}

.album-popularity {
  display: flex;
  align-items: center;
  gap: 6px;
}

.popularity-label {
  font-size: 0.65rem;
  color: var(--color-text-secondary);
  white-space: nowrap;
}

.popularity-bar {
  flex: 1;
  height: 3px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 2px;
  overflow: hidden;
}

.popularity-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--color-primary), var(--color-card-album-accent));
  border-radius: 2px;
  display: block;
}
</style>
