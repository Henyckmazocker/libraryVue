import { storeToRefs } from 'pinia';
import { useGamesStore } from '@/store/games';
import { useAuthStore } from '@/store/auth';
import { useConfirmationModal } from './useConfirmationModal';
import Logger from '@/utils/logger';

/**
 * Composable para gestión de videojuegos
 * Wrapper ligero del store Pinia useGamesStore
 * Proporciona helpers adicionales y lógica específica de UI
 * 
 * La lógica de negocio está en el store, aquí solo helpers de UI
 */
export function useGames() {
  const gamesStore = useGamesStore();
  const authStore = useAuthStore();
  
  // ✅ Estado reactivo via storeToRefs (directamente del store)
  const {
    games,
    allowedStatuses,
    userTags,
    isLoading,
    error,
    searchResults,
    isSearching,
    lastSearchQuery,
    // Getters computados
    totalGames,
    hasGames,
    hasSearchResults,
    gamesWithRating,
    averageRating,
    gamesByStatus,
    gameCountByStatus
  } = storeToRefs(gamesStore);

  // ✅ Actions del store (delegación directa)
  const {
    fetchGames,
    searchGames: searchGamesStore,
    fetchAllowedStatuses,
    fetchUserTags,
    createTag: createTagStore,
    updateGameTags,
    clearSearchResults,
    clearError
  } = gamesStore;

  /**
   * Agrega un juego con validación de estados permitidos
   */
  const addGame = async (game, statuses = []) => {
    // Pre-cargar estados permitidos si no existen
    if (allowedStatuses.value.length === 0) {
      await fetchAllowedStatuses();
    }
    
    return await gamesStore.addGame(game, statuses);
  };

  /**
   * Elimina un juego CON confirmación modal
   */
  const deleteGame = async (gameId, skipConfirmation = false) => {
    const { confirmDelete } = useConfirmationModal();
    
    try {
      const game = games.value.find(g => 
        g.id === gameId || g.rawgId === gameId || g.gameId === gameId
      );
      const gameTitle = game ? (game.title || game.name) : `ID: ${gameId}`;

      // Mostrar confirmación si no se omite
      if (!skipConfirmation) {
        const confirmed = await confirmDelete(
          gameTitle,
          'Esta acción no se puede deshacer'
        );
        
        if (!confirmed) {
          return { success: false, cancelled: true };
        }
      }

      return await gamesStore.deleteGame(gameId);
    } catch (err) {
      Logger.error('[useGames] Error in deleteGame wrapper:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Actualiza la calificación de un juego
   */
  const updateGameRating = async (gameId, rating) => {
    return await gamesStore.updateGameRating(gameId, rating);
  };

  /**
   * Actualiza los estados de un juego
   */
  const updateGameStatuses = async (gameId, statuses) => {
    return await gamesStore.updateGameStatuses(gameId, statuses);
  };

  /**
   * Edita un juego completo
   */
  const editUserGame = async (gameId, userId, data = {}, tags = [], notes = []) => {
    try {
      Logger.debug('[useGames] Editing user_game:', { gameId, userId, data, tags, notes });
      
      const response = await authStore.authenticatedApiCall('edit_user_game', {
        gameId: gameId,
        userId,
        data,
        tags,
        notes
      });
      
      if (response.data.status === 'success') {
        // Actualizar juego en el store local
        const gameIndex = games.value.findIndex(g => 
          g.id === gameId || g.rawgId === gameId || g.gameId === gameId
        );
        if (gameIndex !== -1) {
          games.value[gameIndex] = {
            ...games.value[gameIndex],
            user_rating: data.personalRating !== undefined ? data.personalRating : games.value[gameIndex].user_rating,
            userStatuses: data.statuses || games.value[gameIndex].userStatuses,
            hoursPlayed: data.hoursPlayed !== undefined ? data.hoursPlayed : games.value[gameIndex].hoursPlayed,
            platformPlayed: data.platformPlayed !== undefined ? data.platformPlayed : games.value[gameIndex].platformPlayed,
            dateStarted: data.dateStarted !== undefined ? data.dateStarted : games.value[gameIndex].dateStarted,
            dateFinished: data.dateFinished !== undefined ? data.dateFinished : games.value[gameIndex].dateFinished,
            personalNotes: data.personalNotes !== undefined ? data.personalNotes : games.value[gameIndex].personalNotes
          };
        }
        
        Logger.debug('[useGames] User game edited successfully');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Error editing user_game');
      }
    } catch (err) {
      Logger.error('[useGames] Error editing user_game:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Crea un nuevo tag CON validación
   */
  const createUserTag = async (tagName, color = '#1976d2') => {
    if (!tagName || tagName.trim().length === 0) {
      return { success: false, message: 'Tag name cannot be empty' };
    }
    
    return await createTagStore(tagName, color);
  };

  /**
   * Obtiene los tags de un juego específico
   */
  const getGameTags = async (gameId) => {
    try {
      const response = await authStore.authenticatedApiCall('get_game_tags', { gameId: gameId });
      
      if (response.data.status === 'success') {
        return { success: true, data: response.data.data || [] };
      } else {
        throw new Error(response.data.message || 'Error getting game tags');
      }
    } catch (err) {
      Logger.error('[useGames] Error getting game tags:', err);
      return { success: false, message: err.message };
    }
  };

  // ==========================================
  // HELPERS DE UTILIDAD (sin estado - solo funciones)
  // ==========================================

  /**
   * Busca un juego específico por ID
   */
  const findGameById = (gameId) => {
    return games.value.find(g => 
      g.id === gameId || g.rawgId === gameId || g.gameId === gameId
    );
  };

  /**
   * Busca un juego específico por IGDB ID
   * @param {string|number} igdbId - ID de IGDB del juego
   */
  const findGameByIGDBId = (igdbId) => {
    return games.value.find(game => 
      game.igdbId === igdbId || game.id === igdbId || game.gameId === igdbId
    );
  };

  /**
   * Filtra juegos por criterios
   */
  const filterGames = (criteria) => {
    return games.value.filter(game => {
      let matches = true;

      if (criteria.status) {
        matches = matches && game.userStatuses && game.userStatuses.includes(criteria.status);
      }
      if (criteria.rating !== undefined) {
        matches = matches && game.user_rating === criteria.rating;
      }
      if (criteria.hasRating !== undefined) {
        matches = matches && (criteria.hasRating ? game.user_rating > 0 : !game.user_rating || game.user_rating === 0);
      }
      if (criteria.developer) {
        matches = matches && game.developer && game.developer.toLowerCase().includes(criteria.developer.toLowerCase());
      }
      if (criteria.title) {
        const title = game.title || game.name;
        matches = matches && title && title.toLowerCase().includes(criteria.title.toLowerCase());
      }
      if (criteria.platform) {
        const platforms = typeof game.platforms === 'string' ? game.platforms : 
          (Array.isArray(game.platforms) ? game.platforms.map(p => p.platform?.name || p.name || p).join(' ') : '');
        matches = matches && platforms.toLowerCase().includes(criteria.platform.toLowerCase());
      }

      return matches;
    });
  };

  /**
   * Wrapper de búsqueda (alias)
   */
  const searchGamesWrapper = async (query) => {
    return await searchGamesStore(query);
  };

  return {
    // ===== ESTADO REACTIVO (desde store) =====
    games,
    searchResults,
    allowedStatuses,
    userTags,
    isLoading,
    isSearching,
    error,
    lastSearchQuery,

    // ===== GETTERS COMPUTADOS (desde store) =====
    totalGames,
    hasGames,
    hasSearchResults,
    gamesWithRating,
    averageRating,
    gamesByStatus,
    gameCountByStatus,

    // ===== MÉTODOS PRINCIPALES =====
    fetchGames,                           // Directo del store
    searchGames: searchGamesWrapper,      // Alias
    addGame,                              // Wrapper con validación
    editUserGame,                         // Wrapper con formateo
    deleteGame,                           // Wrapper con confirmación
    updateGameRating,                     // Directo del store
    updateGameStatuses,                   // Directo del store
    fetchAllowedStatuses,                 // Directo del store

    // ===== TAGS =====
    fetchUserTags,                        // Directo del store
    createUserTag,                        // Wrapper con validación
    getGameTags,                          // Método específico
    updateGameTags,                       // Directo del store

    // ===== UTILIDADES =====
    findGameById,                         // Helper puro
    findGameByIGDBId,                     // Helper puro
    filterGames,                          // Helper puro
    clearSearchResults,                   // Directo del store
    clearError                            // Directo del store
  };
}
