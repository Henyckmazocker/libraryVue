<template>
  <MediaDetailView
    media="album"
    :store="albumsStore"
  >
    <template #meta-top="{ item }">
      <div
        v-if="item.album_type || item.albumType"
        class="album-type-badge"
      >
        <i :class="albumTypeIcon(item.album_type || item.albumType)" />
        {{ albumTypeLabel(item.album_type || item.albumType) }}
      </div>
    </template>

    <template #meta="{ item }">
      <div class="album-artist-large">
        <i class="fas fa-user" />
        <span>{{ artistName(item) }}</span>
      </div>

      <div class="album-metadata">
        <span
          v-if="releaseYear(item)"
          class="metadata-item"
        >
          <i class="fas fa-calendar" />
          {{ releaseYear(item) }}
        </span>
        <span
          v-if="item.total_tracks || item.totalTracks"
          class="metadata-item"
        >
          <i class="fas fa-music" />
          {{ item.total_tracks || item.totalTracks }} pistas
        </span>
        <span
          v-if="item.label"
          class="metadata-item"
        >
          <i class="fas fa-building" />
          {{ item.label }}
        </span>
        <span
          v-if="formattedDuration(item)"
          class="metadata-item"
        >
          <i class="fas fa-clock" />
          {{ formattedDuration(item) }}
        </span>
      </div>

      <div
        v-if="item.popularity"
        class="album-popularity"
      >
        <span class="popularity-label">Popularidad:</span>
        <div class="popularity-bar-container">
          <div
            class="popularity-bar"
            :style="{ width: item.popularity + '%' }"
          />
        </div>
        <span class="popularity-value">{{ item.popularity }}/100</span>
      </div>

      <div
        v-if="genresArray(item).length > 0"
        class="album-genres"
      >
        <i class="fas fa-tags" />
        <div class="genre-tags">
          <span
            v-for="genre in genresArray(item)"
            :key="genre"
            class="genre-tag"
          >
            {{ genre }}
          </span>
        </div>
      </div>

      <div
        v-if="item.external_url || item.externalUrl"
        class="album-links"
      >
        <a
          :href="item.external_url || item.externalUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="spotify-link"
        >
          <i class="fab fa-spotify" />
          Abrir en Spotify
        </a>
      </div>
    </template>

    <template #extra="{ item, context }">
      <!-- Last.fm -->
      <div class="lastfm-section">
        <h2 class="section-title">
          <i
            class="fas fa-headphones u-brand-lastfm"
          />
          Last.fm
        </h2>
        <AlbumLastFmCard
          :artist-name="artistName(item)"
          :album-name="item.title || item.name || ''"
        />
      </div>

      <!-- Lista de pistas -->
      <div
        v-if="(context.tracks || []).length > 0"
        class="tracks-section"
      >
        <h2 class="section-title">
          <i class="fas fa-list-ul" />
          Pistas
        </h2>
        <div class="tracks-list">
          <div
            v-for="track in context.tracks"
            :key="track.id || track.track_number"
            class="track-item"
          >
            <span class="track-number">{{ track.track_number }}</span>
            <span class="track-name">{{ track.name }}</span>
            <span class="track-duration">{{ trackDuration(track.duration_ms) }}</span>
          </div>
        </div>
      </div>
    </template>
  </MediaDetailView>
</template>

<script setup>
import MediaDetailView from '@/views/shared/MediaDetailView.vue';
import AlbumLastFmCard from '@/components/Albums/AlbumLastFmCard.vue';
import { useAlbumsStore } from '@/store/albums';

/**
 * Ficha de álbum. El esqueleto —estados, cabecera, formulario de biblioteca,
 * modal y notas— vive en MediaDetailView, configurado desde mediaRegistry;
 * aquí queda lo propio de la música: el badge de tipo, la barra de popularidad,
 * la tarjeta de Last.fm y la lista de pistas, que llega por `context`.
 */
const albumsStore = useAlbumsStore();

const artistName = (album) => album?.artist || album?.artists?.[0]?.name || '';

const releaseYear = (album) => {
  const date = album?.release_date || album?.releaseDate;
  return date ? date.toString().substring(0, 4) : '';
};

const genresArray = (album) => {
  const genres = album?.genres;
  if (!genres) return [];
  if (Array.isArray(genres)) return genres;
  if (typeof genres === 'string') return genres.split(',').map(g => g.trim()).filter(Boolean);
  return [];
};

const formattedDuration = (album) => {
  const ms = album?.duration_ms || album?.durationMs;
  if (!ms) return '';
  const totalSec = Math.floor(ms / 1000);
  const hours = Math.floor(totalSec / 3600);
  const minutes = Math.floor((totalSec % 3600) / 60);
  const seconds = totalSec % 60;
  if (hours > 0) return `${hours}h ${minutes}m`;
  return `${minutes}:${String(seconds).padStart(2, '0')}`;
};

const trackDuration = (ms) => {
  if (!ms) return '';
  const totalSec = Math.floor(ms / 1000);
  return `${Math.floor(totalSec / 60)}:${String(totalSec % 60).padStart(2, '0')}`;
};

const albumTypeIcon = (type) => {
  if (!type) return 'fas fa-music';
  switch (type.toLowerCase()) {
    case 'album': return 'fas fa-record-vinyl';
    case 'single': return 'fas fa-music';
    case 'compilation': return 'fas fa-layer-group';
    case 'ep': return 'fas fa-compact-disc';
    default: return 'fas fa-music';
  }
};

const albumTypeLabel = (type) => {
  if (!type) return '';
  const labels = { album: 'Álbum', single: 'Single', compilation: 'Compilación', ep: 'EP' };
  return labels[type.toLowerCase()] || type;
};
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/detail-view' as *;

.album-detail-view {
  @include detail-view-page('album');

  .tracks-section,
  .library-section,
  .lastfm-section {
    @include detail-section-card;
  }

  // Album cover es cuadrado (1:1) por convención musical
  .album-cover-large {
    flex-shrink: 0;
    width: 240px;
    height: 240px;
    border-radius: radius(md);
    overflow: hidden;
    box-shadow: shadow(heavy);
  }

  .cover-image-large {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border: none;
    box-shadow: none;
  }

  .cover-placeholder {
    aspect-ratio: 1 / 1;
    background: var(--color-background-soft);
    border: none;
    font-size: 4rem;
    color: var(--color-text-muted);
  }

  .album-main-info {
    flex: 1;
    min-width: 0;
  }

  .album-type-badge {
    display: inline-flex;
    align-items: center;
    gap: spacing(2xs);
    padding: spacing(3xs) spacing(sm);
    border-radius: radius(full);
    background: var(--color-card-album-accent);
    color: white;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: spacing(xs);
  }

  .album-artist-large {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    font-size: 1rem;
    color: var(--color-text-secondary);
    margin-bottom: spacing(md);

    i { color: var(--color-card-album-accent); }
  }

  .album-metadata {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(sm);
    margin-bottom: spacing(md);
  }

  .album-popularity {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    margin-bottom: spacing(sm);
  }

  .popularity-label {
    font-size: 0.82rem;
    color: var(--color-text-secondary);
    white-space: nowrap;
  }

  .popularity-bar-container {
    flex: 1;
    max-width: 180px;
    height: 6px;
    background: var(--color-background-soft);
    border-radius: 3px;
    overflow: hidden;
  }

  .popularity-bar {
    height: 100%;
    background: linear-gradient(
      90deg,
      var(--color-card-album-accent),
      color-mix(in srgb, var(--color-card-album-accent) 60%, white)
    );
    border-radius: 3px;
    transition: width 0.5s ease;
  }

  .popularity-value {
    font-size: 0.78rem;
    color: var(--color-text-secondary);
  }

  .album-genres {
    display: flex;
    align-items: flex-start;
    gap: spacing(xs);
    margin-bottom: spacing(md);

    > i {
      color: var(--color-card-album-accent);
      margin-top: 6px;
      flex-shrink: 0;
    }
  }

  .genre-tags {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(xs);
  }

  .album-links {
    margin-top: spacing(xs);
  }

  // Spotify branding link conserva su color verde semantico
  .spotify-link {
    display: inline-flex;
    align-items: center;
    gap: spacing(xs);
    padding: spacing(xs) spacing(md);
    /* stylelint-disable-next-line color-no-hex -- Spotify: color de marca, drift intencional (styles.md) */
    background: #1DB954;
    // Tinta fija, no `--color-on-status`: el verde de debajo es de marca y no cambia
    // con el tema, así que su tinta tampoco puede. Blanco encima da 2.59; este par
    // da 7.19, y es además el que pide la guía de marca de Spotify.
    /* stylelint-disable-next-line color-no-hex -- tinta del par de marca de Spotify */
    color: #0F1412;
    border-radius: radius(full);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background transition(fast);

    /* stylelint-disable-next-line color-no-hex -- Spotify (hover): color de marca, drift intencional (styles.md) */
    &:hover { background: #1AA34A; }
    i { font-size: 1.1rem; }
  }

  // Lista de tracks
  .tracks-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .track-item {
    display: flex;
    align-items: center;
    gap: spacing(sm);
    padding: spacing(xs) spacing(sm);
    border-radius: radius(sm);
    transition: background transition(fast);

    &:hover { background: var(--color-background-soft); }
  }

  .track-number {
    width: 24px;
    text-align: right;
    font-size: 0.8rem;
    color: var(--color-text-secondary);
    flex-shrink: 0;
  }

  .track-name {
    flex: 1;
    font-size: 0.88rem;
    color: var(--color-text);
  }

  .track-duration {
    font-size: 0.78rem;
    color: var(--color-text-secondary);
    flex-shrink: 0;
  }

  @include responsive-below(md) {
    .album-header {
      align-items: center;
      text-align: center;
    }

    .album-cover-large {
      width: 180px;
      height: 180px;
    }

    .album-metadata,
    .album-genres {
      justify-content: center;
    }
  }
}

/* stylelint-disable-next-line color-no-hex -- rojo de Last.fm: color de marca, drift intencional (styles.md) */
.u-brand-lastfm { color: #d51007; }
</style>
