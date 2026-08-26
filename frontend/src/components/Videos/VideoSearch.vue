<template>
  <div class="video-search-container">
    <!-- Search -->
    <GenericSearch :config="searchConfig" />

    <!-- Trending Videos -->
    <TrendingCarousel
      v-if="authStore.isAuthenticated"
      :items="trendingVideos"
      :is-loading="isLoadingTrending"
      :error="errorTrending"
      type="videos"
      :item-component="VideoCarouselItem"
      title="Vídeos Recientes"
      subtitle="Vídeos añadidos recientemente por la comunidad"
      @item-click="handleTrendingClick"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import GenericSearch from '@/components/shared/GenericSearch.vue';
import TrendingCarousel from '@/components/TrendingCarousel.vue';
import VideoCarouselItem from './VideoCarouselItem.vue';
import { useVideosStore } from '@/store/videos';
import { useAuthStore } from '@/store/auth';
import { useTrending } from '@/composables/useTrending';
import { storeToRefs } from 'pinia';
import Logger from '@/utils/logger';

const router = useRouter();
const videosStore = useVideosStore();
const authStore = useAuthStore();
const { isAuthenticated } = storeToRefs(authStore);
const {
  trendingVideos,
  isLoadingVideos: isLoadingTrending,
  errorVideos: errorTrending,
  fetchTrendingVideos
} = useTrending();

onMounted(async () => {
  if (isAuthenticated.value) {
    if (videosStore.videos.length === 0) {
      await videosStore.fetchVideos();
    }
    fetchTrendingVideos(12);
  }
});

watch(isAuthenticated, async (newValue) => {
  if (newValue) {
    if (videosStore.videos.length === 0) {
      Logger.debug('[VideoSearch] User authenticated, fetching user videos...');
      await videosStore.fetchVideos();
    }
    if (trendingVideos.value.length === 0) {
      Logger.debug('[VideoSearch] Fetching trending videos...');
      fetchTrendingVideos(12);
    }
  }
});

// Search videos via YouTube Data API
const searchVideos = async (query) => {
  try {
    Logger.debug(`Searching videos on YouTube: "${query}"`);

    const response = await authStore.apiCall('search_youtube_videos', {
      q: query,
      maxResults: 20
    });

    if (response.data.status === 'success') {
      return {
        results: response.data.data?.videos || [],
        stale: response.data.data?.stale === true,
        cached_at: response.data.data?.cached_at ?? null
      };
    } else {
      throw new Error(response.data.message || 'Error searching videos');
    }
  } catch (error) {
    Logger.error('Error searching videos via YouTube:', error);
    throw new Error('No se pudo buscar en YouTube. Verifica la configuración.');
  }
};

// Transform YouTube result to internal format
const transformResult = (result) => {
  return {
    id: result.id || result.youtube_id || result.youtubeId,
    youtube_id: result.id || result.youtube_id || result.youtubeId,
    youtubeId: result.id || result.youtube_id || result.youtubeId,
    title: result.title || result.name || '',
    channel_name: result.channel_name || result.channelName || '',
    channel_id: result.channel_id || result.channelId || '',
    cover_url: result.thumbnail || result.cover_url || result.coverUrl || null,
    coverUrl: result.thumbnail || result.cover_url || result.coverUrl || null,
    duration: result.duration || '',
    duration_seconds: result.duration_seconds || result.durationSeconds || 0,
    view_count: result.view_count || result.viewCount || 0,
    like_count: result.like_count || result.likeCount || 0,
    published_at: result.published_at || result.publishedAt || '',
    description: result.description || '',
    categories: result.categories || [],
    user_rating: null,
    userStatuses: [],
    itemType: 'video'
  };
};

// Navigate to video detail
const navigateToDetail = (router, video) => {
  Logger.debug('Navigating to video detail:', video);

  const youtubeId = video.youtube_id || video.youtubeId || video.id;

  router.push({
    name: 'VideoDetail',
    params: { youtubeId: youtubeId },
    state: { video: JSON.parse(JSON.stringify(video)) }
  });
};

// Handler for trending item clicks
const handleTrendingClick = (video) => {
  Logger.debug('Trending video clicked:', video);
  navigateToDetail(router, video);
};

const getResultKey = (result) => {
  return result.youtube_id || result.id || `video-${Date.now()}-${Math.random()}`;
};

const fetchAllowedStatuses = async () => {
  await videosStore.fetchAllowedStatuses();
  return Array.isArray(videosStore.allowedStatuses) ? videosStore.allowedStatuses : [];
};

const searchConfig = computed(() => ({
  title: 'Buscador de Vídeos (YouTube)',
  inputs: [
    {
      type: 'name',
      placeholder: 'Buscar vídeo o canal...',
      buttonText: '',
      idField: 'youtube_id',
      emptyMessage: 'Introduce el título de un vídeo o nombre de canal para buscar.',
      errorMessage: 'Error al buscar el vídeo.'
    }
  ],
  carouselItemComponent: VideoCarouselItem,
  itemProp: 'video',
  media: 'video',
  staleProvider: 'YouTube',
  searchHandler: searchVideos,
  transformResult: transformResult,
  navigateToDetail: navigateToDetail,
  getResultKey: getResultKey,
  fetchAllowedStatuses: fetchAllowedStatuses
}));
</script>

<style scoped lang="scss">
@use '@/assets/styles/components/search' as *;

.video-search-container {
  @include search-page;
}
</style>
