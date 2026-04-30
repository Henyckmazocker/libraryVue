<template>
  <div class="album-detail-view">
    <!-- Back Button -->
    <button @click="goBack" class="back-button">
      <i class="fas fa-arrow-left"></i>
      <span>Volver</span>
    </button>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Cargando información del álbum...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <p>{{ error }}</p>
      <button @click="goBack" class="retry-button">Volver al buscador</button>
    </div>

    <!-- Album Details -->
    <div v-else-if="album" class="album-detail-content">
      <!-- Cabecera principal con portada y datos -->
      <div class="album-header">
        <div class="album-cover-large">
          <img
            v-if="album.cover_url || album.coverUrl"
            :src="album.cover_url || album.coverUrl"
            :alt="album.title || album.name"
            class="cover-image-large"
          />
          <div v-else class="cover-placeholder">
            <i class="fas fa-music"></i>
          </div>
        </div>

        <div class="album-main-info">
          <div class="album-type-badge" v-if="album.album_type || album.albumType">
            <i :class="getAlbumTypeIcon(album.album_type || album.albumType)"></i>
            {{ getAlbumTypeLabel(album.album_type || album.albumType) }}
          </div>

          <h1 class="album-title-large">{{ album.title || album.name }}</h1>

          <div class="album-artist-large">
            <i class="fas fa-user-music"></i>
            <span>{{ artistName }}</span>
          </div>

          <div class="album-metadata">
            <span v-if="releaseYear" class="metadata-item">
              <i class="fas fa-calendar"></i>
              {{ releaseYear }}
            </span>
            <span v-if="album.total_tracks || album.totalTracks" class="metadata-item">
              <i class="fas fa-list-music"></i>
              {{ album.total_tracks || album.totalTracks }} pistas
            </span>
            <span v-if="album.label" class="metadata-item">
              <i class="fas fa-building"></i>
              {{ album.label }}
            </span>
            <span v-if="formattedDuration" class="metadata-item">
              <i class="fas fa-clock"></i>
              {{ formattedDuration }}
            </span>
          </div>

          <!-- Popularidad -->
          <div v-if="album.popularity" class="album-popularity">
            <span class="popularity-label">Popularidad:</span>
            <div class="popularity-bar-container">
              <div class="popularity-bar" :style="{ width: album.popularity + '%' }"></div>
            </div>
            <span class="popularity-value">{{ album.popularity }}/100</span>
          </div>

          <!-- Géneros -->
          <div v-if="genresArray.length > 0" class="album-genres">
            <i class="fas fa-tags"></i>
            <div class="genre-tags">
              <span v-for="genre in genresArray" :key="genre" class="genre-tag">
                {{ genre }}
              </span>
            </div>
          </div>

          <!-- Enlace Spotify -->
          <div v-if="album.external_url || album.externalUrl" class="album-links">
            <a
              :href="album.external_url || album.externalUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="spotify-link"
            >
              <i class="fab fa-spotify"></i>
              Abrir en Spotify
            </a>
          </div>
        </div>
      </div>

      <!-- Last.fm Album Info -->
      <div class="lastfm-section">
        <h2 class="section-title">
          <i class="fas fa-headphones" style="color: #d51007;"></i>
          Last.fm
        </h2>
        <AlbumLastFmCard
          :artist-name="artistName"
          :album-name="album.title || album.name || ''"
        />
      </div>

      <!-- Lista de pistas -->
      <div v-if="tracks.length > 0" class="tracks-section">
        <h2 class="section-title">
          <i class="fas fa-list-ul"></i>
          Pistas
        </h2>
        <div class="tracks-list">
          <div
            v-for="track in tracks"
            :key="track.id || track.track_number"
            class="track-item"
          >
            <span class="track-number">{{ track.track_number }}</span>
            <span class="track-name">{{ track.name }}</span>
            <span class="track-duration">{{ formatTrackDuration(track.duration_ms) }}</span>
          </div>
        </div>
      </div>

      <!-- Library Item Form -->
      <div class="library-section">
        <h2>{{ existingAlbum ? 'Detalles en tu Biblioteca' : 'Añadir a tu Biblioteca' }}</h2>
        <LibraryAlbumItem
          ref="libraryAlbumItemRef"
          :album="albumForLibrary"
          :allowed-statuses="allowedStatuses"
          :is-new-album="!existingAlbum"
          :can-delete="!!existingAlbum"
          @save="handleSaveAlbum"
          @edit="handleEditItem"
          @delete="handleDeleteAlbum"
        />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <i class="fas fa-music"></i>
      <p>No se encontró información del álbum</p>
      <button @click="goBack" class="retry-button">Volver al buscador</button>
    </div>

    <!-- Edit Item Modal -->
    <EditItemModal
      v-if="editModal.isVisible"
      :item="editModal.item"
      :item-type="'album'"
      :allowed-statuses="allowedStatuses"
      :is-visible="editModal.isVisible"
      :album-tracks="tracks"
      @close="closeEditModal"
      @saved="handleModalSaved"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, toRaw } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import LibraryAlbumItem from '@/components/Albums/LibraryAlbumItem.vue';
import EditItemModal from '@/components/EditItemModal.vue';
import AlbumLastFmCard from '@/components/Albums/AlbumLastFmCard.vue';
import { useAlbumsStore } from '@/store/albums';
import { useAuthStore } from '@/store/auth';
import Logger from '@/utils/logger';

const route = useRoute();
const router = useRouter();
const albumsStore = useAlbumsStore();
const authStore = useAuthStore();

// State
const album = ref((history.state && history.state.album) ? history.state.album : null);
const tracks = ref([]);
const isLoading = ref(!album.value);
const error = ref(null);
const allowedStatuses = ref([]);
const libraryAlbumItemRef = ref(null);
const editModal = ref({
  isVisible: false,
  item: null
});

// Computed
const isAuthenticated = computed(() => authStore.isAuthenticated);

const existingAlbum = computed(() => {
  if (!album.value) return null;
  const spotifyId = album.value.spotify_id || album.value.id;
  return albumsStore.getAlbumBySpotifyId(spotifyId) || albumsStore.getAlbumById(Number(album.value.id));
});

const albumForLibrary = computed(() => {
  if (existingAlbum.value) {
    return {
      ...album.value,
      ...existingAlbum.value,
      // Keep Spotify cover if library entry has none
      cover_url: existingAlbum.value.cover_url || album.value?.cover_url
    };
  }
  return album.value;
});

const artistName = computed(() => {
  if (!album.value) return '';
  return album.value.artist || album.value.artists?.[0]?.name || '';
});

const releaseYear = computed(() => {
  const date = album.value?.release_date || album.value?.releaseDate;
  if (!date) return '';
  return date.toString().substring(0, 4);
});

const genresArray = computed(() => {
  const genres = album.value?.genres;
  if (!genres) return [];
  if (Array.isArray(genres)) return genres;
  if (typeof genres === 'string') return genres.split(',').map(g => g.trim()).filter(Boolean);
  return [];
});

const formattedDuration = computed(() => {
  const ms = album.value?.duration_ms || album.value?.durationMs;
  if (!ms) return '';
  const totalSec = Math.floor(ms / 1000);
  const hours = Math.floor(totalSec / 3600);
  const minutes = Math.floor((totalSec % 3600) / 60);
  const seconds = totalSec % 60;
  if (hours > 0) return `${hours}h ${minutes}m`;
  return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

// Methods
const goBack = () => {
  if (window.history.length > 1) {
    router.go(-1);
  } else {
    router.push({ name: 'Albums' });
  }
};

const getAlbumTypeIcon = (type) => {
  if (!type) return 'fas fa-music';
  switch (type.toLowerCase()) {
    case 'album': return 'fas fa-record-vinyl';
    case 'single': return 'fas fa-music';
    case 'compilation': return 'fas fa-layer-group';
    case 'ep': return 'fas fa-compact-disc';
    default: return 'fas fa-music';
  }
};

const getAlbumTypeLabel = (type) => {
  if (!type) return '';
  const labels = { album: 'Álbum', single: 'Single', compilation: 'Compilación', ep: 'EP' };
  return labels[type.toLowerCase()] || type;
};

const formatTrackDuration = (ms) => {
  if (!ms) return '';
  const totalSec = Math.floor(ms / 1000);
  const minutes = Math.floor(totalSec / 60);
  const seconds = totalSec % 60;
  return `${minutes}:${String(seconds).padStart(2, '0')}`;
};

const fetchAlbumDetails = async (spotifyId) => {
  const isBackgroundEnrichment = !!album.value;
  if (!isBackgroundEnrichment) {
    isLoading.value = true;
  }
  error.value = null;

  try {
    Logger.debug(`[AlbumDetailView] Fetching Spotify details for ID: ${spotifyId}`);
    const response = await authStore.apiCall('get_spotify_album', { spotifyId });

    if (response.data.status === 'success' && response.data.data) {
      const data = response.data.data.album || response.data.data;
      album.value = transformAlbumData(data);
      // Populate tracks immediately from album detail (avoids waiting for separate call)
      if (data.tracks?.items?.length > 0) {
        tracks.value = data.tracks.items;
      }
      Logger.debug('[AlbumDetailView] Album loaded:', album.value.title);
    } else {
      if (!isBackgroundEnrichment) {
        error.value = response.data.message || 'No se encontró información del álbum.';
      }
    }
  } catch (err) {
    if (!isBackgroundEnrichment) {
      error.value = 'No se pudo obtener información del álbum.';
    }
    Logger.error('[AlbumDetailView] Error fetching album details:', err);
  } finally {
    if (!isBackgroundEnrichment) {
      isLoading.value = false;
    }
  }
};

const fetchAlbumTracks = async (spotifyId) => {
  try {
    const response = await authStore.apiCall('get_spotify_album_tracks', { spotifyId });
    if (response.data.status === 'success' && response.data.data) {
      tracks.value = response.data.data.tracks || response.data.data.items || [];
    }
  } catch (err) {
    Logger.warn('[AlbumDetailView] Could not load tracks:', err);
  }
};

const transformAlbumData = (data) => {
  const artists = data.artists || [];
  const artistName = artists.map(a => a.name).join(', ');
  const artistId = artists[0]?.id || '';
  const genres = data.genres || [];
  const coverUrl = data.images?.[0]?.url || data.cover_url || '';
  const totalDurationMs = data.tracks?.items?.reduce((sum, t) => sum + (t.duration_ms || 0), 0) || data.duration_ms || 0;

  return {
    id: data.id,
    spotify_id: data.id,
    title: data.name,
    name: data.name,
    artist: artistName,
    artist_id: artistId,
    artists: artists,
    release_date: data.release_date,
    release_date_precision: data.release_date_precision,
    cover_url: coverUrl,
    coverUrl: coverUrl,
    genres: genres,
    label: data.label,
    total_tracks: data.total_tracks,
    album_type: data.album_type,
    duration_ms: totalDurationMs,
    popularity: data.popularity,
    external_url: data.external_urls?.spotify,
    upc: data.external_ids?.upc,
    user_rating: null,
    userStatuses: [],
    itemType: 'album'
  };
};

const handleSaveAlbum = async (albumData) => {
  try {
    Logger.debug('[AlbumDetailView] Saving album to library:', albumData);
    const statuses = albumData.userStatuses || [];
    const result = await albumsStore.addAlbum(albumData, statuses);

    if (result.success) {
      Logger.info('[AlbumDetailView] Album saved successfully');
    } else {
      Logger.error('[AlbumDetailView] Error saving album:', result.message);
      alert('Error al añadir el álbum: ' + (result.message || 'Error desconocido'));
    }
  } catch (err) {
    Logger.error('[AlbumDetailView] Error saving album:', err);
    alert('Error al añadir el álbum');
  }
};

const handleEditItem = async () => {
  // Ensure albums are loaded in the store before opening the modal.
  if (albumsStore.albums.length === 0) {
    await albumsStore.fetchAlbums();
  }

  const storeAlbum = existingAlbum.value ? toRaw(existingAlbum.value) : null;

  const itemForModal = storeAlbum
    ? {
        ...album.value,
        ...storeAlbum,
        cover_url: storeAlbum.cover_url || album.value?.cover_url,
        user_rating: storeAlbum.user_rating ?? null,
        userStatuses: Array.isArray(storeAlbum.userStatuses) ? [...storeAlbum.userStatuses] : [],
        ownershipFormat: storeAlbum.ownershipFormat ?? storeAlbum.ownership_format ?? null,
        ownership_format: storeAlbum.ownership_format ?? storeAlbum.ownershipFormat ?? null,
        ownership_format_id: storeAlbum.ownershipFormat?.id ?? storeAlbum.ownership_format?.id ?? null,
        tags: storeAlbum.tags ?? null,
      }
    : album.value;

  editModal.value = {
    isVisible: true,
    item: itemForModal
  };
};

const closeEditModal = () => {
  editModal.value = { isVisible: false, item: null };
};

const handleModalSaved = async (updatedItem) => {
  Logger.debug('[AlbumDetailView] Album saved from modal:', updatedItem);
  closeEditModal();

  if (album.value && updatedItem) {
    album.value = {
      ...album.value,
      ...updatedItem,
      user_rating: updatedItem.user_rating,
      userStatuses: updatedItem.userStatuses,
      listenCount: updatedItem.listenCount ?? album.value.listenCount,
      favoriteTrack: updatedItem.favoriteTrack ?? album.value.favoriteTrack,
      dateStarted: updatedItem.dateStarted ?? album.value.dateStarted,
      dateFinished: updatedItem.dateFinished ?? album.value.dateFinished,
      personalNotes: updatedItem.personalNotes ?? album.value.personalNotes
    };
  }

  // Actualizar en el store
  const albumInStore = albumsStore.albums.find(a =>
    a.spotify_id === album.value?.spotify_id || a.id === album.value?.id
  );
  if (albumInStore) Object.assign(albumInStore, updatedItem);

  // Recargar en segundo plano
  setTimeout(() => {
    albumsStore.fetchAlbums().catch(err =>
      Logger.error('[AlbumDetailView] Background refresh failed:', err)
    );
  }, 500);
};

const handleDeleteAlbum = async (albumId) => {
  if (!confirm('¿Eliminar este álbum de tu biblioteca?')) return;

  try {
    const result = await albumsStore.deleteAlbum(albumId);
    if (result.success) {
      alert('Álbum eliminado de tu biblioteca');
      goBack();
    } else {
      alert('Error al eliminar el álbum');
    }
  } catch (err) {
    Logger.error('[AlbumDetailView] Error deleting album:', err);
    alert('Error al eliminar el álbum');
  }
};

const loadAlbumData = async () => {
  const hasEagerData = !!album.value;
  const routeAlbumId = route.params.albumId;
  // Prefer spotify_id from pre-loaded data (trending passes DB integer ID as route param)
  const albumId = album.value?.spotify_id || album.value?.id || routeAlbumId;

  if (hasEagerData) {
    isLoading.value = false;
    Logger.debug('[AlbumDetailView] Using pre-loaded album data');
  }

  await Promise.all([
    albumsStore.albums.length === 0 ? albumsStore.fetchAlbums() : Promise.resolve(),
    albumsStore.allowedStatuses.length === 0 ? albumsStore.fetchAllowedStatuses() : Promise.resolve()
  ]);
  allowedStatuses.value = albumsStore.allowedStatuses;

  // Fetch full Spotify details (in background if we have eager data)
  fetchAlbumDetails(albumId).then(() => {
    _mergeExistingAlbumData();
    // Also fetch tracks
    fetchAlbumTracks(albumId);
  });

  if (!hasEagerData) {
    _mergeExistingAlbumData();
  }
};

const _mergeExistingAlbumData = () => {
  if (!existingAlbum.value || !album.value) return;
  Logger.debug('[AlbumDetailView] Merging with library data');
  album.value = {
    ...album.value,
    id: existingAlbum.value.id,
    user_rating: existingAlbum.value.user_rating,
    userStatuses: existingAlbum.value.userStatuses || [],
    listenCount: existingAlbum.value.listenCount || existingAlbum.value.listen_count || null,
    favoriteTrack: existingAlbum.value.favoriteTrack || existingAlbum.value.favorite_track || '',
    dateStarted: existingAlbum.value.dateStarted || existingAlbum.value.date_started || '',
    dateFinished: existingAlbum.value.dateFinished || existingAlbum.value.date_finished || '',
    personalNotes: existingAlbum.value.personalNotes || existingAlbum.value.personal_notes || '',
    ownershipFormat: existingAlbum.value.ownershipFormat ?? existingAlbum.value.ownership_format ?? null,
    ownership_format: existingAlbum.value.ownership_format ?? existingAlbum.value.ownershipFormat ?? null,
    ownership_format_id: existingAlbum.value.ownership_format_id ?? existingAlbum.value.ownershipFormat?.id ?? null,
    tags: existingAlbum.value.tags ?? null,
  };
};

onMounted(async () => {
  if (isAuthenticated.value) {
    await loadAlbumData();
  }
});

watch(isAuthenticated, async (newValue) => {
  if (newValue && !album.value) {
    await loadAlbumData();
  }
});
</script>

<style scoped>
.album-detail-view {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: var(--surface-card, #1e2028);
  color: var(--text-color, #e0e0e0);
  border: 1px solid var(--surface-border, #2d3141);
  border-radius: 8px;
  cursor: pointer;
  margin-bottom: 20px;
  transition: background 0.15s;
}

.back-button:hover { background: var(--surface-hover, #252836); }

.loading-container,
.error-container,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 60px 20px;
  color: var(--text-color-secondary, #9ca3af);
  font-size: 1rem;
}

.loading-container i,
.error-container i,
.empty-state i {
  font-size: 2.5rem;
  color: var(--primary-color, #1D4E4A);
}

.retry-button {
  padding: 8px 20px;
  background: var(--primary-color, #1D4E4A);
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

/* Album Header */
.album-header {
  display: flex;
  gap: 32px;
  align-items: flex-start;
  margin-bottom: 32px;
  padding: 24px;
  background: var(--surface-card, #1e2028);
  border-radius: 12px;
  border: 1px solid var(--surface-border, #2d3141);
}

.album-cover-large {
  flex-shrink: 0;
  width: 240px;
  height: 240px;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0,0,0,0.4);
}

.cover-image-large {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.cover-placeholder {
  width: 100%;
  height: 100%;
  background: var(--surface-section, #2a2d36);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 4rem;
  color: var(--text-color-secondary, #6b7280);
}

.album-main-info {
  flex: 1;
  min-width: 0;
}

.album-type-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 12px;
  background: var(--primary-color, #1D4E4A);
  color: white;
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 8px;
}

.album-title-large {
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--text-color, #e0e0e0);
  margin: 0 0 8px;
  line-height: 1.2;
}

.album-artist-large {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 1rem;
  color: var(--text-color-secondary, #9ca3af);
  margin-bottom: 14px;
}

.album-metadata {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 14px;
}

.metadata-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.84rem;
  color: var(--text-color-secondary, #9ca3af);
  background: var(--surface-section, #2a2d36);
  padding: 4px 10px;
  border-radius: 6px;
}

.album-popularity {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.popularity-label {
  font-size: 0.82rem;
  color: var(--text-color-secondary, #9ca3af);
  white-space: nowrap;
}

.popularity-bar-container {
  flex: 1;
  max-width: 180px;
  height: 6px;
  background: var(--surface-section, #2a2d36);
  border-radius: 3px;
  overflow: hidden;
}

.popularity-bar {
  height: 100%;
  background: linear-gradient(90deg, #1D4E4A, #2eb87e);
  border-radius: 3px;
  transition: width 0.5s ease;
}

.popularity-value {
  font-size: 0.78rem;
  color: var(--text-color-secondary, #9ca3af);
}

.album-genres {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 14px;
}

.album-genres i {
  color: var(--text-color-secondary, #9ca3af);
  margin-top: 3px;
}

.genre-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.genre-tag {
  font-size: 0.76rem;
  padding: 3px 10px;
  border-radius: 12px;
  background: var(--surface-section, #2a2d36);
  color: var(--text-color-secondary, #9ca3af);
  border: 1px solid var(--surface-border, #2d3141);
}

.album-links {
  margin-top: 8px;
}

.spotify-link {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 7px 16px;
  background: #1DB954;
  color: white;
  border-radius: 20px;
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 600;
  transition: background 0.2s;
}

.spotify-link:hover { background: #1aa34a; }
.spotify-link i { font-size: 1.1rem; }

/* Tracks */
.tracks-section {
  margin-bottom: 28px;
  background: var(--surface-card, #1e2028);
  border-radius: 12px;
  border: 1px solid var(--surface-border, #2d3141);
  padding: 20px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-color, #e0e0e0);
  margin: 0 0 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--surface-border, #2d3141);
}

.tracks-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.track-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 8px 10px;
  border-radius: 6px;
  transition: background 0.1s;
}

.track-item:hover { background: var(--surface-hover, #252836); }

.track-number {
  width: 24px;
  text-align: right;
  font-size: 0.8rem;
  color: var(--text-color-secondary, #9ca3af);
  flex-shrink: 0;
}

.track-name {
  flex: 1;
  font-size: 0.88rem;
  color: var(--text-color, #e0e0e0);
}

.track-duration {
  font-size: 0.78rem;
  color: var(--text-color-secondary, #9ca3af);
  flex-shrink: 0;
}

/* Library Section */
.library-section {
  background: var(--surface-card, #1e2028);
  border-radius: 12px;
  border: 1px solid var(--surface-border, #2d3141);
  padding: 20px;
  margin-bottom: 28px;
}

.library-section h2 {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-color, #e0e0e0);
  margin: 0 0 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--surface-border, #2d3141);
}

@media (max-width: 768px) {
  .album-header {
    flex-direction: column;
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

.lastfm-section {
  background: var(--surface-card, #1e2028);
  border-radius: 12px;
  border: 1px solid var(--surface-border, #2d3141);
  padding: 20px;
  margin-bottom: 28px;
}
</style>
