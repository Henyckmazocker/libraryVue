import { ref, computed, watch } from 'vue';
import Logger from '@/utils/logger';

/**
 * Composable para gestión de búsquedas con debouncing y caché
 * Proporciona funcionalidades para búsquedas optimizadas con historial y caché
 */
export function useSearch(options = {}) {
  // Configuración por defecto
  const defaultOptions = {
    debounceDelay: 300,
    minQueryLength: 2,
    maxCacheSize: 50,
    cacheExpiration: 5 * 60 * 1000, // 5 minutos
    historyMaxSize: 20
  };

  const config = { ...defaultOptions, ...options };

  // Estados reactivos
  const query = ref('');
  const results = ref([]);
  const isSearching = ref(false);
  const error = ref(null);
  const searchHistory = ref([]);
  const selectedResultIndex = ref(-1);

  // Caché de búsquedas
  const searchCache = ref(new Map());
  
  // Variables internas
  let debounceTimer = null;
  let searchFunction = null;

  // Estados computados
  const hasQuery = computed(() => query.value.length >= config.minQueryLength);
  const hasResults = computed(() => results.value.length > 0);
  const hasError = computed(() => error.value !== null);
  const canSearch = computed(() => hasQuery.value && !isSearching.value);
  const isEmpty = computed(() => !hasQuery.value && !hasResults.value);
  const isValidQuery = computed(() => query.value.trim().length >= config.minQueryLength);

  // Estadísticas de caché
  const cacheStats = computed(() => ({
    size: searchCache.value.size,
    maxSize: config.maxCacheSize,
    hitRate: searchCache.value.size > 0 ? 
      Array.from(searchCache.value.values()).reduce((acc, item) => acc + (item.hits || 0), 0) / searchCache.value.size : 0
  }));

  /**
   * Configura la función de búsqueda
   * @param {Function} searchFn - Función que ejecuta la búsqueda
   */
  const setSearchFunction = (searchFn) => {
    if (typeof searchFn !== 'function') {
      throw new Error('Search function must be a function');
    }
    searchFunction = searchFn;
  };

  /**
   * Ejecuta la búsqueda con debouncing
   * @param {string} searchQuery - Consulta de búsqueda
   */
  const search = async (searchQuery = query.value) => {
    if (!searchFunction) {
      error.value = 'No search function configured';
      Logger.error('[useSearch] No search function configured');
      return;
    }

    if (!searchQuery || searchQuery.trim().length < config.minQueryLength) {
      results.value = [];
      return;
    }

    const trimmedQuery = searchQuery.trim();
    
    // Verificar caché primero
    const cachedResult = getCachedResult(trimmedQuery);
    if (cachedResult) {
      results.value = cachedResult.data;
      Logger.debug(`[useSearch] Using cached result for query: ${trimmedQuery}`);
      return;
    }

    isSearching.value = true;
    error.value = null;

    try {
      Logger.debug(`[useSearch] Executing search for query: ${trimmedQuery}`);
      
      const searchResults = await searchFunction(trimmedQuery);
      
      // Validar resultados
      if (!Array.isArray(searchResults)) {
        throw new Error('Search function must return an array');
      }

      results.value = searchResults;
      
      // Guardar en caché
      setCachedResult(trimmedQuery, searchResults);
      
      // Agregar al historial
      addToHistory(trimmedQuery);
      
      Logger.debug(`[useSearch] Search completed. Found ${searchResults.length} results`);
      
    } catch (err) {
      error.value = err.message || 'Error durante la búsqueda';
      Logger.error('[useSearch] Search error:', err);
      results.value = [];
    } finally {
      isSearching.value = false;
    }
  };

  /**
   * Búsqueda inmediata sin debouncing
   * @param {string} searchQuery - Consulta de búsqueda
   */
  const searchImmediate = async (searchQuery) => {
    clearDebounce();
    await search(searchQuery);
  };

  /**
   * Búsqueda con debouncing
   * @param {string} searchQuery - Consulta de búsqueda
   */
  const searchDebounced = (searchQuery = query.value) => {
    clearDebounce();
    
    // Don't search if no search function is configured yet
    if (!searchFunction) {
      return;
    }
    
    if (!searchQuery || searchQuery.trim().length < config.minQueryLength) {
      results.value = [];
      return;
    }

    debounceTimer = setTimeout(() => {
      search(searchQuery);
    }, config.debounceDelay);
  };

  /**
   * Obtiene resultado del caché
   * @param {string} searchQuery - Consulta de búsqueda
   * @returns {Object|null} - Resultado cacheado o null
   */
  const getCachedResult = (searchQuery) => {
    const cacheKey = searchQuery.toLowerCase();
    const cachedItem = searchCache.value.get(cacheKey);
    
    if (!cachedItem) return null;
    
    // Verificar expiración
    if (Date.now() - cachedItem.timestamp > config.cacheExpiration) {
      searchCache.value.delete(cacheKey);
      return null;
    }
    
    // Incrementar hits
    cachedItem.hits = (cachedItem.hits || 0) + 1;
    
    return cachedItem;
  };

  /**
   * Guarda resultado en caché
   * @param {string} searchQuery - Consulta de búsqueda
   * @param {Array} data - Resultados de la búsqueda
   */
  const setCachedResult = (searchQuery, data) => {
    const cacheKey = searchQuery.toLowerCase();
    
    // Limpiar caché si está lleno
    if (searchCache.value.size >= config.maxCacheSize) {
      // Remover el item más antiguo
      const oldestKey = Array.from(searchCache.value.keys())[0];
      searchCache.value.delete(oldestKey);
    }
    
    searchCache.value.set(cacheKey, {
      data: [...data],
      timestamp: Date.now(),
      hits: 0
    });
  };

  /**
   * Agrega consulta al historial
   * @param {string} searchQuery - Consulta de búsqueda
   */
  const addToHistory = (searchQuery) => {
    const trimmedQuery = searchQuery.trim();
    
    if (!trimmedQuery || searchHistory.value.includes(trimmedQuery)) {
      return;
    }
    
    searchHistory.value.unshift(trimmedQuery);
    
    // Limitar tamaño del historial
    if (searchHistory.value.length > config.historyMaxSize) {
      searchHistory.value = searchHistory.value.slice(0, config.historyMaxSize);
    }
  };

  /**
   * Limpia el historial de búsquedas
   */
  const clearHistory = () => {
    searchHistory.value = [];
    Logger.debug('[useSearch] Search history cleared');
  };

  /**
   * Limpia el caché de búsquedas
   */
  const clearCache = () => {
    searchCache.value.clear();
    Logger.debug('[useSearch] Search cache cleared');
  };

  /**
   * Manejo de navegación por teclado en resultados
   * @param {string} direction - 'up' o 'down'
   */
  const navigateResults = (direction) => {
    if (!hasResults.value) return;
    
    const maxIndex = results.value.length - 1;
    
    if (direction === 'down') {
      selectedResultIndex.value = selectedResultIndex.value < maxIndex ? 
        selectedResultIndex.value + 1 : 0;
    } else if (direction === 'up') {
      selectedResultIndex.value = selectedResultIndex.value > 0 ? 
        selectedResultIndex.value - 1 : maxIndex;
    }
  };

  /**
   * Selecciona un resultado por índice
   * @param {number} index - Índice del resultado
   */
  const selectResult = (index) => {
    if (index >= 0 && index < results.value.length) {
      selectedResultIndex.value = index;
      return results.value[index];
    }
    return null;
  };

  /**
   * Obtiene el resultado seleccionado actualmente
   */
  const getSelectedResult = () => {
    if (selectedResultIndex.value >= 0 && selectedResultIndex.value < results.value.length) {
      return results.value[selectedResultIndex.value];
    }
    return null;
  };

  /**
   * Limpia los resultados de búsqueda
   */
  const clearResults = () => {
    results.value = [];
    selectedResultIndex.value = -1;
    error.value = null;
  };

  /**
   * Limpia la consulta actual
   */
  const clearQuery = () => {
    query.value = '';
    clearResults();
    clearDebounce();
  };

  /**
   * Limpia el timer de debounce
   */
  const clearDebounce = () => {
    if (debounceTimer) {
      clearTimeout(debounceTimer);
      debounceTimer = null;
    }
  };

  /**
   * Reinicia todos los estados
   */
  const reset = () => {
    clearQuery();
    clearResults();
    clearDebounce();
    isSearching.value = false;
    error.value = null;
    selectedResultIndex.value = -1;
  };

  /**
   * Filtra resultados localmente
   * @param {Function} filterFn - Función de filtrado
   */
  const filterResults = (filterFn) => {
    if (typeof filterFn !== 'function') {
      Logger.error('[useSearch] Filter function must be a function');
      return;
    }
    
    results.value = results.value.filter(filterFn);
    selectedResultIndex.value = -1;
  };

  /**
   * Ordena resultados localmente
   * @param {Function} sortFn - Función de ordenamiento
   */
  const sortResults = (sortFn) => {
    if (typeof sortFn !== 'function') {
      Logger.error('[useSearch] Sort function must be a function');
      return;
    }
    
    results.value = [...results.value].sort(sortFn);
    selectedResultIndex.value = -1;
  };

  // Watcher para búsqueda automática con debouncing
  watch(query, (newQuery) => {
    if (newQuery !== query.value) return; // Evitar loops
    if (!searchFunction) return; // Don't search if no function configured
    searchDebounced(newQuery);
  });

  // Limpiar timer al desmontar
  const cleanup = () => {
    clearDebounce();
  };

  return {
    // Estados
    query,
    results,
    isSearching,
    error,
    searchHistory,
    selectedResultIndex,

    // Estados computados
    hasQuery,
    hasResults,
    hasError,
    canSearch,
    isEmpty,
    isValidQuery,
    cacheStats,

    // Métodos principales
    setSearchFunction,
    search,
    searchImmediate,
    searchDebounced,

    // Navegación
    navigateResults,
    selectResult,
    getSelectedResult,

    // Historial y caché
    clearHistory,
    clearCache,
    addToHistory,

    // Utilidades
    filterResults,
    sortResults,
    clearResults,
    clearQuery,
    reset,
    cleanup
  };
}
