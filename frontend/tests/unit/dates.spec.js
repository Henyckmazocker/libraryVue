// La zona horaria se fija **antes** de importar nada que construya fechas: el
// contenedor donde corre Vitest es UTC, y en UTC el día local y el de Greenwich
// coinciden siempre, así que un test de husos sin esta línea pasa igual con la
// implementación mala. `Europe/Madrid` es donde vive la app.
process.env.TZ = 'Europe/Madrid'

import { afterEach, describe, expect, it, vi } from 'vitest'
import { hoyISO } from '@/utils/dates'

/**
 * El tope de los cinco `<input type="date">` de la app.
 *
 * Hasta el 2026-09-09 ninguno tenía `max` y se podía fechar dentro de tres
 * semanas; el diario, que ordena por fecha, era el que más lo notaba.
 */

afterEach(() => {
  vi.useRealTimers()
})

describe('hoyISO', () => {
  it('devuelve el día de hoy en el formato que `min`/`max` exigen', () => {
    const hoy = hoyISO()

    expect(hoy).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    expect(hoy).toBe(new Date().toLocaleDateString('sv-SE'))
  })

  it('no retrocede un día en la franja en la que UTC va por detrás', () => {
    // 00:30 en Madrid es todavía el día anterior en Greenwich. Es el caso que
    // discrimina: `toISOString()` daría `2026-09-09` y el tope impediría fechar
    // el día en curso. Las 23:30 no valen — a esa hora ambos días coinciden.
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-09-10T00:30:00+02:00'))

    expect(new Date().toISOString().slice(0, 10)).toBe('2026-09-09')
    expect(hoyISO()).toBe('2026-09-10')
  })

  it('se recalcula al cruzar la medianoche, no se cachea', () => {
    // Una pestaña abierta desde ayer tiene que cambiar de tope sola.
    vi.useFakeTimers()

    vi.setSystemTime(new Date('2026-09-09T23:59:00+02:00'))
    expect(hoyISO()).toBe('2026-09-09')

    vi.setSystemTime(new Date('2026-09-10T00:01:00+02:00'))
    expect(hoyISO()).toBe('2026-09-10')
  })
})
