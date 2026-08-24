import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import VideoDetailView from '@/views/VideoDetailView.vue'
import AlbumDetailView from '@/views/AlbumDetailView.vue'
import GameDetailView from '@/views/GameDetailView.vue'
import { mountComponent } from './helpers/mount'

/**
 * Verificación de no-regresión de las fichas de detalle: monta las vistas de
 * verdad —wrapper + MediaDetailView + registry— y comprueba que cada bloque
 * propio del medio sigue estando y en el orden correcto. No mira estilos.
 */

const route = { params: {} }

vi.mock('vue-router', () => ({
  useRoute: () => route,
  useRouter: () => ({ push: vi.fn(), back: vi.fn(), go: vi.fn() })
}))

const apiCall = vi.fn()

vi.mock('@/store/auth', () => ({
  useAuthStore: () => ({ isAuthenticated: true, apiCall })
}))

vi.mock('@/store/ui', () => ({
  useUIStore: () => ({ showSuccess: vi.fn(), showError: vi.fn() })
}))

const store = (items = []) => ({
  items,
  allowedStatuses: ['owned', 'wishlist'],
  fetch: vi.fn().mockResolvedValue(items),
  fetchAllowedStatuses: vi.fn().mockResolvedValue([]),
  add: vi.fn().mockResolvedValue({ success: true }),
  remove: vi.fn().mockResolvedValue({ success: true }),
  getVideoByYouTubeId: (id) => items.find((i) => i.youtube_id === id),
  getAlbumBySpotifyId: (id) => items.find((i) => i.spotify_id === id),
  getAlbumById: (id) => items.find((i) => i.id === id),
  getGameById: (id) => items.find((i) => i.id === id)
})

let videos = store()
let albums = store()
let games = store()

vi.mock('@/store/videos', () => ({ useVideosStore: () => videos }))
vi.mock('@/store/albums', () => ({ useAlbumsStore: () => albums }))
vi.mock('@/store/games', () => ({ useGamesStore: () => games }))

const conEstado = (media, item) => window.history.replaceState({ [media]: item }, '')

const montar = (view) => mountComponent(view, {
  global: { stubs: { AlbumLastFmCard: true, MediaNotes: true } }
})

beforeEach(() => {
  window.history.replaceState({}, '')
  route.params = {}
  apiCall.mockReset()
  videos = store()
  albums = store()
  games = store()
})

describe('VideoDetailView', () => {
  const VIDEO = {
    title: 'Charla sobre Vue',
    youtube_id: 'abc123',
    channel_name: 'Canal X',
    duration: '12:04',
    published_at: '2024-03-01',
    view_count: 1_500_000,
    like_count: 2400,
    categories: ['Tecnología', 'Programación'],
    description: 'x'.repeat(400)
  }

  it('pinta la cabecera con canal, metadatos y categorías', async () => {
    conEstado('video', VIDEO)
    const wrapper = montar(VideoDetailView)
    await flushPromises()

    expect(wrapper.find('.video-detail-view').exists()).toBe(true)
    expect(wrapper.find('.video-title-large').text()).toBe('Charla sobre Vue')
    expect(wrapper.find('.video-channel-large').text()).toContain('Canal X')

    const meta = wrapper.find('.video-metadata').text()
    expect(meta).toContain('12:04')
    expect(meta).toContain('2024')
    // Los contadores se abrevian, como antes.
    expect(meta).toContain('1.5M vistas')
    expect(meta).toContain('2.4K')

    expect(wrapper.findAll('.category-tag').map((t) => t.text()))
      .toEqual(['Tecnología', 'Programación'])
  })

  it('superpone el botón de YouTube a la miniatura y enlaza al vídeo', async () => {
    conEstado('video', VIDEO)
    const wrapper = montar(VideoDetailView)
    await flushPromises()

    const play = wrapper.find('.video-cover-large .youtube-play-btn')
    expect(play.attributes('href')).toBe('https://www.youtube.com/watch?v=abc123')
    expect(wrapper.find('.video-links .youtube-link').exists()).toBe(true)
  })

  it('recorta la descripción larga y la despliega al pulsar', async () => {
    conEstado('video', VIDEO)
    const wrapper = montar(VideoDetailView)
    await flushPromises()

    expect(wrapper.find('.video-description').text()).toHaveLength(303) // 300 + '...'
    await wrapper.find('.toggle-desc-btn').trigger('click')
    expect(wrapper.find('.video-description').text()).toHaveLength(400)
  })

  it('las categorías en JSON como cadena también se pintan', async () => {
    conEstado('video', { ...VIDEO, categories: '["Música"]' })
    const wrapper = montar(VideoDetailView)
    await flushPromises()

    expect(wrapper.findAll('.category-tag')).toHaveLength(1)
  })
})

describe('AlbumDetailView', () => {
  const ALBUM = {
    name: 'Kid A',
    spotify_id: 'sp1',
    album_type: 'album',
    artist: 'Radiohead',
    release_date: '2000-10-02',
    total_tracks: 10,
    label: 'Parlophone',
    duration_ms: 2_587_000,
    popularity: 78,
    genres: ['Rock', 'Electrónica'],
    external_url: 'https://open.spotify.com/album/sp1'
  }

  it('pinta el badge de tipo por encima del título', async () => {
    conEstado('album', ALBUM)
    const wrapper = montar(AlbumDetailView)
    await flushPromises()

    const html = wrapper.find('.album-main-info').html()
    expect(html.indexOf('album-type-badge')).toBeLessThan(html.indexOf('album-title-large'))
    expect(wrapper.find('.album-type-badge').text()).toContain('Álbum')
    expect(wrapper.find('.album-type-badge i').classes()).toContain('fa-record-vinyl')
  })

  it('pinta artista, metadatos, popularidad, géneros y el enlace a Spotify', async () => {
    conEstado('album', ALBUM)
    const wrapper = montar(AlbumDetailView)
    await flushPromises()

    expect(wrapper.find('.album-artist-large').text()).toContain('Radiohead')

    const meta = wrapper.find('.album-metadata').text()
    expect(meta).toContain('2000')
    expect(meta).toContain('10 pistas')
    expect(meta).toContain('Parlophone')
    expect(meta).toContain('43:07')

    expect(wrapper.find('.popularity-bar').attributes('style')).toContain('width: 78%')
    expect(wrapper.findAll('.genre-tag').map((t) => t.text())).toEqual(['Rock', 'Electrónica'])
    expect(wrapper.find('.spotify-link').attributes('href')).toBe(ALBUM.external_url)
    expect(wrapper.find('.lastfm-section').exists()).toBe(true)
  })

  it('la lista de pistas sale del enriquecimiento de Spotify', async () => {
    route.params = { albumId: 'sp1' }
    conEstado('album', ALBUM)
    apiCall.mockResolvedValue({
      data: {
        status: 'success',
        data: {
          id: 'sp1',
          name: 'Kid A',
          artists: [{ id: 'a1', name: 'Radiohead' }],
          release_date: '2000-10-02',
          total_tracks: 2,
          tracks: {
            items: [
              { id: 't1', track_number: 1, name: 'Everything In Its Right Place', duration_ms: 251_000 },
              { id: 't2', track_number: 2, name: 'Kid A', duration_ms: 264_000 }
            ]
          }
        }
      }
    })

    const wrapper = montar(AlbumDetailView)
    await flushPromises()

    const pistas = wrapper.findAll('.track-item')
    expect(pistas).toHaveLength(2)
    expect(pistas[0].find('.track-name').text()).toBe('Everything In Its Right Place')
    expect(pistas[0].find('.track-duration').text()).toBe('4:11')
  })
})

describe('GameDetailView', () => {
  const GAME = {
    name: 'Hollow Knight',
    id: 7,
    developers: [{ name: 'Team Cherry' }],
    publishers: [{ name: 'Team Cherry' }],
    releaseDate: '2017-02-24',
    esrbRating: 'E10+',
    rating: 5,
    ratings_count: 12345,
    genres: [{ name: 'Metroidvania' }, { name: 'Acción' }],
    platforms: [{ platform: { name: 'PC' } }, { platform: { name: 'Nintendo Switch' } }],
    description: '<p>Un metroidvania</p><script>alert(1)</script>',
    websites: [{ url: 'https://hollowknight.com', category: 1 }],
    playtime: 30
  }

  it('pinta desarrollador, metadatos y valoraciones', async () => {
    conEstado('game', GAME)
    const wrapper = montar(GameDetailView)
    await flushPromises()

    expect(wrapper.find('.game-developer-large').text()).toContain('por Team Cherry')

    const meta = wrapper.find('.game-metadata').text()
    expect(meta).toContain('Team Cherry')
    expect(meta).toContain('2017')
    expect(meta).toContain('E10+')

    expect(wrapper.find('.game-ratings').text()).toContain('5 / 5')
    expect(wrapper.find('.rating-count').text()).toContain('12.345 valoraciones')
  })

  it('pinta géneros y plataformas como etiquetas, con su icono de marca', async () => {
    conEstado('game', GAME)
    const wrapper = montar(GameDetailView)
    await flushPromises()

    expect(wrapper.findAll('.category-tag').map((t) => t.text())).toEqual(['Metroidvania', 'Acción'])

    const plataformas = wrapper.findAll('.platform-tag')
    expect(plataformas.map((p) => p.text())).toEqual(['PC', 'Nintendo Switch'])
    expect(plataformas[0].find('i').classes()).toContain('fa-windows')
    expect(plataformas[1].find('i').classes()).toContain('fa-gamepad')
  })

  it('la descripción se pinta como HTML pero sin <script>', async () => {
    conEstado('game', GAME)
    const wrapper = montar(GameDetailView)
    await flushPromises()

    const html = wrapper.find('.game-description-content').html()
    expect(html).toContain('<p>Un metroidvania</p>')
    expect(html).not.toContain('alert(1)')
  })

  it('los enlaces externos traducen la categoría de IGDB', async () => {
    conEstado('game', GAME)
    const wrapper = montar(GameDetailView)
    await flushPromises()

    expect(wrapper.find('.external-link').text()).toContain('Sitio Oficial')
    expect(wrapper.find('.game-additional-info').text()).toContain('30 horas')
  })

  it('la rejilla de capturas sale del enriquecimiento de IGDB, con tope de seis', async () => {
    route.params = { gameId: '7' }
    conEstado('game', GAME)
    apiCall.mockResolvedValue({
      data: {
        status: 'success',
        data: {
          id: 7,
          name: 'Hollow Knight',
          detailed_screenshots: Array.from({ length: 8 }, (_, i) => ({ url: `//img/t_thumb/s${i}.jpg` }))
        }
      }
    })

    const wrapper = montar(GameDetailView)
    await flushPromises()

    const capturas = wrapper.findAll('.screenshot-thumb')
    expect(capturas).toHaveLength(6)
    expect(capturas[0].attributes('src')).toBe('https://img/t_screenshot_med/s0.jpg')
  })
})
