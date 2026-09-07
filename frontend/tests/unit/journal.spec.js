import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { RouterLinkStub } from '@vue/test-utils'
import { mountComponent } from './helpers/mount'
import { useJournalStore } from '@/store/journal'
import { useAuthStore } from '@/store/auth'
import JournalEntryRow from '@/components/Journal/JournalEntryRow.vue'
import JournalEntryModal from '@/components/Journal/JournalEntryModal.vue'
import { mediaKeys } from '@/config/mediaRegistry'

const respuesta = (data, status = 'success', httpCode = 200) => ({
  data: { status, data, message: 'backend message in english', http_code: httpCode }
})

const entrada = (extra = {}) => ({
  id: 1,
  media: 'movie',
  entity_id: 'tt0133093',
  entity_title: 'The Matrix',
  entity_cover: null,
  entry_date: '2026-09-04',
  rating: null,
  source: 'manual',
  is_repeat: false,
  ...extra
})

describe('JournalStore — agrupado y paginación', () => {
  let apiCall

  beforeEach(() => {
    setActivePinia(createPinia())
    apiCall = vi.fn()
    useAuthStore().authenticatedApiCall = apiCall
  })

  it('agrupa por día conservando el orden que trajo el backend', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({
      entries: [
        entrada({ id: 3, entry_date: '2026-09-04' }),
        entrada({ id: 2, entry_date: '2026-09-04' }),
        entrada({ id: 1, entry_date: '2026-08-27' })
      ],
      total: 3,
      hasMore: false
    }))

    await store.fetch()

    expect(store.byDay).toHaveLength(2)
    expect(store.byDay[0].date).toBe('2026-09-04')
    expect(store.byDay[0].entries.map((e) => e.id)).toEqual([3, 2])
    expect(store.byDay[1].entries.map((e) => e.id)).toEqual([1])
  })

  it('no agrupa dos días distintos aunque vuelva a aparecer uno anterior', async () => {
    // El agrupado recorre en orden y abre un grupo por cambio de fecha, no
    // acumula en un mapa: si el backend devolviera desordenado, se verían dos
    // encabezados del mismo día en vez de mezclarlos en silencio.
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({
      entries: [
        entrada({ id: 3, entry_date: '2026-09-04' }),
        entrada({ id: 2, entry_date: '2026-08-27' }),
        entrada({ id: 1, entry_date: '2026-09-04' })
      ],
      total: 3,
      hasMore: false
    }))

    await store.fetch()

    expect(store.byDay.map((d) => d.date)).toEqual(['2026-09-04', '2026-08-27', '2026-09-04'])
  })

  it('`loadMore` acumula y pide el offset correcto', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValueOnce(respuesta({ entries: [entrada({ id: 1 })], total: 2, hasMore: true }))
    await store.fetch()

    apiCall.mockResolvedValueOnce(respuesta({ entries: [entrada({ id: 2 })], total: 2, hasMore: false }))
    await store.loadMore()

    expect(apiCall).toHaveBeenLastCalledWith('get_journal', { limit: 30, offset: 1 })
    expect(store.entries.map((e) => e.id)).toEqual([1, 2])
    expect(store.hasMore).toBe(false)
  })

  it('el filtro por medio reinicia la lista y viaja en el payload', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({ entries: [entrada({ media: 'game' })], total: 1, hasMore: false }))

    await store.setMedia('game')

    expect(apiCall).toHaveBeenLastCalledWith('get_journal', { limit: 30, offset: 0, media: 'game' })
    expect(store.entries).toHaveLength(1)
  })

  it('«todos» no manda `media` en el payload', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({ entries: [], total: 0, hasMore: false }))

    await store.setMedia(null)

    expect(apiCall).toHaveBeenLastCalledWith('get_journal', { limit: 30, offset: 0 })
  })

  it('la valoración `null` viaja al editar: quitarla es una edición legítima', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({ id: 7 }))

    await store.update(7, { entryDate: '2026-09-04', rating: null })

    expect(apiCall).toHaveBeenCalledWith('update_journal_entry', {
      entryId: 7, entryDate: '2026-09-04', rating: null
    })
  })

  it('un error del backend deja mensaje y no rompe la lista', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta(null, 'error', 500))

    await store.fetch()

    expect(store.entries).toEqual([])
    expect(store.error).toBeTruthy()
    // Traducido por código, nunca el `message` del backend.
    expect(store.error).not.toContain('backend message in english')
  })
})

describe('JournalStore — el diario AJENO, el del perfil público', () => {
  let apiCall

  beforeEach(() => {
    setActivePinia(createPinia())
    apiCall = vi.fn()
    useAuthStore().authenticatedApiCall = apiCall
  })

  it('pide `get_user_journal` con el username y la página corta del perfil', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({ entries: [entrada()], total: 1, hasMore: false }))

    await store.fetchUserJournal('david')

    expect(apiCall).toHaveBeenCalledWith('get_user_journal', {
      username: 'david', limit: 10, offset: 0
    })
    expect(store.hasUserEntries).toBe(true)
  })

  it('agrupa por día igual que el propio', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({
      entries: [
        entrada({ id: 3, entry_date: '2026-09-04' }),
        entrada({ id: 2, entry_date: '2026-09-04' }),
        entrada({ id: 1, entry_date: '2026-08-27' })
      ],
      total: 3,
      hasMore: false
    }))

    await store.fetchUserJournal('david')

    expect(store.userByDay.map((d) => d.date)).toEqual(['2026-09-04', '2026-08-27'])
    expect(store.userByDay[0].entries.map((e) => e.id)).toEqual([3, 2])
  })

  it('la lista vacía deja la sección sin nada que enseñar, sea cual sea el motivo', async () => {
    // El backend devuelve LO MISMO sin amistad aceptada, con `show_journal`
    // apagado y con el diario vacío. Que aquí no se pueda distinguir es el
    // punto: un aviso de «no lo enseña» ya contaría que hay un diario.
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({ entries: [], total: 0, hasMore: false }))

    await store.fetchUserJournal('desconocido')

    expect(store.hasUserEntries).toBe(false)
    expect(store.userByDay).toEqual([])
  })

  it('`loadMoreUserJournal` acumula, pide el offset correcto y no necesita el username otra vez', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValueOnce(respuesta({ entries: [entrada({ id: 1 })], total: 2, hasMore: true }))
    await store.fetchUserJournal('david')

    apiCall.mockResolvedValueOnce(respuesta({ entries: [entrada({ id: 2 })], total: 2, hasMore: false }))
    await store.loadMoreUserJournal()

    expect(apiCall).toHaveBeenLastCalledWith('get_user_journal', {
      username: 'david', limit: 10, offset: 1
    })
    expect(store.userEntries.map((e) => e.id)).toEqual([1, 2])
    expect(store.userHasMore).toBe(false)
  })

  it('sin más páginas no vuelve a pedir', async () => {
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta({ entries: [entrada()], total: 1, hasMore: false }))
    await store.fetchUserJournal('david')

    await store.loadMoreUserJournal()

    expect(apiCall).toHaveBeenCalledTimes(1)
  })

  it('abrir el perfil de otra persona NO pisa tu diario, ni al revés', async () => {
    // Los dos estados viven aparte por esto: compartir `entries` dejaría el
    // perfil ajeno pintando tus entradas, o /journal pintando las suyas.
    const store = useJournalStore()

    apiCall.mockResolvedValueOnce(respuesta({ entries: [entrada({ id: 1 })], total: 1, hasMore: false }))
    await store.fetch()

    apiCall.mockResolvedValueOnce(respuesta({ entries: [entrada({ id: 99 })], total: 1, hasMore: false }))
    await store.fetchUserJournal('david')

    expect(store.entries.map((e) => e.id)).toEqual([1])
    expect(store.userEntries.map((e) => e.id)).toEqual([99])
  })

  it('cambiar de perfil vacía lo anterior ANTES de pedir', async () => {
    // Sin el reinicio, al pasar del perfil de A al de B se verían las entradas
    // de A mientras llega la respuesta de B.
    const store = useJournalStore()
    apiCall.mockResolvedValueOnce(respuesta({ entries: [entrada({ id: 1 })], total: 1, hasMore: false }))
    await store.fetchUserJournal('a')

    let vistoDurante = null
    apiCall.mockImplementationOnce(() => {
      vistoDurante = store.userEntries.length
      return Promise.resolve(respuesta({ entries: [], total: 0, hasMore: false }))
    })
    await store.fetchUserJournal('b')

    expect(vistoDurante).toBe(0)
  })

  it('un fallo del diario ajeno no escribe el `error` del propio', async () => {
    // `error` es el de /journal: pintarlo desde aquí sacaría un aviso en tu
    // diario por algo que pasó en el perfil de otra persona.
    const store = useJournalStore()
    apiCall.mockResolvedValue(respuesta(null, 'error', 500))

    await store.fetchUserJournal('david')

    expect(store.userEntries).toEqual([])
    expect(store.error).toBeNull()
  })
})

describe('JournalEntryRow — la fila densa', () => {
  const montar = (props) => mountComponent(JournalEntryRow, {
    props,
    global: { stubs: { RouterLink: RouterLinkStub } }
  })

  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it.each(mediaKeys)('pinta una entrada de %s sin reventar', (media) => {
    const wrapper = montar({ entry: entrada({ media, entity_id: 'x1' }) })

    expect(wrapper.text()).toContain('The Matrix')
  })

  it('un medio que el registry no conoce no lanza y cae a la etiqueta neutra', () => {
    // `media` viene de la base: `getMediaConfig` LANZA con uno desconocido, y
    // por eso la fila comprueba contra `mediaKeys` antes de llamarlo.
    const wrapper = montar({ entry: entrada({ media: 'vinilo' }) })

    expect(wrapper.text()).toContain('The Matrix')
    expect(wrapper.findAllComponents(RouterLinkStub)).toHaveLength(0)
  })

  it('marca la repetición solo cuando el backend lo dice', () => {
    expect(montar({ entry: entrada() }).find('.journal-row__repeat').exists()).toBe(false)
    expect(montar({ entry: entrada({ is_repeat: true }) }).find('.journal-row__repeat').exists()).toBe(true)
  })

  it('no pinta valoración cuando la entrada no la tiene', () => {
    expect(montar({ entry: entrada() }).find('.journal-row__rating').exists()).toBe(false)
    expect(montar({ entry: entrada({ rating: 4.5 }) }).find('.journal-row__rating').exists()).toBe(true)
  })

  it('una valoración de 0 SÍ se pinta: cero no es «sin valorar»', () => {
    expect(montar({ entry: entrada({ rating: 0 }) }).find('.journal-row__rating').exists()).toBe(true)
  })

  it('una serie pide su portada como `movie`, no como `series`', () => {
    // Las series se guardan con `AddMovieUseCase`, así que su fila de
    // `cover_file` lleva `media_type = 'movie'` y no existe ninguna con
    // `'series'`: `?cover=series/tt…` da 404. Se vio en el navegador con la
    // imagen rota, no en los tests.
    const wrapper = montar({ entry: entrada({ media: 'series', entity_id: 'tt14452776' }) })

    expect(wrapper.find('img').attributes('src')).toContain('cover=movie/tt14452776')
  })

  it('tras fallar la portada local, el `src` baja a la remota', async () => {
    // La contraparte del bucle: anclar el reset de los indicadores a `coverSrc`
    // —que depende de ellos— hacía que el `src` volviera a la local en cuanto
    // fallaba, y el escalón a la remota no llegaba nunca.
    const wrapper = montar({
      entry: entrada({ media: 'series', entity_id: 'tt1', entity_cover: 'https://cdn.test/bear.jpg' })
    })

    await wrapper.find('img').trigger('error')

    expect(wrapper.find('img').attributes('src')).toBe('https://cdn.test/bear.jpg')
  })

  it('tras fallar también la remota, se pinta el placeholder', async () => {
    const wrapper = montar({
      entry: entrada({ entity_cover: 'https://cdn.test/x.jpg' })
    })

    await wrapper.find('img').trigger('error')
    await wrapper.find('img').trigger('error')

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('.journal-row__cover-placeholder').exists()).toBe(true)
  })

  it('con `readonly` no hay menú ⋯: en el perfil ajeno no se edita ni se borra', () => {
    // Por prop, no por una copia del componente: el escalón de portadas de esta
    // fila es demasiado delicado como para mantener dos versiones.
    expect(montar({ entry: entrada() }).find('.journal-row__menu').exists()).toBe(true)
    expect(montar({ entry: entrada(), readonly: true }).find('.journal-row__menu').exists()).toBe(false)
  })

  it('el título sin dato cae al texto del catálogo, no a una cadena vacía', () => {
    const wrapper = montar({ entry: entrada({ entity_title: '' }) })

    expect(wrapper.find('.journal-row__title').text()).not.toBe('')
  })
})

describe('JournalEntryModal — el alta manual', () => {
  let apiCall

  beforeEach(() => {
    setActivePinia(createPinia())
    apiCall = vi.fn().mockResolvedValue(respuesta({
      books: [{ isbn: '9788427200203', title: 'Dune (Nueva edición)' }],
      movies: []
    }))
    useAuthStore().authenticatedApiCall = apiCall
  })

  it('al abrirse carga la biblioteca: en /journal nadie la ha cargado antes', async () => {
    // El fallo que se vio en el navegador: la vista del diario no necesita los
    // stores de biblioteca para pintar sus entradas —el backend le da título y
    // portada denormalizados—, así que al llegar directo a /journal están a cero
    // y el buscador decía «nada casa con eso» con la biblioteca llena.
    const wrapper = mountComponent(JournalEntryModal, { props: { modelValue: false } })

    await wrapper.setProps({ modelValue: true })
    await new Promise((r) => setTimeout(r, 0))

    const acciones = apiCall.mock.calls.map((c) => c[0])
    expect(acciones).toContain('get_library_items')
  })

  it('montado YA abierto —como lo hace la ficha con `v-if`— también se rellena', async () => {
    // `MediaDetailView` monta el modal con `v-if` además del `v-model`, así que
    // nace con `modelValue: true` y ese prop nunca cambia: sin `immediate` en el
    // watch, el formulario salía con el buscador en vez del ítem y sin fecha.
    const wrapper = mountComponent(JournalEntryModal, {
      props: {
        modelValue: true,
        item: { media: 'movie', entityId: 'tt0133093', title: 'The Matrix' }
      }
    })

    await wrapper.vm.$nextTick()

    expect(wrapper.find('.journal-form__chosen-title').text()).toBe('The Matrix')
    expect(wrapper.find('#journal-search').exists()).toBe(false)
    expect(wrapper.find('#journal-date').element.value).not.toBe('')
  })

  it('el buscador encuentra un libro de la biblioteca por su título', async () => {
    const wrapper = mountComponent(JournalEntryModal, { props: { modelValue: false } })

    await wrapper.setProps({ modelValue: true })
    await new Promise((r) => setTimeout(r, 0))
    await wrapper.vm.$nextTick()

    await wrapper.find('#journal-search').setValue('dune')

    expect(wrapper.findAll('.journal-form__result').map((b) => b.text())).toContain('Dune (Nueva edición)')
  })
})
