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
 */
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import Logger from '@/utils/logger'
import { apiError } from '@/composables/useApiError'
import { t } from '@/config/i18n'

const PAGINA = 30

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
    error: null
  }),

  getters: {
    hasEntries: (state) => state.entries.length > 0,

    /**
     * Las entradas agrupadas por día, conservando el orden que trajo el backend.
     *
     * Se agrupa por la CADENA `entry_date` (`YYYY-MM-DD`), nunca construyendo un
     * `Date`: `new Date('2026-09-04')` se interpreta como UTC y en cualquier zona
     * al oeste de Greenwich caería en el día anterior. La fecha solo se convierte
     * a `Date` para pintarla, y ahí se le añade la hora local a propósito.
     *
     * @returns {{ date: string, entries: object[] }[]}
     */
    byDay: (state) => {
      const dias = []
      let actual = null

      for (const entrada of state.entries) {
        if (actual === null || actual.date !== entrada.entry_date) {
          actual = { date: entrada.entry_date, entries: [] }
          dias.push(actual)
        }
        actual.entries.push(entrada)
      }

      return dias
    }
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
    }
  }
})
