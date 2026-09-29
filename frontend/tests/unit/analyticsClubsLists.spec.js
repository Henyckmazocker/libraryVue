// Los disparos del M4 del Plan «Catálogo de Eventos de Producto»: clubs y listas.
//
// Mismo patrón que `analyticsLibrary.spec.js` (M3): `track` se falsea (mock parcial de
// `@/analytics`) y se mira QUÉ se manda y CUÁNDO: el evento sale tras el OK del backend, el
// `*_failed` en el error, y nada antes. Cada disparo pasa además por el `checkEvent` REAL, y
// ninguna prop lleva texto: los nombres, descripciones y títulos de estos tests están sembrados
// para poder buscarlos.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'

vi.mock('@/analytics', async (importOriginal) => ({ ...(await importOriginal()), track: vi.fn() }))

import { track, checkEvent } from '@/analytics'
import { useAuthStore } from '@/store/auth'
import { useClubsStore } from '@/store/clubs'
import { useListsStore } from '@/store/lists'

// Lo que ningún evento puede llevar dentro.
const SECRETS = ['Club secreto', 'Lista secreta', 'Descripción íntima', 'Un título secreto', 'ana@example.com']
const [CLUB_NAME, LIST_NAME, DESCRIPTION, TITLE] = SECRETS

const ok = (data = {}) => ({ data: { status: 'success', data } })
const businessError = (httpCode = 409) => ({ data: { status: 'error', http_code: httpCode, message: 'Conflict' } })
const httpError = (status) => Object.assign(new Error(`HTTP ${status}`), {
  isAxiosError: true, request: {}, response: { status, data: { status: 'error', http_code: status } }
})

/** Las props de los disparos con ese nombre. */
const sent = (name) => track.mock.calls.filter(([n]) => n === name).map(([, p]) => p ?? {})
const names = () => track.mock.calls.map(([n]) => n)

let apiCall

beforeEach(() => {
  setActivePinia(createPinia())
  track.mockClear()
  apiCall = vi.fn()
  useAuthStore().authenticatedApiCall = apiCall
})

afterEach(() => {
  for (const [name, props = {}] of track.mock.calls) {
    expect(checkEvent(name, props), name).toBeNull()
    for (const value of Object.values(props)) {
      if (typeof value === 'string') expect(SECRETS.some((s) => value.includes(s)), name).toBe(false)
    }
  }
})

const pick = { entityType: 'book', entityId: 'b1', entityTitle: TITLE, entityCover: 'https://x/c.jpg' }

describe('clubs', () => {
  it('crear, invitar, salir y el ciclo del ítem y la votación salen tras el OK', async () => {
    const store = useClubsStore()
    apiCall.mockImplementation(async (action) => (action === 'close_club_vote'
      ? ok({ phase: 'closed', pickId: 3 })
      : ok({ clubId: 1 })))

    await store.createClub({ name: CLUB_NAME, description: DESCRIPTION })
    await store.inviteToClub(1, 42)
    await store.setPick(1, pick)
    await store.finishPick(1)
    await store.proposeItem(1, { ...pick, entityType: 'movie' })
    await store.openVote(1)
    await store.voteProposal(1, 5)
    await store.closeVote(1)
    await store.leaveClub(1)
    await flushPromises()

    expect(sent('club_created')).toEqual([{}])
    expect(sent('club_member_invited')).toEqual([{}])
    expect(sent('club_pick_set')).toEqual([{ media: 'book' }])
    expect(sent('club_pick_finished')).toEqual([{}])
    expect(sent('club_item_proposed')).toEqual([{ media: 'movie' }])
    expect(sent('club_vote_opened')).toEqual([{}])
    expect(sent('club_proposal_voted')).toEqual([{}])
    expect(sent('club_vote_closed')).toEqual([{ phase: 'closed' }])
    expect(sent('club_left')).toEqual([{}])
    expect(sent('club_action_failed')).toEqual([])
    // Las relecturas que siguen a cada escritura no son visitas.
    expect(sent('club_viewed')).toEqual([])
  })

  it('club_vote_closed: el desempate queda en voting y una fase rara como unknown', async () => {
    const store = useClubsStore()
    apiCall.mockImplementation(async (action) => (action === 'close_club_vote' ? ok({ phase: 'voting' }) : ok({})))
    await store.closeVote(1)
    apiCall.mockImplementation(async (action) => (action === 'close_club_vote' ? ok({ phase: 'otra' }) : ok({})))
    await store.closeVote(1)
    await flushPromises()
    expect(sent('club_vote_closed')).toEqual([{ phase: 'voting' }, { phase: 'unknown' }])
  })

  it('club_action_failed en los dos errores de _write, con su acción y código, y ningún éxito', async () => {
    const store = useClubsStore()
    apiCall.mockRejectedValueOnce(httpError(500))
    await store.createClub({ name: CLUB_NAME, description: DESCRIPTION })
    apiCall.mockResolvedValueOnce(businessError(400))
    await store.inviteToClub(1, 42)
    apiCall.mockResolvedValueOnce(businessError(403))
    await store.proposeItem(1, pick)
    apiCall.mockResolvedValueOnce(businessError(409))
    await store.closeVote(1)

    expect(sent('club_action_failed')).toEqual([
      { action: 'create_club', code: 500 },
      { action: 'invite_to_club', code: 400 },
      { action: 'propose_club_item', code: 403 },
      { action: 'close_club_vote', code: 409 },
    ])
    expect(names().filter((n) => n !== 'club_action_failed')).toEqual([])
  })

  it('club_viewed al abrir el club; club_view_failed con el código si no se puede', async () => {
    const store = useClubsStore()
    apiCall.mockResolvedValueOnce(ok({ club: { id: 1, name: CLUB_NAME }, members: [] }))
    await store.fetchClub(1)
    expect(sent('club_viewed')).toEqual([{}])

    apiCall.mockResolvedValueOnce(businessError(403))
    await store.fetchClub(2)
    apiCall.mockRejectedValueOnce(httpError(404))
    await store.fetchClub(3)
    expect(sent('club_view_failed')).toEqual([{ code: 403 }, { code: 404 }])
    expect(sent('club_viewed')).toEqual([{}])
  })
})

describe('listas', () => {
  it('crear lleva la visibilidad (privada por defecto), y nada del nombre', async () => {
    const store = useListsStore()
    apiCall.mockResolvedValue(ok({ listId: 7 }))
    await store.createList({ name: LIST_NAME, description: DESCRIPTION, visibility: 'collaborative' })
    await store.createList({ name: LIST_NAME, description: DESCRIPTION })
    await flushPromises()
    expect(sent('list_created')).toEqual([{ visibility: 'collaborative' }, { visibility: 'private' }])
  })

  it('list_updated cuenta los campos que cambian de verdad y manda la visibilidad con la que queda', async () => {
    const store = useListsStore()
    store.current = { id: 7, name: LIST_NAME, description: null, visibility: 'private' }
    apiCall.mockResolvedValue(ok({ id: 7, visibility: 'public' }))
    // El diálogo manda los tres campos siempre; aquí solo cambia la visibilidad.
    await store.updateList(7, { name: LIST_NAME, description: '', visibility: 'public' })
    await flushPromises()
    expect(sent('list_updated')).toEqual([{ fields: 1, visibility: 'public' }])
  })

  it('borrar, añadir y quitar ítems e invitar y quitar colaboradores salen tras el OK', async () => {
    const store = useListsStore()
    apiCall.mockResolvedValue(ok({ id: 3, entity_title: TITLE }))
    await store.addItem(7, { entityType: 'album', entityId: 'a1', entityTitle: TITLE, entityCover: null })
    await store.removeItem(7, 3)
    await store.inviteCollaborator(7, 42)
    await store.removeCollaborator(7, 42)
    await store.deleteList(7)

    expect(sent('list_item_added')).toEqual([{ media: 'album' }])
    expect(sent('list_item_removed')).toEqual([{}])
    expect(sent('list_collaborator_invited')).toEqual([{}])
    expect(sent('list_collaborator_removed')).toEqual([{}])
    expect(sent('list_deleted')).toEqual([{}])
    expect(sent('list_action_failed')).toEqual([])
  })

  it('list_action_failed en los dos errores de _write, con su acción y código, y ningún éxito', async () => {
    const store = useListsStore()
    apiCall.mockResolvedValueOnce(businessError(409))
    await store.addItem(7, { entityType: 'book', entityId: 'b1', entityTitle: TITLE })
    apiCall.mockResolvedValueOnce(businessError(400))
    await store.inviteCollaborator(7, 42)
    apiCall.mockRejectedValueOnce(httpError(500))
    await store.createList({ name: LIST_NAME, description: DESCRIPTION, visibility: 'public' })
    apiCall.mockRejectedValueOnce(new Error('sin red'))
    await store.updateList(7, { name: LIST_NAME })

    expect(sent('list_action_failed')).toEqual([
      { action: 'add_list_item', code: 409 },
      { action: 'invite_collaborator', code: 400 },
      { action: 'create_list', code: 500 },
      { action: 'update_list', code: 0 },
    ])
    expect(names().filter((n) => n !== 'list_action_failed')).toEqual([])
  })

  it('list_viewed al abrir la lista; list_view_failed con el código si no se puede', async () => {
    const store = useListsStore()
    apiCall.mockResolvedValueOnce(ok({ list: { id: 7, name: LIST_NAME }, items: [] }))
    await store.fetchList(7)
    apiCall.mockResolvedValueOnce(businessError(403))
    await store.fetchList(8)
    apiCall.mockRejectedValueOnce(httpError(404))
    await store.fetchList(9)
    expect(sent('list_viewed')).toEqual([{}])
    expect(sent('list_view_failed')).toEqual([{ code: 403 }, { code: 404 }])
  })
})
