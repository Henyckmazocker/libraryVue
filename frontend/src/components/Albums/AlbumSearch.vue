<template>
  <div class="album-search-container">
    <!-- Search -->
    <GenericSearch :config="searchConfig" />

    <!-- Trending Albums -->
    <TrendingCarousel
      v-if="authStore.isAuthenticated"
      :items="trendingAlbums"
      :is-loading="isLoadingTrending"
      :error="errorTrending"
      type="albums"
      :item-component="AlbumCarouselItem"
      :title="t('search.popularAlbums')"
      :subtitle="t('search.popularAlbumsHint')"
      @item-click="handleTrendingClick"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import GenericSearch from '@/components/shared/GenericSearch.vue';
import TrendingCarousel from '@/components/TrendingCarousel.vue';
import AlbumCarouselItem from './AlbumCarouselItem.vue';
import { useAlbumsStore } from '@/store/albums';
import { useAuthStore } from '@/store/auth';
import { useTrending } from '@/composables/useTrending';
import { storeToRefs } from 'pinia';
import Logger from '@/utils/logger';
import { useI18n } from '@/composables/useI18n';
import { mediaRegistry } from '@/config/mediaRegistry';

const { t } = useI18n();

const router = useRouter();
const albumsStore = useAlbumsStore();
const authStore = useAuthStore();
const { isAuthenticated } = storeToRefs(authStore);
const {
  trendingAlbums,
  isLoadingAlbums: isLoadingTrending,
  errorAlbums: errorTrending,
  fetchTrendingAlbums
} = useTrending();

onMounted(async () => {
  if (isAuthenticated.value) {
    if (albumsStore.albums.length === 0) {
      await albumsStore.fetchAlbums();
    }
    fetchTrendingAlbums(12, 90);
  }
});

watch(isAuthenticated, async (newValue) => {
  if (newValue) {
    if (albumsStore.albums.length === 0) {
      Logger.debug('[AlbumSearch] User authenticated, fetching user albums...');
      await albumsStore.fetchAlbums();
    }
    if (trendingAlbums.value.length === 0) {
      Logger.debug('[AlbumSearch] Fetching trending albums...');
      fetchTrendingAlbums(12, 90);
    }
  }
});

// Search albums via Spotify proxy
const searchAlbums = async (query) => {
  try {
    Logger.debug(`Searching albums on Spotify: "${query}"`);

    const response = await authStore.apiCall('search_spotify_albums', {
      query: query,
      limit: 20
    });

    if (response.data.status === 'success') {
      return response.data.data?.albums || response.data.data || [];
    } else {
      throw new Error(t('toasts.albumSearchFailed'));
    }
  } catch (error) {
    Logger.error('Error searching albums via Spotify:', error);
    throw new Error(t('toasts.spotifyFailed'));
  }
};

// Transform Spotify result to internal format

// Navigate to album detail
const navigateToDetail = (router, album) => {
  Logger.debug('Navigating to album detail:', album);

  const albumData = {
    id: album.id || album.spotify_id,
    spotify_id: album.spotify_id || album.id,
    title: album.title || album.name,
    artist: album.artist,
    release_date: album.release_date,
    cover_url: album.cover_url || album.coverUrl,
    genres: album.genres,
    label: album.label,
    total_tracks: album.total_tracks,
    album_type: album.album_type,
    duration_ms: album.duration_ms,
    popularity: album.popularity,
    user_rating: album.user_rating,
    userStatuses: album.userStatuses || [],
    itemType: 'album'
  };

  router.push({
    name: 'AlbumDetail',
    params: { albumId: album.spotify_id || album.id },
    state: { album: JSON.parse(JSON.stringify(albumData)) }
  });
};

// Handler for trending item clicks
const handleTrendingClick = (album) => {
  Logger.debug('Trending album clicked:', album);
  navigateToDetail(router, album);
};

const getResultKey = (result) => {
  return result.spotify_id || result.id || `album-${Date.now()}-${Math.random()}`;
};

const fetchAllowedStatuses = async () => {
  await albumsStore.fetchAllowedStatuses();
  return Array.isArray(albumsStore.allowedStatuses) ? albumsStore.allowedStatuses : [];
};

const searchConfig = computed(() => ({
  title: t('searchPage.albums.title'),
  inputs: [
    {
      type: 'name',
      placeholder: t('searchPage.albums.placeholder'),
      buttonText: '',
      idField: 'spotify_id',
      emptyMessage: t('searchPage.albums.empty'),
      errorMessage: t('searchPage.albums.error')
    }
  ],
  carouselItemComponent: AlbumCarouselItem,
  itemProp: 'album',
  searchHandler: searchAlbums,
  // La transformación vive en el registry desde el M1: la comparte con
  // el buscador general en vez de existir dos veces.
  transformResult: mediaRegistry.album.api.search.transform,
  navigateToDetail: navigateToDetail,
  getResultKey: getResultKey,
  fetchAllowedStatuses: fetchAllowedStatuses
}));
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

@use '@/assets/styles/components/search' as *;

.album-search-container {
  @include search-page;
}
</style>
