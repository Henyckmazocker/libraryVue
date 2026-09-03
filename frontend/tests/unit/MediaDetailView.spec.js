import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import MediaDetailView from '@/views/shared/MediaDetailView.vue'
import MediaNotes from '@/components/shared/MediaNotes.vue'
import { getMediaConfig } from '@/config/mediaRegistry'
import { mountComponent } from './helpers/mount'

// La vista lee la ruta y navega; ninguna de las dos cosas se ejerce aquí.
const route = { params: {} }
const push = vi.fn()

vi.mock('vue-router', () => ({
  useRoute: () => route,
  useRouter: () => ({ push, back: vi.fn(), go: vi.fn() })
}))

const apiCall = vi.fn()

// La URL de CDN de la que este bloque quiere dejar de depender.
const CDN_TMDB = 'https://image.tmdb.org/t/p/w500/x.jpg'

vi.mock('@/store/auth', () => ({
  useAuthStore: () => ({ isAuthenticated: true, apiCall })
}))

vi.mock('@/store/ui', () => ({
  useUIStore: () => ({ showSuccess: vi.fn(), showError: vi.fn() })
}))

/** Store de medio mínimo con la superficie que MediaDetailView consume. */
const crearStore = (items = []) => ({
  items,
  allowedStatuses: ['owned', 'wishlist'],
  fetch: vi.fn().mockResolvedValue(items),
  fetchAllowedStatuses: vi.fn().mockResolvedValue(['owned']),
  add: vi.fn().mockResolvedValue({ success: true }),
  remove: vi.fn().mockResolvedValue({ success: true }),
  getVideoByYouTubeId: (id) => items.find((i) => i.youtube_id === id),
  getAlbumBySpotifyId: (id) => items.find((i) => i.spotify_id === id),
  getAlbumById: (id) => items.find((i) => i.id === id),
  getGameById: (id) => items.find((i) => i.id === id),
  getMovieById: (id) => items.find((i) => i.imdbID === id),
  getBookByIsbn: (isbn) => items.find((i) => i.isbn === isbn)
})

/** Deja un ítem en `history.state`, que es de donde arranca la vista. */
const conEstado = (media, item) => {
  window.history.replaceState({ [media]: item }, '')
}

const montar = (media, store, slots = {}, props = {}) => mountComponent(MediaDetailView, {
  props: { media, store, ...props },
  slots
})

/** Las entradas del menú `⋯`, que es donde viven hoy las acciones colaterales. */
const menu = (wrapper) => wrapper.vm.$.setupState.menuItems

const rotulosDelMenu = (wrapper) => menu(wrapper)
  .filter((i) => !i.separator)
  .map((i) => i.label)

/** Dispara una entrada del menú por su rótulo. */
const pulsarEnMenu = (wrapper, rotulo) => menu(wrapper).find((i) => i.label === rotulo).command()

describe('MediaDetailView — esqueleto compartido', () => {
  beforeEach(() => {
    window.history.replaceState({}, '')
    route.params = {}
    apiCall.mockReset()
  })

  it('sin datos arranca en estado de carga, con el texto del medio', () => {
    const wrapper = montar('video', crearStore())

    // La espera la pinta `MediaSkeleton` con la variante de ficha; el texto del
    // medio pasó de ser un <p> visible a la etiqueta que oye el lector.
    expect(wrapper.find('.media-skeleton--detail').exists()).toBe(true)
    expect(wrapper.text()).toContain('Cargando información del vídeo...')
  })

  it('la raíz y el contenido llevan las clases que el mixin espera', async () => {
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const wrapper = montar('video', crearStore())
    await wrapper.vm.$nextTick()

    expect(wrapper.classes()).toContain('video-detail-view')
    expect(wrapper.find('.video-detail-content').exists()).toBe(true)
    expect(wrapper.find('.video-header').exists()).toBe(true)
    expect(wrapper.find('.video-main-info').exists()).toBe(true)
  })

  it('el botón de volver usa el texto declarado', () => {
    expect(montar('video', crearStore()).find('.back-button').text()).toContain('Volver')
  })

  it('sin portada cae al icono de relleno del medio', async () => {
    conEstado('album', { name: 'Kid A', spotify_id: 'sp' })
    const wrapper = montar('album', crearStore())
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.cover-placeholder i').classes()).toContain('fa-music')
  })

  it('el título cae a `name` cuando el medio no trae `title`', async () => {
    conEstado('album', { name: 'Kid A', spotify_id: 'sp' })
    const wrapper = montar('album', crearStore())
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.album-title-large').text()).toBe('Kid A')
  })
})

describe('MediaDetailView — slots por medio', () => {
  beforeEach(() => {
    window.history.replaceState({}, '')
    route.params = {}
  })

  it('`#meta` y `#extra` reciben el ítem y el contexto', async () => {
    conEstado('video', { title: 'Charla', youtube_id: 'abc', description: 'texto' })
    const wrapper = montar('video', crearStore(), {
      meta: '<p class="probe-meta">{{ params.item.title }}</p>',
      extra: '<p class="probe-extra">{{ params.item.description }}</p>'
    })
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.probe-meta').text()).toBe('Charla')
    expect(wrapper.find('.probe-extra').text()).toBe('texto')
  })

  it('`#meta-top` se pinta por encima del título', async () => {
    conEstado('album', { name: 'Kid A', spotify_id: 'sp' })
    const wrapper = montar('album', crearStore(), {
      'meta-top': '<span class="probe-badge">LP</span>'
    })
    await wrapper.vm.$nextTick()

    const html = wrapper.find('.album-main-info').html()
    expect(html.indexOf('probe-badge')).toBeLessThan(html.indexOf('album-title-large'))
  })

  it('`#cover-overlay` se pinta dentro de la portada', async () => {
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const wrapper = montar('video', crearStore(), {
      'cover-overlay': '<a class="probe-play">play</a>'
    })
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.video-cover-large .probe-play').exists()).toBe(true)
  })
})

describe('MediaDetailView — formulario de biblioteca', () => {
  beforeEach(() => {
    window.history.replaceState({}, '')
    route.params = {}
  })

  it('un ítem que no está en la biblioteca se ofrece para añadir', async () => {
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const wrapper = montar('video', crearStore())
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Añadir a tu Biblioteca')
    expect(wrapper.find('.btn--primary').exists()).toBe(true)
    expect(wrapper.find('.notes-section').exists()).toBe(false)
  })

  it('un ítem ya guardado enseña sus detalles, el borrado y las notas', async () => {
    route.params = { youtubeId: 'abc' }
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const store = crearStore([{ title: 'Charla', youtube_id: 'abc', userStatuses: ['owned'] }])
    const wrapper = montar('video', store)
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Detalles en tu Biblioteca')
    // El borrado dejó de ser un botón del panel el 2026-09-02: bajó al menú `⋯`.
    expect(rotulosDelMenu(wrapper)).toContain('Eliminar')
    expect(wrapper.find('.notes-section').exists()).toBe(true)
  })

  // Este test afirmaba lo contrario hasta el 2026-09-02: que vídeo, álbum y juego
  // usaban `.library-section` **sin** icono mientras libro, película y serie usaban
  // `.library-form-section` **con** icono. Esa bifurcación la declaraban tres banderas
  // del registry que nadie había decidido —venían de respetar la divergencia existente
  // al unificar las seis vistas—, y se retiraron. Ahora la sección es la misma para los
  // seis, y eso es lo que se fija aquí.
  it.each([
    ['game', { name: 'Hollow Knight', id: 7 }],
    ['album', { title: 'Graduation', id: 3 }],
    ['video', { title: 'Charla', youtube_id: 'abc' }],
    ['book', { title: 'Dune', isbn: '9788466342667' }],
    ['movie', { title: 'The Matrix', imdbID: 'tt0133093' }]
  ])('%s usa `.library-section` con su encabezado con icono', async (media, item) => {
    conEstado(media, item)
    const wrapper = montar(media, crearStore())
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.library-section').exists()).toBe(true)
    expect(wrapper.find('.library-section .section-title').exists()).toBe(true)
    expect(wrapper.find('.library-section .section-title i').exists()).toBe(true)
    // La clase que se retiró no puede volver por la puerta de atrás.
    expect(wrapper.find('.library-form-section').exists()).toBe(false)
  })
})

describe('MediaDetailView — guardar y borrar pasan por el store', () => {
  beforeEach(() => {
    window.history.replaceState({}, '')
    route.params = {}
  })

  it('guardar desenvuelve el payload según el medio', async () => {
    conEstado('game', { name: 'Hollow Knight', id: 7 })
    const store = crearStore()
    const wrapper = montar('game', store)
    // Hay que dejar terminar `loadData`: los estados permitidos llegan de ahí,
    // y de ellos depende que `owned` quede preseleccionado.
    await flushPromises()

    await wrapper.find('.btn--primary').trigger('click')

    // El juego emite `{ game, statuses }`; el store recibe los dos por separado.
    expect(store.add).toHaveBeenCalledWith(
      expect.objectContaining({ name: 'Hollow Knight' }),
      ['owned']
    )
  })

  // Desde el 2026-08-29 la confirmación ya no es el `confirm()` del navegador
  // sino `ConfirmationModal`, así que no se espía `window`: se comprueba que el
  // borrado NO llega al store mientras nadie confirma —que es lo que el test
  // afirmaba de verdad— y que el medio sin `deleteConfirm` pasa de largo.
  it('borrar un juego no pide confirmación y borrar un álbum sí', async () => {

    route.params = { gameId: '7' }
    conEstado('game', { name: 'Hollow Knight', id: 7 })
    const gameStore = crearStore([{ name: 'Hollow Knight', id: 7 }])
    const game = montar('game', gameStore)
    await game.vm.$nextTick()
    await pulsarEnMenu(game, 'Eliminar')

    expect(gameStore.remove).toHaveBeenCalledWith(7)

    route.params = { albumId: '9' }
    conEstado('album', { name: 'Kid A', id: 9, spotify_id: 'sp' })
    const albumStore = crearStore([{ name: 'Kid A', id: 9, spotify_id: 'sp' }])
    const album = montar('album', albumStore)
    await album.vm.$nextTick()
    pulsarEnMenu(album, 'Eliminar')
    await flushPromises()

    // El álbum sí declara `deleteConfirm`, así que se queda esperando al modal.
    expect(albumStore.remove).not.toHaveBeenCalled()
  })
})

describe('MediaDetailView — la portada sale de la copia local', () => {
  beforeEach(() => {
    window.history.replaceState({}, '')
    route.params = {}
  })

  it('un ítem guardado pide su portada al backend, no al CDN', async () => {
    route.params = { imdbId: 'tt0068646' }
    conEstado('movie', { title: 'El Padrino', imdbID: 'tt0068646', coverUrl: CDN_TMDB })
    const wrapper = montar('movie', crearStore([
      { title: 'El Padrino', imdbID: 'tt0068646', coverUrl: CDN_TMDB }
    ]))
    await wrapper.vm.$nextTick()

    // Solo se afirma el medio y la clave: la base la pone `VUE_APP_API_URL`.
    expect(wrapper.find('img.poster-image-large').attributes('src'))
      .toMatch(/[?&]cover=movie\/tt0068646$/)
  })

  it('un ítem que no está en la biblioteca sigue tirando del CDN', async () => {
    // El caso normal al llegar desde la búsqueda: sin fila en `cover_file`,
    // pedir la portada local sería un 404 garantizado.
    conEstado('movie', { title: 'Matrix', imdbID: 'tt0133093', coverUrl: CDN_TMDB })
    const wrapper = montar('movie', crearStore())
    await wrapper.vm.$nextTick()

    expect(wrapper.find('img.poster-image-large').attributes('src')).toBe(CDN_TMDB)
  })

  it('si la copia local falla, la portada cae a la URL remota', async () => {
    route.params = { imdbId: 'tt0068646' }
    conEstado('movie', { title: 'El Padrino', imdbID: 'tt0068646', coverUrl: CDN_TMDB })
    const wrapper = montar('movie', crearStore([
      { title: 'El Padrino', imdbID: 'tt0068646', coverUrl: CDN_TMDB }
    ]))
    await flushPromises()

    await wrapper.find('img.poster-image-large').trigger('error')

    expect(wrapper.find('img.poster-image-large').attributes('src')).toBe(CDN_TMDB)
    expect(wrapper.find('.poster-placeholder').exists()).toBe(false)
  })

  it('si también falla la remota, se pinta el placeholder del medio', async () => {
    route.params = { imdbId: 'tt0068646' }
    conEstado('movie', { title: 'El Padrino', imdbID: 'tt0068646', coverUrl: CDN_TMDB })
    const wrapper = montar('movie', crearStore([
      { title: 'El Padrino', imdbID: 'tt0068646', coverUrl: CDN_TMDB }
    ]))
    await flushPromises()

    await wrapper.find('img.poster-image-large').trigger('error')
    await wrapper.find('img.poster-image-large').trigger('error')

    expect(wrapper.find('img.poster-image-large').exists()).toBe(false)
    expect(wrapper.find('.poster-placeholder i').classes()).toContain('fa-film')
  })

  it('una serie guardada pide la clave de película, no la suya', async () => {
    // Las series se guardan con `AddMovieUseCase`, así que su fila de
    // `cover_file` lleva `media_type = 'movie'`: `?cover=series/…` daría 404.
    route.params = { imdbId: 'tt0386676' }
    conEstado('movie', { title: 'The Office', imdbID: 'tt0386676', coverUrl: CDN_TMDB })
    const wrapper = montar('series', crearStore([
      { title: 'The Office', imdbID: 'tt0386676', coverUrl: CDN_TMDB }
    ]))
    await wrapper.vm.$nextTick()

    expect(wrapper.find('img.poster-image-large').attributes('src'))
      .toMatch(/[?&]cover=movie\/tt0386676$/)
  })
})

/**
 * El guardado al vuelo desde el panel.
 *
 * Lo que se fija aquí no es que guarde —eso lo haría cualquier mock— sino **por dónde
 * guarda y con qué guardas**. El panel NO puede llamar al store por su cuenta: en
 * libros, `update<One>Statuses` es la versión de `useBooks` con confirmación de sesión,
 * y saltársela es exactamente lo que este plan quería evitar.
 *
 * Las tres guardas son las que `EditItemModal.vue:636-648` lleva aplicando meses.
 */
describe('MediaDetailView — el panel guarda al vuelo', () => {
  const GUARDADO = { title: 'Charla', youtube_id: 'abc', userStatuses: ['owned'], user_rating: 2 }

  const conPanel = async (props = {}) => {
    route.params = { youtubeId: 'abc' }
    conEstado('video', GUARDADO)
    const store = crearStore([{ ...GUARDADO }])
    const wrapper = mountComponent(MediaDetailView, {
      props: { media: 'video', store, ...props }
    })
    await flushPromises()
    return { wrapper, store, panel: wrapper.findComponent({ name: 'LibraryMediaItem' }) }
  }

  beforeEach(() => {
    window.history.replaceState({}, '')
    route.params = {}
    apiCall.mockReset()
  })

  it('sin las props no es editable: el panel se comporta como siempre', async () => {
    const { panel } = await conPanel()
    expect(panel.props('editable')).toBe(false)
  })

  it('valorar llama a `onRate` y NO al store', async () => {
    const onRate = vi.fn().mockResolvedValue({ success: true })
    const { store, panel } = await conPanel({ onRate })

    panel.vm.$emit('rate', 5)
    await flushPromises()

    expect(onRate).toHaveBeenCalledWith('abc', 5)
    expect(store.add).not.toHaveBeenCalled()
  })

  it('cambiar de estado llama a `onStatus` y NO al store', async () => {
    const onStatus = vi.fn().mockResolvedValue({ success: true })
    const { store, panel } = await conPanel({ onStatus })

    panel.vm.$emit('set-statuses', ['owned', 'watched'])
    await flushPromises()

    expect(onStatus).toHaveBeenCalledWith('abc', ['owned', 'watched'])
    expect(store.add).not.toHaveBeenCalled()
  })

  // Guarda 2 del contrato: el modal la aplica desde el 2026-08-27 y aquí es nueva.
  it('no llama a `onStatus` si los estados no cambian de verdad', async () => {
    const onStatus = vi.fn().mockResolvedValue({ success: true })
    const { panel } = await conPanel({ onStatus })

    panel.vm.$emit('set-statuses', ['owned'])
    await flushPromises()

    expect(onStatus).not.toHaveBeenCalled()
  })

  // Guarda 3: el usuario dijo que no en la confirmación de sesión de `useBooks`.
  it('un `cancelled` deja los estados como estaban', async () => {
    const onStatus = vi.fn().mockResolvedValue({ cancelled: true })
    const { wrapper, panel } = await conPanel({ onStatus })

    panel.vm.$emit('set-statuses', ['owned', 'watched'])
    await flushPromises()

    expect(onStatus).toHaveBeenCalled()
    expect(wrapper.vm.existing.userStatuses).toEqual(['owned'])
  })

  it('si el guardado falla, el valor vuelve al anterior', async () => {
    const onStatus = vi.fn().mockResolvedValue({ success: false, message: 'boom' })
    const { wrapper, panel } = await conPanel({ onStatus })

    panel.vm.$emit('set-statuses', ['owned', 'watched'])
    await flushPromises()

    expect(wrapper.vm.existing.userStatuses).toEqual(['owned'])
  })
})

/**
 * La barra dejó de ser cuatro botones del mismo peso —volver, recomendar, añadir a
 * una lista y ponerlo en un club— y pasó a volver + una acción primaria + `⋯`. Lo
 * que se fija aquí es el reparto: qué acción es la primaria en cada estado, qué baja
 * al menú, y que el acuse en verde lo sigue dando el store y no el botón.
 */
describe('MediaDetailView — el CTA de la barra', () => {
  beforeEach(() => {
    window.history.replaceState({}, '')
    route.params = {}
  })

  it('sin el ítem en la biblioteca la acción primaria es guardar', async () => {
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const wrapper = montar('video', crearStore())
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.detail-cta').text()).toContain('Guardar en mi biblioteca')
    expect(wrapper.find('.detail-cta i').classes()).toContain('fa-save')
  })

  it('con el ítem ya guardado la acción primaria pasa a editar', async () => {
    route.params = { youtubeId: 'abc' }
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const wrapper = montar('video', crearStore([{ title: 'Charla', youtube_id: 'abc' }]))
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.detail-cta').text()).toContain('Editar')
    expect(wrapper.find('.detail-cta i').classes()).toContain('fa-pencil-alt')
  })

  it('editar abre el modal, no guarda nada por su cuenta', async () => {
    route.params = { youtubeId: 'abc' }
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const store = crearStore([{ title: 'Charla', youtube_id: 'abc' }])
    // El modal va en stub: su `setup` instancia los cinco composables de medio y
    // exige un Pinia activo, que este spec no monta a propósito.
    const wrapper = mountComponent(MediaDetailView, {
      props: { media: 'video', store },
      global: { stubs: { EditItemModal: true } }
    })
    await wrapper.vm.$nextTick()

    await wrapper.find('.detail-cta').trigger('click')
    await flushPromises()

    expect(store.add).not.toHaveBeenCalled()
    expect(wrapper.vm.$.setupState.editModal.isVisible).toBe(true)
  })

  it('el CTA no se pone en verde solo: espera a que el store conteste', async () => {
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const store = crearStore()
    // El alta se queda colgada: es el hueco en el que el botón NO puede cantar
    // victoria. Antes, álbumes y vídeos se daban el guardado por bueno solos.
    store.add = vi.fn(() => new Promise(() => {}))
    const wrapper = montar('video', store)
    await flushPromises()

    await wrapper.find('.detail-cta').trigger('click')
    await wrapper.vm.$nextTick()

    const clases = wrapper.find('.detail-cta').classes()
    expect(clases).not.toContain('is-success')
    expect(clases).not.toContain('is-error')
  })

  it('un alta fallida pinta el CTA en rojo', async () => {
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const store = crearStore()
    store.add = vi.fn().mockResolvedValue({ success: false, message: 'no' })
    const wrapper = montar('video', store)
    await flushPromises()

    await wrapper.find('.detail-cta').trigger('click')
    await flushPromises()

    expect(wrapper.find('.detail-cta').classes()).toContain('is-error')
  })

  it('las tres colaterales bajan al menú, y «Eliminar» solo con el ítem guardado', async () => {
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const nuevo = montar('video', crearStore())
    await nuevo.vm.$nextTick()

    expect(rotulosDelMenu(nuevo)).toEqual([
      'Recomendar', 'Añadir a una lista', 'Ponerlo en un club'
    ])
    // Ya no son botones de la barra: eran cuatro decisiones antes de ver la ficha.
    expect(nuevo.find('.recommend-button').exists()).toBe(false)

    route.params = { youtubeId: 'abc' }
    const guardado = montar('video', crearStore([{ title: 'Charla', youtube_id: 'abc' }]))
    await guardado.vm.$nextTick()

    expect(rotulosDelMenu(guardado)).toContain('Eliminar')
    // Y separado de las otras tres, para que no se pulse por inercia.
    expect(menu(guardado).some((i) => i.separator)).toBe(true)
  })

  it('cada entrada del menú abre su diálogo', async () => {
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const wrapper = montar('video', crearStore())
    await wrapper.vm.$nextTick()

    pulsarEnMenu(wrapper, 'Recomendar')
    pulsarEnMenu(wrapper, 'Añadir a una lista')
    pulsarEnMenu(wrapper, 'Ponerlo en un club')

    const estado = wrapper.vm.$.setupState
    expect(estado.showRecommendDialog).toBe(true)
    expect(estado.showAddToListDialog).toBe(true)
    expect(estado.showAddToClubDialog).toBe(true)
  })
})

/**
 * Las notas estaban en dos sitios y en ninguno para todos: juego, álbum y vídeo las
 * tenían en la página **y** dentro del modal de edición; libro, película y serie,
 * solo dentro del modal. Lo decidía un `hasNotes` del registry que ya no existe.
 */
describe('MediaDetailView — las notas, en un solo sitio', () => {
  beforeEach(() => {
    window.history.replaceState({}, '')
    route.params = {}
  })

  const guardado = {
    book:   ['bookIsbn',  { title: 'Dune', isbn: '978', id: 42 }],
    movie:  ['movieId',   { title: 'Matrix', imdbID: 'tt1', isbn: 'tt1' }],
    game:   ['gameId',    { title: 'Hollow', id: 7 }],
    album:  ['albumId',   { title: 'Graduation', id: 3 }],
    video:  ['youtubeId', { title: 'Charla', youtube_id: 'abc' }],
    series: ['seriesId',  { title: 'GoT', imdbID: 'tt9', isbn: 'tt9' }]
  }

  const conFicha = async (media) => {
    const [param, item] = guardado[media]
    route.params = { [param]: item.isbn ?? item.youtube_id ?? String(item.id) }
    // La serie comparte `stateKey` con la película: en el backend son la misma
    // entidad, y por eso `conEstado` recibe el medio del estado, no el de la ficha.
    conEstado(media === 'series' ? 'movie' : media, item)
    const wrapper = montar(media, crearStore([item]))
    await flushPromises()
    return wrapper
  }

  it.each(Object.keys(guardado))('%s tiene su panel de notas en la página', async (media) => {
    const wrapper = await conFicha(media)

    expect(wrapper.find('.notes-section').exists()).toBe(true)
    expect(wrapper.findComponent(MediaNotes).exists()).toBe(true)
  })

  // Es el único medio donde la nota NO cuelga del identificador de la ficha:
  // `add_edition_note` quiere el id de TU edición, no el ISBN de la ruta. Pedirlas
  // con el ISBN no daría error: devolvería siempre cero notas.
  it('el libro pide sus notas con el id de su edición, no con el ISBN', async () => {
    const wrapper = await conFicha('book')

    expect(wrapper.findComponent(MediaNotes).props('itemId')).toBe(42)
  })

  it('y los otros cinco, con el identificador de la ficha', async () => {
    expect((await conFicha('movie')).findComponent(MediaNotes).props('itemId')).toBe('tt1')
    expect((await conFicha('game')).findComponent(MediaNotes).props('itemId')).toBe(7)
    expect((await conFicha('video')).findComponent(MediaNotes).props('itemId')).toBe('abc')
  })

  it('la serie toma las notas de película, pero con su propio título', () => {
    const serie = getMediaConfig('series').notes
    const pelicula = getMediaConfig('movie').notes

    // Mismo backend: una serie guardada es una fila de `movie`.
    expect(serie.actions).toBe(pelicula.actions)
    expect(getMediaConfig('series').idPayloadKey).toBe(getMediaConfig('movie').idPayloadKey)
    // Distinto texto, y por getter: si fuera un spread quedaría congelado.
    expect(serie.title).toBe('Notas de la Serie')
    expect(serie.title).not.toBe(pelicula.title)
  })

  it('sin el ítem en la biblioteca no hay notas que enseñar', async () => {
    conEstado('book', { title: 'Dune', isbn: '978' })
    const wrapper = montar('book', crearStore())
    await flushPromises()

    expect(wrapper.find('.notes-section').exists()).toBe(false)
  })
})

/**
 * Los identificadores —el ISBN, el id de IMDb— vivían en `#meta`, entre el título y
 * la sinopsis. No se leen: se copian. Desde el 2026-09-03 bajan a un `<details>` al
 * final de la columna izquierda, y solo si el medio llena el slot.
 */
describe('MediaDetailView — los datos técnicos, plegados', () => {
  beforeEach(() => {
    window.history.replaceState({}, '')
    route.params = {}
  })

  it('sin slot `#technical` no se pinta el plegable', async () => {
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const wrapper = montar('video', crearStore())
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.detail-technical').exists()).toBe(false)
  })

  it('con slot, el plegable va DENTRO de la columna izquierda y arranca cerrado', async () => {
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const wrapper = montar('video', crearStore(), {
      technical: '<span class="probe-id">{{ params.item.youtube_id }}</span>'
    })
    await wrapper.vm.$nextTick()

    const detalles = wrapper.find('.detail-extra .detail-technical')
    expect(detalles.exists()).toBe(true)
    // `open` ausente = plegado: el identificador no ocupa sitio hasta que se pide.
    expect(detalles.attributes('open')).toBeUndefined()
    // Y va envuelto: el `display: flex` del identificador le ganaba al `display: none`
    // con que el navegador oculta el contenido de un `<details>` cerrado, así que se
    // veía igualmente. El envoltorio no declara `display` y sí se oculta.
    expect(detalles.find('.detail-technical__body .probe-id').exists()).toBe(true)
    expect(detalles.find('summary').text()).toBe('Datos técnicos')
    expect(detalles.find('.probe-id').text()).toBe('abc')
  })
})

/**
 * `extraActions` del registry emite en el panel y lo consume el wrapper del medio,
 * que es quien tiene el modal. Esta vista solo hace de puente, y si dejara de
 * declarar uno de los tres eventos Vue soltaría un warning que no rompe nada.
 */
describe('MediaDetailView — el puente de las acciones del panel', () => {
  beforeEach(() => {
    window.history.replaceState({}, '')
    route.params = {}
  })

  it.each(['show-history', 'show-editions', 'show-seasons'])('reenvía `%s` al wrapper', async (evento) => {
    route.params = { youtubeId: 'abc' }
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const wrapper = montar('video', crearStore([{ title: 'Charla', youtube_id: 'abc' }]))
    await flushPromises()

    const panel = wrapper.findComponent({ name: 'LibraryMediaItem' })
    panel.vm.$emit(evento, { probe: 1 })
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted(evento)[0][0]).toEqual({ probe: 1 })
  })
})
