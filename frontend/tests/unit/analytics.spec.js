// El módulo src/analytics/ con el SDK de Augur mockeado (Plan «Consentimiento de Analítica», M2):
// cuándo configura, qué decisión aplica y en qué orden apaga. Más la barrera de que nadie fuera de
// src/analytics/ importa el SDK.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { createRouter, createMemoryHistory } from 'vue-router'

// El consentimiento del mock sigue a setConsent(), como el del SDK real.
const state = vi.hoisted(() => ({ consent: false }))
vi.mock('@/augur/augur.js', () => ({
  default: {
    configure: vi.fn(),
    setConsent: vi.fn((granted) => { state.consent = granted === true }),
    hasConsent: vi.fn(() => state.consent),
    trackRouter: vi.fn(),
    captureErrors: vi.fn(),
    track: vi.fn(),
    flush: vi.fn(() => Promise.resolve(true)),
  },
}))

import Augur from '@/augur/augur.js'
import {
  initAnalytics, syncAnalytics, stopAnalytics, isAnalyticsAvailable, _resetForTests,
} from '@/analytics'

const Dummy = { render: () => null }

function makeRouter () {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: Dummy },
      { path: '/books/:isbn', component: Dummy },
      { path: '/profile', component: Dummy },
    ],
  })
}

function setSecure (secure) {
  Object.defineProperty(window, 'isSecureContext', { value: secure, configurable: true })
  Object.defineProperty(navigator, 'locks', { value: secure ? { request: vi.fn() } : undefined, configurable: true })
}

beforeEach(() => {
  _resetForTests()
  vi.clearAllMocks()
  state.consent = false
  vi.stubEnv('VUE_APP_AUGUR_KEY', 'clave-de-prueba')
  setSecure(true)
})

afterEach(() => {
  vi.unstubAllEnvs()
})

describe('initAnalytics', () => {
  it('sin clave no llama a nada, y el resto del módulo tampoco', async () => {
    vi.stubEnv('VUE_APP_AUGUR_KEY', '')
    initAnalytics(makeRouter())
    syncAnalytics({ analytics_consent: 1 })
    await stopAnalytics()
    expect(isAnalyticsAvailable()).toBe(false)
    for (const fn of Object.values(Augur)) expect(fn).not.toHaveBeenCalled()
  })

  it('fuera de contexto seguro (sin navigator.locks) no configura', () => {
    setSecure(false)
    initAnalytics(makeRouter())
    expect(Augur.configure).not.toHaveBeenCalled()
    expect(isAnalyticsAvailable()).toBe(false)
  })

  it('con clave: configure → setConsent(false) → trackRouter → captureErrors, una sola vez', () => {
    const router = makeRouter()
    initAnalytics(router)
    initAnalytics(router)
    expect(Augur.configure).toHaveBeenCalledTimes(1)
    expect(Augur.configure.mock.calls[0][0].writeKey).toBe('clave-de-prueba')
    expect(Augur.setConsent).toHaveBeenCalledTimes(1)
    expect(Augur.setConsent).toHaveBeenCalledWith(false)
    const order = [Augur.configure, Augur.setConsent, Augur.trackRouter, Augur.captureErrors]
      .map((f) => f.mock.invocationCallOrder[0])
    expect(order).toEqual([...order].sort((a, b) => a - b))
    expect(Augur.trackRouter).toHaveBeenCalledWith(router)
    expect(isAnalyticsAvailable()).toBe(true)
  })
})

describe('syncAnalytics', () => {
  beforeEach(() => {
    initAnalytics(makeRouter())
    vi.clearAllMocks()
  })

  it.each([
    ['null', null],
    ['undefined', undefined],
    ['analytics_consent: null', { analytics_consent: null }],
    ['sin el campo (backend sin migrar)', { id: 1 }],
    ['analytics_consent: 0', { analytics_consent: 0 }],
    ['analytics_consent: true (no es 1)', { analytics_consent: true }],
  ])('%s → setConsent(false)', (_label, user) => {
    syncAnalytics(user)
    expect(Augur.setConsent).toHaveBeenCalledTimes(1)
    expect(Augur.setConsent).toHaveBeenCalledWith(false)
  })

  it('analytics_consent: 1 → setConsent(true)', () => {
    syncAnalytics({ analytics_consent: 1 })
    expect(Augur.setConsent).toHaveBeenCalledTimes(1)
    expect(Augur.setConsent).toHaveBeenCalledWith(true)
  })
})

describe('stopAnalytics', () => {
  it('flush ANTES de setConsent(false) (setConsent(false) borra la cola)', async () => {
    initAnalytics(makeRouter())
    syncAnalytics({ analytics_consent: 1 })
    vi.clearAllMocks()
    await stopAnalytics()
    expect(Augur.flush).toHaveBeenCalledTimes(1)
    expect(Augur.setConsent).toHaveBeenCalledWith(false)
    expect(Augur.flush.mock.invocationCallOrder[0]).toBeLessThan(Augur.setConsent.mock.invocationCallOrder[0])
  })

  it('apaga aunque el flush falle', async () => {
    initAnalytics(makeRouter())
    Augur.flush.mockImplementationOnce(() => Promise.reject(new Error('red')))
    await expect(stopAnalytics()).resolves.toBeUndefined()
    expect(Augur.setConsent).toHaveBeenLastCalledWith(false)
  })
})

describe('page_view de la ruta actual en la transición false → true', () => {
  it('ruta pública: la navegación terminó sin consentimiento → se emite a mano, con el PATRÓN', async () => {
    const router = makeRouter()
    initAnalytics(router)
    await router.push('/books/9788408172178')
    syncAnalytics({ analytics_consent: 1 })
    expect(Augur.track).toHaveBeenCalledTimes(1)
    expect(Augur.track).toHaveBeenCalledWith('page_view', { path: '/books/:isbn' })
    // Una segunda sincronización con la sesión ya encendida no duplica
    syncAnalytics({ analytics_consent: 1 })
    expect(Augur.track).toHaveBeenCalledTimes(1)
  })

  it('ruta protegida: el consentimiento llega antes de acabar la navegación → no se emite a mano', async () => {
    const router = makeRouter()
    initAnalytics(router)
    router.beforeEach(() => { syncAnalytics({ analytics_consent: 1 }) })  // el guard espera a initializeAuth
    await router.push('/profile')
    expect(Augur.track).not.toHaveBeenCalled()
  })

  it('navegación ya emitida con consentimiento: logout y login la vuelven a emitir en la sesión nueva', async () => {
    const router = makeRouter()
    initAnalytics(router)
    syncAnalytics({ analytics_consent: 1 })
    await router.push('/profile')          // el trackRouter real la habría emitido
    expect(Augur.track).not.toHaveBeenCalled()
    await stopAnalytics()
    syncAnalytics({ analytics_consent: 1 })
    expect(Augur.track).toHaveBeenCalledWith('page_view', { path: '/profile' })
  })

  it('con consentimiento 0 no se emite nada', async () => {
    const router = makeRouter()
    initAnalytics(router)
    await router.push('/books/1')
    syncAnalytics({ analytics_consent: 0 })
    expect(Augur.track).not.toHaveBeenCalled()
  })
})

describe('barrera: el SDK solo se importa desde src/analytics/', () => {
  it('ningún fichero de src/ fuera de analytics/ (y del propio SDK) importa @/augur/', () => {
    // Desde process.cwd(), como i18n.spec.js: en Vitest import.meta.url no siempre es file://
    const src = join(process.cwd(), 'src')
    const importsSdk = (code) =>
      /(?:from\s*|import\s*\(\s*|require\s*\(\s*|import\s+)['"](?:@\/augur\/|(?:\.\.?\/)+augur\/)/.test(code)
    // La barrera no es de adorno: el único importador legítimo sí la dispara
    expect(importsSdk(readFileSync(join(src, 'analytics/index.js'), 'utf8'))).toBe(true)
    const offenders = []
    const walk = (dir) => {
      for (const name of readdirSync(dir)) {
        const full = join(dir, name)
        if (statSync(full).isDirectory()) { walk(full); continue }
        if (!/\.(js|mjs|cjs|ts|vue)$/.test(name)) continue
        const rel = relative(src, full).split(/[\\/]/)
        if (rel[0] === 'analytics' || rel[0] === 'augur') continue
        const code = readFileSync(full, 'utf8')
        if (importsSdk(code)) {
          offenders.push(rel.join('/'))
        }
      }
    }
    walk(src)
    expect(offenders).toEqual([])
  })
})
