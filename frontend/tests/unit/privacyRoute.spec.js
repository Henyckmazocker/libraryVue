// La ruta /privacy (Plan «Consentimiento de Analítica», M3) se abre SIN sesión: el modal de
// consentimiento enlaza aquí y hay que poder leerla antes de decidir.
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

// El resto del módulo, el real: `track` y compañía validan y, sin clave, no mandan nada.
vi.mock('@/analytics', async (importOriginal) => ({
  ...(await importOriginal()),
  isAnalyticsAvailable: () => false,
  syncAnalytics: vi.fn(),
  stopAnalytics: vi.fn(() => Promise.resolve()),
}))
// Sin backend: si el guard llegara a preguntar por la sesión, la respuesta es «no hay».
const axios = vi.hoisted(() => ({ post: vi.fn(() => Promise.reject(Object.assign(new Error('401'), { response: { status: 401, data: {} } }))) }))
vi.mock('axios', () => ({ default: axios }))

import router from '@/router'
import { useAuthStore } from '@/store/auth'

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('/privacy', () => {
  it('existe con nombre y sin requiresAuth', () => {
    const ruta = router.resolve('/privacy')
    expect(ruta.name).toBe('Privacy')
    expect(ruta.meta.requiresAuth).toBeFalsy()
  })

  it('se abre sin sesión (el guard no la manda a Home)', async () => {
    const auth = useAuthStore()
    expect(auth.isLoggedIn).toBeFalsy()
    await router.push('/privacy')
    expect(router.currentRoute.value.name).toBe('Privacy')
    // Ni siquiera pregunta por la sesión: el guard solo mira las rutas con requiresAuth.
    expect(axios.post).not.toHaveBeenCalled()
  })
})
