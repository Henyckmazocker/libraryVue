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

import { apiError } from '@/composables/useApiError'
import { t } from '@/config/i18n'
import { track, failureCode, mediaOrNull } from '@/analytics'
import { VISIBILITIES } from '@/analytics/catalog'

/**
 * Lo que las listas dicen de cada código. Lo genérico —y el respaldo— lo pone
 * `apiError`; aquí solo va lo que cambia por ser una lista y no otra cosa.
 */
const CLAVES = {
  403: 'lists.error403',
  404: 'lists.error404',
  409: 'lists.error409'
}

/** La visibilidad si es una de las del catálogo, o `null` (y entonces el evento se calla). */
const visibilityOrNull = (value) => (VISIBILITIES.includes(value) ? value : null)

/**
 * Cuántos de los campos que se mandan cambian de verdad respecto a la lista que había. Sin lista
 * de referencia, cuentan todos los enviados. `null` y cadena vacía son lo mismo: una descripción
 * vacía vuelve del servidor como una u otra.
 */
function changedFieldCount (before, changes) {
  const keys = Object.keys(changes ?? {})
  if (!before) return keys.length
  return keys.filter((key) => (before[key] ?? '') !== (changes[key] ?? '')).length
}

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
          this.error = apiError(response.data, { ...CLAVES, defecto: 'lists.loadError' })
        }
      } catch (err) {
        Logger.error('[ListsStore] fetchMyLists error:', err)
        this.error = t('lists.loadError')
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
          track('list_viewed')
          return { success: true }
        }

        this.error = apiError(response.data, CLAVES)
        track('list_view_failed', { code: failureCode(response.data) })
        return { success: false, code: response.data.http_code ?? null }
      } catch (err) {
        Logger.error('[ListsStore] fetchList error:', err)
        const code = err.response?.status ?? err.response?.data?.http_code ?? null
        this.error = apiError(code, CLAVES)
        track('list_view_failed', { code: failureCode(err) })
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

        this.error = apiError(response.data, CLAVES)
        return { success: false, code: response.data.http_code ?? null }
      } catch (err) {
        Logger.error('[ListsStore] fetchUserLists error:', err)
        const code = err.response?.status ?? err.response?.data?.http_code ?? null
        this.error = apiError(code, CLAVES)
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
        // Solo la visibilidad: ni el nombre ni la descripción. Sin ella el
        // backend la crea privada (`CreateListCommand`).
        const created = visibilityOrNull(visibility ?? 'private')
        if (created) track('list_created', { visibility: created })
        return { listId: data?.listId }
      })
    },

    async updateList (listId, changes) {
      // La lista de ANTES, para contar qué cambia: el callback de éxito ya la
      // pisa con los cambios.
      const before = this.current?.id === listId
        ? this.current
        : this.lists.find((l) => l.id === listId) ?? null
      const fields = changedFieldCount(before, changes)

      return this._write('update_list', { listId, ...changes }, (data) => {
        // La visibilidad con la que queda: la que devuelve el servidor, la
        // enviada o la que ya tenía.
        const visibility = visibilityOrNull(data?.visibility ?? changes?.visibility ?? before?.visibility)
        if (this.current?.id === listId) {
          this.current = { ...this.current, ...changes }
        }
        this.fetchMyLists()
        if (visibility) track('list_updated', { fields, visibility })
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
        track('list_deleted')
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
          // Solo el medio: ni el título ni la portada. El backend rechaza un
          // `entityType` fuera de `MediaListItem::VALID_ENTITY_TYPES`, así que
          // tras un OK siempre está en `MEDIA`; si no, se calla.
          const media = mediaOrNull(entityType)
          if (media) track('list_item_added', { media })
          return { item: data }
        }
      )
    },

    async removeItem (listId, itemId) {
      return this._write('remove_list_item', { listId, itemId }, () => {
        this.currentItems = this.currentItems.filter((i) => i.id !== itemId)
        track('list_item_removed')
        return {}
      })
    },

    /**
     * Invita a un amigo a colaborar. **No le da acceso**: crea una fila
     * pendiente en su bandeja, y el acceso llega cuando acepta. Por eso no se
     * añade nada a `currentCollaborators` aquí.
     */
    async inviteCollaborator (listId, userId) {
      const result = await this._write('invite_collaborator', { listId, userId }, () => {
        // Sin el invitado: quién es no es asunto de Augur.
        track('list_collaborator_invited')
        return {}
      })

      // El 400 solo significa «no sois amigos» AQUÍ; en las demás escrituras es
      // un fallo de validación, así que no puede vivir en el mapa compartido.
      if (!result.success && result.code === 400) {
        this.error = t('lists.inviteNotFriends')
        return { ...result, message: this.error }
      }
      if (!result.success && result.code === 409) {
        this.error = t('lists.inviteAlready')
        return { ...result, message: this.error }
      }

      return result
    },

    async removeCollaborator (listId, userId) {
      return this._write('remove_collaborator', { listId, userId }, () => {
        this.currentCollaborators = this.currentCollaborators.filter((c) => c.user_id !== userId)
        track('list_collaborator_removed')
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
          this.error = apiError(code, CLAVES)
          // El `api_error` ya lo contó `auth.apiCall`; este es el de dominio.
          track('list_action_failed', { action, code: failureCode(response.data) })
          return { success: false, message: this.error, code }
        }

        return { success: true, ...(onSuccess?.(response.data.data) ?? {}) }
      } catch (err) {
        Logger.error(`[ListsStore] ${action} error:`, err)
        const code = err.response?.status ?? err.response?.data?.http_code ?? null
        this.error = apiError(err, CLAVES)
        track('list_action_failed', { action, code: failureCode(err) })
        return { success: false, message: this.error, code }
      } finally {
        this.isSaving = false
      }
    },

  }
})
