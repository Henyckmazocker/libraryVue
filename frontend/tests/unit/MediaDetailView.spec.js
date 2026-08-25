import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import MediaDetailView from '@/views/shared/MediaDetailView.vue'
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

const montar = (media, store, slots = {}) => mountComponent(MediaDetailView, {
  props: { media, store },
  slots
})

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
    expect(wrapper.find('.save-button').exists()).toBe(true)
    expect(wrapper.find('.notes-section').exists()).toBe(false)
  })

  it('un ítem ya guardado enseña sus detalles, el borrado y las notas', async () => {
    route.params = { youtubeId: 'abc' }
    conEstado('video', { title: 'Charla', youtube_id: 'abc' })
    const store = crearStore([{ title: 'Charla', youtube_id: 'abc', userStatuses: ['owned'] }])
    const wrapper = montar('video', store)
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Detalles en tu Biblioteca')
    expect(wrapper.find('.delete-button').exists()).toBe(true)
    expect(wrapper.find('.notes-section').exists()).toBe(true)
  })

  it('vídeo, álbum y juego usan `.library-section` sin icono en el encabezado', async () => {
    conEstado('game', { name: 'Hollow Knight', id: 7 })
    const wrapper = montar('game', crearStore())
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.library-section').exists()).toBe(true)
    expect(wrapper.find('.library-section .section-title').exists()).toBe(false)
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

    await wrapper.find('.save-button').trigger('click')

    // El juego emite `{ game, statuses }`; el store recibe los dos por separado.
    expect(store.add).toHaveBeenCalledWith(
      expect.objectContaining({ name: 'Hollow Knight' }),
      ['owned']
    )
  })

  it('borrar un juego no pide confirmación y borrar un álbum sí', async () => {
    const confirmar = vi.spyOn(window, 'confirm').mockReturnValue(false)

    route.params = { gameId: '7' }
    conEstado('game', { name: 'Hollow Knight', id: 7 })
    const gameStore = crearStore([{ name: 'Hollow Knight', id: 7 }])
    const game = montar('game', gameStore)
    await game.vm.$nextTick()
    await game.find('.delete-button').trigger('click')

    expect(confirmar).not.toHaveBeenCalled()
    expect(gameStore.remove).toHaveBeenCalledWith(7)

    route.params = { albumId: '9' }
    conEstado('album', { name: 'Kid A', id: 9, spotify_id: 'sp' })
    const albumStore = crearStore([{ name: 'Kid A', id: 9, spotify_id: 'sp' }])
    const album = montar('album', albumStore)
    await album.vm.$nextTick()
    await album.find('.delete-button').trigger('click')

    expect(confirmar).toHaveBeenCalled()
    expect(albumStore.remove).not.toHaveBeenCalled()

    confirmar.mockRestore()
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
