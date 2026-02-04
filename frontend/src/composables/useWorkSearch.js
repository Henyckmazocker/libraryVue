import { ref, computed } from 'vue';
import axios from 'axios';
import { useAuthStore } from '@/store/auth';
import Logger from '@/utils/logger';

/**
 * Composable para búsqueda de obras (works) literarias
 * Utiliza el backend que consulta OpenLibrary + Google Books
 */
export function useWorkSearch() {
  // Store
  const authStore = useAuthStore();

  // Estados reactivos
  const works = ref([]);
  const isSearching = ref(false);
  const error = ref(null);
  const lastQuery = ref('');

  // Computed
  const hasWorks = computed(() => works.value.length > 0);
  const hasError = computed(() => error.value !== null);

  /**
   * Busca obras por nombre
   * @param {string} query - Término de búsqueda
   * @param {Object} options - Opciones de búsqueda
   * @returns {Promise<Array>} Array de obras encontradas
   */
  const searchWorks = async (query, options = {}) => {
    if (!query || query.trim().length === 0) {
      works.value = [];
      return [];
    }

    const {
      limit = 20,
      enrich = false
    } = options;

    isSearching.value = true;
    error.value = null;
    lastQuery.value = query;

    try {
      Logger.debug('[useWorkSearch] Searching works:', { query, limit, enrich });

      const response = await authStore.apiCall('search_works', {
        q: query,
        limit,
        enrich
      });

      if (response.data && response.data.status === 'success' && response.data.data) {
        const foundWorks = response.data.data.works || [];
        works.value = foundWorks;
        
        Logger.debug(`[useWorkSearch] Found ${foundWorks.length} works`);
        return foundWorks;
      }

      Logger.warn('[useWorkSearch] Backend search returned no works');
      works.value = [];
      return [];

    } catch (err) {
      Logger.error('[useWorkSearch] Search failed:', err);
      error.value = err.message || 'Error al buscar obras';
      works.value = [];

      // Fallback a OpenLibrary directo
      return await searchWorksDirectFallback(query, limit);
    } finally {
      isSearching.value = false;
    }
  };

  /**
   * Fallback: búsqueda directa a OpenLibrary si el backend falla
   * @param {string} query - Término de búsqueda
   * @param {number} limit - Límite de resultados
   * @returns {Promise<Array>} Array de obras
   */
  const searchWorksDirectFallback = async (query, limit = 20) => {
    try {
      Logger.debug('[useWorkSearch] Falling back to direct OpenLibrary search');
      
      const apiUrl = `https://openlibrary.org/search.json?q=${encodeURIComponent(query)}&limit=${limit}`;
      const response = await axios.get(apiUrl);
      const docs = response.data.docs || [];

      // Agrupar por work_key para simular agrupación de obras
      const workMap = new Map();
      
      docs.forEach(doc => {
        const workKey = doc.key || doc.work_key || `temp-${doc.isbn?.[0]}`;
        
        if (!workMap.has(workKey)) {
          workMap.set(workKey, {
            work_key: workKey,
            title: doc.title,
            authors: doc.author_name || [],
            authors_display: doc.author_name ? doc.author_name.join(', ') : 'Unknown',
            cover_id: doc.cover_i,
            cover_url: doc.cover_i ? `https://covers.openlibrary.org/b/id/${doc.cover_i}-M.jpg` : '',
            first_publish_year: doc.first_publish_year,
            editions_count: doc.edition_count || 1,
            subjects: doc.subject?.slice(0, 10) || [],
            languages: doc.language || [],
            sample_isbn: doc.isbn?.[0] || null,
            has_cover: !!doc.cover_i,
            has_description: false
          });
        }
      });

      const fallbackWorks = Array.from(workMap.values());
      works.value = fallbackWorks;
      
      Logger.debug(`[useWorkSearch] Fallback found ${fallbackWorks.length} works`);
      return fallbackWorks;

    } catch (fallbackErr) {
      Logger.error('[useWorkSearch] Fallback search also failed:', fallbackErr);
      error.value = 'No se pudo conectar con el servicio de búsqueda';
      return [];
    }
  };

  /**
   * Obtiene detalles de una obra específica
   * @param {string} workKey - Clave de la obra en OpenLibrary (ej: OL82563W)
   * @param {boolean} enrich - Si debe enriquecer con Google Books
   * @returns {Promise<Object|null>} Detalles de la obra
   */
  const getWork = async (workKey, enrich = true) => {
    if (!workKey) {
      Logger.warn('[useWorkSearch] getWork called without workKey');
      return null;
    }

    isSearching.value = true;
    error.value = null;

    try {
      Logger.debug('[useWorkSearch] Getting work details:', { workKey, enrich });

      const response = await authStore.apiCall('get_work', {
        workKey,
        enrich
      });

      if (response.data && response.data.status === 'success' && response.data.data) {
        Logger.debug('[useWorkSearch] Work details retrieved successfully');
        return response.data.data;
      }

      Logger.warn('[useWorkSearch] Backend returned no work details');
      return null;

    } catch (err) {
      Logger.error('[useWorkSearch] Failed to get work details:', err);
      error.value = err.message || 'Error al obtener detalles de la obra';
      return null;
    } finally {
      isSearching.value = false;
    }
  };

  /**
   * Obtiene las ediciones de una obra
   * @param {string} workKey - Clave de la obra
   * @param {Object} filters - Filtros para las ediciones
   * @returns {Promise<Array>} Array de ediciones
   */
  const getWorkEditions = async (workKey, filters = {}) => {
    if (!workKey) {
      Logger.warn('[useWorkSearch] getWorkEditions called without workKey');
      return [];
    }

    isSearching.value = true;
    error.value = null;

    try {
      Logger.debug('[useWorkSearch] Getting work editions:', { workKey, filters });

      const response = await authStore.apiCall('get_work_editions', {
        workKey,
        filters
      });

      if (response.data && response.data.status === 'success' && response.data.data) {
        const editions = response.data.data.editions || [];
        Logger.debug(`[useWorkSearch] Found ${editions.length} editions`);
        return editions;
      }

      Logger.warn('[useWorkSearch] Backend returned no editions');
      return [];

    } catch (err) {
      Logger.error('[useWorkSearch] Failed to get work editions:', err);
      error.value = err.message || 'Error al obtener ediciones';
      return [];
    } finally {
      isSearching.value = false;
    }
  };

  /**
   * Valida un ISBN y obtiene la obra asociada
   * @param {string} isbn - ISBN a validar
   * @returns {Promise<Object|null>} Obra asociada al ISBN
   */
  const validateISBN = async (isbn) => {
    if (!isbn) {
      Logger.warn('[useWorkSearch] validateISBN called without ISBN');
      return null;
    }

    isSearching.value = true;
    error.value = null;

    try {
      Logger.debug('[useWorkSearch] Validating ISBN:', isbn);

      const response = await authStore.apiCall('validate_isbn', {
        isbn
      });

      if (response.data && response.data.status === 'success' && response.data.data) {
        Logger.debug('[useWorkSearch] ISBN validated successfully');
        return response.data.data;
      }

      Logger.warn('[useWorkSearch] ISBN validation failed');
      return null;

    } catch (err) {
      Logger.error('[useWorkSearch] ISBN validation error:', err);
      error.value = err.message || 'Error al validar ISBN';
      return null;
    } finally {
      isSearching.value = false;
    }
  };

  /**
   * Limpia los resultados de búsqueda
   */
  const clearWorks = () => {
    works.value = [];
    error.value = null;
    lastQuery.value = '';
  };

  /**
   * Limpia el error
   */
  const clearError = () => {
    error.value = null;
  };

  return {
    // Estado
    works,
    isSearching,
    error,
    lastQuery,
    
    // Computed
    hasWorks,
    hasError,
    
    // Métodos
    searchWorks,
    getWork,
    getWorkEditions,
    validateISBN,
    clearWorks,
    clearError
  };
}
