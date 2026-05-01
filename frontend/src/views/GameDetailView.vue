<template>
  <div class="game-detail-view">
    <!-- Back Button -->
    <button @click="goBack" class="back-button">
      <i class="fas fa-arrow-left"></i>
      <span>Volver</span>
    </button>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Cargando información del juego...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <p>{{ error }}</p>
      <button @click="goBack" class="retry-button">Volver al buscador</button>
    </div>

    <!-- Game Details -->
    <div v-else-if="game" class="game-detail-content">
      <!-- Cabecera principal con portada y datos -->
      <div class="game-header">
        <div class="game-cover-large">
          <img 
            v-if="game.coverUrl || game.background_image" 
            :src="game.coverUrl || game.background_image" 
            :alt="game.title || game.name"
            class="cover-image-large"
          />
          <div v-else class="cover-placeholder">
            <i class="fas fa-gamepad"></i>
          </div>
        </div>
        
        <div class="game-main-info">
          <h1 class="game-title-large">{{ game.title || game.name }}</h1>
          
          <div v-if="game.developer || (game.developers && game.developers.length > 0)" class="game-developer-large">
            <i class="fas fa-code"></i>
            <span>por {{ Array.isArray(game.developers) ? game.developers.map(d => d.name).join(', ') : game.developer }}</span>
          </div>
          
          <div class="game-metadata">
            <span v-if="game.publisher || (game.publishers && game.publishers.length > 0)" class="metadata-item">
              <i class="fas fa-building"></i>
              {{ Array.isArray(game.publishers) ? game.publishers.map(p => p.name).join(', ') : game.publisher }}
            </span>
            <span v-if="game.releaseDate || game.released" class="metadata-item">
              <i class="fas fa-calendar"></i>
              {{ formatDate(game.releaseDate || game.released) }}
            </span>
            <span v-if="game.esrbRating || game.esrb_rating" class="metadata-item">
              <i class="fas fa-certificate"></i>
              {{ typeof game.esrb_rating === 'object' ? game.esrb_rating.name : (game.esrbRating || game.esrb_rating) }}
            </span>
          </div>
          
          <!-- Ratings -->
          <div v-if="game.rating || game.ratings_count" class="game-ratings">
            <div v-if="game.rating" class="rating-display">
              <i class="fas fa-star"></i>
              <span>{{ game.rating }} / 5</span>
            </div>
            <div v-if="game.ratings_count" class="rating-count">
              <i class="fas fa-users"></i>
              <span>{{ formatNumber(game.ratings_count) }} valoraciones</span>
            </div>
          </div>
          
          <!-- Categorías/Géneros -->
          <div v-if="genresText" class="game-categories">
            <i class="fas fa-tags"></i>
            <div class="category-tags">
              <span v-for="genre in genresArray" :key="genre" class="category-tag">
                {{ genre }}
              </span>
            </div>
          </div>
          
          <!-- Plataformas -->
          <div v-if="platformsText" class="game-platforms">
            <i class="fas fa-gamepad"></i>
            <div class="platform-tags">
              <span v-for="platform in platformsArray" :key="platform" class="platform-tag">
                <i :class="getPlatformIcon(platform)"></i>
                {{ platform }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Capturas adicionales -->
      <div v-if="screenshots.length > 0" class="screenshots-section">
        <h2 class="section-title">
          <i class="fas fa-images"></i>
          Capturas de Pantalla
        </h2>
        <div class="screenshots-grid">
          <img 
            v-for="(screenshot, index) in screenshots.slice(0, 6)" 
            :key="index"
            :src="screenshot.image"
            :alt="`Screenshot ${index + 1}`"
            class="screenshot-thumb"
          />
        </div>
      </div>

      <!-- Descripción del juego -->
      <div v-if="game.description || game.description_raw" class="game-description-section">
        <h2 class="section-title">
          <i class="fas fa-align-left"></i>
          Descripción
        </h2>
        <div class="game-description-content" v-html="sanitizeHtml(game.description || game.description_raw)"></div>
      </div>

      <!-- Enlaces externos -->
      <div v-if="game.websites && game.websites.length > 0" class="game-links-section">
        <h2 class="section-title">
          <i class="fas fa-external-link-alt"></i>
          Enlaces Externos
        </h2>
        <div class="external-links">
          <a 
            v-for="website in game.websites" 
            :key="website.url"
            :href="website.url"
            target="_blank"
            rel="noopener noreferrer"
            class="external-link"
          >
            <i class="fas fa-link"></i>
            {{ getWebsiteName(website.category) }}
          </a>
        </div>
      </div>

      <!-- Información adicional -->
      <div v-if="game.ratings_count || game.playtime || game.metacritic_score" class="game-additional-info">
        <h2 class="section-title">
          <i class="fas fa-info-circle"></i>
          Información Adicional
        </h2>
        <div class="additional-info-content">
          <div v-if="game.ratings_count" class="info-item">
            <strong>Número de valoraciones:</strong> {{ formatNumber(game.ratings_count) }}
          </div>
          <div v-if="game.playtime" class="info-item">
            <strong>Tiempo de juego promedio:</strong> {{ game.playtime }} horas
          </div>
          <div v-if="game.metacritic_score || game.metacriticScore" class="info-item">
            <strong>Puntuación Metacritic:</strong> {{ game.metacritic_score || game.metacriticScore }}
          </div>
        </div>
      </div>

      <!-- Library Item Form -->
      <div class="library-section">
        <h2>{{ existingGame ? 'Detalles en tu Biblioteca' : 'Añadir a tu Biblioteca' }}</h2>
        <LibraryGameItem
          v-if="allowedStatuses.length > 0"
          ref="libraryGameItemRef"
          :game="game"
          :allowedUserStatuses="allowedStatuses"
          :editable="!!existingGame"
          @delete-game="handleDeleteGame"
          @save-game="handleSaveGame"
          @edit-item="handleEditItem"
        />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <i class="fas fa-gamepad"></i>
      <p>No se encontró información del juego</p>
      <button @click="goBack" class="retry-button">Volver al buscador</button>
    </div>

    <!-- Edit Item Modal -->
    <EditItemModal
      v-if="editModal.isVisible"
      :item="editModal.item"
      :item-type="editModal.itemType"
      :allowed-statuses="allowedStatuses"
      :is-visible="editModal.isVisible"
      @close="closeEditModal"
      @saved="handleModalSaved"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, toRaw } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import LibraryGameItem from '@/components/Games/LibraryGameItem.vue';
import EditItemModal from '@/components/EditItemModal.vue';
import { useGamesStore } from '@/store/games';
import { useAuthStore } from '@/store/auth';
import Logger from '@/utils/logger';

const route = useRoute();
const router = useRouter();
const gamesStore = useGamesStore();
const authStore = useAuthStore();

// State
const game = ref((history.state && history.state.game) ? history.state.game : null);
const screenshots = ref([]);
// Si venimos con datos en el router state, no mostrar spinner (transición seamless)
const isLoading = ref(!game.value);
const error = ref(null);
const allowedStatuses = ref([]);
const libraryGameItemRef = ref(null);
const editModal = ref({
  isVisible: false,
  item: null,
  itemType: 'game'
});

// Computed
const isAuthenticated = computed(() => authStore.isAuthenticated);

const existingGame = computed(() => {
  if (!game.value) return null;
  const gameId = game.value.id || game.value.igdbId || game.value.gameId;
  return gamesStore.getGameById(gameId);
});

const genresText = computed(() => {
  if (!game.value) return '';
  if (typeof game.value.genres === 'string') return game.value.genres;
  if (Array.isArray(game.value.genres)) {
    return game.value.genres.map(g => g.name || g).join(', ');
  }
  return '';
});

const genresArray = computed(() => {
  return genresText.value ? genresText.value.split(', ').filter(g => g) : [];
});

const platformsText = computed(() => {
  if (!game.value) return '';
  if (typeof game.value.platforms === 'string') return game.value.platforms;
  if (Array.isArray(game.value.platforms)) {
    return game.value.platforms
      .map(p => p.platform?.name || p.name || p)
      .join(', ');
  }
  return '';
});

const platformsArray = computed(() => {
  return platformsText.value ? platformsText.value.split(', ').filter(p => p) : [];
});

// Methods
const goBack = () => {
  if (window.history.length > 1) {
    router.go(-1);
  } else {
    router.push({ name: 'GameSearch' });
  }
};

const getWebsiteName = (category) => {
  const categories = {
    1: 'Sitio Oficial',
    2: 'Wikia',
    3: 'Wikipedia',
    4: 'Facebook',
    5: 'Twitter',
    6: 'Twitch',
    8: 'Instagram',
    9: 'YouTube',
    10: 'iPhone',
    11: 'iPad',
    12: 'Android',
    13: 'Steam',
    14: 'Reddit',
    15: 'Discord',
    16: 'Google+',
    17: 'Tumblr',
    18: 'LinkedIn'
  };
  return categories[category] || 'Ver enlace';
};

const getPlatformIcon = (platform) => {
  const platformLower = platform.toLowerCase();
  if (platformLower.includes('playstation') || platformLower.includes('ps')) return 'fab fa-playstation';
  if (platformLower.includes('xbox')) return 'fab fa-xbox';
  if (platformLower.includes('nintendo') || platformLower.includes('switch')) return 'fas fa-gamepad';
  if (platformLower.includes('pc') || platformLower.includes('windows')) return 'fab fa-windows';
  if (platformLower.includes('linux')) return 'fab fa-linux';
  if (platformLower.includes('mac')) return 'fab fa-apple';
  if (platformLower.includes('android') || platformLower.includes('ios')) return 'fas fa-mobile-alt';
  return 'fas fa-gamepad';
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  return date.toLocaleDateString('es-ES', { year: 'numeric', month: 'long', day: 'numeric' });
};

const formatNumber = (num) => {
  return num.toLocaleString('es-ES');
};

const sanitizeHtml = (html) => {
  if (!html) return '';
  // Simple sanitization - remove dangerous tags but keep basic formatting
  return html.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
};

const transformGameData = (igdbGame) => {
  // Extraer desarrolladores y publishers de involved_companies
  const developers = igdbGame.involved_companies
    ?.filter(ic => ic.developer)
    .map(ic => ({ name: ic.company?.name || 'Unknown' })) || [];
  
  const publishers = igdbGame.involved_companies
    ?.filter(ic => ic.publisher)
    .map(ic => ({ name: ic.company?.name || 'Unknown' })) || [];
  
  // Formatear fecha de lanzamiento
  const releaseDate = igdbGame.first_release_date 
    ? new Date(igdbGame.first_release_date * 1000).toISOString().split('T')[0]
    : null;
  
  return {
    id: igdbGame.id,
    igdbId: igdbGame.id,
    gameId: igdbGame.id,
    title: igdbGame.name,
    name: igdbGame.name,
    originalTitle: igdbGame.name,
    releaseDate: releaseDate,
    released: releaseDate,
    coverUrl: igdbGame.cover?.url ? `https:${igdbGame.cover.url.replace('t_thumb', 't_cover_big')}` : null,
    background_image: igdbGame.cover?.url ? `https:${igdbGame.cover.url.replace('t_thumb', 't_1080p')}` : null,
    description: igdbGame.summary || '',
    description_raw: igdbGame.summary || '',
    rating: igdbGame.rating ? Math.round(igdbGame.rating / 20) : null,
    ratings_count: igdbGame.rating_count || 0,
    esrb_rating: igdbGame.age_ratings?.find(r => r.category === 1), // ESRB
    esrbRating: igdbGame.age_ratings?.find(r => r.category === 1)?.rating,
    platforms: igdbGame.platforms || [],
    genres: igdbGame.genres || [],
    developers: developers,
    publishers: publishers,
    websites: igdbGame.websites || [],
    user_rating: null,
    userStatuses: [],
    itemType: 'game'
  };
};

const fetchGameDetails = async (gameId) => {
  // Solo mostrar spinner si no hay datos previos (evitar flash en enrichment)
  const isBackgroundEnrichment = !!game.value;
  if (!isBackgroundEnrichment) {
    isLoading.value = true;
  }
  error.value = null;

  try {
    Logger.debug(`[GameDetailView] Fetching details for IGDB ID: ${gameId}`);
    
    // Use backend proxy to get game details (including screenshots)
    const response = await authStore.apiCall('get_igdb_game_details', {
      gameId: parseInt(gameId)
    });

    if (response.data.status === 'success' && response.data.data) {
      const gameData = response.data.data;
      game.value = transformGameData(gameData);
      
      // Process screenshots from detailed_screenshots field
      screenshots.value = (gameData.detailed_screenshots || []).map(s => ({
        image: `https:${s.url.replace('t_thumb', 't_screenshot_med')}`
      }));
      
      Logger.debug(`[GameDetailView] Game loaded successfully:`, game.value.title);
    } else {
      error.value = response.data.message || 'No se encontró información del juego.';
      Logger.error(`[GameDetailView] Backend API error:`, response.data.message);
    }
  } catch (err) {
    error.value = 'No se pudo obtener información del juego. Verifica el ID.';
    Logger.error(`[GameDetailView] Error fetching game details:`, err);
  } finally {
    if (!isBackgroundEnrichment) {
      isLoading.value = false;
    }
  }
};

const handleDeleteGame = async ({ gameId }) => {
  try {
    Logger.debug('[GameDetailView] Deleting game:', gameId);
    const result = await gamesStore.deleteGame(gameId);
    
    if (result.success) {
      alert('Juego eliminado de tu biblioteca');
      goBack();
    } else {
      alert('Error al eliminar el juego');
    }
  } catch (err) {
    Logger.error('[GameDetailView] Error deleting game:', err);
    alert('Error al eliminar el juego');
  }
};

const handleSaveGame = async (data) => {
  try {
    Logger.debug('[GameDetailView] Saving game to library:', data);
    
    const result = await gamesStore.addGame(data.game, data.statuses);
    
    if (result.success) {
      if (libraryGameItemRef.value) {
        libraryGameItemRef.value.setSaveSuccess();
      }
      if (game.value) {
        game.value.userStatuses = data.statuses;
      }
    } else {
      if (libraryGameItemRef.value) {
        libraryGameItemRef.value.setSaveError();
      }
      alert('Error al añadir el juego');
    }
  } catch (err) {
    Logger.error('[GameDetailView] Error saving game:', err);
    if (libraryGameItemRef.value) {
      libraryGameItemRef.value.setSaveError();
    }
    alert('Error al añadir el juego');
  }
};

const handleEditItem = async (gameData) => {
  Logger.debug('[GameDetailView] Opening edit modal for game:', gameData);

  // Ensure games are loaded in the store before opening the modal.
  if (gamesStore.games.length === 0) {
    await gamesStore.fetchGames();
  }

  const storeGame = existingGame.value ? toRaw(existingGame.value) : null;

  const itemForModal = storeGame
    ? {
        ...game.value,
        user_rating: storeGame.user_rating ?? null,
        userStatuses: Array.isArray(storeGame.userStatuses) ? [...storeGame.userStatuses] : [],
        hoursPlayed: storeGame.hoursPlayed ?? storeGame.hours_played ?? null,
        platformPlayed: storeGame.platformPlayed ?? storeGame.platform_played ?? '',
        dateStarted: storeGame.dateStarted ?? storeGame.date_started ?? '',
        dateFinished: storeGame.dateFinished ?? storeGame.date_finished ?? '',
        personalNotes: storeGame.personalNotes ?? storeGame.personal_notes ?? storeGame.notes ?? '',
        ownershipFormat: storeGame.ownershipFormat ?? storeGame.ownership_format ?? null,
        ownership_format: storeGame.ownership_format ?? storeGame.ownershipFormat ?? null,
        ownership_format_id: storeGame.ownershipFormat?.id ?? storeGame.ownership_format?.id ?? null,
        tags: storeGame.tags ?? null,
      }
    : game.value;

  editModal.value = {
    isVisible: true,
    item: itemForModal,
    itemType: 'game'
  };
};

const closeEditModal = () => {
  editModal.value = {
    isVisible: false,
    item: null,
    itemType: 'game'
  };
};

const handleModalSaved = async (updatedItem) => {
  Logger.debug('[GameDetailView] Game saved from modal, updating local data', updatedItem);
  
  // Cerrar el modal
  closeEditModal();
  
  try {
    // Actualizar inmediatamente con datos del evento (optimista)
    if (game.value && updatedItem) {
      game.value = {
        ...game.value,
        ...updatedItem,
        user_rating: updatedItem.user_rating,
        userStatuses: updatedItem.userStatuses,
        hoursPlayed: updatedItem.hoursPlayed ?? game.value.hoursPlayed,
        platformPlayed: updatedItem.platformPlayed ?? game.value.platformPlayed,
        dateStarted: updatedItem.dateStarted ?? game.value.dateStarted,
        dateFinished: updatedItem.dateFinished ?? game.value.dateFinished,
        personalNotes: updatedItem.personalNotes ?? game.value.personalNotes
      };
    }
    
    // Actualizar en el store local de games también
    const gameInStore = gamesStore.games.find(g => 
      g.id === game.value.id || g.rawgId === game.value.rawgId
    );
    if (gameInStore) {
      Object.assign(gameInStore, updatedItem);
    }
    
    // Llamar al método de éxito del componente hijo
    if (libraryGameItemRef.value) {
      libraryGameItemRef.value.setEditSuccess();
    }
    
    Logger.info('[GameDetailView] Game data updated successfully');
    
    // Opcional: Recargar en segundo plano para sincronizar (sin bloquear UI)
    setTimeout(() => {
      gamesStore.fetchGames().catch(err => {
        Logger.error('[GameDetailView] Background refresh failed:', err);
      });
    }, 500);
  } catch (err) {
    Logger.error('[GameDetailView] Error updating game data:', err);
    if (libraryGameItemRef.value) {
      libraryGameItemRef.value.setEditError();
    }
  }
};

const loadGameData = async () => {
  Logger.debug('[GameDetailView] Loading game data');

  // Datos ya cargados eagerly desde history.state, o via route.state
  const hasEagerData = !!game.value;

  if (hasEagerData || (route.state && route.state.game)) {
    if (!hasEagerData && route.state.game) {
      game.value = route.state.game;
    }
    isLoading.value = false;
    Logger.debug('[GameDetailView] Using pre-loaded game data (seamless)');

    // Cargar datos de biblioteca en segundo plano
    await Promise.all([
      gamesStore.games.length === 0 ? gamesStore.fetchGames() : Promise.resolve(),
      gamesStore.allowedStatuses.length === 0 ? gamesStore.fetchAllowedStatuses() : Promise.resolve()
    ]);
    allowedStatuses.value = gamesStore.allowedStatuses;

    // Fetch full details in background (sin mostrar spinner)
    fetchGameDetails(route.params.gameId)
      .then(() => _mergeExistingGameData())
      .catch(err =>
        Logger.warn('[GameDetailView] Background enrichment failed:', err)
      );
  } else {
    // Sin state: acceso directo por URL — mostrar spinner
    await Promise.all([
      gamesStore.games.length === 0 ? gamesStore.fetchGames() : Promise.resolve(),
      gamesStore.allowedStatuses.length === 0 ? gamesStore.fetchAllowedStatuses() : Promise.resolve()
    ]);
    allowedStatuses.value = gamesStore.allowedStatuses;
    await fetchGameDetails(route.params.gameId);
  }
  
  _mergeExistingGameData();
};

const _mergeExistingGameData = () => {
  if (!existingGame.value || !game.value) return;

  Logger.debug('[GameDetailView] Merging with existing game data');
  game.value = {
    ...game.value,
    user_rating: existingGame.value.user_rating,
    userStatuses: existingGame.value.userStatuses || [],
    hoursPlayed: existingGame.value.hoursPlayed || existingGame.value.hours_played || 0,
    platformPlayed: existingGame.value.platformPlayed || existingGame.value.platform_played || '',
    notes: existingGame.value.notes || existingGame.value.personalNotes || existingGame.value.personal_notes || '',
    dateStarted: existingGame.value.dateStarted || existingGame.value.date_started || '',
    dateFinished: existingGame.value.dateFinished || existingGame.value.date_finished || '',
    ownershipFormat: existingGame.value.ownershipFormat ?? existingGame.value.ownership_format ?? null,
    ownership_format: existingGame.value.ownership_format ?? existingGame.value.ownershipFormat ?? null,
    ownership_format_id: existingGame.value.ownership_format_id ?? existingGame.value.ownershipFormat?.id ?? null,
    tags: existingGame.value.tags ?? null,
  };
};

onMounted(async () => {
  Logger.debug('[GameDetailView] Component mounted');
  
  if (isAuthenticated.value) {
    await loadGameData();
  }
});

watch(isAuthenticated, async (newValue) => {
  if (newValue && !game.value) {
    Logger.debug('[GameDetailView] User authenticated, loading game data...');
    await loadGameData();
  }
});
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    font-size: 4rem;
  }

  .game-main-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: spacing(sm);
  }

  .game-developer-large {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    font-size: 1.1rem;
    color: var(--color-text-secondary);
    margin-bottom: spacing(xs);

    i { color: var(--color-card-game-accent); }

    @include responsive-below(md) {
      font-size: 1rem;
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

  .rating-display,
  .rating-count {
    display: flex;
    align-items: center;
    gap: spacing(2xs);
    padding: spacing(xs) spacing(md);
    background: var(--color-background-soft);
    border-radius: radius(sm);
    font-size: 0.95rem;

    i { color: var(--color-card-game-accent); }
  }

  .game-categories,
  .game-platforms {
    display: flex;
    align-items: flex-start;
    gap: spacing(xs);

    > i {
      color: var(--color-card-game-accent);
      margin-top: 6px;
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
    font-size: 1rem;
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
    font-size: 0.9rem;

    strong {
      margin-right: spacing(xs);
      color: var(--color-text-secondary);
    }
  }

  .library-section {
    border-top: 3px solid var(--color-card-game-accent);

    h2 {
      font-size: 1.5rem;
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

