import { beforeEach, describe, expect, it, vi } from 'vitest'
import MediaDetailView from '@/views/shared/MediaDetailView.vue'
import BookDetailView from '@/views/BookDetailView.vue'
import MovieDetailView from '@/views/MovieDetailView.vue'
import SeriesDetailView from '@/views/SeriesDetailView.vue'
import GameDetailView from '@/views/GameDetailView.vue'
import AlbumDetailView from '@/views/AlbumDetailView.vue'
import VideoDetailView from '@/views/VideoDetailView.vue'
import { mountComponent } from './helpers/mount'

/**
 * Barrera del M3 de «Valoración en Línea de las Fichas» (2026-09-09): valorar desde
 * la ficha va por la acción propia `update_<medio>_rating` y **no** por `edit_user_*`.
 *
 * Se comprueba en los seis wrappers a la vez porque el punto de cambio es la función
 * `guardarValoracion` de cada uno, que llega a `MediaDetailView` por la prop `onRate`
 * (`MediaDetailView.vue:405-424`). Que la ficha genérica llame a esa prop lo fija ya
 * `MediaDetailView.spec.js`; lo que falta —y lo que este fichero fija— es a qué método
 * del composable apunta la función que le pasan.
 *
 * `MediaDetailView` va **stubbeado**: aquí no se mira lo que pinta la ficha, solo la
 * costura. Y los composables se doblan enteros, que es donde vive la diferencia entre
 * los dos caminos: `editUser<One>` (el del modal de edición, que sigue en pie) y
 * `update<One>Rating` (la acción propia).
 */

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: {} }),
  useRouter: () => ({ push: vi.fn(), back: vi.fn(), go: vi.fn() })
}))

vi.mock('@/store/ui', () => ({
  useUIStore: () => ({ showSuccess: vi.fn(), showError: vi.fn() })
}))

/** Un store de mentira: el wrapper solo se lo reenvía a `MediaDetailView`. */
const storeDoble = () => ({ items: [], allowedStatuses: [] })

vi.mock('@/store/books', () => ({ useBooksStore: () => storeDoble() }))
vi.mock('@/store/movies', () => ({ useMoviesStore: () => storeDoble() }))
vi.mock('@/store/games', () => ({ useGamesStore: () => storeDoble() }))
vi.mock('@/store/albums', () => ({ useAlbumsStore: () => storeDoble() }))
vi.mock('@/store/videos', () => ({ useVideosStore: () => storeDoble() }))

/**
 * Un composable doblado con los dos métodos en disputa. Se reparte por referencia
 * para poder asertar sobre él después de montar.
 */
const composableDoble = (One) => ({
  [`editUser${One}`]: vi.fn().mockResolvedValue({ success: true }),
  [`update${One}Rating`]: vi.fn().mockResolvedValue({ success: true })
})

const composables = {
  book: composableDoble('Book'),
  movie: composableDoble('Movie'),
  game: composableDoble('Game'),
  album: composableDoble('Album'),
  video: composableDoble('Video')
}

vi.mock('@/composables/useBooks', () => ({ useBooks: () => composables.book }))
vi.mock('@/composables/useMovies', () => ({ useMovies: () => composables.movie }))
vi.mock('@/composables/useGames', () => ({ useGames: () => composables.game }))
vi.mock('@/composables/useAlbums', () => ({ useAlbums: () => composables.album }))
vi.mock('@/composables/useVideos', () => ({ useVideos: () => composables.video }))

const FICHAS = [
  { nombre: 'BookDetailView', view: BookDetailView, medio: 'book', One: 'Book', id: '9780441013593' },
  { nombre: 'MovieDetailView', view: MovieDetailView, medio: 'movie', One: 'Movie', id: 'tt0903747' },
  { nombre: 'SeriesDetailView', view: SeriesDetailView, medio: 'movie', One: 'Movie', id: 'tt0944947' },
  { nombre: 'GameDetailView', view: GameDetailView, medio: 'game', One: 'Game', id: 1942 },
  { nombre: 'AlbumDetailView', view: AlbumDetailView, medio: 'album', One: 'Album', id: 87 },
  { nombre: 'VideoDetailView', view: VideoDetailView, medio: 'video', One: 'Video', id: 'dQw4w9WgXcQ' }
]

/** Monta el wrapper y devuelve la función que le pasa a `MediaDetailView` en `onRate`. */
const onRateDe = (view) => {
  const wrapper = mountComponent(view, { global: { stubs: { MediaDetailView: true } } })
  return wrapper.findComponent(MediaDetailView).props('onRate')
}

beforeEach(() => {
  Object.values(composables).forEach((c) => Object.values(c).forEach((m) => m.mockClear()))
})

describe('la valoración de la ficha va por la acción propia de rating', () => {
  it.each(FICHAS)('$nombre pasa un `onRate` a MediaDetailView', ({ view }) => {
    expect(typeof onRateDe(view)).toBe('function')
  })

  it.each(FICHAS)('$nombre valora con update<One>Rating(id, valor)', async ({ view, medio, One, id }) => {
    await onRateDe(view)(id, 4.5)

    expect(composables[medio][`update${One}Rating`]).toHaveBeenCalledWith(id, 4.5)
  })

  it.each(FICHAS)('$nombre NO valora por editUser<One>', async ({ view, medio, One, id }) => {
    await onRateDe(view)(id, 4.5)

    expect(composables[medio][`editUser${One}`]).not.toHaveBeenCalled()
  })

  it.each(FICHAS)('$nombre manda el 0 tal cual, que es «borrar mi valoración»', async ({ view, medio, One, id }) => {
    // `rating: 0` borra la valoración desde el 2026-09-09 (M1 del plan); el wrapper
    // no puede filtrarlo ni convertirlo en `null`.
    await onRateDe(view)(id, 0)

    expect(composables[medio][`update${One}Rating`]).toHaveBeenCalledWith(id, 0)
  })
})

describe('serie y película comparten el composable de películas', () => {
  it('SeriesDetailView valora con updateMovieRating, no con uno propio de serie', async () => {
    await onRateDe(SeriesDetailView)('tt0944947', 3)

    expect(composables.movie.updateMovieRating).toHaveBeenCalledWith('tt0944947', 3)
  })
})
