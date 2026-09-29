// Los disparos del M5 del Plan «Catálogo de Eventos de Producto»: social, bandeja y perfil.
//
// Mismo patrón que `analyticsClubsLists.spec.js` (M4): `track` se falsea (mock parcial de
// `@/analytics`) y se mira QUÉ se manda y CUÁNDO —tras el OK del backend, y nada si falla—. Cada
// disparo pasa además por el `checkEvent` REAL, y ninguna prop lleva texto: el nombre de usuario,
// el comentario y el título de estos tests están sembrados para poder buscarlos.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'

vi.mock('@/analytics', async (importOriginal) => ({ ...(await importOriginal()), track: vi.fn() }))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { username: 'usuario_secreto' } }),
  RouterLink: { name: 'RouterLink', props: ['to'], template: '<a><slot /></a>' }
}))

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: vi.fn() })
}))

import { track, checkEvent } from '@/analytics'
import { useAuthStore } from '@/store/auth'
import { useSocialStore } from '@/store/social'
import { useInboxStore } from '@/store/inbox'
import { useBooksStore } from '@/store/books'
import { getMediaConfig } from '@/config/mediaRegistry'
import PublicProfileView from '@/views/PublicProfileView.vue'
import { mountComponent } from './helpers/mount'

// Lo que ningún evento puede llevar dentro.
const SECRETS = ['usuario_secreto', 'Un comentario íntimo', 'Un título secreto', 'ana@example.com']
const [USERNAME, COMMENT, TITLE] = SECRETS

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
  vi.restoreAllMocks()
})

describe('social', () => {
  it('pedir, aceptar, rechazar y eliminar una amistad salen tras el OK; source lo pone quien llama', async () => {
    const store = useSocialStore()
    apiCall.mockImplementation(async (action) => (action === 'get_friends' ? ok([]) : ok({})))

    await store.sendFriendRequest(2, 'user_search')
    await store.sendFriendRequest(3, 'public_profile')
    await store.sendFriendRequest(4)
    await store.sendFriendRequest(5, USERNAME)
    await store.acceptFriendRequest(7)
    await store.rejectFriendRequest(8)
    await store.removeFriend(2)

    expect(sent('friend_request_sent')).toEqual([
      { source: 'user_search' }, { source: 'public_profile' }, { source: 'unknown' }, { source: 'unknown' }
    ])
    expect(sent('friend_request_accepted')).toEqual([{}])
    expect(sent('friend_request_rejected')).toEqual([{}])
    expect(sent('friend_removed')).toEqual([{}])
  })

  it('un fallo del backend no cuenta ninguna', async () => {
    const store = useSocialStore()
    apiCall.mockResolvedValue(businessError(400))

    await expect(store.sendFriendRequest(2, 'user_search')).rejects.toThrow()
    await expect(store.acceptFriendRequest(7)).rejects.toThrow()
    await expect(store.rejectFriendRequest(8)).rejects.toThrow()
    await expect(store.removeFriend(2)).rejects.toThrow()
    apiCall.mockRejectedValue(httpError(500))
    await expect(store.sendFriendRequest(2, 'user_search')).rejects.toThrow()

    expect(names()).toEqual([])
  })

  it('feed_loaded numera las páginas desde 1 y cuenta los eventos; un reset vuelve a la 1', async () => {
    const store = useSocialStore()
    const events = (n) => Array.from({ length: n }, (_, i) => ({ id: i, title: TITLE }))
    apiCall.mockResolvedValueOnce(ok({ events: events(20), hasMore: true }))
    await store.loadFeed(true)
    apiCall.mockResolvedValueOnce(ok({ events: events(5), hasMore: false }))
    await store.loadFeed()
    apiCall.mockResolvedValueOnce(ok({ events: [], hasMore: false }))
    await store.loadFeed(true)
    apiCall.mockRejectedValueOnce(httpError(500))
    await store.loadFeed(true)

    expect(sent('feed_loaded')).toEqual([
      { page: 1, n_events: 20 }, { page: 2, n_events: 5 }, { page: 1, n_events: 0 }
    ])
  })

  it('privacy_settings_updated cuenta los interruptores cambiados, no cuáles', async () => {
    const store = useSocialStore()
    store.privacySettings = { show_additions: true, show_notes: false, show_journal: false }
    const nuevos = { show_additions: false, show_notes: true, show_journal: false }
    apiCall.mockResolvedValueOnce(ok(nuevos))
    await store.updatePrivacySettings(nuevos)
    apiCall.mockResolvedValueOnce(businessError(500))
    await expect(store.updatePrivacySettings({ show_additions: true })).rejects.toThrow()

    expect(sent('privacy_settings_updated')).toEqual([{ fields: 2 }])
  })
})

describe('bandeja', () => {
  it('inbox_viewed con el filtro y cuántas filas; nada si falla', async () => {
    const store = useInboxStore()
    apiCall.mockResolvedValueOnce(ok({
      recommendations: [{ id: 1, entity_type: 'book', entity_title: TITLE }, { id: 2, entity_type: 'list' }],
      total: 2
    }))
    await store.fetchInbox()
    apiCall.mockResolvedValueOnce(ok({ recommendations: [], total: 0 }))
    await store.fetchInbox('dismissed')
    apiCall.mockResolvedValueOnce(businessError(500))
    await store.fetchInbox('added')

    expect(sent('inbox_viewed')).toEqual([
      { filter: 'pending', n_items: 2 }, { filter: 'dismissed', n_items: 0 }
    ])
  })

  it('recommendation_sent con el medio y si lleva comentario, nunca el comentario ni el título', async () => {
    const store = useInboxStore()
    const base = { recipientId: 3, entityType: 'movie', entityId: '9', entityTitle: TITLE, entityCover: null }
    apiCall.mockResolvedValue(ok({ recommendationId: 11 }))
    await store.sendRecommendation({ ...base, comment: COMMENT })
    await store.sendRecommendation({ ...base, entityType: 'book', comment: '   ' })
    apiCall.mockResolvedValueOnce(businessError(409))
    await store.sendRecommendation({ ...base, comment: COMMENT })
    apiCall.mockRejectedValueOnce(httpError(500))
    await store.sendRecommendation({ ...base, comment: COMMENT })

    expect(sent('recommendation_sent')).toEqual([
      { media: 'movie', has_comment: true }, { media: 'book', has_comment: false }
    ])
  })

  it('descartar cuenta recommendation_resolved; descartar una invitación a una lista, no', async () => {
    const store = useInboxStore()
    apiCall.mockResolvedValue(ok({}))
    await store.dismiss({ id: 1, entity_type: 'game', entity_title: TITLE })
    await store.dismiss({ id: 2, entity_type: 'list' })
    apiCall.mockResolvedValueOnce(businessError(500))
    await store.dismiss({ id: 3, entity_type: 'book' })

    expect(sent('recommendation_resolved')).toEqual([{ resolution: 'dismissed', media: 'game' }])
  })

  it('añadir a la biblioteca da de alta con source recommendation y resuelve como added', async () => {
    const store = useInboxStore()
    vi.spyOn(getMediaConfig('book').detail, 'enrich').mockResolvedValue({ item: { id: 'b1', title: TITLE } })
    const add = vi.fn(async () => ({ success: true }))
    useBooksStore().add = add
    apiCall.mockResolvedValue(ok({}))

    const result = await store.addToLibrary({ id: 1, entity_type: 'book', entity_id: 'b1' })

    expect(result.success).toBe(true)
    expect(add).toHaveBeenCalledWith(expect.anything(), expect.any(Array), 'recommendation')
    expect(sent('recommendation_resolved')).toEqual([{ resolution: 'added', media: 'book' }])
  })

  it('si el alta falla no se resuelve ni se cuenta', async () => {
    const store = useInboxStore()
    vi.spyOn(getMediaConfig('book').detail, 'enrich').mockResolvedValue({ item: { id: 'b1', title: TITLE } })
    useBooksStore().add = vi.fn(async () => ({ success: false, message: 'x' }))

    await store.addToLibrary({ id: 1, entity_type: 'book', entity_id: 'b1' })

    expect(sent('recommendation_resolved')).toEqual([])
  })

  it('list_collaboration_accepted tras el OK, y nada si falla', async () => {
    const store = useInboxStore()
    apiCall.mockResolvedValueOnce(ok({ listId: 7 }))
    await store.acceptCollaboration({ id: 2 })
    apiCall.mockResolvedValueOnce(businessError(403))
    await store.acceptCollaboration({ id: 3 })

    expect(sent('list_collaboration_accepted')).toEqual([{}])
  })
})

describe('perfil público', () => {
  const montar = (profileResponse) => {
    apiCall.mockImplementation((action) => {
      if (action === 'get_public_profile') return profileResponse()
      if (action === 'get_user_lists') return Promise.resolve(ok({ lists: [] }))
      if (action === 'get_user_journal') return Promise.resolve(ok({ entries: [], total: 0, hasMore: false }))
      return Promise.resolve(ok(null))
    })
    return mountComponent(PublicProfileView)
  }
  const perfil = (friendStatus) => ({
    id: 2, username: USERNAME, name: USERNAME, picture: null, friend_status: friendStatus, friendship_id: 7
  })

  it('public_profile_viewed dice solo si es amigo', async () => {
    montar(() => Promise.resolve(ok(perfil('friends'))))
    await flushPromises()
    montar(() => Promise.resolve(ok(perfil('pending_received'))))
    await flushPromises()

    expect(sent('public_profile_viewed')).toEqual([{ is_friend: true }, { is_friend: false }])
    expect(sent('public_profile_view_failed')).toEqual([])
  })

  it('public_profile_view_failed con el código, del sobre o del HTTP', async () => {
    montar(() => Promise.resolve(businessError(404)))
    await flushPromises()
    montar(() => Promise.reject(httpError(500)))
    await flushPromises()

    expect(sent('public_profile_view_failed')).toEqual([{ code: 404 }, { code: 500 }])
    expect(sent('public_profile_viewed')).toEqual([])
  })

  it('pedir la amistad desde el perfil manda source public_profile', async () => {
    const wrapper = montar(() => Promise.resolve(ok(perfil('none'))))
    await flushPromises()
    await wrapper.find('.public-profile-view__actions button').trigger('click')
    await flushPromises()

    expect(sent('friend_request_sent')).toEqual([{ source: 'public_profile' }])
  })
})
