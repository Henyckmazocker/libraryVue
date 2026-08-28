/**
 * Lists Store using Pinia
 *
 * Las listas con nombre que mezclan medios. Una lista privada funciona sin
 * amigos, sin bandeja y sin nada de lo social: es lo que hace que esta pantalla
 * valga por sí sola.
 *
 * **Quién puede editar lo decide el servidor, no este store.** `get_list`
 * devuelve `can_edit` e `is_owner` ya resueltos por `ListAccess`, y la interfaz
 * los pinta sin recalcular nada: la regla vive en el backend y solo ahí. Repetir
 * aquí «es mía o soy colaborador» sería la duodécima copia de la regla que todo
 * este plan existe para evitar.
 */
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import Logger from '@/utils/logger'

export const useListsStore = defineStore('lists', {
  state: () => ({
    // Las tarjetas de /lists: cada una con su `item_count` e `is_owner`.
    lists: [],
    // La lista abierta en /lists/:id, con sus ítems.
    current: null,
    currentItems: [],
    currentCollaborators: [],
    // Las listas PÚBLICAS de otro, para /user/:username. Van aparte de `lists`
    // a propósito: son de otra persona y no se mezclan con las mías, que es lo
    // que pasaría si `fetchMyLists` las pisara al volver al propio perfil.
    userLists: [],
    isLoading: false,
    isSaving: false,
    error: null
  }),

  getters: {
    hasLists: (state) => state.lists.length > 0,
    hasUserLists: (state) => state.userLists.length > 0,
    // `can_edit` viene del servidor; sin lista abierta, no se puede editar nada.
    canEditCurrent: (state) => Boolean(state.current?.can_edit),
    isCurrentOwner: (state) => Boolean(state.current?.is_owner)
  },

  actions: {
    async fetchMyLists () {
      const authStore = useAuthStore()
      this.isLoading = true
      this.error = null

      try {
        const response = await authStore.authenticatedApiCall('get_my_lists', {})

        if (response.data.status === 'success') {
          this.lists = response.data.data?.lists ?? []
        } else {
          this.error = response.data.message || 'No se pudieron cargar tus listas'
        }
      } catch (err) {
        Logger.error('[ListsStore] fetchMyLists error:', err)
        this.error = 'No se pudieron cargar tus listas'
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Abre una lista. El 403 de una lista ajena y el 404 de una que no existe
     * llegan como error del backend y se distinguen por código, no por texto:
     * el backend responde en inglés, como todo el repo.
     */
    async fetchList (listId) {
      const authStore = useAuthStore()
      this.isLoading = true
      this.error = null
      this.current = null
      this.currentItems = []
      this.currentCollaborators = []

      try {
        const response = await authStore.authenticatedApiCall('get_list', { listId })

        if (response.data.status === 'success') {
          this.current = response.data.data?.list ?? null
          this.currentItems = response.data.data?.items ?? []
          this.currentCollaborators = response.data.data?.collaborators ?? []
          return { success: true }
        }

        this.error = this._messageFor(response.data.http_code)
        return { success: false, code: response.data.http_code ?? null }
      } catch (err) {
        Logger.error('[ListsStore] fetchList error:', err)
        const code = err.response?.status ?? err.response?.data?.http_code ?? null
        this.error = this._messageFor(code)
        return { success: false, code }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Las listas públicas de otro usuario, para su perfil.
     *
     * Lo que NO se ve aquí es la mitad importante: el filtro por `public` va en
     * el `WHERE` de la consulta del backend, no en un `.filter()` de este store.
     * Filtrarlo en el cliente significaría que las listas privadas de esa
     * persona viajaron hasta aquí.
     */
    async fetchUserLists (username) {
      const authStore = useAuthStore()
      this.isLoading = true
      this.error = null
      this.userLists = []

      try {
        const response = await authStore.authenticatedApiCall('get_user_lists', { username })

        if (response.data.status === 'success') {
          this.userLists = response.data.data?.lists ?? []
          return { success: true }
        }

        this.error = this._messageFor(response.data.http_code)
        return { success: false, code: response.data.http_code ?? null }
      } catch (err) {
        Logger.error('[ListsStore] fetchUserLists error:', err)
        const code = err.response?.status ?? err.response?.data?.http_code ?? null
        this.error = this._messageFor(code)
        return { success: false, code }
      } finally {
        this.isLoading = false
      }
    },

    async createList ({ name, description, visibility }) {
      return this._write('create_list', { name, description, visibility }, (data) => {
        // No se inserta a mano en `lists`: la fila del servidor trae
        // `created_at`, `item_count` y `is_owner`, y componerla aquí sería
        // inventarse tres campos que la tarjeta pinta.
        this.fetchMyLists()
        return { listId: data?.listId }
      })
    },

    async updateList (listId, changes) {
      return this._write('update_list', { listId, ...changes }, () => {
        if (this.current?.id === listId) {
          this.current = { ...this.current, ...changes }
        }
        this.fetchMyLists()
        return {}
      })
    },

    async deleteList (listId) {
      return this._write('delete_list', { listId }, () => {
        this.lists = this.lists.filter((l) => l.id !== listId)
        if (this.current?.id === listId) {
          this.current = null
          this.currentItems = []
        }
        return {}
      })
    },

    /**
     * Añade un ítem. `entityType` tiene que ser el medio con el que el backend
     * guarda el ítem y **no el del registry**: una serie se guarda con
     * `AddMovieUseCase`, así que viaja como `movie` — lo mismo que ya hacen
     * `cover_file`, `feed_events` y `recommendations`.
     */
    async addItem (listId, { entityType, entityId, entityTitle, entityCover }) {
      return this._write(
        'add_list_item',
        { listId, entityType, entityId, entityTitle, entityCover },
        (data) => {
          if (this.current?.id === listId && data) {
            this.currentItems = [...this.currentItems, data]
          }
          return { item: data }
        }
      )
    },

    async removeItem (listId, itemId) {
      return this._write('remove_list_item', { listId, itemId }, () => {
        this.currentItems = this.currentItems.filter((i) => i.id !== itemId)
        return {}
      })
    },

    /**
     * Invita a un amigo a colaborar. **No le da acceso**: crea una fila
     * pendiente en su bandeja, y el acceso llega cuando acepta. Por eso no se
     * añade nada a `currentCollaborators` aquí.
     */
    async inviteCollaborator (listId, userId) {
      const result = await this._write('invite_collaborator', { listId, userId }, () => ({}))

      // El 400 solo significa «no sois amigos» AQUÍ; en las demás escrituras es
      // un fallo de validación, así que no puede vivir en el mapa compartido.
      if (!result.success && result.code === 400) {
        this.error = 'Solo puedes invitar a tus amigos'
        return { ...result, message: this.error }
      }
      if (!result.success && result.code === 409) {
        this.error = 'Ya le has invitado, o ya colabora en esta lista'
        return { ...result, message: this.error }
      }

      return result
    },

    async removeCollaborator (listId, userId) {
      return this._write('remove_collaborator', { listId, userId }, () => {
        this.currentCollaborators = this.currentCollaborators.filter((c) => c.user_id !== userId)
        return {}
      })
    },

    /**
     * El paso común de las siete escrituras: llamar, distinguir el fallo del
     * cliente del sobre de error del backend, y devolver siempre `{ success }`
     * con el código HTTP intacto. Quien lo pinta traduce por **código**, no por
     * texto: el 409 del ítem repetido es del dominio y no puede perderse.
     */
    async _write (action, payload, onSuccess) {
      const authStore = useAuthStore()
      this.isSaving = true
      this.error = null

      try {
        const response = await authStore.authenticatedApiCall(action, payload)

        if (response.data.status !== 'success') {
          const code = response.data.http_code ?? null
          this.error = this._messageFor(code, response.data.message)
          return { success: false, message: this.error, code }
        }

        return { success: true, ...(onSuccess?.(response.data.data) ?? {}) }
      } catch (err) {
        Logger.error(`[ListsStore] ${action} error:`, err)
        const code = err.response?.status ?? err.response?.data?.http_code ?? null
        this.error = this._messageFor(code, err.response?.data?.message || err.message)
        return { success: false, message: this.error, code }
      } finally {
        this.isSaving = false
      }
    },

    /** Traducción por código. El backend responde en inglés y no se lee su texto. */
    _messageFor (code, fallback = null) {
      const messages = {
        403: 'No tienes permiso sobre esta lista',
        404: 'Esta lista ya no existe',
        409: 'Ese ítem ya está en la lista'
      }

      return messages[code] ?? fallback ?? 'No se pudo completar la operación'
    }
  }
})
