/**
 * Composable para gestión del historial de progreso de lectura
 * Permite consultar el historial de avance en la lectura de libros
 */
import { ref } from 'vue';
import { useAuth } from './useAuth';
import Logger from '@/utils/logger';

export function useReadingProgress() {
  const { authenticatedApiCall } = useAuth();
  
  const isLoading = ref(false);
  const error = ref(null);
  const progressHistory = ref([]);

  /**
   * Obtiene el historial de progreso de lectura de un libro
   * @param {string} isbn - ISBN del libro
   * @returns {Promise<Array>} - Historial de progreso
   */
  const getProgressHistory = async (isbn) => {
    if (!isbn) {
      throw new Error('ISBN es requerido');
    }

    isLoading.value = true;
    error.value = null;
    
    try {
      Logger.debug('[useReadingProgress] Obteniendo historial de progreso para:', isbn);
      
      const response = await authenticatedApiCall('get_reading_progress_history', {
        isbn
      });
      
      if (response.data.status === 'success') {
        const history = response.data.data || [];
        
        // Formatear datos para mostrar
        progressHistory.value = history.map(entry => ({
          ...entry,
          pagesAdvanced: entry.current_page - entry.previous_page,
          date: new Date(entry.logged_at).toLocaleDateString('es-ES'),
          time: new Date(entry.logged_at).toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit' 
          })
        }));
        
        Logger.debug('[useReadingProgress] Historial obtenido:', progressHistory.value);
        return progressHistory.value;
      } else {
        throw new Error(response.data.message || 'Error al obtener historial de progreso');
      }
    } catch (err) {
      error.value = err.message || 'Error al obtener historial de progreso';
      Logger.error('[useReadingProgress] Error:', err);
      progressHistory.value = [];
      return [];
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Calcula estadísticas del progreso de lectura
   * @param {Array} history - Historial de progreso
   * @returns {Object} - Estadísticas calculadas
   */
  const calculateStats = (history = progressHistory.value) => {
    if (!history || history.length === 0) {
      return {
        totalSessions: 0,
        totalPagesRead: 0,
        averagePagesPerSession: 0,
        daysActive: 0,
        readingSpeed: 0
      };
    }

    const totalPagesRead = history.reduce((sum, entry) => sum + entry.pagesAdvanced, 0);
    const uniqueDays = new Set(history.map(entry => entry.date)).size;
    const firstSession = new Date(history[history.length - 1].logged_at);
    const lastSession = new Date(history[0].logged_at);
    const daysDiff = Math.ceil((lastSession - firstSession) / (1000 * 60 * 60 * 24)) + 1;

    return {
      totalSessions: history.length,
      totalPagesRead,
      averagePagesPerSession: Math.round(totalPagesRead / history.length),
      daysActive: uniqueDays,
      readingSpeed: daysDiff > 0 ? Math.round(totalPagesRead / daysDiff) : 0
    };
  };

  /**
   * Limpia el estado
   */
  const clearHistory = () => {
    progressHistory.value = [];
    error.value = null;
  };

  return {
    // Estados
    isLoading,
    error,
    progressHistory,
    
    // Métodos
    getProgressHistory,
    calculateStats,
    clearHistory
  };
}