import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { mountComponent } from './helpers/mount'
import { useJournalStore } from '@/store/journal'
import { useAuthStore } from '@/store/auth'
import JournalCalendarYear from '@/components/Journal/JournalCalendarYear.vue'

/**
 * El conmutador y las dos enmiendas — M4 del Plan - Calendario del Diario.
 *
 * Lo que se fija aquí es lo que ninguna otra barrera ve: que los tres estados
 * viven en la URL (y por tanto que «atrás» los deshace, porque cada paso es un
 * `push`), que el mes se recuerda al ir y volver del año, que el filtro por
 * medio viaja a las DOS consultas del calendario y **no** al selector de años, y
 * que el heatmap abre por el mes que toca en vez de por enero.
 */

const HOY = new Date()

const respuesta = (data, status = 'success') => ({
  data: { status, data, message: 'backend message in english', http_code: 200 }
})

const dosDigitos = (n) => String(n).padStart(2, '0')

// ═══════════════════════════════════════════════════════════════════════════
// JournalView — los tres estados en la URL
// ═══════════════════════════════════════════════════════════════════════════

// La ruta se muta entre tests, así que el mock devuelve SIEMPRE el mismo objeto
// y lo que cambia es su contenido: `useRoute()` en la vista se resuelve una vez.
const ruta = { query: {} }
const push = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => ruta,
  useRouter: () => ({ push, back: vi.fn(), go: vi.fn() })
}))

// Se importa DESPUÉS del mock: la vista llama a `useRoute` en su `setup`.
const { default: JournalView } = await import('@/views/JournalView.vue')

const montarVista = async (query = {}) => {
  ruta.query = query
  push.mockClear()

  const apiCall = vi.fn().mockResolvedValue(respuesta({ entries: [], total: 0, hasMore: false }))
  useAuthStore().authenticatedApiCall = apiCall

  const wrapper = mountComponent(JournalView, {
    global: {
      stubs: {
        JournalCalendarYear: true,
        JournalCalendarMonth: true,
        JournalEntryModal: true
      }
    }
  })

  await new Promise((resolve) => setTimeout(resolve, 0))
  await wrapper.vm.$nextTick()

  return { wrapper, apiCall }
}

/** El botón del conmutador que lleva ese rótulo. */
const botonDeVista = (wrapper, rotulo) =>
  wrapper.findAll('.journal-view__view').find((b) => b.text().includes(rotulo))

describe('JournalView — el conmutador vive en la URL', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('sin parámetros abre el listado, que sigue siendo la vista por defecto', async () => {
    const { wrapper } = await montarVista()

    expect(wrapper.findComponent({ name: 'JournalCalendarYear' }).exists()).toBe(false)
    expect(wrapper.findComponent({ name: 'JournalCalendarMonth' }).exists()).toBe(false)
    expect(botonDeVista(wrapper, 'Listado').attributes('aria-pressed')).toBe('true')
  })

  it('`?view=year` abre el año: recargar no lo pierde', async () => {
    const { wrapper } = await montarVista({ view: 'year', date: '2025' })
    const anio = wrapper.findComponent({ name: 'JournalCalendarYear' })

    expect(anio.exists()).toBe(true)
    expect(anio.props('year')).toBe(2025)
    expect(botonDeVista(wrapper, 'Año').attributes('aria-pressed')).toBe('true')
  })

  it('`?view=month&date=2026-09` abre ese mes', async () => {
    const { wrapper } = await montarVista({ view: 'month', date: '2026-09' })
    const mes = wrapper.findComponent({ name: 'JournalCalendarMonth' })

    expect(mes.props('year')).toBe(2026)
    expect(mes.props('month')).toBe(9)
  })

  it('un `date` imposible no pinta un mes 0: cae al que corre', async () => {
    const { wrapper } = await montarVista({ view: 'month', date: '2026-13' })
    const mes = wrapper.findComponent({ name: 'JournalCalendarMonth' })

    expect(mes.props('month')).toBe(HOY.getMonth() + 1)
  })

  it('pulsar un mes del año abre ese mes, con un `push` que «atrás» puede deshacer', async () => {
    const { wrapper } = await montarVista({ view: 'year', date: '2025' })

    wrapper.findComponent({ name: 'JournalCalendarYear' }).vm.$emit('select-month', '2025-03')

    expect(push).toHaveBeenCalledWith({ name: 'Journal', query: { view: 'month', date: '2025-03' } })
  })

  it('pulsar un día del mes lleva al listado en ese día', async () => {
    const { wrapper } = await montarVista({ view: 'month', date: '2026-09' })

    wrapper.findComponent({ name: 'JournalCalendarMonth' }).vm.$emit('select-day', '2026-09-04')

    expect(push).toHaveBeenCalledWith({ name: 'Journal', query: { date: '2026-09-04' } })
  })

  it('las flechas del año escriben el año en la URL', async () => {
    const { wrapper } = await montarVista({ view: 'year' })

    wrapper.findComponent({ name: 'JournalCalendarYear' }).vm.$emit('select-year', 2024)

    expect(push).toHaveBeenCalledWith({ name: 'Journal', query: { view: 'year', date: '2024' } })
  })

  it('el mes se recuerda al ir y volver del año', async () => {
    const { wrapper } = await montarVista({ view: 'month', date: '2026-03' })

    // Al año: sube al año del mes que se estaba mirando, no al que corre.
    await botonDeVista(wrapper, 'Año').trigger('click')
    expect(push).toHaveBeenLastCalledWith({ name: 'Journal', query: { view: 'year', date: '2026' } })

    // El `push` está mockeado, así que la ruta se mueve a mano, como haría el
    // router de verdad.
    ruta.query = { view: 'year', date: '2026' }
    await wrapper.vm.$nextTick()

    await botonDeVista(wrapper, 'Mes').trigger('click')
    expect(push).toHaveBeenLastCalledWith({ name: 'Journal', query: { view: 'month', date: '2026-03' } })
  })

  it('sin mes recordado, el conmutador abre el mes que corre', async () => {
    const { wrapper } = await montarVista()

    await botonDeVista(wrapper, 'Mes').trigger('click')

    expect(push).toHaveBeenLastCalledWith({
      name: 'Journal',
      query: { view: 'month', date: `${HOY.getFullYear()}-${dosDigitos(HOY.getMonth() + 1)}` }
    })
  })

  it('el listado abierto por un día se PIDE desde ese día, no solo se desplaza', async () => {
    // Es la diferencia entre el M3 y el M4: un día de hace medio año no ha
    // entrado en ninguna página del listado, así que no basta con hacer scroll.
    const { apiCall } = await montarVista({ date: '2026-02-14' })

    expect(apiCall).toHaveBeenCalledWith('get_journal', expect.objectContaining({ to: '2026-02-14' }))
    expect(useJournalStore().anchorDate).toBe('2026-02-14')
  })

  it('volver al listado entero suelta el ancla', async () => {
    const { wrapper } = await montarVista({ date: '2026-02-14' })

    await botonDeVista(wrapper, 'Listado').trigger('click')

    expect(push).toHaveBeenLastCalledWith({ name: 'Journal', query: {} })
  })

  it('sin ancla el listado no manda `to`: es el diario de siempre', async () => {
    const { apiCall } = await montarVista()

    expect(apiCall).toHaveBeenCalledWith('get_journal', { limit: 30, offset: 0 })
  })
})

// ═══════════════════════════════════════════════════════════════════════════
// El filtro por medio manda también sobre el calendario
// ═══════════════════════════════════════════════════════════════════════════

describe('JournalStore — el filtro por medio y el calendario', () => {
  let apiCall

  beforeEach(() => {
    setActivePinia(createPinia())
    apiCall = vi.fn()
    useAuthStore().authenticatedApiCall = apiCall
  })

  it('el medio viaja al agregado del año', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({ days: {}, total: 0, years: [2026] }))
    store.media = 'book'

    await store.fetchCalendar(2026)

    expect(apiCall).toHaveBeenCalledWith('get_journal_calendar', { year: 2026, media: 'book' })
  })

  it('sin filtro, el agregado se pide sin `media`', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({ days: {}, total: 0, years: [2026] }))

    await store.fetchCalendar(2026)

    expect(apiCall).toHaveBeenCalledWith('get_journal_calendar', { year: 2026 })
  })

  it('el medio viaja también a las entradas del mes', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({ entries: [], total: 0, hasMore: false }))
    store.media = 'movie'

    await store.fetchMonth(2026, 9)

    expect(apiCall).toHaveBeenCalledWith('get_journal', {
      from: '2026-09-01', to: '2026-09-30', limit: 100, offset: 0, media: 'movie'
    })
  })

  it('cambiar de píldora invalida lo cargado del calendario', async () => {
    // La vista que esté abierta lo recarga con su propio `watch`; lo que este
    // test fija es que la caché no puede quedarse con datos de otro filtro.
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({ entries: [], total: 0, hasMore: false }))
    store.calendarYear = 2026
    store.monthKey = '2026-09'

    await store.setMedia('game')

    expect(store.calendarYear).toBeNull()
    expect(store.monthKey).toBeNull()
  })
})

// ═══════════════════════════════════════════════════════════════════════════
// El heatmap abre por el mes actual, no por enero
// ═══════════════════════════════════════════════════════════════════════════

/**
 * jsdom no maqueta: todos los rects son ceros y `scrollLeft` no se mueve solo.
 * Se le pone geometría a mano —cada rótulo de mes cien píxeles a la derecha del
 * anterior— y se espía el `scrollLeft` del contenedor, que es exactamente lo
 * que el componente escribe.
 */
const conGeometria = (wrapper) => {
  const caja = wrapper.find('.journal-year__scroll').element
  const estado = { valor: 0 }

  Object.defineProperty(caja, 'scrollLeft', {
    get: () => estado.valor,
    set: (v) => { estado.valor = v },
    configurable: true
  })

  caja.getBoundingClientRect = () => ({ left: 0, right: 0, top: 0, bottom: 0, width: 0, height: 0 })

  wrapper.findAll('.journal-year__month').forEach((boton, i) => {
    boton.element.getBoundingClientRect = () => ({
      left: i * 100, right: 0, top: 0, bottom: 0, width: 0, height: 0
    })
  })

  return estado
}

describe('JournalCalendarYear — la posición inicial del scroll', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  const montarAnio = async (datos, year) => {
    useAuthStore().authenticatedApiCall = vi.fn().mockResolvedValue(respuesta(datos))

    const wrapper = mountComponent(JournalCalendarYear, { props: { year } })
    await new Promise((resolve) => setTimeout(resolve, 0))
    await wrapper.vm.$nextTick()

    return wrapper
  }

  const dias = {
    '2025-03-04': { count: 1, media: ['book'] },
    '2025-03-19': { count: 2, media: ['book'] },
    [`${HOY.getFullYear()}-01-02`]: { count: 1, media: ['book'] }
  }

  it('un año pasado abre por el último mes con entradas, no por enero', async () => {
    const wrapper = await montarAnio({ days: dias, total: 4, years: [HOY.getFullYear(), 2025] }, HOY.getFullYear())
    const scroll = conGeometria(wrapper)

    await wrapper.setProps({ year: 2025 })
    await new Promise((resolve) => setTimeout(resolve, 0))

    // Marzo es el índice 2, y solo se miran las fechas de ESE año: el día de
    // enero del año en curso no cuenta.
    expect(scroll.valor).toBe(200)
  })

  it('el año en curso abre por el mes de hoy', async () => {
    const wrapper = await montarAnio({ days: dias, total: 4, years: [HOY.getFullYear(), 2025] }, 2025)
    const scroll = conGeometria(wrapper)

    await wrapper.setProps({ year: HOY.getFullYear() })
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(scroll.valor).toBe(HOY.getMonth() * 100)
  })

  it('pulsar un rótulo de mes emite su clave `YYYY-MM`', async () => {
    const wrapper = await montarAnio({ days: {}, total: 0, years: [2026] }, 2026)

    await wrapper.findAll('.journal-year__month')[8].trigger('click')

    expect(wrapper.emitted('select-month')[0]).toEqual(['2026-09'])
  })

  it('cambiar el filtro por medio recarga el heatmap abierto', async () => {
    await montarAnio({ days: {}, total: 0, years: [2026] }, 2026)

    const store = useJournalStore()
    const apiCall = useAuthStore().authenticatedApiCall

    apiCall.mockClear()
    // Es lo que hace `setMedia`: cambia el medio e invalida la caché.
    store.media = 'book'
    store.calendarYear = null
    await new Promise((resolve) => setTimeout(resolve, 0))

    expect(apiCall).toHaveBeenCalledWith('get_journal_calendar', { year: 2026, media: 'book' })
  })
})
