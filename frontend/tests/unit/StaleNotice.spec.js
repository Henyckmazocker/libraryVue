import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { mountComponent } from './helpers/mount'
import StaleNotice from '@/components/shared/StaleNotice.vue'

describe('StaleNotice — la franja de degradación', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-08-26T12:00:00Z'))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('no pinta nada —ni un hueco— cuando los datos son frescos', () => {
    const w = mountComponent(StaleNotice, {
      props: { stale: false, cachedAt: '2026-08-03T11:42:07+00:00', provider: 'IGDB' }
    })

    // La garantía del plan: con el proveedor respondiendo, la interfaz no
    // cambia en nada. Un `v-if`, no un `v-show` ni una clase.
    expect(w.find('.stale-notice').exists()).toBe(false)
    expect(w.html()).toBe('<!--v-if-->')
  })

  it('nombra al proveedor y dice de cuándo son los datos', () => {
    const w = mountComponent(StaleNotice, {
      props: { stale: true, cachedAt: '2026-08-23T12:00:00Z', provider: 'IGDB' }
    })

    const texto = w.find('.stale-notice').text()
    expect(texto).toContain('Sin conexión con IGDB')
    expect(texto).toContain('hace 3 d')
  })

  it('degrada el mensaje sin fecha en vez de pintar Invalid Date', () => {
    // `cached_at` llega null si la copia se guardó sin marca de tiempo.
    const w = mountComponent(StaleNotice, {
      props: { stale: true, cachedAt: null, provider: 'YouTube' }
    })

    const texto = w.find('.stale-notice').text()
    expect(texto).toContain('Sin conexión con YouTube')
    expect(texto).toContain('pueden no estar actualizados')
    expect(texto).not.toContain('Invalid Date')
    expect(texto).not.toContain('NaN')
  })

  it('una fecha imparseable tampoco produce Invalid Date', () => {
    const w = mountComponent(StaleNotice, {
      props: { stale: true, cachedAt: 'no soy una fecha', provider: 'IGDB' }
    })

    expect(w.find('.stale-notice').text()).not.toContain('Invalid Date')
  })

  it('escala de minutos a horas y a días', () => {
    const casos = [
      ['2026-08-26T11:58:00Z', 'hace 2 min'],
      ['2026-08-26T07:00:00Z', 'hace 5 h'],
      ['2026-07-27T12:00:00Z', 'hace 30 d']
    ]

    for (const [cachedAt, esperado] of casos) {
      const w = mountComponent(StaleNotice, { props: { stale: true, cachedAt, provider: 'IGDB' } })
      expect(w.find('.stale-notice').text()).toContain(esperado)
    }
  })

  it('es un `status` para que un lector de pantalla lo anuncie sin robar el foco', () => {
    const w = mountComponent(StaleNotice, {
      props: { stale: true, cachedAt: null, provider: 'IGDB' }
    })

    expect(w.find('.stale-notice').attributes('role')).toBe('status')
  })
})
