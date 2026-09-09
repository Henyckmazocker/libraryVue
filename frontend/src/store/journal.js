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

// La página del mes de la rejilla. **El servidor topa el `limit` en 100**
// (`GetJournalQuery.php:32-39`), así que pedir 200 no falla: se topa. Con
// `from`/`to` puestos, `total` y `hasMore` cuentan ya solo el rango, y por eso un
// mes de más de cien entradas se recorre con `offset` en vez de con un límite
// mayor.
const PAGINA_MES = 100

// Tope de seguridad del bucle de páginas del mes. No es una regla de negocio: es
// lo que impide que un `hasMore` que no baje nunca deje el navegador pidiendo
// páginas para siempre. Trescientas entradas en un mes son diez al día.
const TOPE_MES = 300

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

/**
 * El `days` del agregado, saneado.
 *
 * **Un año sin entradas llega como `[]`, no como `{}`**: en PHP es un array vacío
 * y `json_encode` lo serializa como lista. Recorrerlo por claves es inocuo —
 * `Object.entries([])` da `[]`— pero el resto del store trabaja con un objeto, y
 * dejar que a veces sea una lista es la clase de detalle que se descubre tarde.
 *
 * Se filtran además los días con `count` no positivo: el `GROUP BY` no puede
 * producirlos, pero quien recorre esto pinta un escalón por cada clave y un cero
 * pintaría una celda «llena» vacía.
 *
 * @param {object|Array|null} dias
 * @returns {Object<string, {count: number, media: string[]}>}
 */
const normalizarDias = (dias) => {
  if (!dias || typeof dias !== 'object') {
    return {}
  }

  const salida = {}

  for (const [fecha, dato] of Object.entries(dias)) {
    const cuenta = Number(dato?.count ?? 0)

    if (Number.isFinite(cuenta) && cuenta > 0) {
      salida[fecha] = {
        count: cuenta,
        media: Array.isArray(dato?.media) ? dato.media : []
      }
    }
  }

  return salida
}

export const useJournalStore = defineStore('journal', {
  state: () => ({
    entries: [],
    total: 0,
    hasMore: false,
    // `null` es «todos los medios». No se usa cadena vacía: el backend ignora
    // un medio que no conoce, y `''` viajaría como uno de esos.
    media: null,
    // El día por el que abrir el listado, como cadena `YYYY-MM-DD`, o `null`
    // para el diario entero desde hoy. Es el `?date=` del conmutador (M4):
    // pulsar un día de la rejilla del mes lleva al listado a ese día, y el
    // listado se pagina de 30 en 30 hacia atrás, así que un día viejo puede no
    // haber entrado en ninguna página. Viaja como `to` —el listado va de lo
    // más reciente a lo más antiguo—, así que el día pedido queda ARRIBA y
    // debajo sigue estando todo lo anterior.
    anchorDate: null,
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
    isLoadingUser: false,

    // El agregado por día del calendario. Vive aparte de `entries` por lo mismo
    // que `userEntries`: son dos preguntas distintas al backend —cuántas cosas
    // hubo cada día frente a cuáles fueron— y mezclarlas ataría el heatmap a la
    // paginación del listado.
    calendarDays: {},
    calendarTotal: 0,
    // Los años con al menos una entrada, para el navegador del heatmap. Sale de
    // la misma respuesta pero de otra consulta: `days` solo mira un año.
    calendarYears: [],
    // Qué año hay cargado en `calendarDays`, para no volver a pedirlo.
    calendarYear: null,
    isLoadingCalendar: false,

    // El mes de la rejilla: las ENTRADAS de un rango, no el agregado por día.
    // Vive aparte de `entries` por lo mismo que `calendarDays`: la rejilla
    // pregunta **cuáles** cosas hubo cada día de un mes concreto y el listado
    // pregunta **las últimas**, así que compartir `entries` ataría la rejilla a
    // la paginación del listado y a su filtro por medio.
    monthEntries: [],
    // Qué mes hay cargado en `monthEntries`, como cadena `YYYY-MM`, para no
    // volver a pedirlo. Cadena y no dos números por lo mismo que las fechas: es
    // lo que se compara, y comparar dos campos es una ocasión de equivocarse.
    monthKey: null,
    isLoadingMonth: false
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
    userByDay: (state) => agruparPorDia(state.userEntries),

    /** Las cuentas del año cargado, sin fechas: es lo que reparte la escala. */
    calendarCounts: (state) => Object.values(state.calendarDays).map((d) => d.count),

    /**
     * Las entradas del mes cargado, indexadas por su `entry_date`.
     *
     * La clave es la CADENA `YYYY-MM-DD` que trae el backend, sin pasar por
     * `Date`: `new Date('2026-09-01')` se lee como UTC y al oeste de Greenwich
     * caería en el día anterior, o sea en otra celda de la rejilla.
     *
     * @returns {Object<string, object[]>}
     */
    monthByDate: (state) => {
      const salida = {}

      for (const entrada of state.monthEntries) {
        if (!salida[entrada.entry_date]) {
          salida[entrada.entry_date] = []
        }

        salida[entrada.entry_date].push(entrada)
      }

      return salida
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

    /**
     * El agregado por día de un año, para el heatmap.
     *
     * `get_journal_calendar` y no `get_journal` con rango: el año necesita saber
     * **cuántas** cosas hubo cada día, no cuáles, y traerse las entradas enteras
     * para contarlas en el navegador es justo lo que la acción evita.
     *
     * Reinicia `calendarDays` **antes** de pedir: al cambiar de año, dejar el
     * anterior mientras llega la respuesta pinta el mapa de 2025 bajo el rótulo
     * de 2024. `calendarYears` **no** se reinicia — es la misma lista para todos
     * los años y borrarla haría parpadear el navegador en cada salto.
     *
     * @param {number} year
     * @returns {Promise<boolean>} si se cargó
     */
    async fetchCalendar (year) {
      this.isLoadingCalendar = true
      this.calendarDays = {}
      this.calendarTotal = 0
      this.calendarYear = year
      this.error = null

      try {
        const authStore = useAuthStore()
        const payload = { year }

        // El filtro por medio del listado manda también aquí (M4). `years` NO
        // se filtra en el servidor a propósito: filtrarlo haría desaparecer
        // años enteros del selector.
        if (this.media) {
          payload.media = this.media
        }

        const response = await authStore.authenticatedApiCall('get_journal_calendar', payload)

        if (response.data.status !== 'success') {
          this.error = apiError(response.data, { defecto: 'journalError.load' })
          return false
        }

        const datos = response.data.data ?? {}

        this.calendarDays = normalizarDias(datos.days)
        this.calendarTotal = Number(datos.total) || 0
        this.calendarYears = Array.isArray(datos.years)
          ? datos.years.map(Number).filter(Number.isFinite)
          : []

        return true
      } catch (err) {
        Logger.error('[JournalStore] get_journal_calendar error:', err)
        this.error = t('journalError.load')
        return false
      } finally {
        this.isLoadingCalendar = false
      }
    },

    /**
     * Las entradas de un mes, para la rejilla del mes.
     *
     * `get_journal` con `from`/`to` y no `get_journal_calendar`: el mes necesita
     * saber **cuáles** cosas hubo cada día —el medio, la portada, el id— y el
     * agregado del año solo sabe cuántas.
     *
     * **Aplica el filtro por medio del listado**, igual que el heatmap del año:
     * las píldoras viven justo encima de la rejilla y prometen que la filtran
     * (M4 del plan). El M3 había resuelto lo contrario, y cambiarlo es una
     * enmienda deliberada, no un descuido.
     *
     * Reinicia `monthEntries` **antes** de pedir, como `fetchCalendar`: dejar el
     * mes anterior mientras llega la respuesta pinta agosto bajo el rótulo de
     * septiembre.
     *
     * @param {number} year
     * @param {number} month 1-12
     * @returns {Promise<boolean>} si se cargó
     */
    async fetchMonth (year, month) {
      const mes = String(month).padStart(2, '0')
      const from = `${year}-${mes}-01`
      // Día 0 del mes siguiente es el último del actual: cubre febrero bisiesto
      // sin escribir a mano la regla de los años bisiestos. El `Date` se
      // construye con componentes numéricos, que sí son hora local.
      const ultimo = String(new Date(year, month, 0).getDate()).padStart(2, '0')
      const to = `${year}-${mes}-${ultimo}`

      this.isLoadingMonth = true
      this.monthEntries = []
      this.monthKey = `${year}-${mes}`
      this.error = null

      try {
        const acumulado = []
        let quedan = true

        // El bucle existe porque el `limit` se topa en 100 en el servidor: un mes
        // de más de cien entradas se pagina con `offset`, no se pide entero.
        while (quedan && acumulado.length < TOPE_MES) {
          const datos = await this._pedirRango(from, to, acumulado.length)

          if (!datos) {
            return false
          }

          acumulado.push(...datos.entries)
          // `datos.entries.length` en la condición además de `hasMore`: una
          // página vacía con `hasMore` a `true` sería un bucle infinito, y el
          // tope de arriba solo lo cortaría tras tres vueltas.
          quedan = Boolean(datos.hasMore) && datos.entries.length > 0
        }

        this.monthEntries = acumulado
        return true
      } finally {
        this.isLoadingMonth = false
      }
    },

    /**
     * Cambia el filtro por medio y recarga desde el principio.
     *
     * **El filtro manda también sobre el calendario** (M4 del plan): las
     * píldoras viven justo encima del heatmap y de la rejilla, así que lo que
     * hubiera cargado de ellos queda obsoleto. Se invalidan las dos claves de
     * caché —y no se pide nada aquí—: la vista de calendario que esté abierta
     * lo recarga con su propio `watch` sobre `media`, y la que no lo esté lo
     * pedirá al montarse. Pedir los tres desde aquí serían dos peticiones que
     * nadie está mirando.
     */
    async setMedia (media) {
      this.media = media || null
      this.entries = []
      this.calendarYear = null
      this.monthKey = null
      await this.fetch()
    },

    /**
     * El día por el que abre el listado, o `null` para el diario entero.
     *
     * Recarga siempre, también con el mismo valor: quien lo llama es el
     * conmutador al entrar en el listado, y ahí «vuelve a ponerlo como estaba»
     * es una petición legítima.
     *
     * @param {?string} date `YYYY-MM-DD`
     */
    async setAnchor (date) {
      this.anchorDate = date || null
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

        // El ancla es un `to` y no un `from`: el listado está ordenado de lo más
        // reciente a lo más antiguo, así que «abrir en el 27 de agosto» es
        // cortar por arriba, no por abajo.
        if (this.anchorDate) {
          payload.to = this.anchorDate
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
     * Gemelo de `_pedir` con rango de fechas, para la rejilla del mes.
     *
     * Va aparte de `_pedir` y no como un parámetro suyo porque lo que cambia no
     * es solo el rango: aquí el límite es otro y **no viaja el ancla** del
     * listado, que es un `to` y pisaría el del mes.
     */
    async _pedirRango (from, to, offset) {
      try {
        const authStore = useAuthStore()
        const payload = { from, to, limit: PAGINA_MES, offset }

        // El filtro por medio manda también sobre la rejilla (M4).
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
        Logger.error('[JournalStore] get_journal (rango) error:', err)
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
