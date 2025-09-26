import { ref, computed } from 'vue';
import { useAuth } from './useAuth';
import Logger from '@/utils/logger';

// Estados globales compartidos (singleton)
const movies = ref([]);
const allowedStatuses = ref([]);
const userTags = ref([]);
const isLoading = ref(false);
const error = ref(null);
const lastSearchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);

/**
 * Composable para gestión de películas
 * Proporciona funcionalidades CRUD para películas y gestión de estados
 * Implementado como singleton para compartir estado entre componentes
 */
export function useMovies() {
  const { authenticatedApiCall } = useAuth();

  // Estados computados
  const totalMovies = computed(() => movies.value.length);
  const hasMovies = computed(() => movies.value.length > 0);
  const hasSearchResults = computed(() => searchResults.value.length > 0);
  const moviesWithRating = computed(() => 
    movies.value.filter(movie => movie.user_rating && movie.user_rating > 0)
  );
  const moviesByStatus = computed(() => {
    const statusGroups = {};
    movies.value.forEach(movie => {
      if (movie.userStatuses && Array.isArray(movie.userStatuses)) {
        movie.userStatuses.forEach(status => {
          if (!statusGroups[status]) statusGroups[status] = [];
          statusGroups[status].push(movie);
        });
      }
    });
    return statusGroups;
  });

  /**
   * Obtiene todas las películas del usuario
   */
  const fetchMovies = async () => {
    isLoading.value = true;
    error.value = null;
    
    try {
      const response = await authenticatedApiCall('get_library_items');
      
      if (response.data.status === 'success') {
        // El backend devuelve { books: [], movies: [] }
        const data = response.data.data || {};
        const moviesArray = Array.isArray(data.movies) ? data.movies : [];
        
        // Asignar directamente las películas ya que vienen filtradas del backend
        movies.value = moviesArray.map(movie => ({
          ...movie,
          itemType: 'movie'
        }));
      } else {
        throw new Error(response.data.message || 'Failed to fetch movies');
      }
    } catch (err) {
      error.value = err.message || 'Failed to fetch movies';
      Logger.error('[useMovies] Error fetching movies:', err);
      movies.value = [];
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Busca películas por título
   * @param {string} query - Consulta de búsqueda
   * @returns {Promise<Array>} - Resultados de la búsqueda
   */
  const searchMovies = async (query) => {
    if (!query || query.trim().length === 0) {
      searchResults.value = [];
      return [];
    }

    isSearching.value = true;
    error.value = null;
    lastSearchQuery.value = query;

    try {
      const response = await authenticatedApiCall('search_movie_name', {
        name: query
      });

      if (response.data.status === 'success') {
        searchResults.value = response.data.data || [];
        return searchResults.value;
      } else {
        throw new Error(response.data.message || 'Search failed');
      }
    } catch (err) {
      error.value = err.message || 'Search failed';
      Logger.error('[useMovies] Error searching movies:', err);
      searchResults.value = [];
      return [];
    } finally {
      isSearching.value = false;
    }
  };

  /**
   * Agrega una película a la biblioteca del usuario
   * @param {Object} movie - Datos de la película (formato OMDb transformado)
   * @param {Array} statuses - Estados de la película
   */
  const addMovie = async (movie, statuses = []) => {
    isLoading.value = true;
    error.value = null;

    try {
      const movieData = {
        id: movie.isbn || movie.imdbID, // ID para el backend
        isbn: movie.isbn || movie.imdbID, // ID principal (imdbID)
        imdbID: movie.imdbID,
        title: movie.title,
        originalTitle: movie.originalTitle || movie.title,
        director: movie.director || '',
        author: movie.author || movie.director || '', // Para consistencia
        year: movie.year || '',
        genre: movie.genre || '',
        plot: movie.plot || '',
        coverUrl: movie.coverUrl || '',
        userStatuses: statuses,
        user_rating: movie.user_rating || 0,
        itemType: 'movie',
        // Include genres if present
        genres: movie.genres || []
      };
      
      const response = await authenticatedApiCall('add_movie', { movie: movieData });

      if (response.data.status === 'success') {
        // Agregar la película a la lista local con los datos actualizados
        const newMovie = {
          ...movieData,
          userStatuses: statuses,
          user_rating: movie.user_rating || 0
        };
        movies.value.push(newMovie);
        
        return { success: true, movie: newMovie };
      } else {
        throw new Error(response.data.message || 'Failed to add movie');
      }
    } catch (err) {
      // Manejar diferentes tipos de errores
      let errorMessage = 'Failed to add movie';
      
      if (err.response) {
        // Error de respuesta HTTP del servidor
        const status = err.response.status;
        const data = err.response.data;
        
        if (status === 401) {
          errorMessage = 'Authentication required. Please login again.';
        } else if (status === 403) {
          errorMessage = 'Invalid CSRF token. Please refresh the page and try again.';
        } else if (data && data.message) {
          errorMessage = data.message;
        } else {
          errorMessage = `Server error (${status})`;
        }
      } else if (err.request) {
        // Error de red
        errorMessage = 'Network error. Please check your connection.';
      } else if (err.message) {
        // Error general
        errorMessage = err.message;
      }
      
      error.value = errorMessage;
      return { success: false, message: errorMessage };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Elimina una película de la biblioteca
   * @param {string} tmdbId - ID de TMDB de la película
   */
  const deleteMovie = async (tmdbId) => {
    isLoading.value = true;
    error.value = null;

    try {
      const response = await authenticatedApiCall('delete_movie', {
        movieId: tmdbId,
        itemType: 'movie'
      });

      if (response.data.status === 'success') {
        // Remover la película de la lista local
        movies.value = movies.value.filter(movie => movie.tmdbId !== tmdbId);
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to delete movie');
      }
    } catch (err) {
      error.value = err.message || 'Failed to delete movie';
      Logger.error('[useMovies] Error deleting movie:', err);
      return { success: false, message: err.message };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Actualiza la calificación de una película
   * @param {string} movieId - ID de la película (isbn)
   * @param {number} rating - Nueva calificación (0-5)
   */
  const updateMovieRating = async (movieId, rating) => {
    try {
      const response = await authenticatedApiCall('update_movie_rating', {
        movieId: movieId,
        rating: rating
      });

      if (response.data.status === 'success') {
        // Actualizar la calificación en la lista local - buscar por isbn
        const movie = movies.value.find(m => m.isbn === movieId);
        if (movie) {
          movie.user_rating = rating;
        }
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to update rating');
      }
    } catch (err) {
      error.value = err.message || 'Failed to update rating';
      Logger.error('[useMovies] Error updating movie rating:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Actualiza los estados de una película
   * @param {string} movieId - ID de la película (isbn)
   * @param {Array} statuses - Nuevos estados
   */
  const updateMovieStatuses = async (movieId, statuses) => {
    try {
      const response = await authenticatedApiCall('update_movie_user_statuses', {
        movieId: movieId,
        statuses: statuses
      });

      if (response.data.status === 'success') {
        // Actualizar los estados en la lista local - buscar por isbn
        const movie = movies.value.find(m => m.isbn === movieId);
        if (movie) {
          movie.userStatuses = [...statuses];
        }
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to update statuses');
      }
    } catch (err) {
      error.value = err.message || 'Failed to update statuses';
      Logger.error('[useMovies] Error updating movie statuses:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Obtiene los estados permitidos para películas
   */
  const fetchAllowedStatuses = async () => {
    try {
      const response = await authenticatedApiCall('get_movie_allowed_statuses');

      if (response.data.status === 'success') {
        allowedStatuses.value = response.data.data || [];
        return allowedStatuses.value;
      } else {
        throw new Error(response.data.message || 'Failed to fetch allowed statuses');
      }
    } catch (err) {
      error.value = err.message || 'Failed to fetch allowed statuses';
      Logger.error('[useMovies] Error fetching allowed statuses:', err);
      allowedStatuses.value = [];
      return [];
    }
  };

  /**
   * Edita todos los aspectos de un user_movie (datos, tags, notas)
   * @param {string} tmdbId
   * @param {number} userId
   * @param {object} data
   * @param {Array} tags
   * @param {Array} notes
   */
  const editUserMovie = async (movieId, userId, data = {}, tags = [], notes = []) => {
    try {
      const response = await authenticatedApiCall('edit_user_movie', {
        movieId: movieId,
        userId,
        data,
        tags,
        notes
      });
      
      if (response.data.status === 'success') {
        // Actualizar datos locales usando el mismo patrón que useBooks
        const movieIndex = movies.value.findIndex(movie => movie.isbn === movieId);
        
        if (movieIndex !== -1) {
          // Actualizar la película usando el patrón de useBooks (spread completo)
          movies.value[movieIndex] = {
            ...movies.value[movieIndex],
            user_rating: data.personalRating !== undefined ? data.personalRating : movies.value[movieIndex].user_rating,
            userStatuses: data.statuses || movies.value[movieIndex].userStatuses
          };
        } else {
          Logger.warn('[useMovies] Movie not found for local update:', movieId);
        }
        
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Error al editar user_movie');
      }
    } catch (err) {
      error.value = err.message || 'Error al editar user_movie';
      Logger.error('[useMovies] Error editando user_movie:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Busca una película específica por TMDB ID
   * @param {string} tmdbId - ID de TMDB de la película
   */
  const findMovieByTMDBId = (tmdbId) => {
    return movies.value.find(movie => movie.tmdbId === tmdbId);
  };

  /**
   * Filtra películas por criterios específicos
   * @param {Object} criteria - Criterios de filtrado
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

      if (criteria.genre) {
        matches = matches && movie.genre && movie.genre.toLowerCase().includes(criteria.genre.toLowerCase());
      }

      if (criteria.releaseYear) {
        matches = matches && movie.releaseDate && movie.releaseDate.includes(criteria.releaseYear.toString());
      }

      return matches;
    });
  };

  /**
   * Limpia los resultados de búsqueda
   */
  const clearSearchResults = () => {
    searchResults.value = [];
    lastSearchQuery.value = '';
  };

  /**
   * Limpia los errores
   */
  const clearError = () => {
    error.value = null;
  };

  /**
   * Obtiene todos los tags del usuario para películas
   */
  const fetchUserTags = async () => {
    try {
      const response = await authenticatedApiCall('get_user_movie_tags');

      if (response.data.status === 'success') {
        userTags.value = response.data.data || [];
        return { success: true, data: userTags.value };
      } else {
        throw new Error(response.data.message || 'Error al obtener tags');
      }
    } catch (error) {
      Logger.error('[useMovies] Error obteniendo tags:', error);
      return { success: false, message: error.message };
    }
  };

  /**
   * Crea un nuevo tag para el usuario (películas)
   */
  const createUserTag = async (tagName, color = '#1976d2') => {
    try {
      const response = await authenticatedApiCall('create_user_movie_tag', {
        name: tagName, 
        color
      });

      if (response.data.status === 'success') {
        const newTag = response.data.data;
        userTags.value.push(newTag);
        return { success: true, data: newTag };
      } else {
        throw new Error(response.data.message || 'Error al crear tag');
      }
    } catch (error) {
      Logger.error('[useMovies] Error creando tag:', error);
      return { success: false, message: error.message };
    }
  };

  /**
   * Obtiene los tags de una película específica
   */
  const getMovieTags = async (movieIsbn) => {
    try {
      const response = await authenticatedApiCall('get_movie_tags', {
        movieIsbn
      });

      if (response.data.status === 'success') {
        return { success: true, data: response.data.data || [] };
      } else {
        throw new Error(response.data.message || 'Error al obtener tags de la película');
      }
    } catch (error) {
      Logger.error('[useMovies] Error obteniendo tags de la película:', error);
      return { success: false, message: error.message };
    }
  };

  /**
   * Reinicia todos los estados
   */
  const reset = () => {
    movies.value = [];
    searchResults.value = [];
    allowedStatuses.value = [];
    error.value = null;
    lastSearchQuery.value = '';
    isLoading.value = false;
    isSearching.value = false;
  };

  return {
    // Estados
    movies,
    searchResults,
    allowedStatuses,
    userTags,
    isLoading,
    isSearching,
    error,
    lastSearchQuery,

    // Estados computados
    totalMovies,
    hasMovies,
    hasSearchResults,
    moviesWithRating,
    moviesByStatus,

    // Métodos de API
    fetchMovies,
    searchMovies,
    addMovie,
    deleteMovie,
    updateMovieRating,
    updateMovieStatuses,
    fetchAllowedStatuses,
    editUserMovie,

    // Métodos de tags
    fetchUserTags,
    createUserTag,
    getMovieTags,

    // Métodos de utilidad
    findMovieByTMDBId,
    filterMovies,
    clearSearchResults,
    clearError,
    reset
  };
}
