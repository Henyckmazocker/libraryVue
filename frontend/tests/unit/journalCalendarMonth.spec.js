import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { mountComponent } from './helpers/mount'
import { useJournalStore } from '@/store/journal'
import { useAuthStore } from '@/store/auth'
import JournalCalendarMonth from '@/components/Journal/JournalCalendarMonth.vue'
import JournalDayCell from '@/components/Journal/JournalDayCell.vue'

/**
 * La rejilla del mes — M3 del Plan - Calendario del Diario.
 *
 * Lo que se fija aquí es lo que **ningún otro control ve**: la rotación a lunes y
 * el largo del mes. Un mes desplazado un día pinta perfectamente, pasa el lint,
 * pasa stylelint, pasa la barrera de desbordamiento y sale bien en una captura —
 * solo se nota contando los huecos de delante del día 1. Los dos casos que pide
 * el plan son el mes que cae en domingo (seis huecos, el máximo) y febrero de un
 * año bisiesto (29 días, que es la regla que no se escribe a mano).
 *
 * El reparto de portadas y el `+N` van en el mismo fichero porque son de la misma
 * celda, y la resolución local → remota es la del listado.
 */

const respuesta = (data, status = 'success') => ({
  data: { status, data, message: 'backend message in english', http_code: 200 }
})

const entrada = (extra = {}) => ({
  id: 1,
  media: 'movie',
  entity_id: 'tt0133093',
  entity_title: 'The Matrix',
  entity_cover: null,
  entry_date: '2026-09-04',
  rating: null,
  source: 'manual',
  is_repeat: false,
  ...extra
})

const montar = async (opciones = {}) => {
  const { entries = [], hasMore = false, year = 2026, month = 9 } = opciones

  const apiCall = vi.fn().mockResolvedValue(respuesta({
    entries,
    total: entries.length,
    hasMore
  }))

  useAuthStore().authenticatedApiCall = apiCall

  // El mes va por props y no por el reloj del sistema: `vi.setSystemTime` obliga
  // a temporizadores falsos, y con ellos el `await` del `onMounted` no avanza.
  const wrapper = mountComponent(JournalCalendarMonth, { props: { year, month } })
  await new Promise((resolve) => setTimeout(resolve, 0))
  await wrapper.vm.$nextTick()

  return { wrapper, apiCall }
}

const huecos = (wrapper) => wrapper.findAll('.journal-month__pad').length
const celdas = (wrapper) => wrapper.findAllComponents(JournalDayCell)

describe('JournalCalendarMonth — la rejilla empieza en lunes', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('deja seis huecos en un mes que empieza en domingo', async () => {
    // Marzo de 2026 cae en domingo. `getDay()` devuelve 0 ahí, así que sin la
    // rotación `(getDay() + 6) % 7` no habría ni un hueco y el mes entero
    // aparecería desplazado seis días, sin que nada más lo notara.
    const { wrapper } = await montar({ year: 2026, month: 3 })

    expect(huecos(wrapper)).toBe(6)
    expect(celdas(wrapper)).toHaveLength(31)
    expect(celdas(wrapper)[0].props('date')).toBe('2026-03-01')
    expect(celdas(wrapper)[30].props('date')).toBe('2026-03-31')
  })

  it('no deja ningún hueco en un mes que empieza en lunes', async () => {
    // Junio de 2026 cae en lunes: es el caso en el que una rotación mal escrita
    // (por ejemplo `getDay() - 1`) daría -1 o 6 en vez de 0.
    const { wrapper } = await montar({ year: 2026, month: 6 })

    expect(huecos(wrapper)).toBe(0)
    expect(celdas(wrapper)).toHaveLength(30)
  })

  it('pinta los 29 días de febrero de un año bisiesto', async () => {
    // Febrero de 2024 tiene 29 días y cae en jueves. El largo sale de «día 0 del
    // mes siguiente», no de una tabla de meses ni de la regla de los bisiestos.
    const { wrapper } = await montar({ year: 2024, month: 2 })

    expect(celdas(wrapper)).toHaveLength(29)
    expect(huecos(wrapper)).toBe(3)
    expect(celdas(wrapper)[28].props('date')).toBe('2024-02-29')
  })

  it('pinta 28 días en febrero de un año que no es bisiesto', async () => {
    const { wrapper } = await montar({ year: 2026, month: 2 })

    expect(celdas(wrapper)).toHaveLength(28)
    expect(celdas(wrapper)[27].props('date')).toBe('2026-02-28')
  })
})

describe('JournalCalendarMonth — los datos salen del store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('pide el rango del mes, no el listado entero', async () => {
    const { apiCall } = await montar({ year: 2026, month: 9 })

    expect(apiCall).toHaveBeenCalledTimes(1)
    expect(apiCall.mock.calls[0][0]).toBe('get_journal')
    expect(apiCall.mock.calls[0][1]).toMatchObject({
      from: '2026-09-01',
      to: '2026-09-30',
      offset: 0
    })
  })

  it('cierra el rango en el 29 de febrero de un año bisiesto', async () => {
    const { apiCall } = await montar({ year: 2024, month: 2 })

    expect(apiCall.mock.calls[0][1]).toMatchObject({ from: '2024-02-01', to: '2024-02-29' })
  })

  it('reparte cada entrada en la celda de su fecha, sin pasar por Date', async () => {
    const { wrapper } = await montar({
      entries: [
        entrada({ id: 1, entry_date: '2026-09-01' }),
        entrada({ id: 2, entry_date: '2026-09-07' }),
        entrada({ id: 3, entry_date: '2026-09-07' })
      ]
    })

    const porFecha = Object.fromEntries(
      celdas(wrapper).map((celda) => [celda.props('date'), celda.props('entries').length])
    )

    expect(porFecha['2026-09-01']).toBe(1)
    expect(porFecha['2026-09-07']).toBe(2)
    expect(porFecha['2026-09-02']).toBe(0)
  })

  it('pagina con offset mientras el backend diga que hay más', async () => {
    // El `limit` se topa en 100 en el servidor (`GetJournalQuery.php:32-39`), así
    // que un mes de más de cien entradas se recorre con `offset`.
    const primera = Array.from({ length: 100 }, (_, i) =>
      entrada({ id: i + 1, entry_date: '2026-09-04' })
    )
    const segunda = [entrada({ id: 101, entry_date: '2026-09-05' })]

    const apiCall = vi.fn()
      .mockResolvedValueOnce(respuesta({ entries: primera, total: 101, hasMore: true }))
      .mockResolvedValueOnce(respuesta({ entries: segunda, total: 101, hasMore: false }))

    useAuthStore().authenticatedApiCall = apiCall

    const store = useJournalStore()
    await store.fetchMonth(2026, 9)

    expect(apiCall).toHaveBeenCalledTimes(2)
    expect(apiCall.mock.calls[1][1].offset).toBe(100)
    expect(store.monthEntries).toHaveLength(101)
    expect(store.monthByDate['2026-09-05']).toHaveLength(1)
  })

  it('no vuelve a pedir el mes que ya tiene cargado', async () => {
    const apiCall = vi.fn().mockResolvedValue(respuesta({ entries: [], total: 0, hasMore: false }))
    useAuthStore().authenticatedApiCall = apiCall

    const store = useJournalStore()
    await store.fetchMonth(2026, 9)

    const wrapper = mountComponent(JournalCalendarMonth, { props: { year: 2026, month: 9 } })
    await new Promise((resolve) => setTimeout(resolve, 0))
    await wrapper.vm.$nextTick()

    expect(apiCall).toHaveBeenCalledTimes(1)
  })
})

describe('JournalDayCell — portadas, +N y el evento', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  const celda = (entries, extra = {}) => mountComponent(JournalDayCell, {
    props: { date: '2026-09-04', label: '4 de septiembre', entries, ...extra }
  })

  it('distingue un día vacío de uno lleno sin leer números', async () => {
    const vacia = celda([])
    const llena = celda([entrada()])

    expect(vacia.classes()).toContain('journal-day--vacia')
    expect(vacia.attributes('disabled')).toBeDefined()

    expect(llena.classes()).toContain('journal-day--llena')
    // El acento es el del medio dominante: una película pinta el de `movie`.
    expect(llena.classes()).toContain('journal-day--movie')
    expect(llena.attributes('disabled')).toBeUndefined()
  })

  it('usa el acento de película para una serie, que no tiene el suyo', () => {
    const wrapper = celda([entrada({ media: 'series', entity_id: 'tt0903747' })])

    expect(wrapper.classes()).toContain('journal-day--movie')
  })

  it('resuelve la portada como el listado: local primero y remota de respaldo', async () => {
    const wrapper = celda([entrada({ entity_cover: 'https://cdn.example/matrix.jpg' })])
    const img = wrapper.find('img')

    // La copia local es el endpoint propio `?cover=<medio>/<clave>`.
    expect(img.attributes('src')).toContain('cover=movie/tt0133093')

    await img.trigger('error')

    expect(wrapper.find('img').attributes('src')).toBe('https://cdn.example/matrix.jpg')
  })

  it('pide la portada de una serie como movie, que es su media_type real', () => {
    const wrapper = celda([entrada({ media: 'series', entity_id: 'tt0903747' })])

    expect(wrapper.find('img').attributes('src')).toContain('cover=movie/tt0903747')
  })

  it('cae al icono del medio cuando no queda ninguna portada', async () => {
    const wrapper = celda([entrada({ entity_cover: null })])

    await wrapper.find('img').trigger('error')

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('.journal-day__cover i').exists()).toBe(true)
  })

  it('enseña cuatro portadas y +N con el resto', () => {
    const entradas = Array.from({ length: 7 }, (_, i) =>
      entrada({ id: i + 1, entity_id: `tt000000${i}` })
    )

    const wrapper = celda(entradas)

    expect(wrapper.findAll('.journal-day__cover')).toHaveLength(4)
    expect(wrapper.find('.journal-day__more').text()).toBe('+3')
  })

  it('no enseña +N cuando las portadas caben', () => {
    const entradas = Array.from({ length: 4 }, (_, i) => entrada({ id: i + 1 }))

    expect(celda(entradas).find('.journal-day__more').exists()).toBe(false)
  })

  it('pinta el contador del plan B con el número de entradas', () => {
    // El plan B lo esconde el CSS por encima de `md`, no un `v-if`: el contador
    // está siempre en el DOM y jsdom no evalúa CSS, así que aquí solo se
    // comprueba su texto.
    const entradas = Array.from({ length: 3 }, (_, i) => entrada({ id: i + 1 }))

    expect(celda(entradas).find('.journal-day__count').text()).toBe('×3')
  })

  it('emite su fecha al pulsarla, que es lo que hereda el M4', async () => {
    const wrapper = celda([entrada()])

    await wrapper.trigger('click')

    expect(wrapper.emitted('select')).toEqual([['2026-09-04']])
  })

  it('la rejilla propaga la fecha hacia arriba como select-day', async () => {
    const { wrapper } = await montar({ entries: [entrada({ entry_date: '2026-09-04' })] })

    const llena = celdas(wrapper).find((c) => c.props('date') === '2026-09-04')
    await llena.trigger('click')

    expect(wrapper.emitted('select-day')).toEqual([['2026-09-04']])
  })
})
