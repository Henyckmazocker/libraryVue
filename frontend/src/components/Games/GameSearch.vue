<template>
  <div class="game-search-container">
    <!-- Búsqueda -->
    <GenericSearch :config="searchConfig" />
    
    <!-- Trending/Popular Games -->
    <TrendingCarousel
      v-if="authStore.isAuthenticated"
      :items="trendingGames"
      :is-loading="isLoadingTrending"
      :error="errorTrending"
      type="games"
      :item-component="GameCarouselItem"
      :title="t('search.popularGames')"
      :subtitle="t('search.popularGamesHint')"
      @item-click="handleTrendingClick"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import GenericSearch from '@/components/shared/GenericSearch.vue';
import TrendingCarousel from '@/components/TrendingCarousel.vue';
import GameCarouselItem from './GameCarouselItem.vue';
import { useGamesStore } from '@/store/games';
import { useAuthStore } from '@/store/auth';
import { useTrending } from '@/composables/useTrending';
import { storeToRefs } from 'pinia';
import Logger from '@/utils/logger';
import { useI18n } from '@/composables/useI18n';
import { mediaRegistry } from '@/config/mediaRegistry';

const { t } = useI18n();

const router = useRouter();
const gamesStore = useGamesStore();
const authStore = useAuthStore();
const { isAuthenticated } = storeToRefs(authStore);
const { 
  trendingGames, 
  isLoadingGames: isLoadingTrending, 
  errorGames: errorTrending,
  fetchTrendingGames 
} = useTrending();

// Cargar juegos del usuario y trending al montar (solo si está autenticado)
onMounted(async () => {
  if (isAuthenticated.value) {
    // Cargar juegos de la biblioteca para poder verificar qué items tiene el usuario
    if (gamesStore.games.length === 0) {
      await gamesStore.fetchGames();
    }
    // Cargar trending games
    fetchTrendingGames(12, 90); // 12 juegos, últimos 90 días
  }
});

// Cargar juegos cuando se autentique
watch(isAuthenticated, async (newValue) => {
  if (newValue) {
    // Cargar biblioteca del usuario
    if (gamesStore.games.length === 0) {
      Logger.debug('[GameSearch] User authenticated, fetching user games...');
      await gamesStore.fetchGames();
    }
    // Cargar trending games
    if (trendingGames.value.length === 0) {
      Logger.debug('[GameSearch] Fetching trending games...');
      fetchTrendingGames(12, 90);
    }
  }
});

// Detectar tipo de búsqueda (IGDB ID o nombre)
const detectSearchType = (query) => {
  // IGDB IDs are numeric
  if (/^\d+$/.test(query.trim())) {
    return 'id';
  }
  return 'name';
};

// Buscar juegos en IGDB API (usando backend como proxy)
const searchGames = async (query, searchType) => {
  try {
    Logger.debug(`Searching games: "${query}" (type: ${searchType})`);
    
    let response;
    
    if (searchType === 'id') {
      // Búsqueda por ID
      response = await authStore.apiCall('get_igdb_game_by_id', {
        gameId: parseInt(query)
      });
    } else {
      // Búsqueda por nombre
      response = await authStore.apiCall('search_igdb_games', {
        query: query,
        limit: 20
      });
    }
    
    if (response.data.status === 'success') {
      // La búsqueda por ID no pasa por la caché resiliente: no lleva sobre.
      if (searchType === 'id') {
        return response.data.data.game ? [response.data.data.game] : [];
      }

      return {
        results: response.data.data.games || [],
        stale: response.data.data.stale === true,
        cached_at: response.data.data.cached_at ?? null
      };
    } else {
      throw new Error(t('toasts.gameSearchFailed'));
    }
  } catch (error) {
    Logger.error('Error searching games in IGDB:', error);
    throw new Error(t('toasts.igdbFailed'));
  }
};

// Transformar resultado de IGDB al formato interno

// Navegar al detalle del juego
const navigateToDetail = (router, game) => {
  Logger.debug('Navigating to game detail:', game);
  
  const gameData = {
    id: game.id || game.igdbId,
    igdbId: game.igdbId || game.id,
    gameId: game.gameId || game.id,
    title: game.title || game.name,
    name: game.name || game.title,
    originalTitle: game.originalTitle || game.name,
    releaseDate: game.releaseDate || game.released,
    coverUrl: game.coverUrl || game.background_image,
    rating: game.rating,
    platforms: game.platforms,
    genres: game.genres,
    developers: game.developers,
    publishers: game.publishers,
    description: game.description,
    user_rating: game.user_rating,
    userStatuses: game.userStatuses || [],
    itemType: 'game'
  };
  
  router.push({
    name: 'GameDetail',
    params: { gameId: game.id || game.igdbId },
    state: { game: JSON.parse(JSON.stringify(gameData)) }
  });
};

// Handler para clicks en trending
const handleTrendingClick = (game) => {
  Logger.debug('Trending game clicked:', game);
  
  const gameData = {
    id: game.id || game.igdbId,
    igdbId: game.igdbId || game.id,
    gameId: game.gameId || game.id,
    title: game.title || game.name,
    name: game.name || game.title,
    releaseDate: game.releaseDate || game.released,
    coverUrl: game.coverUrl || game.background_image,
    rating: game.rating,
    platforms: game.platforms,
    genres: game.genres,
    user_rating: game.avg_rating || 0,
    userStatuses: [],
    itemType: 'game'
  };
  
  router.push({
    name: 'GameDetail',
    params: { gameId: game.id || game.igdbId },
    state: { game: JSON.parse(JSON.stringify(gameData)) }
  });
};

// Obtener clave única del resultado
const getResultKey = (result) => {
  return result.id || result.igdbId || `game-${Date.now()}-${Math.random()}`;
};

// Cargar estados permitidos
const fetchAllowedStatuses = async () => {
  await gamesStore.fetchAllowedStatuses();
  return Array.isArray(gamesStore.allowedStatuses) 
    ? gamesStore.allowedStatuses 
    : [];
};

// Configuración del componente genérico
const searchConfig = computed(() => ({
  title: t('searchPage.games.title'),
  inputs: [
    {
      type: 'auto',
      placeholder: t('searchPage.games.placeholder'),
      buttonText: '',
      idField: 'igdbId',
      emptyMessage: t('searchPage.games.empty'),
      errorMessage: t('searchPage.games.error')
    }
  ],
  carouselItemComponent: GameCarouselItem,
  itemProp: 'game',
  media: 'game',
  staleProvider: 'IGDB',
  searchHandler: searchGames,
  // La transformación vive en el registry desde el M1: la comparte con
  // el buscador general en vez de existir dos veces.
  transformResult: mediaRegistry.game.api.search.transform,
  navigateToDetail: navigateToDetail,
  getResultKey: getResultKey,
  fetchAllowedStatuses: fetchAllowedStatuses,
  detectSearchType: detectSearchType
}));
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

@use '@/assets/styles/components/search' as *;

.game-search-container {
  @include search-page;
}
</style>
