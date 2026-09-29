// Extremo a extremo SIN navegador (Plan «Consentimiento de Analítica», M2): el store de auth, el
// módulo src/analytics/ y el SDK de Augur REAL, con storages en memoria y el `fetch` espiado por
// `_setTestHooks` (el mismo montaje que el M0). Solo se falsea el backend (axios). La prueba en un
// navegador real con el Augur de dev queda para David.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('axios', () => ({ default: { post: vi.fn() } }))

const noTimers = { setInterval: () => 0, clearInterval: () => {}, setTimeout: () => 0, clearTimeout: () => {} }
const Dummy = { render: () => null }

let fetchSpy
let Augur, analytics, auth, axios, createMemoryStorage

// Módulos nuevos en cada prueba: configure() del SDK es de un solo uso.
async function boot ({ oldConsent = false } = {}) {
  vi.resetModules()
  ;({ default: Augur } = await import('@/augur/augur.js'))
  ;({ createMemoryStorage } = await import('@/augur/store.js'))
  analytics = await import('@/analytics')
  const pinia = await import('pinia')
  pinia.setActivePinia(pinia.createPinia())
  ;({ default: axios } = await import('axios'))
  const { useAuthStore } = await import('@/store/auth')
  auth = useAuthStore()

  const local = createMemoryStorage()
  // Un navegador que pasó por el humo: `consent: true` guardado de una visita anterior.
  if (oldConsent) local.setItem('augur:state', JSON.stringify({ install_id: '11111111-1111-4111-8111-111111111111', consent: true }))
  fetchSpy = vi.fn(async () => ({ status: 200, text: async () => '{}', headers: { get: () => null } }))
  // navigator.locks asíncrono, como el del navegador (contexto seguro)
  const locks = { request: (_n, _o, cb) => Promise.resolve().then(() => cb({})) }
  Augur._setTestHooks({
    localStorage: local,
    sessionStorage: createMemoryStorage(),
    transport: { fetch: fetchSpy, sendBeacon: () => true, locks },
    timers: noTimers,
  })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/', component: Dummy }, { path: '/books/:isbn', component: Dummy }],
  })
  analytics.initAnalytics(router)
  return router
}

const sentEvents = () => fetchSpy.mock.calls.flatMap(([, init]) => {
  const body = JSON.parse(init.body)
  return (body.sessions ?? []).flatMap((s) => (s.events ?? []).map((e) => e.name))
})

const checkAuth = (analytics_consent) => axios.post.mockResolvedValueOnce({
  data: { status: 'success', data: { user: { id: 7, name: 'Ana', analytics_consent }, csrf_token: 'csrf' } },
})

beforeEach(() => {
  vi.stubEnv('VUE_APP_AUGUR_KEY', '0123456789abcdef0123456789abcdef')
  vi.stubEnv('VUE_APP_AUGUR_ENDPOINT', 'http://augur.test')
  Object.defineProperty(window, 'isSecureContext', { value: true, configurable: true })
  Object.defineProperty(navigator, 'locks', { value: {}, configurable: true })
})

afterEach(() => {
  vi.unstubAllEnvs()
})

describe('analítica de extremo a extremo (SDK real, fetch espiado)', () => {
  it('sin sesión no sale nada, ni con un consent:true viejo en localStorage', async () => {
    const router = await boot({ oldConsent: true })
    await router.push('/books/123')
    await Augur.flush()
    await new Promise((r) => setTimeout(r, 0))
    expect(fetchSpy).not.toHaveBeenCalled()
  })

  it('usuario con 1 → sube page_view por patrón; tras logout, ninguna subida más', async () => {
    const router = await boot()
    await router.push('/books/9788408172178')     // ruta pública: la sesión llega después
    checkAuth(1)
    await auth.initializeAuth()
    await Augur.flush()
    expect(fetchSpy).toHaveBeenCalled()
    expect(fetchSpy.mock.calls[0][0]).toMatch(/^http:\/\/augur\.test\//)
    expect(sentEvents()).toContain('page_view')
    expect(JSON.stringify(fetchSpy.mock.calls.map(([, i]) => JSON.parse(i.body)))).toContain('/books/:isbn')
    expect(JSON.stringify(fetchSpy.mock.calls.map(([, i]) => i.body))).not.toContain('9788408172178')

    axios.post.mockResolvedValueOnce({ data: { status: 'success' } })
    await auth.logout()
    const afterLogout = fetchSpy.mock.calls.length
    await router.push('/')
    Augur.track('algo')
    await Augur.flush()
    expect(Augur.hasConsent()).toBe(false)
    expect(fetchSpy.mock.calls.length).toBe(afterLogout)
  })

  it('usuario con 0 → ninguna subida', async () => {
    const router = await boot({ oldConsent: true })
    checkAuth(0)
    await auth.initializeAuth()
    await router.push('/books/1')
    await Augur.flush()
    expect(fetchSpy).not.toHaveBeenCalled()
  })

  it('usuario sin decidir (null) → ninguna subida', async () => {
    const router = await boot()
    checkAuth(null)
    await auth.initializeAuth()
    await router.push('/books/1')
    await Augur.flush()
    expect(fetchSpy).not.toHaveBeenCalled()
  })
})
