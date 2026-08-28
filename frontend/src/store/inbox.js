/**
 * Inbox Store using Pinia
 *
 * La bandeja de recomendaciones y el contador de la campanita.
 *
 * **No hay polling y es a propósito**: el contador se pide al montar y en cada
 * navegación del router (`main.js` engancha el `afterEach`), así que en reposo
 * la pestaña no hace ni una petición. El coste aceptado es que con la pestaña
 * abierta y quieta no te enteras hasta que navegues o recargues; el backend es
 * endpoint único sobre `mod_php`, un proceso por petición, y detrás hay un
 * Cloudflare Tunnel: un SSE aquí sería un plan propio.
 *
 * La suscripción vive aquí y no en `Header.vue` para que no se enganche dos
 * veces si el header se vuelve a montar.
 */
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import { mediaStores } from '@/composables/createMediaComposable'
import { getMediaConfig, storeMediaKeys } from '@/config/mediaRegistry'
import Logger from '@/utils/logger'

export const useInboxStore = defineStore('inbox', {
  state: () => ({
    // Lo que llega de `get_inbox`, ya tipado: cada elemento lleva su `kind`
    // para que la vista despache por componente. Hoy solo hay un tipo; las
    // invitaciones a listas y a clubs entrarán como `kind` nuevos sin rehacer
    // la pantalla.
    items: [],
    total: 0,
    pendingCount: 0,
    isLoading: false,
    // Qué recomendación se está resolviendo, por id: los dos botones de esa
    // tarjeta se desactivan sin congelar la lista entera.
    resolvingId: null,
    error: null,
    // La suscripción al router se engancha una sola vez (`subscribeToRouter`).
    _subscribed: false
  }),

  getters: {
    hasItems: (state) => state.items.length > 0,
    hasPending: (state) => state.pendingCount > 0
  },

  actions: {
    /**
     * El contador. Es lo más llamado de la app, así que no toca nada más del
     * estado y se traga sus errores: un fallo de red aquí no puede romper una
     * navegación.
     */
    async refreshCount () {
      const authStore = useAuthStore()

      if (!authStore.isAuthenticated) {
        this.pendingCount = 0
        return
      }

      try {
        const response = await authStore.authenticatedApiCall('get_inbox_count', {})
        if (response.data.status === 'success') {
          this.pendingCount = response.data.data?.pending ?? 0
        }
      } catch (err) {
        Logger.error('[InboxStore] refreshCount error:', err)
      }
    },

    /**
     * Engancha el contador a la navegación: al montar la app y en cada cambio de
     * ruta. Vive aquí y no en `Header.vue` para que no se suscriba dos veces si
     * el header se vuelve a montar, y la guarda lo hace idempotente.
     */
    subscribeToRouter (router) {
      if (this._subscribed) return
      this._subscribed = true

      router.afterEach(() => this.refreshCount())
    },

    async fetchInbox (status = 'pending') {
      const authStore = useAuthStore()
      this.isLoading = true
      this.error = null

      try {
        const response = await authStore.authenticatedApiCall('get_inbox', { status })

        if (response.data.status === 'success') {
          const data = response.data.data ?? {}
          // El `kind` sale del `entity_type`: la invitación a colaborar viaja
          // por el MISMO buzón, con `entity_type = 'list'`. Añadir un tipo es
          // una línea aquí y un componente en `InboxView`.
          this.items = (data.recommendations ?? []).map((r) => ({
            ...r,
            kind: r.entity_type === 'list' ? 'list_invitation' : 'recommendation'
          }))
          this.total = data.total ?? this.items.length
          // El contador sale de la misma respuesta cuando se piden las
          // pendientes: una petición menos y no puede quedar desfasado.
          if (status === 'pending') {
            this.pendingCount = this.total
          }
        } else {
          this.error = response.data.message || 'No se pudo cargar la bandeja'
        }
      } catch (err) {
        Logger.error('[InboxStore] fetchInbox error:', err)
        this.error = 'No se pudo cargar la bandeja'
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Manda un ítem a un amigo. La contraparte de la bandeja.
     *
     * `entityType` tiene que ser el medio con el que el backend guarda el ítem,
     * **no el del registry**: una serie se guarda con `AddMovieUseCase`, así que
     * viaja como `movie` —lo mismo que ya hacen `cover_file` y `feed_events`—.
     * Quien llama lo resuelve con el `coverMedia` de `MediaDetailView`.
     */
    async sendRecommendation ({ recipientId, entityType, entityId, entityTitle, entityCover, comment }) {
      const authStore = useAuthStore()
      this.error = null

      try {
        const response = await authStore.authenticatedApiCall('send_recommendation', {
          recipientId,
          entityType,
          entityId,
          entityTitle,
          entityCover,
          comment
        })

        if (response.data.status !== 'success') {
          // Si el cliente no lanzó, el código está en el sobre del backend.
          return {
            success: false,
            message: response.data.message || 'No se pudo enviar la recomendación',
            code: response.data.http_code ?? null
          }
        }

        return { success: true, recommendationId: response.data.data?.recommendationId }
      } catch (err) {
        Logger.error('[InboxStore] sendRecommendation error:', err)
        // El código viaja junto al mensaje porque quien lo pinta traduce por
        // código y no por texto: el backend responde en inglés, como todo el
        // repo, y acoplar la interfaz a sus cadenas se rompería al reescribir
        // una. El HTTP real ES el del dominio —un duplicado responde 409—, así
        // que basta con no perderlo por el camino.
        const message = err.response?.data?.message || err.message || 'No se pudo enviar la recomendación'
        const code = err.response?.status ?? err.response?.data?.http_code ?? null
        this.error = message
        return { success: false, message, code }
      }
    },

    /**
     * Descarta una recomendación: se marca resuelta y sale de la lista.
     */
    async dismiss (recommendation) {
      return this._resolve(recommendation, 'dismissed')
    },

    /**
     * La da de alta en la biblioteca y solo entonces la marca resuelta.
     *
     * El ítem **no viaja en la recomendación**: la fila guarda `entity_type` +
     * `entity_id` y poco más, mientras que las cinco acciones de alta reciben la
     * ficha entera. Se rehidrata con el `detail.enrich` del registry, que es
     * exactamente lo que la ficha de detalle ya usa para pintar un ítem que no
     * tienes. Si la rehidratación falla, **no se marca como añadida**: quedaría
     * fuera de la bandeja y fuera de la biblioteca a la vez.
     */
    async addToLibrary (recommendation) {
      const authStore = useAuthStore()
      const media = recommendation.entity_type

      if (!storeMediaKeys.includes(media) || !mediaStores[media]) {
        this.error = 'Este medio no se puede añadir desde la bandeja'
        return { success: false, message: this.error }
      }

      this.resolvingId = recommendation.id
      this.error = null

      try {
        const config = getMediaConfig(media)
        const enrich = config.detail?.enrich

        if (!enrich) {
          throw new Error(`El medio ${media} no sabe rehidratar su ficha`)
        }

        const result = await enrich(recommendation.entity_id, authStore.apiCall.bind(authStore))
        if (!result?.item) {
          throw new Error('No se pudo recuperar la ficha del ítem')
        }

        const store = mediaStores[media]()
        const defaultStatus = config.libraryItem?.defaultStatus
        const added = await store.add(result.item, defaultStatus ? [defaultStatus] : [])

        if (!added?.success) {
          throw new Error(added?.message || 'No se pudo añadir a la biblioteca')
        }

        return this._resolve(recommendation, 'added')
      } catch (err) {
        Logger.error('[InboxStore] addToLibrary error:', err)
        this.error = err.message || 'No se pudo añadir a la biblioteca'
        return { success: false, message: this.error }
      } finally {
        this.resolvingId = null
      }
    },

    /**
     * Acepta una invitación a colaborar: da de alta al colaborador y resuelve
     * la fila, las dos cosas en el backend.
     *
     * No pasa por `_resolve` aunque acabe igual —la fila sale del buzón—:
     * `resolve_recommendation` solo marca el estado, y aquí hace falta además
     * el alta en `media_list_collaborator`.
     */
    async acceptCollaboration (invitation) {
      const authStore = useAuthStore()
      this.resolvingId = invitation.id
      this.error = null

      try {
        const response = await authStore.authenticatedApiCall('accept_collaboration', {
          recommendationId: invitation.id
        })

        if (response.data.status !== 'success') {
          throw new Error(response.data.message || 'No se pudo aceptar la invitación')
        }

        this._forget(invitation.id)

        return { success: true, listId: response.data.data?.listId }
      } catch (err) {
        Logger.error('[InboxStore] acceptCollaboration error:', err)
        this.error = err.response?.data?.message || err.message || 'No se pudo aceptar la invitación'
        return { success: false, message: this.error }
      } finally {
        this.resolvingId = null
      }
    },

    /** Saca una fila del buzón y ajusta los dos contadores. */
    _forget (id) {
      this.items = this.items.filter((item) => item.id !== id)
      this.total = Math.max(0, this.total - 1)
      this.pendingCount = Math.max(0, this.pendingCount - 1)
    },

    /** El paso común de los dos botones: marcar resuelta y sacarla de la lista. */
    async _resolve (recommendation, resolution) {
      const authStore = useAuthStore()
      this.resolvingId = recommendation.id

      try {
        // `resolution`, NO `action`: el payload viaja plano junto a la clave
        // `action` del protocolo y la pisaría.
        const response = await authStore.authenticatedApiCall('resolve_recommendation', {
          recommendationId: recommendation.id,
          resolution
        })

        if (response.data.status !== 'success') {
          throw new Error(response.data.message || 'No se pudo resolver la recomendación')
        }

        this._forget(recommendation.id)

        return { success: true }
      } catch (err) {
        Logger.error('[InboxStore] resolve error:', err)
        this.error = err.message || 'No se pudo resolver la recomendación'
        return { success: false, message: this.error }
      } finally {
        this.resolvingId = null
      }
    }
  }
})
