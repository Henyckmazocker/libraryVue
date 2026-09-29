// store/auth.js y la analítica (Plan «Consentimiento de Analítica», M2): quién llama a
// syncAnalytics / stopAnalytics y cómo guarda la decisión updateAnalyticsConsent.
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('@/analytics', () => ({
  syncAnalytics: vi.fn(),
  stopAnalytics: vi.fn(() => Promise.resolve()),
}))
// axios se falsea sin importarlo: la regla no-restricted-imports lo reserva a store/auth.js
const axios = vi.hoisted(() => ({ post: vi.fn() }))
vi.mock('axios', () => ({ default: axios }))

import { syncAnalytics, stopAnalytics } from '@/analytics'
import { useAuthStore } from '@/store/auth'

const ok = (data) => ({ data: { status: 'success', data } })

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  localStorage.clear()
})

describe('auth store → analítica', () => {
  it('initializeAuth con sesión sincroniza con el usuario que devuelve check_auth', async () => {
    const user = { id: 7, name: 'Ana', analytics_consent: 1 }
    axios.post.mockResolvedValueOnce(ok({ user, csrf_token: 'csrf' }))
    const auth = useAuthStore()
    await auth.initializeAuth()
    expect(syncAnalytics).toHaveBeenCalledWith(user)
    expect(stopAnalytics).not.toHaveBeenCalled()
  })

  it('initializeAuth sin sesión apaga (vía logout)', async () => {
    axios.post.mockRejectedValueOnce(Object.assign(new Error('401'), { response: { status: 401, data: {} } }))
    const auth = useAuthStore()
    await auth.initializeAuth()
    expect(stopAnalytics).toHaveBeenCalledTimes(1)
    expect(syncAnalytics).not.toHaveBeenCalled()
  })

  it('login sincroniza con el usuario del login', async () => {
    const user = { id: 7, name: 'Ana', analytics_consent: 0 }
    axios.post.mockResolvedValueOnce(ok({ user, csrf_token: 'csrf' }))
    const auth = useAuthStore()
    const res = await auth.login('google-token')
    expect(res.success).toBe(true)
    expect(syncAnalytics).toHaveBeenCalledWith(user)
  })

  it('logout apaga la analítica y limpia la sesión', async () => {
    axios.post.mockResolvedValueOnce(ok({}))
    const auth = useAuthStore()
    auth.$patch({ user: { id: 7 }, isAuthenticated: true })
    await auth.logout()
    expect(stopAnalytics).toHaveBeenCalledTimes(1)
    expect(auth.user).toBeNull()
  })

  it('updateAnalyticsConsent manda consent + csrf, guarda lo que DEVUELVE el backend y sincroniza', async () => {
    axios.post.mockResolvedValueOnce({
      data: { status: 'success', message: 'ok', data: { analytics_consent: 1, analytics_consent_at: '2026-09-28 12:00:00' } },
    })
    const auth = useAuthStore()
    auth.$patch({ user: { id: 7, name: 'Ana', analytics_consent: null }, isAuthenticated: true, csrfToken: 'csrf' })
    await auth.updateAnalyticsConsent(true)
    const body = axios.post.mock.calls[0][1]
    expect(body).toMatchObject({ action: 'update_analytics_consent', consent: true, csrf_token: 'csrf' })
    expect(auth.user.analytics_consent).toBe(1)
    expect(auth.user.name).toBe('Ana')
    expect(syncAnalytics).toHaveBeenCalledWith(auth.user)
  })

  it('updateAnalyticsConsent sin sesión lanza y no llama al backend', async () => {
    const auth = useAuthStore()
    await expect(auth.updateAnalyticsConsent(true)).rejects.toThrow()
    expect(axios.post).not.toHaveBeenCalled()
  })
})
