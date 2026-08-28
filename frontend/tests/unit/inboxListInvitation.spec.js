import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useInboxStore } from '@/store/inbox'
import { useAuthStore } from '@/store/auth'

/**
 * La invitación a colaborar dentro del buzón compartido.
 *
 * Lo que se fija aquí es la costura del M4: la invitación viaja por la MISMA
 * tabla que las recomendaciones, con `entity_type = 'list'`, y lo único que la
 * distingue en el cliente es el `kind` que este store le pone. Si eso se
 * rompiera, la bandeja intentaría dar de alta una lista en la biblioteca.
 */
const respuesta = (data, status = 'success', httpCode = 200) => ({
  data: { status, data, message: 'backend message in english', http_code: httpCode }
})

describe('InboxStore — invitaciones a colaborar', () => {
  let apiCall

  beforeEach(() => {
    setActivePinia(createPinia())
    apiCall = vi.fn()
    useAuthStore().authenticatedApiCall = apiCall
  })

  it('tipa cada fila por su entity_type, no por su tabla', async () => {
    const store = useInboxStore()
    apiCall.mockResolvedValue(respuesta({
      recommendations: [
        { id: 1, entity_type: 'book', entity_id: '978' },
        { id: 2, entity_type: 'list', entity_id: '7' }
      ],
      total: 2
    }))

    await store.fetchInbox()

    expect(store.items.map((i) => i.kind)).toEqual(['recommendation', 'list_invitation'])
  })

  it('aceptar da de alta y saca la fila del buzón, bajando el contador', async () => {
    const store = useInboxStore()
    store.items = [{ id: 2, kind: 'list_invitation' }, { id: 5, kind: 'recommendation' }]
    store.total = 2
    store.pendingCount = 2
    apiCall.mockResolvedValue(respuesta({ listId: 7, name: 'A cuatro manos' }))

    const result = await store.acceptCollaboration({ id: 2 })

    expect(apiCall).toHaveBeenCalledWith('accept_collaboration', { recommendationId: 2 })
    expect(result).toEqual({ success: true, listId: 7 })
    expect(store.items.map((i) => i.id)).toEqual([5])
    expect(store.pendingCount).toBe(1)
  })

  it('un fallo al aceptar deja la invitación en el buzón', async () => {
    const store = useInboxStore()
    store.items = [{ id: 2, kind: 'list_invitation' }]
    store.pendingCount = 1
    apiCall.mockResolvedValue(respuesta(null, 'error', 403))

    const result = await store.acceptCollaboration({ id: 2 })

    // Perderla sin haber entrado sería irrecuperable: el UNIQUE impide
    // volver a mandarla.
    expect(result.success).toBe(false)
    expect(store.items).toHaveLength(1)
    expect(store.pendingCount).toBe(1)
  })

  it('rechazar una invitación usa el mismo resolve que una recomendación', async () => {
    const store = useInboxStore()
    store.items = [{ id: 2, kind: 'list_invitation' }]
    store.total = 1
    store.pendingCount = 1
    apiCall.mockResolvedValue(respuesta(null))

    await store.dismiss({ id: 2 })

    expect(apiCall).toHaveBeenCalledWith('resolve_recommendation', {
      recommendationId: 2,
      resolution: 'dismissed'
    })
    expect(store.items).toEqual([])
  })
})
