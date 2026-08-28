import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useInboxStore } from '@/store/inbox'
import { useAuthStore } from '@/store/auth'

/**
 * El contador de la campanita y su suscripción.
 *
 * Lo que se fija aquí es el **contrato sin polling**: la suscripción se engancha
 * una sola vez —si no, un header remontado sumaría un `afterEach` por montaje y
 * cada navegación pediría el contador N veces— y el contador se rinde sin sesión
 * en vez de provocar un 401 en cada cambio de ruta.
 *
 * La campanita en sí (icono siempre, contador solo si hay pendientes) se ve en
 * las capturas: `Header.vue` arrastra Google Sign-In y `initializeAuth` al
 * montarse, y montarlo aquí probaría el andamiaje, no la campanita.
 */
describe('El contador de la bandeja', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('se suscribe al router una sola vez aunque se pida varias', () => {
    const store = useInboxStore()
    const router = { afterEach: vi.fn() }

    store.subscribeToRouter(router)
    store.subscribeToRouter(router)
    store.subscribeToRouter(router)

    expect(router.afterEach).toHaveBeenCalledTimes(1)
  })

  it('el callback suscrito refresca el contador en cada navegación', () => {
    const store = useInboxStore()
    let navegar
    const router = { afterEach: (cb) => { navegar = cb } }
    store.refreshCount = vi.fn()

    store.subscribeToRouter(router)
    navegar()
    navegar()

    expect(store.refreshCount).toHaveBeenCalledTimes(2)
  })

  it('resolver una recomendación baja el contador sin volver a pedirlo', async () => {
    const store = useInboxStore()
    store.items = [{ id: 7, kind: 'recommendation' }]
    store.total = 1
    store.pendingCount = 1

    // Con Pinia activo, esta es la MISMA instancia que usa `_resolve` por
    // dentro: basta sustituirle el método para no salir a la red.
    const auth = useAuthStore()
    auth.authenticatedApiCall = vi.fn().mockResolvedValue({ data: { status: 'success' } })

    await store.dismiss({ id: 7 })

    expect(auth.authenticatedApiCall).toHaveBeenCalledWith('resolve_recommendation', {
      recommendationId: 7,
      // `resolution`, no `action`: esa clave la pisaría el enrutado del backend.
      resolution: 'dismissed'
    })
    expect(store.pendingCount).toBe(0)
    expect(store.items).toHaveLength(0)
  })

  it('sin sesión el contador se queda a cero y no pide nada', async () => {
    const store = useInboxStore()
    store.pendingCount = 5

    await store.refreshCount()

    // El store de auth real, recién creado, no está autenticado.
    expect(store.pendingCount).toBe(0)
  })
})
