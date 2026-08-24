import Logger from '@/utils/logger';
import { useAuthStore } from '@/store/auth.js';
import { categoricalPalette, foldToOther, entityColor, chartInk } from '@/config/chartTheme';

/**
 * Service para interactuar con la API de estadísticas
 * Utiliza authStore.authenticatedApiCall() para CSRF y JWT consistentes
 */
// Los cinco medios que tienen acento propio en el registry. Una serie mensual de
// 'games' se pinta con el color de la tarjeta de juegos, no con uno cualquiera.
const MEDIA_KEYS = ['book', 'movie', 'game', 'album', 'video', 'books', 'movies', 'games', 'albums', 'videos'];

class StatsService {

  /**
   * Helper para llamadas autenticadas a la API
   */
  async _apiCall(action) {
    const authStore = useAuthStore();
    const response = await authStore.authenticatedApiCall(action);

    if (response.data.status === 'error') {
      throw new Error(response.data.message || `Failed to fetch ${action}`);
    }

    return response.data.data;
  }

  /**
   * Obtener estadísticas de libros del usuario
   * @returns {Promise<Object>} Estadísticas de libros
   */
  async getBookStats() {
    try {
      Logger.info('[StatsService] Fetching book statistics...');
      const data = await this._apiCall('get_book_stats');
      Logger.info('[StatsService] Book statistics fetched successfully');
      return data;
    } catch (error) {
      Logger.error('[StatsService] Error fetching book statistics:', error);
      throw error;
    }
  }

  /**
   * Obtener estadísticas de películas del usuario
   * @returns {Promise<Object>} Estadísticas de películas
   */
  async getMovieStats() {
    try {
      Logger.info('[StatsService] Fetching movie statistics...');
      const data = await this._apiCall('get_movie_stats');
      Logger.info('[StatsService] Movie statistics fetched successfully');
      return data;
    } catch (error) {
      Logger.error('[StatsService] Error fetching movie statistics:', error);
      throw error;
    }
  }

  /**
   * Obtener estadísticas de videojuegos del usuario
   * @returns {Promise<Object>} Estadísticas de videojuegos
   */
  async getGameStats() {
    try {
      Logger.info('[StatsService] Fetching game statistics...');
      const data = await this._apiCall('get_game_stats');
      Logger.info('[StatsService] Game statistics fetched successfully');
      return data;
    } catch (error) {
      Logger.error('[StatsService] Error fetching game statistics:', error);
      throw error;
    }
  }

  async getAlbumStats() {
    try {
      Logger.info('[StatsService] Fetching album statistics...');
      const data = await this._apiCall('get_album_stats');
      Logger.info('[StatsService] Album statistics fetched successfully');
      return data;
    } catch (error) {
      Logger.error('[StatsService] Error fetching album statistics:', error);
      throw error;
    }
  }

  async getVideoStats() {
    try {
      Logger.info('[StatsService] Fetching video statistics...');
      const data = await this._apiCall('get_video_stats');
      Logger.info('[StatsService] Video statistics fetched successfully');
      return data;
    } catch (error) {
      Logger.error('[StatsService] Error fetching video statistics:', error);
      throw error;
    }
  }

  /**
   * Transformar datos de géneros para Chart.js
   * @param {Object} genreStats - Estadísticas de géneros
   * @returns {Object} Datos formateados para Chart.js
   */
  transformGenreDataForChart(genreStats) {
    if (!genreStats || !genreStats.topGenres) {
      return {
        labels: [],
        datasets: [{
          data: [],
          backgroundColor: []
        }]
      };
    }

    // La cola se agrupa en «Otros» en vez de inventar tonos: la paleta tiene un
    // orden fijo de 7 y a partir de ahí no hay separación garantizada.
    const { labels, data } = foldToOther(
      Object.keys(genreStats.topGenres),
      Object.values(genreStats.topGenres)
    );

    return {
      labels,
      datasets: [{
        data,
        backgroundColor: categoricalPalette(labels.length),
        borderWidth: 1
      }]
    };
  }

  /**
   * Transformar datos de estados para Chart.js
   * @param {Object} statusStats - Estadísticas de estados
   * @returns {Object} Datos formateados para Chart.js
   */
  transformStatusDataForChart(statusStats) {
    if (!statusStats || Object.keys(statusStats).length === 0) {
      return {
        labels: [],
        datasets: [{
          data: [],
          backgroundColor: []
        }]
      };
    }

    const { labels, data } = foldToOther(
      Object.keys(statusStats),
      Object.values(statusStats)
    );

    return {
      labels,
      datasets: [{
        data,
        backgroundColor: categoricalPalette(labels.length),
        borderWidth: 1
      }]
    };
  }

  /**
   * Transformar datos de ratings para Chart.js
   * @param {Object} ratingStats - Estadísticas de ratings
   * @returns {Object} Datos formateados para Chart.js
   */
  transformRatingDataForChart(ratingStats) {
    // Definir todas las categorías posibles de ratings (de 1.0 a 5.0 en incrementos de 0.5)
    const allRatings = ['1', '1.5', '2', '2.5', '3', '3.5', '4', '4.5', '5'];
    
    if (!ratingStats || !ratingStats.distribution) {
      // Si no hay datos, mostrar todas las categorías con 0
      return {
        labels: allRatings.map(rating => `${rating} estrellas`),
        datasets: [{
          data: new Array(allRatings.length).fill(0),
          backgroundColor: categoricalPalette(1)[0],
          borderWidth: 1
        }]
      };
    }

    // Crear array de datos con todas las categorías, rellenando con 0 los que no existen
    const data = allRatings.map(rating => ratingStats.distribution[rating] || 0);
    const labels = allRatings.map(rating => {
      const formattedRating = Number(rating).toFixed(1);
      return `${formattedRating} estrellas`;
    });
    
    // Una sola serie ordinal (1 → 5 estrellas): un color, no nueve. Nueve tonos
    // codificarían una diferencia de categoría que aquí no existe — la magnitud ya
    // la lleva el alto de la barra.
    return {
      labels,
      datasets: [{
        data,
        backgroundColor: categoricalPalette(1)[0],
        borderWidth: 1
      }]
    };
  }

  /**
   * Transformar datos mensuales para Chart.js
   * @param {Object} monthlyStats - Estadísticas mensuales
   * @param {string} type - Tipo de datos ('books' o 'pages')
   * @returns {Object} Datos formateados para Chart.js
   */
  transformMonthlyDataForChart(monthlyStats, type = 'books') {
    if (!monthlyStats || Object.keys(monthlyStats).length === 0) {
      return {
        labels: [],
        datasets: [{
          data: [],
          borderColor: chartInk().muted,
          backgroundColor: 'transparent'
        }]
      };
    }

    const labels = Object.keys(monthlyStats).map(month => {
      const [year, monthNum] = month.split('-');
      const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 
                         'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
      return `${monthNames[parseInt(monthNum) - 1]} ${year}`;
    });
    const data = Object.values(monthlyStats);

    const isPages = type === 'pages';
    const label = isPages ? 'Páginas leídas' : 'Agregados por mes';
    // Las series por medio llevan el color de su tarjeta en /library; el resto,
    // la primera ranura de la paleta categórica.
    const color = MEDIA_KEYS.includes(type) ? entityColor(type) : categoricalPalette(1)[0];
    const backgroundColor = 'transparent';

    return {
      labels,
      datasets: [{
        label,
        data,
        borderColor: color,
        backgroundColor: backgroundColor,
        tension: 0.1,
        fill: true
      }]
    };
  }

}

export default new StatsService();