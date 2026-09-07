/**
 * Journal Store — el diario: qué consumiste y cuándo.
 *
 * Una entrada por vez, no por ítem: la misma película vista tres veces son tres
 * filas. El backend las sirve ya ordenadas de lo más reciente a lo más antiguo y
 * con su `is_repeat` resuelto, así que aquí no se recalcula nada — solo se
 * agrupa por día para pintar los encabezados.
 *
 * **La paginación es por `offset` y acumula**, como el feed: `fetch()` reinicia
 * y `loadMore()` añade. El filtro por medio reinicia siempre, porque el `offset`
 * de una lista filtrada no significa lo mismo que el de la completa.
 *
 * Aquí viven **dos** diarios, en estados separados y sin mezclarse nunca: el
 * propio (`entries`, por `get_journal`) y el de otra persona para su perfil
 * público (`userEntries`, por `get_user_journal`). Es el mismo reparto que hace
 * `store/lists.js` con `lists` y `userLists`, y por la misma razón: compartir
 * `entries` haría que abrir un perfil ajeno pisara tu diario, y volver atrás lo
 * dejaría enseñando entradas que no son tuyas.
 */
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import Logger from '@/utils/logger'
import { apiError } from '@/composables/useApiError'
import { t } from '@/config/i18n'

const PAGINA = 30

// El perfil ajeno enseña un listado corto, no el diario entero: son diez y un
// botón para seguir. `get_user_journal` topa el límite en el servidor de todas
// formas (`GetUserJournalQuery::fromArray`), así que este número es de forma, no
// de seguridad.
const PAGINA_PERFIL = 10

/**
 * Las entradas agrupadas por día, conservando el orden que trajo el backend.
 *
 * Se agrupa por la CADENA `entry_date` (`YYYY-MM-DD`), nunca construyendo un
 * `Date`: `new Date('2026-09-04')` se interpreta como UTC y en cualquier zona al
 * oeste de Greenwich caería en el día anterior. La fecha solo se convierte a
 * `Date` para pintarla, y ahí se le añade la hora local a propósito.
 *
 * @param {object[]} entradas
 * @returns {{ date: string, entries: object[] }[]}
 */
const agruparPorDia = (entradas) => {
  const dias = []
  let actual = null

  for (const entrada of entradas) {
    if (actual === null || actual.date !== entrada.entry_date) {
      actual = { date: entrada.entry_date, entries: [] }
      dias.push(actual)
    }
    actual.entries.push(entrada)
  }

  return dias
}

export const useJournalStore = defineStore('journal', {
  state: () => ({
    entries: [],
    total: 0,
    hasMore: false,
    // `null` es «todos los medios». No se usa cadena vacía: el backend ignora
    // un medio que no conoce, y `''` viajaría como uno de esos.
    media: null,
    isLoading: false,
    isSaving: false,
    error: null,

    // El diario de OTRA persona, para su perfil público. Estado aparte del
    // propio, y su propio `isLoading`: las dos cargas conviven en la misma
    // pantalla sólo por accidente, pero un spinner compartido las ataría.
    userEntries: [],
    userTotal: 0,
    userHasMore: false,
    // De quién es lo que hay en `userEntries`, para que `loadMoreUserJournal()`
    // no necesite que la vista se lo recuerde ni pueda equivocarse de persona.
    userUsername: null,
    isLoadingUser: false
  }),

  getters: {
    hasEntries: (state) => state.entries.length > 0,

    /** @returns {{ date: string, entries: object[] }[]} */
    byDay: (state) => agruparPorDia(state.entries),

    /**
     * Que haya algo que enseñar del diario ajeno. Es lo único que decide si la
     * sección del perfil existe: el backend ya devuelve la lista vacía cuando no
     * hay amistad o el interruptor está apagado, así que aquí no se distingue
     * —ni se puede— «no lo enseña» de «no tiene nada». Ese es el punto.
     */
    hasUserEntries: (state) => state.userEntries.length > 0,

    /** @returns {{ date: string, entries: object[] }[]} */
    userByDay: (state) => agruparPorDia(state.userEntries)
  },

  actions: {
    /** Primera página, o recarga entera tras guardar o borrar. */
    async fetch () {
      this.isLoading = true
      this.error = null

      try {
        const datos = await this._pedir(0)

        if (datos) {
          this.entries = datos.entries
          this.total = datos.total
          this.hasMore = datos.hasMore
        }
      } finally {
        this.isLoading = false
      }
    },

    async loadMore () {
      if (this.isLoading || !this.hasMore) {
        return
      }

      this.isLoading = true

      try {
        const datos = await this._pedir(this.entries.length)

        if (datos) {
          this.entries = [...this.entries, ...datos.entries]
          this.total = datos.total
          this.hasMore = datos.hasMore
        }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * El diario de otra persona, para la sección de su perfil público.
     *
     * Reinicia siempre —incluido `userEntries`— antes de pedir: si no, al pasar
     * del perfil de A al de B se verían las entradas de A mientras llega la
     * respuesta de B. Y no propaga el error: la sección del perfil desaparece
     * sin más, igual que la de listas, porque un aviso de «no se pudo cargar su
     * diario» ya cuenta que hay un diario.
     *
     * @param {string} username
     */
    async fetchUserJournal (username) {
      this.isLoadingUser = true
      this.userUsername = username
      this.userEntries = []
      this.userTotal = 0
      this.userHasMore = false

      try {
        const datos = await this._pedirDeUsuario(username, 0)

        if (datos) {
          this.userEntries = datos.entries
          this.userTotal = datos.total
          this.userHasMore = datos.hasMore
        }
      } finally {
        this.isLoadingUser = false
      }
    },

    /** La página siguiente del diario ajeno, acumulando como `loadMore()`. */
    async loadMoreUserJournal () {
      if (this.isLoadingUser || !this.userHasMore || !this.userUsername) {
        return
      }

      this.isLoadingUser = true

      try {
        const datos = await this._pedirDeUsuario(this.userUsername, this.userEntries.length)

        if (datos) {
          this.userEntries = [...this.userEntries, ...datos.entries]
          this.userTotal = datos.total
          this.userHasMore = datos.hasMore
        }
      } finally {
        this.isLoadingUser = false
      }
    },

    /** Cambia el filtro por medio y recarga desde el principio. */
    async setMedia (media) {
      this.media = media || null
      this.entries = []
      await this.fetch()
    },

    /**
     * @param {{ media: string, entityId: string, entryDate: string, rating: ?number }} datos
     * @returns {Promise<boolean>} si se guardó
     */
    async add (datos) {
      return this._escribir('add_journal_entry', {
        media: datos.media,
        entityId: datos.entityId,
        entryDate: datos.entryDate,
        rating: datos.rating
      })
    },

    /**
     * El `rating` viaja **siempre**, también en `null`: quitarle la valoración a
     * una entrada es una edición legítima y el backend distingue «no lo toques»
     * (cadena vacía) de «quítalo» (`null`).
     */
    async update (entryId, datos) {
      return this._escribir('update_journal_entry', {
        entryId,
        entryDate: datos.entryDate,
        rating: datos.rating
      })
    },

    async remove (entryId) {
      return this._escribir('delete_journal_entry', { entryId })
    },

    async _escribir (accion, payload) {
      this.isSaving = true
      this.error = null

      try {
        const authStore = useAuthStore()
        const response = await authStore.authenticatedApiCall(accion, payload)

        if (response.data.status !== 'success') {
          this.error = apiError(response.data, { defecto: 'journalError.save' })
          return false
        }

        await this.fetch()
        return true
      } catch (err) {
        Logger.error(`[JournalStore] ${accion} error:`, err)
        this.error = t('journalError.save')
        return false
      } finally {
        this.isSaving = false
      }
    },

    async _pedir (offset) {
      try {
        const authStore = useAuthStore()
        const payload = { limit: PAGINA, offset }

        if (this.media) {
          payload.media = this.media
        }

        const response = await authStore.authenticatedApiCall('get_journal', payload)

        if (response.data.status !== 'success') {
          this.error = apiError(response.data, { defecto: 'journalError.load' })
          return null
        }

        return response.data.data
      } catch (err) {
        Logger.error('[JournalStore] get_journal error:', err)
        this.error = t('journalError.load')
        return null
      }
    },

    /**
     * Gemelo de `_pedir` contra `get_user_journal`. Sin `media`: el filtro por
     * medio es del diario propio, y en el perfil ajeno no hay dónde ponerlo.
     *
     * Un fallo devuelve `null` y **no toca `this.error`**, que es el del diario
     * propio: pintarlo ahí sacaría un aviso en `/journal` por algo que pasó en
     * el perfil de otra persona.
     */
    async _pedirDeUsuario (username, offset) {
      try {
        const authStore = useAuthStore()
        const response = await authStore.authenticatedApiCall('get_user_journal', {
          username,
          limit: PAGINA_PERFIL,
          offset
        })

        if (response.data.status !== 'success') {
          return null
        }

        return response.data.data
      } catch (err) {
        Logger.error('[JournalStore] get_user_journal error:', err)
        return null
      }
    }
  }
})
