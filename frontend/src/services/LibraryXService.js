import { ref } from 'vue';
import Logger from '@/utils/logger';

/**
 * Service para interactuar con la API de LibraryX
 */
class LibraryXService {
  constructor() {
    this.baseUrl = process.env.VUE_APP_API_URL || 'http://127.0.0.1:8888';
  }

  /**
   * Obtener datos de URLs de LibraryX
   * @returns {Promise<Object>} Datos de URLs organizados por dominio
   */
  async getUrls() {
    try {
      Logger.info('[LibraryXService] Fetching URLs data...');
      
      const response = await fetch(`${this.baseUrl}/api.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          action: 'libraryx_get_urls'
        })
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      
      if (result.status === 'error') {
        throw new Error(result.message || 'Failed to fetch URLs data');
      }

      Logger.info('[LibraryXService] URLs data fetched successfully', {
        domains_count: Object.keys(result.data || {}).length
      });

      return result.data || {};
      
    } catch (error) {
      Logger.error('[LibraryXService] Error fetching URLs data:', error);
      throw error;
    }
  }

  /**
   * Actualizar datos de URLs de LibraryX
   * @param {Object} urlsData - Nuevos datos de URLs
   * @returns {Promise<Object>} Respuesta del servidor
   */
  async updateUrls(urlsData) {
    try {
      Logger.info('[LibraryXService] Updating URLs data...');
      
      // Obtener CSRF token (si es necesario)
      const csrfToken = this.getCSRFToken();
      
      const response = await fetch(`${this.baseUrl}/api.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          action: 'libraryx_update_urls',
          csrf_token: csrfToken,
          urls_data: urlsData
        })
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      
      if (result.status === 'error') {
        throw new Error(result.message || 'Failed to update URLs data');
      }

      Logger.info('[LibraryXService] URLs data updated successfully');
      return result;
      
    } catch (error) {
      Logger.error('[LibraryXService] Error updating URLs data:', error);
      throw error;
    }
  }

  /**
   * Obtener token CSRF (helper method)
   * @returns {string|null} Token CSRF
   */
  getCSRFToken() {
    // Implementar según tu sistema de autenticación
    const authStore = window.localStorage.getItem('auth');
    if (authStore) {
      try {
        const parsedAuth = JSON.parse(authStore);
        return parsedAuth.csrfToken || null;
      } catch (e) {
        Logger.warn('[LibraryXService] Failed to parse auth data for CSRF token');
        return null;
      }
    }
    return null;
  }
}

// Composable para usar LibraryX
export function useLibraryX() {
  const service = new LibraryXService();
  const isLoading = ref(false);
  const error = ref(null);

  const getUrls = async () => {
    isLoading.value = true;
    error.value = null;
    try {
      const data = await service.getUrls();
      return data;
    } catch (err) {
      error.value = err.message;
      throw err;
    } finally {
      isLoading.value = false;
    }
  };

  const updateUrls = async (urlsData) => {
    isLoading.value = true;
    error.value = null;
    try {
      const result = await service.updateUrls(urlsData);
      return result;
    } catch (err) {
      error.value = err.message;
      throw err;
    } finally {
      isLoading.value = false;
    }
  };

  return {
    isLoading,
    error,
    getUrls,
    updateUrls
  };
}

export default LibraryXService;
