import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/store/auth'
import { useBooksStore } from '@/store/books'
import { useMoviesStore } from '@/store/movies'
import { useGamesStore } from '@/store/games'
import { useAlbumsStore } from '@/store/albums'
import { useVideosStore } from '@/store/videos'
import { getMediaConfig } from '@/config/mediaRegistry'
import { useConfirmationModal } from './useConfirmationModal'
import Logger from '@/utils/logger'

// Los `useXStore` por medio. Mapa explícito aquí y no una entrada del registry:
// `mediaRegistry` es lo que importa `createMediaStore`, así que declarar ahí el
// store cerraría el ciclo de imports.
//
// Exportado desde el 2026-08-27 para `store/inbox.js`, que da de alta un ítem
// recomendado y necesita el store de su medio. Importarlo desde aquí no
// reintroduce el ciclo: quien lo consume no lo consume el registry.
export const mediaStores = {
  book: useBooksStore,
  movie: useMoviesStore,
  game: useGamesStore,
  album: useAlbumsStore,
  video: useVideosStore
}

/**
 * Factoría de composables de medio.
 *
 * Los cuatro composables (books, movies, games, albums) sumaban 1.382 líneas
 * con la misma forma cambiando el prefijo. Medido en el M0 del plan
 * «Composables Genéricos por Medio»: el 78,4 % era común o difería solo en
 * detalles declarables (la clave del id, la acción de la API, los campos que se
 * sincronizan en local, los criterios de filtrado). Eso se escribe aquí una vez
 * y los alias con nombre de medio (`fetchAlbums`, `addAlbum`, `findAlbumById`…)
 * se generan desde `mediaRegistry`, para que ningún consumidor cambie.
 *
 * El 21,6 % restante son **extras** de un solo medio (las sesiones de lectura
 * de libros, el seguimiento de temporadas de películas). No se generalizan: se
 * escriben en el wrapper del medio y llegan aquí por el segundo argumento.
 *
 * @param {string} media - clave del medio en mediaRegistry
 * @param {Function} [extras] - `(core, store) => ({…})`, lo propio del medio
 * @returns {Object} el composable ya montado
 */
export function createMediaComposable (media, extras = () => ({})) {
  const config = getMediaConfig(media)
  const { store: cfg, api, composable: comp = {} } = config

  if (!cfg || !api) {
    throw new Error(`[createMediaComposable] El medio "${media}" no declara los bloques 'store' y 'api' en mediaRegistry`)
  }

  const { collection, One, Many } = cfg
  const idKey = cfg.idPayloadKey
  const tagsIdKey = cfg.tagsIdKey || idKey
  const ratingField = cfg.ratingField || 'user_rating'
  const log = `[use${Many}]`

  const store = mediaStores[media]()
  const authStore = useAuthStore()

  // Estado reactivo. `storeToRefs` necesita el store ya invocado —al revés que
  // `createMediaStore`, que devuelve el `useXStore` sin invocar—; lo que no
  // puede hacerse es cachear nada a nivel de módulo, o borrar un álbum vaciaría
  // los vídeos.
  const {
    [collection]: items,
    allowedStatuses,
    userTags,
    isLoading,
    error,
    searchResults,
    isSearching,
    lastSearchQuery,
    [`total${Many}`]: total,
    [`has${Many}`]: hasAny,
    hasSearchResults,
    [`${collection}WithRating`]: withRating,
    averageRating,
    [`${collection}ByStatus`]: byStatus,
    [`${media}CountByStatus`]: countByStatus
  } = storeToRefs(store)

  // Acciones del store, por delegación directa.
  const {
    [`fetch${Many}`]: fetchItems,
    [`search${Many}`]: searchStore,
    fetchAllowedStatuses,
    fetchUserTags,
    createTag: createTagStore,
    [`update${One}Tags`]: updateItemTags,
    clearSearchResults,
    clearError
  } = store

  /** Cómo se nombra un ítem en un mensaje al usuario. */
  const titleOf = (item) => item && (item.title || item.name)

  /**
   * Añade un ítem pre-cargando los estados permitidos si aún no están.
   */
  const add = async (item, statuses = []) => {
    if (allowedStatuses.value.length === 0) {
      await fetchAllowedStatuses()
    }

    return await store[`add${One}`](item, statuses)
  }

  /**
   * Elimina un ítem CON confirmación modal.
   */
  const remove = async (id, skipConfirmation = false) => {
    const { confirmDelete } = useConfirmationModal()

    try {
      const item = items.value.find((i) => cfg.matches(i, id))
      const label = titleOf(item) || `${comp.deleteIdLabel || 'ID'}: ${id}`

      if (!skipConfirmation) {
        const confirmed = await confirmDelete(
          label,
          comp.deleteWarning || 'Esta acción no se puede deshacer'
        )

        if (!confirmed) {
          return { success: false, cancelled: true }
        }
      }

      return await store[`delete${One}`](id)
    } catch (err) {
      Logger.error(`${log} Error in delete${One} wrapper:`, err)
      return { success: false, message: err.message }
    }
  }

  /** Actualiza la valoración del usuario. */
  const updateRating = async (id, rating) => {
    return await store[`update${One}Rating`](id, rating)
  }

  /** Actualiza los estados del usuario sobre el ítem. */
  const updateStatuses = async (id, statuses) => {
    return await store[`update${One}Statuses`](id, statuses)
  }

  /**
   * Edición completa del ítem del usuario, con sincronización del array local.
   *
   * La valoración y los estados llegan bajo otro nombre (`personalRating`,
   * `statuses`) en los cuatro medios; el resto de campos que cada uno propaga
   * se declaran en `composable.editSyncFields` del registry.
   */
  const edit = async (id, userId, data = {}, tags = [], notes = []) => {
    try {
      Logger.debug(`${log} Editing user_${media}:`, { id, userId, data, tags, notes })

      const response = await authStore.authenticatedApiCall(api.edit, {
        [idKey]: id,
        userId,
        data,
        tags,
        notes
      })

      if (response.data.status === 'success') {
        const index = items.value.findIndex((i) => cfg.matches(i, id))
        if (index !== -1) {
          const current = items.value[index]
          const patch = {
            [ratingField]: data.personalRating !== undefined ? data.personalRating : current[ratingField],
            userStatuses: data.statuses || current.userStatuses
          }
          for (const field of comp.editSyncFields || []) {
            patch[field] = data[field] !== undefined ? data[field] : current[field]
          }
          items.value[index] = { ...current, ...patch }
        }

        Logger.debug(`${log} User ${media} edited successfully`)
        return { success: true }
      }
      throw new Error(response.data.message || `Error editing user_${media}`)
    } catch (err) {
      Logger.error(`${log} Error editing user_${media}:`, err)
      return { success: false, message: err.message }
    }
  }

  /** Crea una etiqueta CON validación del nombre. */
  const createUserTag = async (tagName, color = '#1976d2') => {
    if (!tagName || tagName.trim().length === 0) {
      return { success: false, message: 'Tag name cannot be empty' }
    }

    return await createTagStore(tagName, color)
  }

  /** Etiquetas asignadas a un ítem concreto. */
  const getTags = async (id) => {
    try {
      const response = await authStore.authenticatedApiCall(api.tags.get, { [tagsIdKey]: id })

      if (response.data.status === 'success') {
        return { success: true, data: response.data.data || [] }
      }
      throw new Error(response.data.message || `Error getting ${media} tags`)
    } catch (err) {
      Logger.error(`${log} Error getting ${media} tags:`, err)
      return { success: false, message: err.message }
    }
  }

  // ==========================================
  // HELPERS DE UTILIDAD (sin estado - solo funciones)
  // ==========================================

  /** Busca un ítem en el array local por su id. */
  const findById = (id) => {
    return items.value.find((i) => cfg.matches(i, id))
  }

  /** Alias de búsqueda. */
  const search = async (query) => {
    return await searchStore(query)
  }

  const core = {
    // ===== ESTADO REACTIVO (desde store) =====
    [collection]: items,
    searchResults,
    allowedStatuses,
    userTags,
    isLoading,
    isSearching,
    error,
    lastSearchQuery,

    // ===== GETTERS COMPUTADOS (desde store) =====
    [`total${Many}`]: total,
    [`has${Many}`]: hasAny,
    hasSearchResults,
    [`${collection}WithRating`]: withRating,
    averageRating,
    [`${collection}ByStatus`]: byStatus,
    [`${media}CountByStatus`]: countByStatus,

    // ===== MÉTODOS PRINCIPALES =====
    [`fetch${Many}`]: fetchItems,
    [`search${Many}`]: search,
    [`add${One}`]: add,
    [`editUser${One}`]: edit,
    [`delete${One}`]: remove,
    [`update${One}Rating`]: updateRating,
    [`update${One}Statuses`]: updateStatuses,
    fetchAllowedStatuses,

    // ===== TAGS =====
    fetchUserTags,
    createUserTag,
    [`get${One}Tags`]: getTags,
    [`update${One}Tags`]: updateItemTags,

    // ===== UTILIDADES =====
    // Libros es el único cuyo helper por id no se llama `find{One}ById`.
    [comp.findByIdName || `find${One}ById`]: findById,
    clearSearchResults,
    clearError
  }

  return { ...core, ...extras(core, store) }
}
