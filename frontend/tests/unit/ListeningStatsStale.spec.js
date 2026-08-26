import { describe, expect, it, vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'
import { mountComponent } from './helpers/mount'
import ListeningStats from '@/components/Albums/ListeningStats.vue'

const authenticatedApiCall = vi.fn()

vi.mock('@/store/auth', () => ({
  useAuthStore: () => ({
    authenticatedApiCall,
    userLastFmUsername: 'dcahomelab'
  })
}))

// El composable saca el usuario con storeToRefs sobre el store mockeado.
vi.mock('pinia', async () => {
  const real = await vi.importActual('pinia')
  return { ...real, storeToRefs: (s) => ({ userLastFmUsername: { value: s.userLastFmUsername } }) }
})

const ok = (data) => ({ data: { status: 'success', data } })

async function montar () {
  const w = mountComponent(ListeningStats, {
    global: { stubs: { RouterLink: true } }
  })
  await flushPromises()
  return w
}

describe('ListeningStats — la franja de degradación', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    authenticatedApiCall.mockReset()
  })

  it('avisa cuando las gráficas salen de una caché caducada', async () => {
    authenticatedApiCall.mockResolvedValue(ok({
      stats_type: 'user_info',
      data: { playcount: 12345 },
      stale: true,
      cached_at: '2026-08-03T11:42:07+00:00'
    }))

    const w = await montar()

    const franja = w.find('.stale-notice')
    expect(franja.exists()).toBe(true)
    expect(franja.text()).toContain('Last.fm')
  })

  it('no avisa nada cuando Last.fm responde con normalidad', async () => {
    authenticatedApiCall.mockResolvedValue(ok({
      stats_type: 'user_info',
      data: { playcount: 12345 },
      stale: false,
      cached_at: '2026-08-26T11:00:00+00:00'
    }))

    const w = await montar()

    expect(w.find('.stale-notice').exists()).toBe(false)
    // Y las estadísticas se siguen pintando igual que siempre.
    expect(w.find('.user-info-card').exists()).toBe(true)
  })

  it('una respuesta sin los campos no rompe ni avisa', async () => {
    // Defensa contra un backend antiguo: `stale` ausente no es «no lo sé», es
    // «no hay nada que avisar».
    authenticatedApiCall.mockResolvedValue(ok({ stats_type: 'user_info', data: { playcount: 1 } }))

    const w = await montar()

    expect(w.find('.stale-notice').exists()).toBe(false)
  })
})
