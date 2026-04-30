import { storeToRefs } from 'pinia';
import { useMoviesStore } from '@/store/movies';
import { useAuthStore } from '@/store/auth';
import { useConfirmationModal } from './useConfirmationModal';
import Logger from '@/utils/logger';

/**
 * Composable para gestión de películas
 * Wrapper ligero del store Pinia useMoviesStore
 * Proporciona helpers adicionales y lógica específica de UI
 * 
 * REFACTORIZADO: La lógica de negocio está en el store, aquí solo helpers de UI
 */
export function useMovies() {
  const moviesStore = useMoviesStore();
  const authStore = useAuthStore();
  
  // ✅ Estado reactivo via storeToRefs (directamente del store)
  const {
    movies,
    allowedStatuses,
    userTags,
    isLoading,
    error,
    searchResults,
    isSearching,
    lastSearchQuery,
    // Getters computados
    totalMovies,
    hasMovies,
    hasSearchResults,
    moviesWithRating,
    averageRating,
    moviesByStatus,
    movieCountByStatus
  } = storeToRefs(moviesStore);

  // ✅ Actions del store (delegación directa)
  const {
    fetchMovies,
    searchMovies: searchMoviesStore,
    fetchAllowedStatuses,
    fetchUserTags,
    createTag: createTagStore,
    updateMovieTags,
    clearSearchResults,
    clearError
  } = moviesStore;

  /**
   * Agrega una película con validación de estados permitidos
   */
  const addMovie = async (movie, statuses = []) => {
    // Pre-cargar estados permitidos si no existen
    if (allowedStatuses.value.length === 0) {
      await fetchAllowedStatuses();
    }
    
    return await moviesStore.addMovie(movie, statuses);
  };

  /**
   * Elimina una película CON confirmación modal
   */
  const deleteMovie = async (movieId, skipConfirmation = false) => {
    const { confirmDelete } = useConfirmationModal();
    
    try {
      const movie = movies.value.find(m => 
        m.id === movieId || m.imdbID === movieId || m.isbn === movieId
      );
      const movieTitle = movie ? movie.title : `ID: ${movieId}`;

      // Mostrar confirmación si no se omite
      if (!skipConfirmation) {
        const confirmed = await confirmDelete(
          movieTitle,
          'Esta acción no se puede deshacer'
        );
        
        if (!confirmed) {
          return { success: false, cancelled: true };
        }
      }

      return await moviesStore.deleteMovie(movieId);
    } catch (err) {
      Logger.error('[useMovies] Error in deleteMovie wrapper:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Actualiza la calificación de una película
   */
  const updateMovieRating = async (movieId, rating) => {
    return await moviesStore.updateMovieRating(movieId, rating);
  };

  /**
   * Actualiza los estados de una película
   */
  const updateMovieStatuses = async (movieId, statuses) => {
    return await moviesStore.updateMovieStatuses(movieId, statuses);
  };

  /**
   * Edita una película completa
   */
  const editUserMovie = async (movieId, userId, data = {}, tags = [], notes = []) => {
    try {
      Logger.debug('[useMovies] Editing user_movie:', { movieId, userId, data, tags, notes });
      
      const response = await authStore.authenticatedApiCall('edit_user_movie', {
        isbn: movieId,
        userId,
        data,
        tags,
        notes
      });
      
      if (response.data.status === 'success') {
        // Actualizar película en el store local
        const movieIndex = movies.value.findIndex(m => 
          m.id === movieId || m.imdbID === movieId || m.isbn === movieId
        );
        if (movieIndex !== -1) {
          movies.value[movieIndex] = {
            ...movies.value[movieIndex],
            user_rating: data.personalRating !== undefined ? data.personalRating : movies.value[movieIndex].user_rating,
            userStatuses: data.statuses || movies.value[movieIndex].userStatuses,
            ownership_format_id: data.ownership_format_id !== undefined ? data.ownership_format_id : movies.value[movieIndex].ownership_format_id,
            ownershipFormat: data.ownershipFormat !== undefined ? data.ownershipFormat : movies.value[movieIndex].ownershipFormat
          };
        }
        
        Logger.debug('[useMovies] User movie edited successfully');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Error editing user_movie');
      }
    } catch (err) {
      Logger.error('[useMovies] Error editing user_movie:', err);
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
   * Obtiene los tags de una película específica
   */
  const getMovieTags = async (movieId) => {
    try {
      const response = await authStore.authenticatedApiCall('get_movie_tags', { isbn: movieId });
      
      if (response.data.status === 'success') {
        return { success: true, data: response.data.data || [] };
      } else {
        throw new Error(response.data.message || 'Error getting movie tags');
      }
    } catch (err) {
      Logger.error('[useMovies] Error getting movie tags:', err);
      return { success: false, message: err.message };
    }
  };

  // ==========================================
  // HELPERS DE UTILIDAD (sin estado - solo funciones)
  // ==========================================

  /**
   * Busca una película específica por ID
   */
  const findMovieById = (movieId) => {
    return movies.value.find(m => 
      m.id === movieId || m.imdbID === movieId || m.isbn === movieId
    );
  };

  /**
   * Busca una película específica por IMDb ID
   * @param {string} imdbId - ID de IMDb de la película
   */
  const findMovieByTMDBId = (imdbId) => {
    return movies.value.find(movie => 
      movie.imdbID === imdbId || movie.isbn === imdbId || movie.id === imdbId
    );
  };

  /**
   * Filtra películas por criterios
   */
  const filterMovies = (criteria) => {
    return movies.value.filter(movie => {
      let matches = true;

      if (criteria.status) {
        matches = matches && movie.userStatuses && movie.userStatuses.includes(criteria.status);
      }
      if (criteria.rating !== undefined) {
        matches = matches && movie.user_rating === criteria.rating;
      }
      if (criteria.hasRating !== undefined) {
        matches = matches && (criteria.hasRating ? movie.user_rating > 0 : !movie.user_rating || movie.user_rating === 0);
      }
      if (criteria.director) {
        matches = matches && movie.director && movie.director.toLowerCase().includes(criteria.director.toLowerCase());
      }
      if (criteria.title) {
        matches = matches && movie.title && movie.title.toLowerCase().includes(criteria.title.toLowerCase());
      }
      if (criteria.year) {
        matches = matches && movie.year && movie.year.toString() === criteria.year.toString();
      }

      return matches;
    });
  };

  /**
   * Wrapper de búsqueda (alias)
   */
  const searchMoviesWrapper = async (query) => {
    return await searchMoviesStore(query);
  };

  // ===== SERIES SEASON TRACKING =====

  const trackSeriesSeason = async (seriesIsbn, seasonNumber, data = {}) => {
    try {
      const { useAuthStore } = await import('@/store/auth.js');
      const authStore = useAuthStore();
      const response = await authStore.apiCall('track_series_season', {
        seriesIsbn,
        seasonNumber,
        status: data.status || 'viewed',
        dateViewed: data.dateViewed || null,
        personalRating: data.personalRating || null,
        notes: data.notes || null,
      });
      return { success: true, data: response.data };
    } catch (err) {
      return { success: false, message: err.message };
    }
  };

  const getSeriesProgress = async (seriesIsbn) => {
    try {
      const { useAuthStore } = await import('@/store/auth.js');
      const authStore = useAuthStore();
      const response = await authStore.apiCall('get_series_progress', { seriesIsbn });
      return { success: true, data: response.data?.data || {} };
    } catch (err) {
      return { success: false, message: err.message, data: {} };
    }
  };

  return {
    // ===== ESTADO REACTIVO (desde store) =====
    movies,
    searchResults,
    allowedStatuses,
    userTags,
    isLoading,
    isSearching,
    error,
    lastSearchQuery,

    // ===== GETTERS COMPUTADOS (desde store) =====
    totalMovies,
    hasMovies,
    hasSearchResults,
    moviesWithRating,
    averageRating,
    moviesByStatus,
    movieCountByStatus,

    // ===== MÉTODOS PRINCIPALES =====
    fetchMovies,                          // Directo del store
    searchMovies: searchMoviesWrapper,    // Alias
    addMovie,                             // Wrapper con validación
    editUserMovie,                        // Wrapper con formateo
    deleteMovie,                          // Wrapper con confirmación
    updateMovieRating,                    // Directo del store
    updateMovieStatuses,                  // Directo del store
    fetchAllowedStatuses,                 // Directo del store

    // ===== TAGS =====
    fetchUserTags,                        // Directo del store
    createUserTag,                        // Wrapper con validación
    getMovieTags,                         // Método específico
    updateMovieTags,                      // Directo del store

    // ===== UTILIDADES =====
    findMovieById,                        // Helper puro
    findMovieByTMDBId,                    // Helper puro
    filterMovies,                         // Helper puro
    clearSearchResults,                   // Directo del store
    clearError,                           // Directo del store

    // ===== SERIES SEASON TRACKING =====
    trackSeriesSeason,
    getSeriesProgress,
  };
}
