// Los disparos del M3 del Plan «Catálogo de Eventos de Producto»: biblioteca, búsqueda,
// importación, diario y lectura.
//
// `track` se falsea (mock parcial de `@/analytics`, como en los specs del M2) y se mira QUÉ se
// manda y CUÁNDO: el evento sale tras el OK del backend, el `*_failed` en el error, y nada antes.
// Cada disparo registrado se pasa además por el `checkEvent` REAL: una prop mal escrita o un valor
// fuera de su enum no se enviaría en la app, así que aquí falla. Y ninguna prop lleva texto: los
// títulos, la consulta y la nota de estos tests están sembrados para poder buscarlos.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'
import { markRaw } from 'vue'
import { mountComponent } from './helpers/mount'

vi.mock('@/analytics', async (importOriginal) => ({ ...(await importOriginal()), track: vi.fn() }))
vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }) }))

const fileApiCall = vi.hoisted(() => vi.fn())
vi.mock('@/composables/useAuth', () => ({ useAuth: () => ({ authenticatedApiCall: fileApiCall }) }))
const processFile = vi.hoisted(() => vi.fn())
vi.mock('@/services/FileProcessorService', () => ({ FileProcessorService: { processFile } }))

import { track, checkEvent, statusSlug, failureCode } from '@/analytics'
import { useAuthStore } from '@/store/auth'
import { useVideosStore } from '@/store/videos'
import { useBooksStore } from '@/store/books'
import { useJournalStore } from '@/store/journal'
import { useSessionsStore } from '@/store/sessions'
import { useSocialStore } from '@/store/social'
import { useMediaNotes } from '@/composables/useMediaNotes'
import { useMovies } from '@/composables/useMovies'
import { useFileImport } from '@/composables/useFileImport'
import GenericSearch from '@/components/shared/GenericSearch.vue'
import SearchView from '@/views/SearchView.vue'

// Lo que ningún evento puede llevar dentro.
const SECRET_TITLE = 'Un título secreto'
const SECRET_QUERY = 'consulta privada'
const SECRET_NOTE = 'Nota íntima de ana@example.com'

const ok = (data = {}) => ({ data: { status: 'success', data } })
const businessError = (httpCode = 422) => ({ data: { status: 'error', http_code: httpCode, message: 'Validation failed' } })
const httpError = (status) => Object.assign(new Error(`HTTP ${status}`), {
  isAxiosError: true, request: {}, response: { status, data: { status: 'error', http_code: status } }
})

/** Las props de los disparos con ese nombre. */
const sent = (name) => track.mock.calls.filter(([n]) => n === name).map(([, p]) => p ?? {})

let apiCall

beforeEach(() => {
  setActivePinia(createPinia())
  track.mockClear()
  apiCall = vi.fn()
  useAuthStore().authenticatedApiCall = apiCall
  useAuthStore().apiCall = apiCall
})

afterEach(() => {
  // Todo lo disparado casa con el catálogo y no lleva ninguna cadena libre.
  for (const [name, props = {}] of track.mock.calls) {
    expect(checkEvent(name, props), name).toBeNull()
    for (const value of Object.values(props)) {
      if (typeof value === 'string') {
        expect([SECRET_TITLE, SECRET_QUERY, SECRET_NOTE].some((s) => value.includes(s)), name).toBe(false)
      }
    }
  }
})

describe('ayudas del M3', () => {
  it('statusSlug normaliza al slug del catálogo y lo desconocido sale como unknown', () => {
    expect(statusSlug('to read')).toBe('to-read')
    expect(statusSlug('Want_To_Watch')).toBe('want-to-watch')
    expect(statusSlug({ id: 3, name: 'watched' })).toBe('watched')
    expect(statusSlug('un estado nuevo')).toBe('unknown')
    expect(statusSlug(null)).toBe('unknown')
  })

  it('failureCode lee http_code, el status HTTP o 0, nunca el mensaje', () => {
    expect(failureCode(httpError(404))).toBe(404)
    expect(failureCode(businessError(409).data)).toBe(409)
    expect(failureCode(new Error('boom'))).toBe(0)
    expect(failureCode(null)).toBe(0)
  })
})

describe('createMediaStore', () => {
  it('library_loaded tras cargar, con el número de ítems', async () => {
    apiCall.mockResolvedValue(ok([{ id: 1, youtube_id: 'a', title: SECRET_TITLE }, { id: 2, youtube_id: 'b' }]))
    await useVideosStore().fetch()
    expect(sent('library_loaded')).toEqual([{ media: 'video', n_items: 2 }])
  })

  it('library_item_added tras el OK con su source; sin source, unknown', async () => {
    const store = useVideosStore()
    apiCall.mockResolvedValue(ok({ id: 9, youtube_id: 'x', title: SECRET_TITLE }))
    await store.add({ youtube_id: 'x', title: SECRET_TITLE }, [], 'detail')
    await store.add({ youtube_id: 'y', title: SECRET_TITLE })
    await store.add({ youtube_id: 'z', title: SECRET_TITLE }, [], 'inventado')
    expect(sent('library_item_added')).toEqual([
      { media: 'video', source: 'detail' },
      { media: 'video', source: 'unknown' },
      { media: 'video', source: 'unknown' },
    ])
    expect(sent('library_item_add_failed')).toEqual([])
  })

  it('library_item_add_failed en el error (HTTP y de negocio), y ningún added', async () => {
    const store = useVideosStore()
    apiCall.mockRejectedValueOnce(httpError(500))
    await store.add({ youtube_id: 'x', title: SECRET_TITLE }, [], 'detail')
    apiCall.mockResolvedValueOnce(businessError(409))
    await store.add({ youtube_id: 'x', title: SECRET_TITLE }, [], 'detail')
    expect(sent('library_item_add_failed')).toEqual([{ media: 'video', code: 500 }, { media: 'video', code: 409 }])
    expect(sent('library_item_added')).toEqual([])
  })

  it('removed, rated (en medias estrellas), edited y tags solo tras el OK', async () => {
    const store = useVideosStore()
    apiCall.mockResolvedValue(ok({ id: 5, name: 'etiqueta' }))
    await store.remove(1)
    await store.updateRating(1, 3.5)
    await store.edit(1, { personalNotes: SECRET_NOTE, rating: 4 })
    await store.createTag('etiqueta')
    await store.updateTags(1, [1, 2, 3])
    expect(sent('library_item_removed')).toEqual([{ media: 'video' }])
    expect(sent('item_rated')).toEqual([{ media: 'video', rating: 7 }])
    expect(sent('item_edited')).toEqual([{ media: 'video', fields: 2 }])
    expect(sent('tag_created')).toEqual([{ media: 'video' }])
    expect(sent('item_tags_updated')).toEqual([{ media: 'video', n_tags: 3 }])

    track.mockClear()
    apiCall.mockRejectedValue(httpError(500))
    await store.remove(1)
    await store.updateRating(1, 2)
    await store.createTag('otra')
    await store.updateTags(1, [1])
    expect(track).not.toHaveBeenCalled()
  })

  it('item_status_changed manda el estado NUEVO como slug, aunque el backend lo escriba con espacio', async () => {
    const store = useBooksStore()
    store.books = [{ isbn: '111', title: SECRET_TITLE, userStatuses: ['owned'] }]
    apiCall.mockResolvedValue(ok())
    await store.updateStatuses('111', ['owned', 'to read'])
    await store.updateStatuses('111', ['to read'])
    await store.updateStatuses('111', [])
    expect(sent('item_status_changed')).toEqual([
      { media: 'book', status: 'to-read' },
      { media: 'book', status: 'to-read' },
      { media: 'book', status: 'none' },
    ])

    track.mockClear()
    apiCall.mockResolvedValue(businessError())
    await store.updateStatuses('111', ['read'])
    expect(track).not.toHaveBeenCalled()
  })

  it('search guarda el stale de la respuesta anidada sin cambiar lo que devuelve', async () => {
    const store = useVideosStore()
    apiCall.mockResolvedValue(ok({ videos: [{ id: 'a', title: SECRET_TITLE }], stale: true }))
    const resultado = await store.search(SECRET_QUERY)
    expect(Array.isArray(resultado)).toBe(true)
    expect(store.searchStale).toBe(true)

    apiCall.mockResolvedValue(ok([{ id: 'b' }]))
    await store.search(SECRET_QUERY)
    expect(store.searchStale).toBe(false)
  })
})

describe('temporadas y notas', () => {
  it('series_season_tracked solo con el OK', async () => {
    const movies = useMovies()
    apiCall.mockResolvedValueOnce(ok())
    await movies.trackSeriesSeason('tt1', 2, { notes: SECRET_NOTE })
    apiCall.mockResolvedValueOnce(businessError())
    await movies.trackSeriesSeason('tt1', 3)
    apiCall.mockRejectedValueOnce(httpError(500))
    await movies.trackSeriesSeason('tt1', 4)
    expect(sent('series_season_tracked')).toEqual([{ season: 2 }])
  })

  it('note_added / updated / deleted tras el OK, sin el texto; nada si falla', async () => {
    const notes = useMediaNotes('movie')
    apiCall.mockResolvedValue(ok([]))
    await notes.addNote('tt1', SECRET_NOTE, 'review', false)
    await notes.updateNote(7, 'tt1', SECRET_NOTE, 'quote', true)
    await notes.deleteNote(7, 'tt1')
    expect(sent('note_added')).toEqual([{ media: 'movie', note_type: 'review', is_private: false }])
    expect(sent('note_updated')).toEqual([{ media: 'movie', note_type: 'quote' }])
    expect(sent('note_deleted')).toEqual([{ media: 'movie' }])

    track.mockClear()
    apiCall.mockResolvedValue(businessError())
    await notes.addNote('tt1', SECRET_NOTE, 'review', false)
    apiCall.mockRejectedValue(httpError(500))
    await notes.deleteNote(7, 'tt1')
    expect(track).not.toHaveBeenCalled()
  })
})

describe('sesiones de lectura', () => {
  it('cada paso sale tras el OK; completed lleva motivo y páginas', async () => {
    const store = useSessionsStore()
    apiCall.mockResolvedValue(ok({ id: 1, start_page: 10, status: 'active' }))
    await store.createSession('111', 10)
    await store.pauseSession('111')
    await store.resumeSession('111')
    await store.completeSession('111', 42, 'completed')
    expect(sent('reading_session_started')).toHaveLength(1)
    expect(sent('reading_session_paused')).toHaveLength(1)
    expect(sent('reading_session_resumed')).toHaveLength(1)
    expect(sent('reading_session_completed')).toEqual([{ reason: 'completed', pages: 32 }])

    await store.createSession('111', 10)
    await store.abandonSession('111')
    await store.deleteSession('111', 1)
    expect(sent('reading_session_abandoned')).toHaveLength(1)
    expect(sent('reading_session_deleted')).toHaveLength(1)
  })

  it('un fallo del backend no dispara nada', async () => {
    const store = useSessionsStore()
    apiCall.mockRejectedValue(httpError(500))
    await store.createSession('111', 1)
    apiCall.mockResolvedValue(businessError())
    await store.deleteSession('111', 1)
    expect(track).not.toHaveBeenCalled()
  })
})

describe('diario', () => {
  const entries = ok({ entries: [{ id: 5, media: 'game', entity_title: SECRET_TITLE, entry_date: '2026-09-01' }], total: 1, hasMore: false })

  it('journal_entry_saved / deleted tras el OK, con el medio de la entrada cargada', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(entries)
    await store.fetch()
    await store.add({ media: 'movie', entityId: 'tt1', entryDate: '2026-09-02', rating: 4 })
    await store.update(5, { entryDate: '2026-09-03', rating: null })
    await store.remove(5)
    await store.remove(999)
    expect(sent('journal_entry_saved')).toEqual([
      { op: 'add', media: 'movie', has_rating: true },
      { op: 'update', media: 'game', has_rating: false },
    ])
    expect(sent('journal_entry_deleted')).toEqual([{ media: 'game' }, { media: 'unknown' }])
    expect(sent('journal_save_failed')).toEqual([])
  })

  it('journal_save_failed en los dos errores, sin el saved', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValueOnce(businessError(409))
    await store.add({ media: 'movie', entityId: 'tt1', entryDate: '2026-09-02', rating: null })
    apiCall.mockRejectedValueOnce(httpError(500))
    await store.remove(5)
    expect(sent('journal_save_failed')).toEqual([{ op: 'add', code: 409 }, { op: 'delete', code: 500 }])
    expect(sent('journal_entry_saved')).toEqual([])
    expect(sent('journal_entry_deleted')).toEqual([])
  })

  it('journal_filter_changed y journal_calendar_viewed', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(ok({ entries: [], total: 0, hasMore: false, days: {}, years: [] }))
    await store.setMedia('album')
    await store.setMedia(null)
    await store.fetchCalendar(2026)
    await store.fetchMonth(2026, 9)
    expect(sent('journal_filter_changed')).toEqual([{ media: 'album' }, { media: 'all' }])
    expect(sent('journal_calendar_viewed')).toEqual([{ view: 'year' }, { view: 'month' }])

    track.mockClear()
    apiCall.mockRejectedValue(httpError(500))
    await store.fetchCalendar(2025)
    expect(sent('journal_calendar_viewed')).toEqual([])
  })
})

describe('búsqueda de usuarios', () => {
  it('user_searched lleva la longitud del término, no el término', async () => {
    apiCall.mockResolvedValueOnce(ok([{ id: 1, username: 'ana' }]))
    await useSocialStore().searchUsers(SECRET_QUERY)
    apiCall.mockRejectedValueOnce(httpError(500))
    await useSocialStore().searchUsers(SECRET_QUERY)
    expect(sent('user_searched')).toEqual([{ query_length: SECRET_QUERY.length, results: 1 }])
  })
})

describe('GenericSearch', () => {
  const Tarjeta = markRaw({ props: ['item'], template: '<div />' })
  const config = (extra = {}) => ({
    title: 'Buscador',
    media: 'book',
    inputs: [{ type: 'name', placeholder: 'Busca' }],
    carouselItemComponent: Tarjeta,
    itemProp: 'item',
    searchHandler: vi.fn().mockResolvedValue([]),
    transformResult: (r) => r,
    navigateToDetail: vi.fn(),
    getResultKey: (r) => r.id,
    fetchAllowedStatuses: vi.fn().mockResolvedValue([]),
    ...extra,
  })

  async function buscar (cfg, texto = SECRET_QUERY) {
    const w = mountComponent(GenericSearch, { props: { config: cfg } })
    await flushPromises()
    await w.find('.search-input').setValue(texto)
    await w.find('.search-button').trigger('click')
    await flushPromises()
    return w
  }

  it('search con results 0 cuando no hay nada, y la longitud en vez de la consulta', async () => {
    await buscar(config())
    expect(sent('search')).toEqual([
      { media: 'book', search_type: 'name', query_length: SECRET_QUERY.length, results: 0, stale: false },
    ])
  })

  it('search lleva el stale del sobre y el número de resultados', async () => {
    await buscar(config({
      media: 'game',
      searchHandler: vi.fn().mockResolvedValue({ results: [{ id: 1, name: SECRET_TITLE }], stale: true }),
    }))
    expect(sent('search')).toEqual([
      { media: 'game', search_type: 'name', query_length: SECRET_QUERY.length, results: 1, stale: true },
    ])
  })

  it('search_failed en el error, sin search', async () => {
    await buscar(config({ searchHandler: vi.fn().mockRejectedValue(new Error('caído')) }))
    expect(sent('search_failed')).toEqual([{ media: 'book' }])
    expect(sent('search')).toEqual([])
  })

  it('search_empty_submitted y search_direct_navigated', async () => {
    await buscar(config(), '   ')
    await buscar(config({ detectSearchType: () => ({ type: 'direct', isDirect: true }) }), '9788445071410')
    expect(sent('search_empty_submitted')).toEqual([{ media: 'book' }])
    expect(sent('search_direct_navigated')).toEqual([{ media: 'book', kind: 'isbn' }])
  })

  it('sin medio en la config no se manda nada', async () => {
    await buscar(config({ media: undefined }))
    expect(track).not.toHaveBeenCalled()
  })
})

describe('SearchView', () => {
  it('catalog_searched una vez, al volver las dos tandas, con fallidos y rancios como número', async () => {
    vi.useFakeTimers()
    try {
      apiCall.mockImplementation((action) => Promise.resolve(action === 'search_catalog_remote'
        ? ok({ results: [], failed: ['book'], stale: { game: true, video: false } })
        : ok({ results: [] })))
      const w = mountComponent(SearchView)
      await w.find('.search-view__input').setValue(SECRET_QUERY)
      await vi.runAllTimersAsync()
      await flushPromises()
      expect(sent('catalog_searched')).toEqual([
        { query_length: SECRET_QUERY.length, results: 0, failed_media: 1, stale_media: 1 },
      ])
    } finally {
      vi.useRealTimers()
    }
  })
})

describe('importación', () => {
  const file = (name) => ({ name, size: 10 })

  it('started y completed con los recuentos; ni el nombre del fichero', async () => {
    const imp = useFileImport()
    imp.setService('goodreads')
    imp.setFile(file(`${SECRET_TITLE}.csv`))
    processFile.mockResolvedValue([{ title: SECRET_TITLE }])
    fileApiCall.mockResolvedValue(ok({ successful_items: 3, failed_items: 1 }))
    await imp.startImport()
    expect(sent('import_started')).toEqual([{ service: 'goodreads' }])
    expect(sent('import_completed')).toEqual([{ service: 'goodreads', ok_items: 3, failed_items: 1 }])
    expect(sent('import_failed')).toEqual([])
  })

  it('import_failed en el error, sin completed', async () => {
    const imp = useFileImport()
    imp.setService('letterboxd')
    imp.setFile(file('a.csv'))
    processFile.mockResolvedValue([{ title: SECRET_TITLE }])
    fileApiCall.mockRejectedValue(httpError(500))
    await imp.startImport()
    expect(sent('import_failed')).toEqual([{ service: 'letterboxd' }])
    expect(sent('import_completed')).toEqual([])
  })

  it('import_cancelled al cancelar una en curso', async () => {
    const imp = useFileImport()
    imp.setService('serialized')
    imp.setFile(file('a.json'))
    processFile.mockReturnValue(new Promise(() => {}))
    imp.startImport()
    imp.cancelImport()
    expect(sent('import_cancelled')).toEqual([{ service: 'serialized' }])
  })

  it('import_file_rejected con un fichero de otro tipo', () => {
    const imp = useFileImport()
    imp.setService('palomitacas')
    imp.setFile(file(`${SECRET_TITLE}.csv`))
    expect(sent('import_file_rejected')).toEqual([{ service: 'palomitacas' }])
  })
})
