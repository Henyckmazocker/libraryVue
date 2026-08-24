import { storeToRefs } from 'pinia';
import { useAlbumsStore } from '@/store/albums';
import { useAuthStore } from '@/store/auth';
import { useConfirmationModal } from './useConfirmationModal';
import Logger from '@/utils/logger';

/**
 * Composable for music album management
 * Lightweight wrapper around the Pinia useAlbumsStore
 * Provides UI-specific helpers and additional logic
 *
 * Business logic lives in the store; only UI helpers here.
 */
export function useAlbums() {
  const albumsStore = useAlbumsStore();
  const authStore = useAuthStore();

  // Reactive state via storeToRefs (directly from store)
  const {
    albums,
    allowedStatuses,
    userTags,
    isLoading,
    error,
    searchResults,
    isSearching,
    lastSearchQuery,
    // Computed getters
    totalAlbums,
    hasAlbums,
    hasSearchResults,
    albumsWithRating,
    averageRating,
    albumsByStatus,
    albumCountByStatus
  } = storeToRefs(albumsStore);

  // Direct store actions (delegated)
  const {
    fetchAlbums,
    searchAlbums: searchAlbumsStore,
    fetchAllowedStatuses,
    fetchUserTags,
    createTag: createTagStore,
    updateAlbumTags,
    clearSearchResults,
    clearError
  } = albumsStore;

  /**
   * Add an album with pre-loaded allowed statuses
   */
  const addAlbum = async (album, statuses = []) => {
    if (allowedStatuses.value.length === 0) {
      await fetchAllowedStatuses();
    }
    return await albumsStore.addAlbum(album, statuses);
  };

  /**
   * Delete an album WITH confirmation modal
   */
  const deleteAlbum = async (albumId, skipConfirmation = false) => {
    const { confirmDelete } = useConfirmationModal();

    try {
      const album = albums.value.find(a => a.id === albumId);
      const albumTitle = album ? (album.title || album.name) : `ID: ${albumId}`;

      if (!skipConfirmation) {
        const confirmed = await confirmDelete(
          albumTitle,
          'Esta acción no se puede deshacer'
        );

        if (!confirmed) {
          return { success: false, cancelled: true };
        }
      }

      return await albumsStore.deleteAlbum(albumId);
    } catch (err) {
      Logger.error('[useAlbums] Error in deleteAlbum wrapper:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Update album rating
   */
  const updateAlbumRating = async (albumId, rating) => {
    return await albumsStore.updateAlbumRating(albumId, rating);
  };

  /**
   * Update album statuses
   */
  const updateAlbumStatuses = async (albumId, statuses) => {
    return await albumsStore.updateAlbumStatuses(albumId, statuses);
  };

  /**
   * Edit user album (full update via API)
   */
  const editUserAlbum = async (albumId, userId, data = {}, tags = [], notes = []) => {
    try {
      Logger.debug('[useAlbums] Editing user_album:', { albumId, userId, data, tags, notes });

      const response = await authStore.authenticatedApiCall('edit_user_album', {
        albumId: albumId,
        userId,
        data,
        tags,
        notes
      });

      if (response.data.status === 'success') {
        const albumIndex = albums.value.findIndex(a => a.id === albumId);
        if (albumIndex !== -1) {
          albums.value[albumIndex] = {
            ...albums.value[albumIndex],
            user_rating: data.personalRating !== undefined ? data.personalRating : albums.value[albumIndex].user_rating,
            userStatuses: data.statuses || albums.value[albumIndex].userStatuses,
            listenCount: data.listenCount !== undefined ? data.listenCount : albums.value[albumIndex].listenCount,
            favoriteTrack: data.favoriteTrack !== undefined ? data.favoriteTrack : albums.value[albumIndex].favoriteTrack,
            dateStarted: data.dateStarted !== undefined ? data.dateStarted : albums.value[albumIndex].dateStarted,
            dateFinished: data.dateFinished !== undefined ? data.dateFinished : albums.value[albumIndex].dateFinished,
            personalNotes: data.personalNotes !== undefined ? data.personalNotes : albums.value[albumIndex].personalNotes,
            ownership_format_id: data.ownership_format_id !== undefined ? data.ownership_format_id : albums.value[albumIndex].ownership_format_id,
            ownershipFormat: data.ownershipFormat !== undefined ? data.ownershipFormat : albums.value[albumIndex].ownershipFormat
          };
        }

        Logger.debug('[useAlbums] User album edited successfully');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Error editing user_album');
      }
    } catch (err) {
      Logger.error('[useAlbums] Error editing user_album:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Create a new tag with validation
   */
  const createUserTag = async (tagName, color = '#1976d2') => {
    if (!tagName || tagName.trim().length === 0) {
      return { success: false, message: 'Tag name cannot be empty' };
    }
    return await createTagStore(tagName, color);
  };

  /**
   * Fetch tags for a specific album
   */
  const getAlbumTags = async (albumId) => {
    try {
      const response = await authStore.authenticatedApiCall('get_album_tags', { albumId });

      if (response.data.status === 'success') {
        return { success: true, data: response.data.data || [] };
      } else {
        throw new Error(response.data.message || 'Error getting album tags');
      }
    } catch (err) {
      Logger.error('[useAlbums] Error getting album tags:', err);
      return { success: false, message: err.message };
    }
  };

  // ==========================================
  // UTILITY HELPERS (pure, no state)
  // ==========================================

  /**
   * Find an album by its library ID
   */
  const findAlbumById = (albumId) => {
    return albums.value.find(a => a.id === albumId);
  };

  /**
   * Find an album by its Spotify ID
   */
  const findAlbumBySpotifyId = (spotifyId) => {
    return albums.value.find(a => a.spotify_id === spotifyId);
  };

  /**
   * Filter albums by criteria
   */
  const filterAlbums = (criteria) => {
    return albums.value.filter(album => {
      let matches = true;

      if (criteria.status) {
        matches = matches && album.userStatuses && album.userStatuses.includes(criteria.status);
      }
      if (criteria.rating !== undefined) {
        matches = matches && album.user_rating === criteria.rating;
      }
      if (criteria.hasRating !== undefined) {
        matches = matches && (
          criteria.hasRating
            ? album.user_rating > 0
            : !album.user_rating || album.user_rating === 0
        );
      }
      if (criteria.artist) {
        matches = matches && album.artist && album.artist.toLowerCase().includes(criteria.artist.toLowerCase());
      }
      if (criteria.title) {
        const title = album.title || album.name;
        matches = matches && title && title.toLowerCase().includes(criteria.title.toLowerCase());
      }
      if (criteria.genre) {
        const genres = Array.isArray(album.genres)
          ? album.genres.join(' ')
          : (album.genres || '');
        matches = matches && genres.toLowerCase().includes(criteria.genre.toLowerCase());
      }

      return matches;
    });
  };

  /**
   * Search albums wrapper (alias)
   */
  const searchAlbumsWrapper = async (query) => {
    return await searchAlbumsStore(query);
  };

  return {
    // ===== REACTIVE STATE (from store) =====
    albums,
    searchResults,
    allowedStatuses,
    userTags,
    isLoading,
    isSearching,
    error,
    lastSearchQuery,

    // ===== COMPUTED GETTERS (from store) =====
    totalAlbums,
    hasAlbums,
    hasSearchResults,
    albumsWithRating,
    averageRating,
    albumsByStatus,
    albumCountByStatus,

    // ===== MAIN METHODS =====
    fetchAlbums,                          // Direct from store
    searchAlbums: searchAlbumsWrapper,    // Alias
    addAlbum,                             // Wrapper with pre-loading
    editUserAlbum,                        // Wrapper with local state sync
    deleteAlbum,                          // Wrapper with confirmation
    updateAlbumRating,                    // Direct from store
    updateAlbumStatuses,                  // Direct from store
    fetchAllowedStatuses,                 // Direct from store

    // ===== TAGS =====
    fetchUserTags,                        // Direct from store
    createUserTag,                        // Wrapper with validation
    getAlbumTags,                         // Specific method
    updateAlbumTags,                      // Direct from store

    // ===== NOTES =====
    // Las notas salieron del store: viven en useMediaNotes('album'). Este
    // bloque reexportaba las cuatro acciones y no lo consumía nadie.

    // ===== UTILITIES =====
    findAlbumById,                        // Pure helper
    findAlbumBySpotifyId,                 // Pure helper
    filterAlbums,                         // Pure helper
    clearSearchResults,                   // Direct from store
    clearError                            // Direct from store
  };
}
