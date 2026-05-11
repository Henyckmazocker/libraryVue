import Logger from '@/utils/logger';
import { useAuthStore } from '@/store/auth.js';

/**
 * Service para interactuar con la API de estadísticas
 * Utiliza authStore.authenticatedApiCall() para CSRF y JWT consistentes
 */
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

    const labels = Object.keys(genreStats.topGenres);
    const data = Object.values(genreStats.topGenres);
    
    // Generar colores dinámicamente
    const colors = this.generateColors(labels.length);

    return {
      labels,
      datasets: [{
        data,
        backgroundColor: colors,
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

    const labels = Object.keys(statusStats);
    const data = Object.values(statusStats);
    const colors = this.generateColors(labels.length);

    return {
      labels,
      datasets: [{
        data,
        backgroundColor: colors,
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
          backgroundColor: ['#ff6384', '#ff9f40', '#ffcd56', '#4bc0c0', '#36a2eb', '#9966ff', '#ff6384', '#ff9f40', '#ffcd56'],
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
    
    const colors = ['#ff6384', '#ff9f40', '#ffcd56', '#4bc0c0', '#36a2eb', '#9966ff', '#ff6384', '#ff9f40', '#ffcd56'];

    return {
      labels,
      datasets: [{
        data,
        backgroundColor: colors,
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
          borderColor: '#36a2eb',
          backgroundColor: 'rgba(54, 162, 235, 0.1)'
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
    const color = isPages ? '#4CAF50' : '#36a2eb';
    const backgroundColor = isPages ? 'rgba(76, 175, 80, 0.1)' : 'rgba(54, 162, 235, 0.1)';

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

  /**
   * Generar colores para gráficos
   * @param {number} count - Número de colores necesarios
   * @returns {Array} Array de colores en formato hex
   */
  generateColors(count) {
    const baseColors = [
      '#ff6384', '#36a2eb', '#ffcd56', '#4bc0c0', '#ff9f40',
      '#c9cbcf', '#ff6384', '#36a2eb', '#ffcd56', '#4bc0c0'
    ];
    
    if (count <= baseColors.length) {
      return baseColors.slice(0, count);
    }
    
    // Generar colores adicionales si se necesitan más
    const colors = [...baseColors];
    for (let i = baseColors.length; i < count; i++) {
      const hue = (i * 137.508) % 360; // Golden angle approximation
      colors.push(`hsl(${hue}, 70%, 60%)`);
    }
    
    return colors;
  }
}

export default new StatsService();