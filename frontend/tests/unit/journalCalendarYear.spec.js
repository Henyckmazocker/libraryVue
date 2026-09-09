import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { mountComponent } from './helpers/mount'
import { useJournalStore } from '@/store/journal'
import { useAuthStore } from '@/store/auth'
import JournalCalendarYear from '@/components/Journal/JournalCalendarYear.vue'

/**
 * El heatmap del año — M2 del Plan - Calendario del Diario.
 *
 * Lo que se fija aquí es el **reparto por cuantiles**, que es lo único del hito
 * que ningún otro control ve: ni el lint, ni stylelint, ni la barrera de
 * desbordamiento, ni una captura dicen nada de si el mapa está bien repartido.
 * Y en particular las dos series degeneradas, que son donde un cuantil ingenuo
 * divide por cero o aplana el año entero en un solo escalón.
 */

const ANIO = 2026

const respuesta = (data, status = 'success') => ({
  data: { status, data, message: 'backend message in english', http_code: 200 }
})

/** `{ '2026-01-05': { count, media } }` a partir de un mapa día→cuenta. */
const calendario = (dias) => {
  const salida = {}

  for (const [fecha, count] of Object.entries(dias)) {
    salida[fecha] = { count, media: ['book'] }
  }

  return salida
}

/** Cuántas celdas hay de cada escalón, indexado por el número del escalón. */
const porNivel = (wrapper) => {
  const cuenta = [0, 0, 0, 0, 0]

  for (const celda of wrapper.findAll('.journal-year__day')) {
    const clase = celda.classes().find((c) => /journal-year__day--n\d$/.test(c))
    cuenta[Number(clase.slice(-1))]++
  }

  return cuenta
}

const montar = async (datos, year = ANIO) => {
  const apiCall = vi.fn().mockResolvedValue(respuesta(datos))
  useAuthStore().authenticatedApiCall = apiCall

  // El año va por prop y no por el reloj del sistema: `vi.setSystemTime` obliga a
  // temporizadores falsos, y con ellos el `await` del `onMounted` no avanza.
  const wrapper = mountComponent(JournalCalendarYear, { props: { year } })
  await new Promise((resolve) => setTimeout(resolve, 0))
  await wrapper.vm.$nextTick()

  return { wrapper, apiCall }
}

describe('JournalCalendarYear — el reparto por cuantiles', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('reparte una serie ancha en los cuatro escalones', async () => {
    // Diez días con cuentas muy distintas: los cuantiles tienen material.
    const { wrapper } = await montar({
      days: calendario({
        '2026-01-05': 1,
        '2026-01-06': 1,
        '2026-01-07': 1,
        '2026-02-10': 2,
        '2026-03-11': 2,
        '2026-04-12': 3,
        '2026-05-13': 4,
        '2026-06-14': 6,
        '2026-07-15': 12,
        '2026-08-16': 40
      }),
      total: 72,
      years: [ANIO]
    })

    const niveles = porNivel(wrapper)

    // Los cuatro escalones se usan, y el escalón 0 se lleva el resto del año.
    expect(niveles.slice(1).every((n) => n > 0)).toBe(true)
    expect(niveles[0]).toBe(365 - 10)
    // El día de 40 es el más intenso; los de 1, los menos.
    expect(niveles[4]).toBeGreaterThan(0)
    expect(niveles[1]).toBeGreaterThanOrEqual(3)
  })

  it('serie degenerada: todos los días con 1 → un solo escalón, sin dividir por cero', async () => {
    const { wrapper } = await montar({
      days: calendario({
        '2026-01-05': 1,
        '2026-02-05': 1,
        '2026-03-05': 1,
        '2026-04-05': 1,
        '2026-05-05': 1,
        '2026-06-05': 1
      }),
      total: 6,
      years: [ANIO]
    })

    const niveles = porNivel(wrapper)

    // Todos en el escalón 1: no hay diferencia que pintar, y el escalón 1 es el
    // que está medido para destacar sobre la celda vacía (2.24 y 2.14).
    expect(niveles[1]).toBe(6)
    expect(niveles[2] + niveles[3] + niveles[4]).toBe(0)
    expect(niveles[0]).toBe(365 - 6)
  })

  it('serie degenerada: un solo día con 40 → un escalón, y el año entero vacío', async () => {
    const { wrapper } = await montar({
      days: calendario({ '2026-08-16': 40 }),
      total: 40,
      years: [ANIO]
    })

    const niveles = porNivel(wrapper)

    expect(niveles[1]).toBe(1)
    expect(niveles[0]).toBe(364)
    expect(niveles[2] + niveles[3] + niveles[4]).toBe(0)
  })

  it('con dos valores distintos se ven DOS escalones, no un mapa plano', async () => {
    // Es la serie real de la base de dev: seis días, con cuentas de 1 y 2. Un
    // cuantil sobre seis muestras que no mirase los valores distintos los
    // metería a todos en el mismo escalón.
    const { wrapper } = await montar({
      days: calendario({
        '2026-08-27': 1,
        '2026-08-29': 1,
        '2026-09-01': 1,
        '2026-09-07': 2,
        '2026-09-17': 1,
        '2026-09-24': 1
      }),
      total: 7,
      years: [ANIO]
    })

    const niveles = porNivel(wrapper)

    expect(niveles[1]).toBe(5)
    expect(niveles[2]).toBe(1)
    expect(niveles[3] + niveles[4]).toBe(0)
  })
})

describe('JournalCalendarYear — la rejilla y sus rótulos', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('pinta un año no bisiesto entero y lo rota a lunes', async () => {
    const { wrapper, apiCall } = await montar({ days: {}, total: 0, years: [ANIO] })

    expect(apiCall).toHaveBeenCalledWith('get_journal_calendar', { year: ANIO })
    expect(wrapper.findAll('.journal-year__day')).toHaveLength(365)
    // El 1 de enero de 2026 es jueves: tres huecos delante (lunes, martes, miércoles).
    expect(wrapper.findAll('.journal-year__pad')).toHaveLength(3)
    // Los doce rótulos de mes, que desde el M4 son botones que llevan al mes.
    expect(wrapper.findAll('.journal-year__month')).toHaveLength(12)
  })

  it('un año bisiesto trae sus 366 días, con el 29 de febrero', async () => {
    const { wrapper } = await montar({ days: {}, total: 0, years: [2024] }, 2024)

    expect(wrapper.findAll('.journal-year__day')).toHaveLength(366)
    const etiquetas = wrapper.findAll('.journal-year__day .u-sr-only').map((n) => n.text())
    expect(etiquetas.some((e) => e.includes('29') && e.includes('febrero'))).toBe(true)
  })

  it('cada celda se nombra con su fecha y su número de entradas', async () => {
    const { wrapper } = await montar({
      days: calendario({ '2026-09-07': 2 }),
      total: 2,
      years: [ANIO]
    })

    const etiquetas = wrapper.findAll('.journal-year__day .u-sr-only').map((n) => n.text())

    expect(etiquetas).toContain('7 de septiembre de 2026: 2 entradas')
    expect(etiquetas).toContain('6 de septiembre de 2026: nada')
  })

  it('la rejilla es UNA parada de tabulador y las flechas mueven el foco', async () => {
    const { wrapper } = await montar({
      days: calendario({ '2026-09-07': 2 }),
      total: 2,
      years: [ANIO]
    })

    const celdas = wrapper.findAll('.journal-year__day')
    const tabulables = celdas.filter((c) => c.attributes('tabindex') === '0')

    // Una sola celda tabulable, y es la primera con algo: 365 paradas delante del
    // resto de la página no es «se recorre con teclado».
    expect(tabulables).toHaveLength(1)
    expect(tabulables[0].find('.u-sr-only').text()).toContain('7 de septiembre')

    // Flecha derecha = la semana siguiente, que son siete días más.
    const indice = celdas.findIndex((c) => c.attributes('tabindex') === '0')
    await celdas[indice].trigger('keydown', { key: 'ArrowRight' })

    const nuevo = wrapper.findAll('.journal-year__day')
      .findIndex((c) => c.attributes('tabindex') === '0')
    expect(nuevo).toBe(indice + 7)
  })
})

describe('JournalStore — el agregado del calendario', () => {
  let apiCall

  beforeEach(() => {
    setActivePinia(createPinia())
    apiCall = vi.fn()
    useAuthStore().authenticatedApiCall = apiCall
  })

  it('un año vacío llega como `[]` y se guarda como objeto', async () => {
    // `days` es un array PHP vacío y `json_encode` lo serializa como lista, no
    // como objeto. Quien lo recorra por claves no lo nota; quien lo asigne, sí.
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({ days: [], total: 0, years: [] }))

    await store.fetchCalendar(2019)

    expect(Array.isArray(store.calendarDays)).toBe(false)
    expect(store.calendarDays).toEqual({})
    expect(store.calendarCounts).toEqual([])
    expect(store.calendarYear).toBe(2019)
  })

  it('descarta los días sin cuenta y conserva los medios', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({
      days: {
        '2026-09-07': { count: 2, media: ['book', 'movie'] },
        '2026-09-08': { count: 0, media: [] }
      },
      total: 2,
      years: [2026]
    }))

    await store.fetchCalendar(2026)

    expect(Object.keys(store.calendarDays)).toEqual(['2026-09-07'])
    expect(store.calendarDays['2026-09-07'].media).toEqual(['book', 'movie'])
    expect(store.calendarCounts).toEqual([2])
  })

  it('un fallo deja el error del diario y no ensucia el año anterior', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta(null, 'error'))

    const ok = await store.fetchCalendar(2026)

    expect(ok).toBe(false)
    expect(store.calendarDays).toEqual({})
    expect(store.error).toBeTruthy()
  })
})
