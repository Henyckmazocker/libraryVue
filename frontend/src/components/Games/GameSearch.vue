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
      title="Juegos Populares"
      subtitle="Los juegos más populares en nuestra comunidad"
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
      return searchType === 'id' 
        ? (response.data.data.game ? [response.data.data.game] : [])
        : (response.data.data.games || []);
    } else {
      throw new Error(response.data.message || 'Error searching games');
    }
  } catch (error) {
    Logger.error('Error searching games in IGDB:', error);
    throw new Error('No se pudo buscar en IGDB. Verifica tus credenciales.');
  }
};

// Transformar resultado de IGDB al formato interno
const transformResult = (result) => {
  // Extraer desarrolladores y publishers de involved_companies
  const developers = result.involved_companies
    ?.filter(ic => ic.developer)
    .map(ic => ({ name: ic.company?.name || 'Unknown' })) || [];
  
  const publishers = result.involved_companies
    ?.filter(ic => ic.publisher)
    .map(ic => ({ name: ic.company?.name || 'Unknown' })) || [];
  
  // Formatear fecha de lanzamiento
  const releaseDate = result.first_release_date 
    ? new Date(result.first_release_date * 1000).toISOString().split('T')[0]
    : null;
  
  return {
    id: result.id,
    igdbId: result.id,
    gameId: result.id,
    title: result.name,
    name: result.name,
    originalTitle: result.name,
    releaseDate: releaseDate,
    released: releaseDate,
    coverUrl: result.cover?.url ? `https:${result.cover.url.replace('t_thumb', 't_cover_big')}` : null,
    background_image: result.cover?.url ? `https:${result.cover.url.replace('t_thumb', 't_cover_big')}` : null,
    rating: result.rating ? Math.round(result.rating / 20) : null, // IGDB usa 0-100, convertir a 0-5
    platforms: result.platforms || [],
    genres: result.genres || [],
    developers: developers,
    publishers: publishers,
    description: result.summary || '',
    user_rating: null,
    userStatuses: [],
    itemType: 'game'
  };
};

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
  title: 'Buscador de Videojuegos (IGDB)',
  inputs: [
    {
      type: 'auto',
      placeholder: 'Buscar por título o IGDB ID...',
      buttonText: '',
      idField: 'igdbId',
      emptyMessage: 'Introduce un título o ID de IGDB para buscar.',
      errorMessage: 'Error al buscar el juego.'
    }
  ],
  carouselItemComponent: GameCarouselItem,
  itemProp: 'game',
  searchHandler: searchGames,
  transformResult: transformResult,
  navigateToDetail: navigateToDetail,
  getResultKey: getResultKey,
  fetchAllowedStatuses: fetchAllowedStatuses,
  detectSearchType: detectSearchType
}));
</script>

<style scoped>
.game-search-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 40px;
}

.trending-section {
  width: 100%;
}

.trending-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin-bottom: 20px;
  color: var(--text-primary, #333);
}

@media (prefers-color-scheme: dark) {
  .trending-title {
    color: var(--text-primary, #e0e0e0);
  }
}
</style>
