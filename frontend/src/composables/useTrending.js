import { ref } from 'vue';
import { useAuthStore } from '@/store/auth';
import Logger from '@/utils/logger';

/**
 * Composable para gestión de contenido trending (libros, películas y juegos)
 * Obtiene contenido popular basado en actividad local de usuarios
 */
export function useTrending() {
  const trendingBooks = ref([]);
  const trendingMovies = ref([]);
  const trendingGames = ref([]);
  const isLoadingBooks = ref(false);
  const isLoadingMovies = ref(false);
  const isLoadingGames = ref(false);
  const errorBooks = ref(null);
  const errorMovies = ref(null);
  const errorGames = ref(null);
  
  const authStore = useAuthStore();

  /**
   * Obtiene libros trending
   * @param {number} limit - Cantidad de libros a obtener (default: 20)
   * @param {number} daysWindow - Ventana temporal en días (default: 90)
   */
  const fetchTrendingBooks = async (limit = 20, daysWindow = 90) => {
    isLoadingBooks.value = true;
    errorBooks.value = null;
    
    try {
      Logger.info('Fetching trending books', { limit, daysWindow });
      
      const response = await authStore.authenticatedApiCall('get_trending_books', {
        limit,
        daysWindow
      });

      if (response.data?.status === 'success' && response.data?.data) {
        trendingBooks.value = response.data.data;
        Logger.info(`Trending books loaded: ${trendingBooks.value.length} items`);
      } else {
        throw new Error('Invalid response format');
      }
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Error al cargar libros trending';
      errorBooks.value = errorMessage;
      Logger.error('Error fetching trending books:', error);
      trendingBooks.value = [];
    } finally {
      isLoadingBooks.value = false;
    }
  };

  /**
   * Obtiene películas trending
   * @param {number} limit - Cantidad de películas a obtener (default: 20)
   * @param {number} daysWindow - Ventana temporal en días (default: 90)
   */
  const fetchTrendingMovies = async (limit = 20, daysWindow = 90) => {
    isLoadingMovies.value = true;
    errorMovies.value = null;
    
    try {
      Logger.info('Fetching trending movies', { limit, daysWindow });
      
      const response = await authStore.authenticatedApiCall('get_trending_movies', {
        limit,
        daysWindow
      });

      if (response.data?.status === 'success' && response.data?.data) {
        trendingMovies.value = response.data.data;
        Logger.info(`Trending movies loaded: ${trendingMovies.value.length} items`);
      } else {
        throw new Error('Invalid response format');
      }
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Error al cargar películas trending';
      errorMovies.value = errorMessage;
      Logger.error('Error fetching trending movies:', error);
      trendingMovies.value = [];
    } finally {
      isLoadingMovies.value = false;
    }
  };

  /**
   * Obtiene juegos trending
   * @param {number} limit - Cantidad de juegos a obtener (default: 20)
   * @param {number} daysWindow - Ventana temporal en días (default: 90)
   */
  const fetchTrendingGames = async (limit = 20, daysWindow = 90) => {
    isLoadingGames.value = true;
    errorGames.value = null;
    
    try {
      Logger.info('Fetching trending games', { limit, daysWindow });
      
      const response = await authStore.authenticatedApiCall('get_trending_games', {
        limit,
        daysWindow
      });

      if (response.data?.status === 'success' && response.data?.data) {
        trendingGames.value = response.data.data;
        Logger.info(`Trending games loaded: ${trendingGames.value.length} items`);
      } else {
        throw new Error('Invalid response format');
      }
    } catch (error) {
      const errorMessage = error.response?.data?.message || error.message || 'Error al cargar juegos trending';
      errorGames.value = errorMessage;
      Logger.error('Error fetching trending games:', error);
      trendingGames.value = [];
    } finally {
      isLoadingGames.value = false;
    }
  };

  /**
   * Limpia datos de libros trending
   */
  const clearTrendingBooks = () => {
    trendingBooks.value = [];
    errorBooks.value = null;
  };

  /**
   * Limpia datos de películas trending
   */
  const clearTrendingMovies = () => {
    trendingMovies.value = [];
    errorMovies.value = null;
  };

  /**
   * Limpia datos de juegos trending
   */
  const clearTrendingGames = () => {
    trendingGames.value = [];
    errorGames.value = null;
  };

  /**
   * Limpia todos los datos trending
   */
  const clearAll = () => {
    clearTrendingBooks();
    clearTrendingMovies();
    clearTrendingGames();
  };

  return {
    // State
    trendingBooks,
    trendingMovies,
    trendingGames,
    isLoadingBooks,
    isLoadingMovies,
    isLoadingGames,
    errorBooks,
    errorMovies,
    errorGames,
    
    // Methods
    fetchTrendingBooks,
    fetchTrendingMovies,
    fetchTrendingGames,
    clearTrendingBooks,
    clearTrendingMovies,
    clearTrendingGames,
    clearAll
  };
}
