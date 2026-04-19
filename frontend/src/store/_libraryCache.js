/**
 * Cache compartido para la llamada get_library_items.
 * 
 * Tanto booksStore.fetchBooks() como moviesStore.fetchMovies() llaman a la misma
 * acción del backend (get_library_items). Este módulo garantiza que, si se invocan
 * al mismo tiempo (e.g. Promise.all), solo se realice UNA petición HTTP y ambos
 * stores reciban los datos de esa misma respuesta.
 */
import { useAuthStore } from './auth'
import Logger from '@/utils/logger'

let _pendingRequest = null

/**
 * Retorna los datos de get_library_items, reutilizando la petición si ya hay una en vuelo.
 * @returns {Promise<{books: Array, movies: Array}>}
 */
export async function fetchLibraryItems() {
  // Si ya hay una petición en curso, reutilizarla
  if (_pendingRequest) {
    Logger.debug('[LibraryCache] Reusing in-flight get_library_items request')
    return _pendingRequest
  }

  _pendingRequest = _doFetch()

  try {
    const result = await _pendingRequest
    return result
  } finally {
    // Limpiar la referencia para que la próxima llamada haga un nuevo request
    _pendingRequest = null
  }
}

async function _doFetch() {
  const authStore = useAuthStore()
  Logger.debug('[LibraryCache] Fetching library items (single request)...')
  
  const response = await authStore.authenticatedApiCall('get_library_items')

  if (response.data.status !== 'success') {
    throw new Error(response.data.message || 'Failed to fetch library items')
  }

  const data = response.data.data || {}
  return {
    books: Array.isArray(data.books) ? data.books : [],
    movies: Array.isArray(data.movies) ? data.movies : []
  }
}
