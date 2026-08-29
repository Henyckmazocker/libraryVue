/**
 * Clubs Store using Pinia
 *
 * Un club es un grupo de amigos consumiendo la misma cosa a la vez. Es la
 * primera agrupación de PERSONAS del proyecto: `friendships` era una relación
 * de dos y no había concepto de grupo en ningún sitio.
 *
 * **Quién manda lo decide el servidor, no este store.** `get_club` devuelve
 * `is_owner` ya resuelto, y elegir ítem, cerrarlo e invitar son del dueño. La
 * interfaz lo pinta sin recalcular nada: repetir aquí «soy el dueño» sería una
 * segunda copia de una regla de permisos.
 *
 * **El progreso va en su propio estado y su propia acción**, separado de
 * `current`: es lo único que cambia mientras la pantalla está abierta, y
 * refrescarlo no debe arrastrar el club, los miembros y el historial enteros.
 */
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import Logger from '@/utils/logger'

export const useClubsStore = defineStore('clubs', {
  state: () => ({
    // Las tarjetas de /clubs: cada una con su `member_count` e `is_owner`.
    clubs: [],
    // El club abierto en /clubs/:id.
    current: null,
    currentMembers: [],
    // El ítem activo, o `null` entre un ítem y el siguiente.
    currentPick: null,
    currentHistory: [],
    // `axis` es 'page' | 'season' | null, y lo manda RESUELTO el servidor:
    // solo él sabe que un `entity_type: 'movie'` es una serie, mirando
    // `movie.media_type`. Aquí no se deduce del medio.
    progressAxis: null,
    progressMembers: [],
    // Las notas llegan YA MARCADAS del servidor: con `isSpoiler: true` su
    // `text` viene `null`. Este store no decide nada sobre spoilers, y no puede
    // — no tiene el texto.
    notes: [],
    notesAxis: null,
    isLoading: false,
    isLoadingProgress: false,
    isLoadingNotes: false,
    isSaving: false,
    error: null
  }),

  getters: {
    hasClubs: (state) => state.clubs.length > 0,
    isCurrentOwner: (state) => Boolean(state.current?.is_owner),
    hasActivePick: (state) => state.currentPick !== null,
    /**
     * Cuántos han acabado, para el resumen de la cabecera. Sale de lo que ya
     * está en memoria: no hay una acción del backend para esto porque el
     * cierre automático lo decide el servidor por su cuenta.
     */
    finishedCount: (state) => state.progressMembers.filter((m) => m.completed).length
  },

  actions: {
    async fetchMyClubs () {
      const authStore = useAuthStore()
      this.isLoading = true
      this.error = null

      try {
        const response = await authStore.authenticatedApiCall('get_my_clubs', {})

        if (response.data.status === 'success') {
          this.clubs = response.data.data?.clubs ?? []
        } else {
          this.error = response.data.message || 'No se pudieron cargar tus clubs'
        }
      } catch (err) {
        Logger.error('[ClubsStore] fetchMyClubs error:', err)
        this.error = 'No se pudieron cargar tus clubs'
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Abre un club. El 403 del club ajeno y el 404 del que no existe llegan por
     * código, no por texto: el backend responde en inglés, como todo el repo.
     *
     * Ojo con un efecto que no se ve aquí: **este `get_club` puede escribir**.
     * Si todos los miembros han completado el ítem, el servidor lo cierra en
     * esta misma llamada —no hay cron en el proyecto—, así que `pick` puede
     * volver `null` y `history` con una entrada más de la que había.
     */
    async fetchClub (clubId) {
      const authStore = useAuthStore()
      this.isLoading = true
      this.error = null
      this.current = null
      this.currentMembers = []
      this.currentPick = null
      this.currentHistory = []
      this.notes = []

      try {
        const response = await authStore.authenticatedApiCall('get_club', { clubId })

        if (response.data.status === 'success') {
          this.current = response.data.data?.club ?? null
          this.currentMembers = response.data.data?.members ?? []
          this.currentPick = response.data.data?.pick ?? null
          this.currentHistory = response.data.data?.history ?? []
          return { success: true }
        }

        this.error = this._messageFor(response.data.http_code)
        return { success: false, code: response.data.http_code ?? null }
      } catch (err) {
        Logger.error('[ClubsStore] fetchClub error:', err)
        const code = err.response?.status ?? err.response?.data?.http_code ?? null
        this.error = this._messageFor(code)
        return { success: false, code }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * El progreso de cada miembro. Va aparte de `fetchClub` porque es lo único
     * que cambia mientras la pantalla está abierta.
     *
     * **No toca `this.error`**: un fallo aquí no puede pintar la pantalla del
     * club como rota cuando el club se ha cargado bien. Se queda sin progreso y
     * ya, que es lo mismo que hace el backend cuando el cierre automático
     * falla.
     */
    async fetchProgress (clubId) {
      const authStore = useAuthStore()
      this.isLoadingProgress = true

      try {
        const response = await authStore.authenticatedApiCall('get_club_progress', { clubId })

        if (response.data.status === 'success') {
          const axis = response.data.data?.axis ?? null
          const members = response.data.data?.members ?? []
          this.progressAxis = axis
          this.progressMembers = members

          // Se devuelven ADEMÁS de guardarlos: quien pregunta por varios clubs
          // a la vez (`AddToClubDialog`) no puede leer `progressMembers`, que
          // es estado compartido y lo pisa la última llamada que termine.
          return { success: true, axis, members }
        }

        return { success: false, code: response.data.http_code ?? null }
      } catch (err) {
        Logger.error('[ClubsStore] fetchProgress error:', err)
        this.progressMembers = []
        return { success: false, code: err.response?.status ?? null }
      } finally {
        this.isLoadingProgress = false
      }
    },

    /**
     * Las notas del club sobre el ítem activo.
     *
     * Como `fetchProgress`, **no toca `this.error`**: un fallo aquí no puede
     * pintar como rota una pantalla cuyo club se cargó bien.
     */
    async fetchNotes (clubId) {
      const authStore = useAuthStore()
      this.isLoadingNotes = true

      try {
        const response = await authStore.authenticatedApiCall('get_club_notes', { clubId })

        if (response.data.status === 'success') {
          this.notesAxis = response.data.data?.axis ?? null
          this.notes = response.data.data?.notes ?? []
          return { success: true }
        }

        return { success: false, code: response.data.http_code ?? null }
      } catch (err) {
        Logger.error('[ClubsStore] fetchNotes error:', err)
        this.notes = []
        return { success: false, code: err.response?.status ?? null }
      } finally {
        this.isLoadingNotes = false
      }
    },

    async createClub ({ name, description }) {
      return this._write('create_club', { name, description }, (data) => {
        // No se inserta a mano en `clubs`: la fila del servidor trae
        // `created_at`, `member_count` e `is_owner`, y componerla aquí sería
        // inventarse tres campos que la tarjeta pinta.
        this.fetchMyClubs()
        return { clubId: data?.clubId }
      })
    },

    /**
     * Invitar es del DUEÑO, no de cualquier miembro: decide ante quién se
     * expone el progreso de todos los demás. El 400 es «no sois amigos» o «ya
     * le invitaste», y ninguno es un fallo de permiso.
     */
    async inviteToClub (clubId, userId) {
      const result = await this._write('invite_to_club', { clubId, userId })

      if (!result.success && result.code === 400) {
        this.error = 'Solo puedes invitar a tus amigos, y solo una vez'
        return { ...result, message: this.error }
      }

      return result
    },

    async leaveClub (clubId) {
      return this._write('leave_club', { clubId }, () => {
        this.clubs = this.clubs.filter((c) => c.id !== clubId)
        return {}
      })
    },

    async setPick (clubId, { entityType, entityId, entityTitle, entityCover }) {
      return this._write(
        'set_club_pick',
        { clubId, entityType, entityId, entityTitle, entityCover },
        () => {
          // Se relee en vez de componer el pick a mano: la fila del servidor
          // trae `started_at`, y el progreso arranca de cero para todos.
          this.fetchClub(clubId)
          this.fetchProgress(clubId)
          // Las notas del ítem anterior no valen para el nuevo.
          this.fetchNotes(clubId)
          return {}
        }
      )
    },

    /**
     * Cerrar el ítem. **No es la excepción, es la vía habitual**: el cierre
     * automático exige que TODOS lo hayan completado, y basta un miembro que no
     * lo tenga en su biblioteca para que no llegue nunca.
     */
    async finishPick (clubId) {
      return this._write('finish_club_pick', { clubId }, () => {
        this.fetchClub(clubId)
        this.fetchProgress(clubId)
        this.fetchNotes(clubId)
        return {}
      })
    },

    /**
     * El paso común de las escrituras: llamar, distinguir el fallo del cliente
     * del sobre de error del backend, y devolver siempre `{ success }` con el
     * código HTTP intacto. Quien lo pinta traduce por **código**, no por texto:
     * el 409 del segundo ítem activo es del dominio y no puede perderse.
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
        Logger.error(`[ClubsStore] ${action} error:`, err)
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
        403: 'No tienes permiso sobre este club',
        404: 'Este club ya no existe',
        409: 'Este club ya tiene un ítem activo; termínalo antes de elegir otro'
      }

      return messages[code] ?? fallback ?? 'No se pudo completar la operación'
    }
  }
})
