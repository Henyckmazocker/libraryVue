import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useListsStore } from '@/store/lists'
import { useAuthStore } from '@/store/auth'

/**
 * El store de listas.
 *
 * Lo que se fija aquí es lo que no puede recalcularse en el cliente: **quién
 * puede editar lo dice el servidor**, y los errores se traducen por CÓDIGO y no
 * por el texto del backend, que responde en inglés como todo el repo.
 */
const respuesta = (data, status = 'success', httpCode = 200) => ({
  data: { status, data, message: 'backend message in english', http_code: httpCode }
})

describe('ListsStore', () => {
  let apiCall

  beforeEach(() => {
    setActivePinia(createPinia())
    apiCall = vi.fn()
    useAuthStore().authenticatedApiCall = apiCall
  })

  it('no recalcula los permisos: los toma tal cual del servidor', async () => {
    const store = useListsStore()
    apiCall.mockResolvedValue(respuesta({
      list: { id: 7, name: 'Ajena pero pública', can_edit: false, is_owner: false },
      items: []
    }))

    await store.fetchList(7)

    // La regla vive en `ListAccess`, en el backend, y esta es la doceava copia
    // que todo el plan existe para no escribir.
    expect(store.canEditCurrent).toBe(false)
    expect(store.isCurrentOwner).toBe(false)
  })

  it('traduce el 403 de una lista ajena sin leer el texto del backend', async () => {
    const store = useListsStore()
    apiCall.mockResolvedValue(respuesta(null, 'error', 403))

    const result = await store.fetchList(7)

    expect(result.success).toBe(false)
    expect(result.code).toBe(403)
    expect(store.error).toBe('No tienes permiso sobre esta lista')
  })

  it('traduce el 409 del ítem repetido, que es del dominio y no puede perderse', async () => {
    const store = useListsStore()
    apiCall.mockResolvedValue(respuesta(null, 'error', 409))

    const result = await store.addItem(7, { entityType: 'book', entityId: '978' })

    expect(result.code).toBe(409)
    expect(result.message).toBe('Ese ítem ya está en la lista')
  })

  it('conserva el código HTTP cuando el fallo lo lanza el cliente', async () => {
    const store = useListsStore()
    apiCall.mockRejectedValue({ response: { status: 404, data: { message: 'List not found' } } })

    const result = await store.fetchList(7)

    expect(result.code).toBe(404)
    expect(store.error).toBe('Esta lista ya no existe')
  })

  it('manda el medio de la FILA, no el del registry', async () => {
    const store = useListsStore()
    apiCall.mockResolvedValue(respuesta({ id: 1, entity_type: 'movie' }, 'success', 201))

    // Una serie se guarda con `AddMovieUseCase`, así que viaja como `movie`.
    await store.addItem(7, {
      entityType: 'movie',
      entityId: 'tt0903747',
      entityTitle: 'Breaking Bad',
      entityCover: null
    })

    expect(apiCall).toHaveBeenCalledWith('add_list_item', {
      listId: 7,
      entityType: 'movie',
      entityId: 'tt0903747',
      entityTitle: 'Breaking Bad',
      entityCover: null
    })
  })

  it('saca la lista borrada de la rejilla y cierra la que estaba abierta', async () => {
    const store = useListsStore()
    store.lists = [{ id: 7, name: 'Se va' }, { id: 8, name: 'Se queda' }]
    store.current = { id: 7, name: 'Se va' }
    store.currentItems = [{ id: 1 }]
    apiCall.mockResolvedValue(respuesta(null))

    await store.deleteList(7)

    expect(store.lists.map((l) => l.id)).toEqual([8])
    expect(store.current).toBeNull()
    expect(store.currentItems).toEqual([])
  })

  it('guarda las listas de OTRO aparte de las mías', async () => {
    const store = useListsStore()
    store.lists = [{ id: 1, name: 'Mía' }]
    apiCall.mockResolvedValue(respuesta({ lists: [{ id: 9, name: 'Suya', item_count: 3 }] }))

    await store.fetchUserLists('ana')

    // Si compartieran hueco, volver al propio perfil pisaría unas con otras.
    expect(store.userLists.map((l) => l.id)).toEqual([9])
    expect(store.lists.map((l) => l.id)).toEqual([1])
    expect(apiCall).toHaveBeenCalledWith('get_user_lists', { username: 'ana' })
  })

  it('un usuario que no existe es un 404, no una lista vacía', async () => {
    const store = useListsStore()
    apiCall.mockResolvedValue(respuesta(null, 'error', 404))

    const result = await store.fetchUserLists('nadie')

    expect(result.code).toBe(404)
    expect(store.userLists).toEqual([])
  })

  it('invitar NO añade al colaborador: solo crea la fila pendiente', async () => {
    const store = useListsStore()
    store.currentCollaborators = []
    apiCall.mockResolvedValue(respuesta({ recommendationId: 3 }, 'success', 201))

    const result = await store.inviteCollaborator(7, 2)

    expect(result.success).toBe(true)
    // El acceso llega cuando la otra persona acepta, no ahora.
    expect(store.currentCollaborators).toEqual([])
  })

  it('el 400 de invitar significa «no sois amigos», y solo ahí', async () => {
    const store = useListsStore()
    apiCall.mockResolvedValue(respuesta(null, 'error', 400))

    const invitacion = await store.inviteCollaborator(7, 2)
    expect(invitacion.message).toBe('Solo puedes invitar a tus amigos')

    // En otra escritura, un 400 no puede decir eso.
    const item = await store.addItem(7, { entityType: 'book', entityId: '978' })
    expect(item.message).not.toBe('Solo puedes invitar a tus amigos')
  })

  it('quitar a un colaborador lo saca de la lista abierta', async () => {
    const store = useListsStore()
    store.currentCollaborators = [{ user_id: 2 }, { user_id: 3 }]
    apiCall.mockResolvedValue(respuesta(null))

    await store.removeCollaborator(7, 2)

    expect(store.currentCollaborators.map((c) => c.user_id)).toEqual([3])
  })

  it('quita el ítem de la lista abierta sin recargarla entera', async () => {
    const store = useListsStore()
    store.currentItems = [{ id: 1 }, { id: 2 }]
    apiCall.mockResolvedValue(respuesta(null))

    await store.removeItem(7, 1)

    expect(store.currentItems.map((i) => i.id)).toEqual([2])
  })
})
