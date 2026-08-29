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
    // La ronda de votación, o `null` cuando hay ítem activo: son estados
    // EXCLUYENTES y el servidor manda uno u otro, nunca los dos. Dentro vienen
    // `canPropose` y `reasonBlocked` ya resueltos —la rotación es una regla de
    // dominio y no se recalcula aquí—, el recuento de cada propuesta y el voto
    // propio. Los votos AJENOS no vienen, y no es un olvido: quién votó a quién
    // no es asunto de nadie.
    currentRound: null,
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
    isVoting: (state) => state.currentRound?.phase === 'voting',
    isProposing: (state) => state.currentRound?.phase === 'proposing',
    /** Cuántos votos hay en el recuento en curso, para el resumen de la fase. */
    castVotes: (state) => (state.currentRound?.proposals ?? [])
      .reduce((total, propuesta) => total + (propuesta.votes ?? 0), 0),
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
     *
     * Y desde la votación escribe más cosas: **abre la ronda, abre el voto
     * cuando han propuesto todos, y la cierra creando el ítem ganador**. Por eso
     * la respuesta puede traer un `pick` que no existía antes de esta llamada, y
     * por eso hay que releer el club después de proponer o votar: el estado
     * siguiente lo decide el servidor al leer, no el cliente.
     */
    async fetchClub (clubId) {
      const authStore = useAuthStore()
      this.isLoading = true
      this.error = null
      this.current = null
      this.currentMembers = []
      this.currentPick = null
      this.currentRound = null
      this.currentHistory = []
      this.notes = []

      try {
        const response = await authStore.authenticatedApiCall('get_club', { clubId })

        if (response.data.status === 'success') {
          this.current = response.data.data?.club ?? null
          this.currentMembers = response.data.data?.members ?? []
          this.currentPick = response.data.data?.pick ?? null
          this.currentRound = response.data.data?.round ?? null
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
     * `get_club` **sin tocar el estado compartido**: devuelve el ítem activo y
     * la ronda, y nada más.
     *
     * Existe para `AddToClubDialog`, que pregunta por VARIOS clubs a la vez
     * para saber qué ofrece en cada uno. Si escribiera en `current`, el estado
     * lo dejaría la última llamada que terminase y las demás respuestas se
     * perderían — es el mismo motivo por el que `fetchProgress` devuelve sus
     * datos además de guardarlos.
     *
     * Y hereda el efecto de `get_club`: **escribe en el servidor**. Preguntar
     * por un club puede abrirle la ronda o cerrarla, igual que mirarlo.
     */
    async fetchClubSnapshot (clubId) {
      const authStore = useAuthStore()

      try {
        const response = await authStore.authenticatedApiCall('get_club', { clubId })

        if (response.data.status !== 'success') {
          return { success: false, code: response.data.http_code ?? null }
        }

        return {
          success: true,
          pick: response.data.data?.pick ?? null,
          round: response.data.data?.round ?? null
        }
      } catch (err) {
        Logger.error('[ClubsStore] fetchClubSnapshot error:', err)
        return { success: false, code: err.response?.status ?? null }
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
     * Proponer un ítem para la ronda en curso. Es de cualquier MIEMBRO, no del
     * dueño: es el sentido de la votación entera.
     *
     * El 403 son dos cosas —no eres miembro, o te toca rotar— y el 400 es «ya
     * propusiste». Se traducen por código, como el resto.
     */
    async proposeItem (clubId, { entityType, entityId, entityTitle, entityCover }) {
      return this._write(
        'propose_club_item',
        { clubId, entityType, entityId, entityTitle, entityCover },
        () => {
          // Se relee: proponer el último que faltaba ABRE el voto, y eso lo
          // decide el servidor en la lectura siguiente, no esta respuesta.
          this.fetchClub(clubId)
          return {}
        }
      )
    },

    /**
     * Votar, o cambiar el voto: es la misma acción otra vez mientras la ronda
     * siga abierta.
     */
    async voteProposal (clubId, proposalId) {
      return this._write('vote_club_proposal', { clubId, proposalId }, () => {
        // Votar el último que faltaba CIERRA la ronda y crea el ítem, y también
        // eso lo resuelve el servidor al leer.
        this.fetchClub(clubId)
        this.fetchProgress(clubId)
        return {}
      })
    },

    /**
     * Las dos válvulas del dueño. No son un atajo: sin cron, si alguien no
     * propone o no vota nunca, la fase no avanzaría jamás por sí sola.
     *
     * **Forzar el cierre no salta el desempate**: si los votos empatan y es el
     * primer recuento, la ronda pasa a `ballot = 2` y sigue votándose. Por eso
     * después se relee en vez de dar por hecho que hay ítem.
     */
    async openVote (clubId) {
      return this._write('open_club_vote', { clubId }, () => {
        this.fetchClub(clubId)
        return {}
      })
    },

    async closeVote (clubId) {
      return this._write('close_club_vote', { clubId }, (data) => {
        this.fetchClub(clubId)
        this.fetchProgress(clubId)
        return { pickId: data?.pickId ?? null, phase: data?.phase ?? null }
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
        400: 'No se pudo completar: revisa la pantalla, puede estar desfasada',
        403: 'No tienes permiso sobre este club',
        404: 'Este club ya no existe',
        // El 409 lo devuelven dos cosas: «ya hay un ítem activo» y «la ronda no
        // está en esa fase». Las dos se arreglan igual —recargar—, así que
        // comparten texto en vez de leer el mensaje del backend, que va en
        // inglés.
        409: 'La ronda ha cambiado de fase; recarga la pantalla'
      }

      return messages[code] ?? fallback ?? 'No se pudo completar la operación'
    }
  }
})
