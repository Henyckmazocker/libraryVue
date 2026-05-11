<template>
  <div class="video-detail-view">
    <!-- Back Button -->
    <button @click="goBack" class="back-button">
      <i class="fas fa-arrow-left"></i>
      <span>Volver</span>
    </button>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Cargando información del vídeo...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <p>{{ error }}</p>
      <button @click="goBack" class="retry-button">Volver al buscador</button>
    </div>

    <!-- Video Details -->
    <div v-else-if="video" class="video-detail-content">
      <!-- Cabecera principal -->
      <div class="video-header">
        <div class="video-cover-large">
          <img
            v-if="video.cover_url || video.coverUrl"
            :src="video.cover_url || video.coverUrl"
            :alt="video.title"
            class="cover-image-large"
          />
          <div v-else class="cover-placeholder">
            <i class="fab fa-youtube"></i>
          </div>
          <a
            v-if="youtubeId"
            :href="`https://www.youtube.com/watch?v=${youtubeId}`"
            target="_blank"
            rel="noopener noreferrer"
            class="youtube-play-btn"
            title="Ver en YouTube"
          >
            <i class="fab fa-youtube"></i>
          </a>
        </div>

        <div class="video-main-info">
          <h1 class="video-title-large">{{ video.title }}</h1>

          <div v-if="video.channel_name || video.channelName" class="video-channel-large">
            <i class="fas fa-tv"></i>
            <span>{{ video.channel_name || video.channelName }}</span>
          </div>

          <div class="video-metadata">
            <span v-if="video.duration" class="metadata-item">
              <i class="fas fa-clock"></i>
              {{ video.duration }}
            </span>
            <span v-if="publishedYear" class="metadata-item">
              <i class="fas fa-calendar"></i>
              {{ publishedYear }}
            </span>
            <span v-if="video.view_count || video.viewCount" class="metadata-item">
              <i class="fas fa-eye"></i>
              {{ formatCount(video.view_count || video.viewCount) }} vistas
            </span>
            <span v-if="video.like_count || video.likeCount" class="metadata-item">
              <i class="fas fa-thumbs-up"></i>
              {{ formatCount(video.like_count || video.likeCount) }}
            </span>
          </div>

          <!-- Categorías -->
          <div v-if="categoriesArray.length > 0" class="video-categories">
            <i class="fas fa-tags"></i>
            <div class="category-tags">
              <span v-for="cat in categoriesArray" :key="cat" class="category-tag">
                {{ cat }}
              </span>
            </div>
          </div>

          <!-- Enlace YouTube -->
          <div v-if="youtubeId" class="video-links">
            <a
              :href="`https://www.youtube.com/watch?v=${youtubeId}`"
              target="_blank"
              rel="noopener noreferrer"
              class="youtube-link"
            >
              <i class="fab fa-youtube"></i>
              Ver en YouTube
            </a>
          </div>
        </div>
      </div>

      <!-- Descripción -->
      <div v-if="video.description" class="video-description-section">
        <h2 class="section-title">
          <i class="fas fa-align-left"></i>
          Descripción
        </h2>
        <p class="video-description">{{ truncateDescription(video.description, showFullDesc ? 9999 : 300) }}</p>
        <button v-if="video.description.length > 300" @click="showFullDesc = !showFullDesc" class="toggle-desc-btn">
          {{ showFullDesc ? 'Mostrar menos' : 'Mostrar más' }}
        </button>
      </div>

      <!-- Library Item Form -->
      <div class="library-section">
        <h2>{{ existingVideo ? 'Detalles en tu Biblioteca' : 'Añadir a tu Biblioteca' }}</h2>
        <LibraryVideoItem
          :video="videoForLibrary"
          :allowed-statuses="allowedStatuses"
          :is-new-video="!existingVideo"
          :can-delete="!!existingVideo"
          @save="handleSaveVideo"
          @edit="handleEditItem"
          @delete="handleDeleteVideo"
        />
      </div>

      <!-- Notes section -->
      <div v-if="existingVideo" class="notes-section">
        <VideoNotes :youtube-id="youtubeId" />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <i class="fab fa-youtube"></i>
      <p>No se encontró información del vídeo</p>
      <button @click="goBack" class="retry-button">Volver al buscador</button>
    </div>

    <!-- Edit Item Modal -->
    <EditItemModal
      v-if="editModal.isVisible"
      :item="editModal.item"
      :item-type="'video'"
      :allowed-statuses="allowedStatuses"
      :is-visible="editModal.isVisible"
      @close="closeEditModal"
      @saved="handleModalSaved"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import LibraryVideoItem from '@/components/Videos/LibraryVideoItem.vue';
import VideoNotes from '@/components/Videos/VideoNotes.vue';
import EditItemModal from '@/components/EditItemModal.vue';
import { useVideosStore } from '@/store/videos';
import { useAuthStore } from '@/store/auth';
import Logger from '@/utils/logger';

const route = useRoute();
const router = useRouter();
const videosStore = useVideosStore();
const authStore = useAuthStore();

// State
const video = ref((history.state && history.state.video) ? history.state.video : null);
const isLoading = ref(!video.value);
const error = ref(null);
const allowedStatuses = ref([]);
const showFullDesc = ref(false);
const editModal = ref({ isVisible: false, item: null });

// Computed
const youtubeId = computed(() => route.params.youtubeId || video.value?.youtube_id || video.value?.youtubeId);
const isAuthenticated = computed(() => authStore.isAuthenticated);

const existingVideo = computed(() => {
  if (!youtubeId.value) return null;
  return videosStore.getVideoByYouTubeId(youtubeId.value);
});

const videoForLibrary = computed(() => {
  return existingVideo.value || video.value || {};
});

const publishedYear = computed(() => {
  const dateStr = video.value?.published_at || video.value?.publishedAt;
  if (!dateStr) return null;
  return new Date(dateStr).getFullYear();
});

const categoriesArray = computed(() => {
  const cats = video.value?.categories;
  if (!cats) return [];
  if (Array.isArray(cats)) return cats;
  if (typeof cats === 'string') {
    try { return JSON.parse(cats); } catch { return []; }
  }
  return [];
});

// Methods
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

function goBack() {
  router.back();
}

async function loadData() {
  if (!isAuthenticated.value) return;

  // Load allowed statuses
  if (videosStore.allowedStatuses.length === 0) {
    await videosStore.fetchAllowedStatuses();
  }
  allowedStatuses.value = videosStore.allowedStatuses.map(s => (typeof s === 'object' && s !== null) ? s.name : s);

  // Load user library
  if (videosStore.videos.length === 0) {
    await videosStore.fetchVideos();
  }

  // If no video from history state, try to find in store or fail
  if (!video.value && youtubeId.value) {
    const found = videosStore.getVideoByYouTubeId(youtubeId.value);
    if (found) {
      video.value = found;
    } else {
      error.value = 'No se encontró el vídeo. Vuelve al buscador y selecciónalo de nuevo.';
    }
    isLoading.value = false;
  }
}

async function handleSaveVideo(videoData) {
  Logger.debug('[VideoDetailView] Saving video:', videoData);
  const statuses = videoData.userStatuses || [];
  const result = await videosStore.addVideo(videoData, statuses);
  if (result.success) {
    Logger.debug('[VideoDetailView] Video saved successfully');
  } else {
    Logger.error('[VideoDetailView] Error saving video:', result.message);
  }
}

function handleEditItem(videoItem) {
  editModal.value = { isVisible: true, item: videoItem };
}

async function handleDeleteVideo(youtubeIdToDelete) {
  if (!confirm('¿Seguro que quieres eliminar este vídeo de tu biblioteca?')) return;
  const result = await videosStore.deleteVideo(youtubeIdToDelete);
  if (result.success) {
    Logger.debug('[VideoDetailView] Video deleted');
  }
}

async function handleModalSaved() {
  closeEditModal();
  await videosStore.fetchVideos();
}

function closeEditModal() {
  editModal.value = { isVisible: false, item: null };
}

onMounted(async () => {
  isLoading.value = true;
  await loadData();
  isLoading.value = false;
});

watch(isAuthenticated, async (newValue) => {
  if (newValue) await loadData();
});
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
    background: linear-gradient(135deg, var(--color-card-video-bg) 0%, #3d1f1f 100%);
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
