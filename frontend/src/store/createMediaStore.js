import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import { fetchLibraryItems } from './_libraryCache'
import { getMediaConfig } from '@/config/mediaRegistry'
import { handleStoreError } from '@/utils/storeHelpers'
import Logger from '@/utils/logger'

/**
 * Factoría de stores de medio.
 *
 * Los cinco stores (books, movies, games, albums, videos) tenían el mismo
 * estado y las mismas once acciones cambiando solo el nombre. Aquí se escriben
 * una vez, con miembros canónicos (`fetch`, `add`, `byId`…), y se generan
 * además los **alias con nombre de medio** (`fetchVideos`, `addVideo`,
 * `getVideoById`…) desde `mediaRegistry`, para que ninguno de los consumidores
 * que ya existen tenga que cambiar.
 *
 * Detrás de esos nombres iguales había ocho divergencias reales (dos acciones
 * de búsqueda en libros, payload de alta anidado o plano, qué se empuja al
 * array tras el alta, cinco claves de id, dos semánticas de `*ByStatus`…).
 * **Todas se declaran en el registry**, ninguna se resuelve con un `if` por
 * medio aquí dentro: la tabla completa está en el plan «Vista de Detalle y
 * Stores Genéricos», hito M1.
 *
 * Devuelve el `useXStore` sin invocar —no el resultado de invocarlo—, que es lo
 * que `storeToRefs` necesita, y `state` es una función que construye un objeto
 * nuevo por medio: si se compartiera, borrar un álbum vaciaría los vídeos.
 *
 * @param {string} media - clave del medio en mediaRegistry
 * @returns {Function} el composable `useXStore` de Pinia
 */
export function createMediaStore (media) {
  const config = getMediaConfig(media)
  const { store: cfg, api } = config

  if (!cfg || !api) {
    throw new Error(`[createMediaStore] El medio "${media}" no declara los bloques 'store' y 'api' en mediaRegistry`)
  }

  const { collection, One, Many } = cfg
  const idKey = cfg.idPayloadKey
  const ratingField = cfg.ratingField || 'user_rating'
  const log = `[${Many}Store]`

  // Cómo se decide que un ítem del array es "el del id que me han dado".
  // `matches` manda; `byId` e `inLibrary` solo se declaran donde difieren.
  const matches = cfg.matches
  const matchesById = cfg.byId || matches
  const matchesInLibrary = cfg.inLibrary || matches

  // ─── Estado ────────────────────────────────────────────────────────────
  // El array conserva el nombre del medio (`videos`, `books`…) porque es lo
  // que leen los consumidores; `items` queda como getter canónico.
  const state = () => ({
    [collection]: [],
    allowedStatuses: [],
    userTags: [],
    isLoading: false,
    error: null,
    lastSearchQuery: '',
    searchResults: [],
    isSearching: false
  })

  // ─── Getters ───────────────────────────────────────────────────────────
  const getters = {
    items: (s) => s[collection],
    total: (s) => s[collection].length,
    hasAny: (s) => s[collection].length > 0,
    hasSearchResults: (s) => s.searchResults.length > 0,
    withRating: (s) => s[collection].filter((i) => i[ratingField] && i[ratingField] > 0),
    averageRating: (s) => {
      const rated = s[collection].filter((i) => i[ratingField] && i[ratingField] > 0)
      if (rated.length === 0) return 0
      const sum = rated.reduce((acc, i) => acc + parseFloat(i[ratingField]), 0)
      return (sum / rated.length).toFixed(1)
    },
    byId: (s) => (id) => s[collection].find((i) => matchesById(i, id)),
    isInLibrary: (s) => (id) => s[collection].some((i) => matchesInLibrary(i, id))
  }

  // `*ByStatus` y `*CountByStatus` tienen dos semánticas incompatibles y hay
  // que conservar las dos: libros y películas agrupan por NOMBRE de estado y
  // devuelven un objeto ya calculado; el resto filtra por `status_id` contra
  // `allowedStatuses` y devuelve una función.
  if (cfg.statusMode === 'byName') {
    getters.byStatus = (s) => {
      const groups = {}
      s[collection].forEach((item) => {
        if (Array.isArray(item.userStatuses)) {
          item.userStatuses.forEach((status) => {
            if (!groups[status]) groups[status] = []
            groups[status].push(item)
          })
        }
      })
      return groups
    }
    getters.countByStatus = (s) => {
      const counts = {}
      s[collection].forEach((item) => {
        if (Array.isArray(item.userStatuses)) {
          item.userStatuses.forEach((status) => {
            counts[status] = (counts[status] || 0) + 1
          })
        }
      })
      return counts
    }
  } else {
    getters.byStatus = (s) => (statusId) => s[collection].filter(
      (i) => i.userStatuses && i.userStatuses.some((st) => st.status_id === statusId)
    )
    getters.countByStatus = (s) => {
      const counts = {}
      s.allowedStatuses.forEach((status) => {
        counts[status.id] = s[collection].filter(
          (i) => i.userStatuses && i.userStatuses.some((st) => st.status_id === status.id)
        ).length
      })
      return counts
    }
  }

  // ─── Acciones ──────────────────────────────────────────────────────────
  const actions = {
    /**
     * Carga la colección del usuario. Libros y películas no llaman al backend
     * directamente: comparten `get_library_items` a través de `_libraryCache`,
     * que deduplica la petición en vuelo cuando `/library` pide los dos.
     */
    async fetch () {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug(`${log} Fetching ${collection} from library...`)

        if (api.list.fromLibraryCache) {
          const data = await fetchLibraryItems()
          const stamp = api.list.stamp || {}
          this[collection] = (data[api.list.fromLibraryCache] || []).map((item) => ({ ...item, ...stamp }))
          Logger.debug(`${log} Fetched ${this[collection].length} ${collection}`)
          return this[collection]
        }

        const authStore = useAuthStore()
        const response = await authStore.authenticatedApiCall(api.list, api.listPayload || {})

        if (response.data.status === 'success') {
          this[collection] = Array.isArray(response.data.data) ? response.data.data : []
          Logger.debug(`${log} Fetched ${this[collection].length} ${collection}`)
          return this[collection]
        }
        throw new Error(response.data.message || `Failed to fetch ${collection}`)
      } catch (err) {
        this.error = this._handleError(err, `Failed to fetch ${collection}`)
        Logger.error(`${log} Error fetching ${collection}:`, err)
        // Solo la vía del caché vaciaba el array al fallar; se conserva.
        if (api.list.fromLibraryCache) this[collection] = []
        return []
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Busca en la API externa del medio. `api.search` es el nombre de la acción,
     * o una función cuando el medio elige entre varias (libros: ISBN o título).
     */
    async search (query) {
      if (!query || query.trim() === '') {
        this.searchResults = []
        return []
      }

      this.isSearching = true
      this.error = null
      this.lastSearchQuery = query

      try {
        Logger.debug(`${log} Searching ${collection}: "${query}"`)
        const authStore = useAuthStore()
        const [action, payload] = typeof api.search === 'function'
          ? api.search(query)
          : [api.search, { [api.searchKey || 'name']: query }]

        const response = await authStore.authenticatedApiCall(action, payload)

        if (response.data.status === 'success') {
          // La mayoría de búsquedas devuelven la lista pelada en `data`; las que
          // avisan de degradación (vídeos) la anidan bajo su colección para poder
          // mandar `stale`/`cached_at` al lado. Se aceptan las dos formas.
          const payload = response.data.data
          this.searchResults = Array.isArray(payload)
            ? payload
            : (payload?.[collection] || [])
          Logger.debug(`${log} Found ${this.searchResults.length} ${collection}`)
          return this.searchResults
        }
        throw new Error(response.data.message || 'Search failed')
      } catch (err) {
        this.error = this._handleError(err, 'Search failed')
        Logger.error(`${log} Error searching ${collection}:`, err)
        if (cfg.clearSearchOnError) this.searchResults = []
        return []
      } finally {
        this.isSearching = false
      }
    },

    /**
     * Añade un ítem a la biblioteca. El payload va anidado bajo la clave del
     * medio salvo en vídeos, y lo que se empuja al array es la respuesta del
     * backend o un objeto local, según lo que hiciera cada store.
     */
    async add (item, statuses = []) {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug(`${log} Adding ${media} to library:`, item)
        const authStore = useAuthStore()

        // Libros mete los estados permitidos dentro del payload, así que se
        // asegura de tenerlos antes de componerlo.
        if (cfg.addNeedsAllowedStatuses && this.allowedStatuses.length === 0) {
          await this.fetchAllowedStatuses()
        }

        const body = cfg.toAddPayload(item, statuses, this.allowedStatuses)
        const payload = cfg.addPayloadKey ? { [cfg.addPayloadKey]: body } : body
        const response = await authStore.authenticatedApiCall(api.add, payload)

        if (response.data.status === 'success') {
          const added = cfg.addPushes === 'local'
            ? cfg.toLocalItem(item, statuses, body)
            : (response.data.data || body)
          this[collection].push(added)
          Logger.debug(`${log} ${One} added successfully`)
          // La clave con nombre de medio se conserva: los consumidores viejos
          // esperan `result.video` / `result.album` / …
          return { success: true, item: added, [media]: added }
        }
        throw new Error(response.data.message || `Failed to add ${media}`)
      } catch (err) {
        this.error = this._handleError(err, `Failed to add ${media}`)
        Logger.error(`${log} Error adding ${media}:`, err)
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /** Elimina un ítem de la biblioteca. */
    async remove (id) {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug(`${log} Deleting ${media}:`, id)
        const authStore = useAuthStore()
        const response = await authStore.authenticatedApiCall(api.remove, {
          [idKey]: id,
          ...(cfg.deleteExtra || {})
        })

        if (response.data.status === 'success') {
          this[collection] = this[collection].filter((i) => !matches(i, id))
          Logger.debug(`${log} ${One} deleted successfully`)
          return { success: true }
        }
        throw new Error(response.data.message || `Failed to delete ${media}`)
      } catch (err) {
        this.error = this._handleError(err, `Failed to delete ${media}`)
        Logger.error(`${log} Error deleting ${media}:`, err)
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /** Actualiza la valoración del usuario. */
    async updateRating (id, rating) {
      try {
        Logger.debug(`${log} Updating ${media} rating: ${id} -> ${rating}`)
        const authStore = useAuthStore()
        const response = await authStore.authenticatedApiCall(api.rating, { [idKey]: id, rating })

        if (response.data.status === 'success') {
          const item = this[collection].find((i) => matches(i, id))
          if (item) item[ratingField] = rating
          Logger.debug(`${log} ${One} rating updated successfully`)
          return { success: true }
        }
        throw new Error(response.data.message || 'Failed to update rating')
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update rating')
        Logger.error(`${log} Error updating ${media} rating:`, err)
        return { success: false, message: this.error }
      }
    },

    /** Actualiza los estados del usuario sobre el ítem. */
    async updateStatuses (id, statuses) {
      try {
        Logger.debug(`${log} Updating ${media} statuses: ${id}`, statuses)
        const authStore = useAuthStore()
        const response = await authStore.authenticatedApiCall(api.statuses, { [idKey]: id, statuses })

        if (response.data.status === 'success') {
          const item = this[collection].find((i) => matches(i, id))
          if (item) item.userStatuses = statuses
          Logger.debug(`${log} ${One} statuses updated successfully`)
          return { success: true }
        }
        throw new Error(response.data.message || 'Failed to update statuses')
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update statuses')
        Logger.error(`${log} Error updating ${media} statuses:`, err)
        return { success: false, message: this.error }
      }
    },

    /** Edición completa del ítem del usuario. */
    async edit (id, updatedData) {
      try {
        Logger.debug(`${log} Editing ${media}: ${id}`, updatedData)
        const authStore = useAuthStore()
        const response = await authStore.authenticatedApiCall(api.edit, {
          [idKey]: id,
          ...updatedData
        })

        if (response.data.status === 'success') {
          const index = this[collection].findIndex((i) => matches(i, id))
          if (index !== -1) {
            this[collection][index] = { ...this[collection][index], ...updatedData }
          }
          Logger.debug(`${log} ${One} edited successfully`)
          return { success: true }
        }
        throw new Error(response.data.message || `Failed to edit ${media}`)
      } catch (err) {
        this.error = this._handleError(err, `Failed to edit ${media}`)
        Logger.error(`${log} Error editing ${media}:`, err)
        return { success: false, message: this.error }
      }
    },

    /** Estados permitidos para este medio. */
    async fetchAllowedStatuses () {
      try {
        Logger.debug(`${log} Fetching allowed statuses...`)
        const authStore = useAuthStore()
        const response = await authStore.authenticatedApiCall(api.allowedStatuses)

        if (response.data.status === 'success') {
          this.allowedStatuses = response.data.data || []
          Logger.debug(`${log} Fetched ${this.allowedStatuses.length} allowed statuses`)
          return this.allowedStatuses
        }
        throw new Error(response.data.message || 'Failed to fetch allowed statuses')
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch allowed statuses')
        Logger.error(`${log} Error fetching allowed statuses:`, err)
        return []
      }
    },

    /** Etiquetas del usuario para este medio. */
    async fetchUserTags () {
      try {
        Logger.debug(`${log} Fetching user tags...`)
        const authStore = useAuthStore()
        const response = await authStore.authenticatedApiCall(api.tags.list)

        if (response.data.status === 'success') {
          this.userTags = response.data.data || []
          Logger.debug(`${log} Fetched ${this.userTags.length} user tags`)
          return this.userTags
        }
        throw new Error(response.data.message || 'Failed to fetch user tags')
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch user tags')
        Logger.error(`${log} Error fetching user tags:`, err)
        return []
      }
    },

    /** Crea una etiqueta nueva. */
    async createTag (name, color = cfg.tagDefaultColor) {
      try {
        Logger.debug(`${log} Creating tag: ${name}`)
        const authStore = useAuthStore()
        const response = await authStore.authenticatedApiCall(api.tags.create, { name, color })

        if (response.data.status === 'success') {
          const newTag = response.data.data
          this.userTags.push(newTag)
          Logger.debug(`${log} Tag created successfully`)
          return { success: true, tag: newTag }
        }
        throw new Error(response.data.message || 'Failed to create tag')
      } catch (err) {
        this.error = this._handleError(err, 'Failed to create tag')
        Logger.error(`${log} Error creating tag:`, err)
        return { success: false, message: this.error }
      }
    },

    /** Asigna etiquetas a un ítem. */
    async updateTags (itemId, tagIds) {
      try {
        Logger.debug(`${log} Updating ${media} tags: ${itemId}`, tagIds)
        const authStore = useAuthStore()
        const response = await authStore.authenticatedApiCall(api.tags.update, {
          [cfg.tagsIdKey || idKey]: itemId,
          tag_ids: tagIds
        })

        if (response.data.status === 'success') {
          Logger.debug(`${log} ${One} tags updated successfully`)
          return { success: true }
        }
        throw new Error(response.data.message || 'Failed to update tags')
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update tags')
        Logger.error(`${log} Error updating ${media} tags:`, err)
        return { success: false, message: this.error }
      }
    },

    clearSearchResults () {
      this.searchResults = []
      this.lastSearchQuery = ''
    },

    clearError () {
      this.error = null
    },

    _handleError (err, defaultMessage = 'Operation failed') {
      return handleStoreError(err, defaultMessage)
    }
  }

  // ─── Alias con nombre de medio ─────────────────────────────────────────
  // Los ~103 puntos de llamada que hay hoy usan estos nombres. Se generan aquí
  // en vez de reescribirlos: el store es uno, la superficie pública no cambia.
  getters[`total${Many}`] = getters.total
  getters[`has${Many}`] = getters.hasAny
  getters[`${collection}WithRating`] = getters.withRating
  getters[`${collection}ByStatus`] = getters.byStatus
  getters[`${media}CountByStatus`] = getters.countByStatus
  getters[`is${One}InLibrary`] = getters.isInLibrary
  // Libros es el único cuyo getter por id no se llama `get{One}ById`.
  getters[cfg.byIdGetterName || `get${One}ById`] = getters.byId

  if (cfg.altGetter) {
    getters[cfg.altGetter.name] = (s) => (id) => s[collection].find((i) => i[cfg.altGetter.key] === id)
  }

  actions[`fetch${Many}`] = actions.fetch
  actions[`search${Many}`] = actions.search
  actions[`add${One}`] = actions.add
  actions[`delete${One}`] = actions.remove
  actions[`update${One}Rating`] = actions.updateRating
  actions[`update${One}Statuses`] = actions.updateStatuses
  actions[`edit${One}`] = actions.edit
  actions[`update${One}Tags`] = actions.updateTags

  return defineStore(cfg.id, { state, getters, actions })
}
