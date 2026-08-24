import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia, storeToRefs } from 'pinia'
import { createMediaStore } from '@/store/createMediaStore'
import { useVideosStore } from '@/store/videos'
import { useBooksStore } from '@/store/books'
import { useMoviesStore } from '@/store/movies'
import { useGamesStore } from '@/store/games'
import { useAlbumsStore } from '@/store/albums'

// El store llama al backend a través de auth.apiCall / authenticatedApiCall.
// Se mockea el módulo entero para que ni un test toque la red.
const authenticatedApiCall = vi.fn()

vi.mock('@/store/auth', () => ({
  useAuthStore: () => ({ authenticatedApiCall })
}))

// books y movies no llaman al backend directamente: pasan por _libraryCache,
// que es justo lo que garantiza una sola get_library_items para los dos.
const fetchLibraryItems = vi.fn()

vi.mock('@/store/_libraryCache', () => ({
  fetchLibraryItems: () => fetchLibraryItems()
}))

const ok = (data) => ({ data: { status: 'success', data } })

describe('createMediaStore — contrato canónico', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    authenticatedApiCall.mockReset()
  })

  it('devuelve el `useStore` sin invocar, no su resultado', () => {
    expect(typeof useVideosStore).toBe('function')
    expect(useVideosStore()).toHaveProperty('$id', 'videos')
  })

  it('storeToRefs devuelve refs reactivas del estado y de los getters', async () => {
    const store = useVideosStore()
    const { videos, totalVideos, isLoading } = storeToRefs(store)

    expect(videos.value).toEqual([])
    expect(totalVideos.value).toBe(0)
    expect(isLoading.value).toBe(false)

    authenticatedApiCall.mockResolvedValue(ok([{ id: 1, youtube_id: 'abc', title: 'Charla' }]))
    await store.fetchVideos()

    expect(videos.value).toHaveLength(1)
    expect(totalVideos.value).toBe(1)
  })

  it('state() construye un objeto nuevo por invocación: dos medios no comparten array', () => {
    // El fallo clásico de una factoría: cerrar sobre un literal en vez de
    // devolverlo desde la función, y que borrar un ítem vacíe otro medio.
    const useOtro = createMediaStore('video')
    const a = useVideosStore()
    const b = useOtro()

    a.videos.push({ id: 1, youtube_id: 'abc' })

    // Mismo id de store ⇒ misma instancia; lo que importa es que el array no
    // se comparta entre *invocaciones distintas de state()*.
    expect(useVideosStore().videos).toBe(a.videos)
    expect(b.$state.videos).toBe(a.videos)

    setActivePinia(createPinia())
    expect(useVideosStore().videos).toEqual([])
  })
})

describe('createMediaStore — alias con nombre de medio', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    authenticatedApiCall.mockReset()
  })

  it('expone los alias que usan los consumidores actuales', () => {
    const store = useVideosStore()

    const alias = [
      'fetchVideos', 'searchVideos', 'addVideo', 'deleteVideo',
      'updateVideoRating', 'updateVideoStatuses', 'editVideo', 'updateVideoTags',
      'fetchAllowedStatuses', 'fetchUserTags', 'createTag',
      'clearSearchResults', 'clearError'
    ]
    alias.forEach((name) => expect(typeof store[name], name).toBe('function'))

    const getters = [
      'totalVideos', 'hasVideos', 'videosWithRating', 'videosByStatus',
      'videoCountByStatus', 'getVideoById', 'getVideoByYouTubeId',
      'isVideoInLibrary', 'hasSearchResults', 'averageRating'
    ]
    getters.forEach((name) => expect(store[name], name).toBeDefined())
  })

  it('los getters por id distinguen la PK del id de YouTube', () => {
    const store = useVideosStore()
    store.videos.push({ id: 7, youtube_id: 'abc', user_rating: 4 })

    expect(store.getVideoById(7)).toBeDefined()
    expect(store.getVideoByYouTubeId('abc')).toBeDefined()
    expect(store.getVideoById('abc')).toBeUndefined()
    expect(store.isVideoInLibrary('abc')).toBe(true)
    expect(store.videosWithRating).toHaveLength(1)
  })
})

describe('createMediaStore — cada acción llama a la acción correcta del backend', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    authenticatedApiCall.mockReset()
  })

  it('fetchVideos manda get_videos con el payload de filtros', async () => {
    authenticatedApiCall.mockResolvedValue(ok([]))
    await useVideosStore().fetchVideos()

    expect(authenticatedApiCall).toHaveBeenCalledWith('get_videos', { filters: {} })
  })

  it('searchVideos manda search_youtube_videos con la clave `q`', async () => {
    authenticatedApiCall.mockResolvedValue(ok([]))
    await useVideosStore().searchVideos('vue')

    expect(authenticatedApiCall).toHaveBeenCalledWith('search_youtube_videos', { q: 'vue' })
  })

  it('addVideo normaliza el ítem de la búsqueda al payload de add_video', async () => {
    authenticatedApiCall.mockResolvedValue(ok({ youtube_id: 'abc' }))
    const store = useVideosStore()

    const result = await store.addVideo({ id: 'abc', title: 'Charla', channelName: 'Canal X' }, [1])

    const [action, payload] = authenticatedApiCall.mock.calls[0]
    expect(action).toBe('add_video')
    expect(payload.youtube_id).toBe('abc')
    expect(payload.channel_name).toBe('Canal X')
    expect(payload.categories).toEqual([])
    expect(payload.userStatuses).toEqual([1])
    // La clave con nombre de medio se conserva para los consumidores viejos.
    expect(result.video).toEqual({ youtube_id: 'abc' })
    expect(store.videos).toHaveLength(1)
  })

  it('deleteVideo, updateVideoRating y updateVideoStatuses casan por youtube_id', async () => {
    const store = useVideosStore()
    store.videos.push({ id: 7, youtube_id: 'abc' })
    authenticatedApiCall.mockResolvedValue(ok(null))

    await store.updateVideoRating('abc', 5)
    expect(authenticatedApiCall).toHaveBeenCalledWith('update_video_rating', { youtubeId: 'abc', rating: 5 })
    expect(store.videos[0].user_rating).toBe(5)

    await store.updateVideoStatuses('abc', [2])
    expect(authenticatedApiCall).toHaveBeenCalledWith('update_video_user_statuses', { youtubeId: 'abc', statuses: [2] })
    expect(store.videos[0].userStatuses).toEqual([2])

    await store.deleteVideo('abc')
    expect(authenticatedApiCall).toHaveBeenCalledWith('delete_video', { youtubeId: 'abc' })
    expect(store.videos).toHaveLength(0)
  })

  it('updateVideoTags manda `videoId`, que es la única acción que no usa youtubeId', async () => {
    authenticatedApiCall.mockResolvedValue(ok(null))
    await useVideosStore().updateVideoTags(7, [1, 2])

    expect(authenticatedApiCall).toHaveBeenCalledWith('update_video_tags', { videoId: 7, tag_ids: [1, 2] })
  })

  it('editVideo mezcla los cambios en el ítem ya cargado', async () => {
    const store = useVideosStore()
    store.videos.push({ id: 7, youtube_id: 'abc', title: 'Viejo' })
    authenticatedApiCall.mockResolvedValue(ok(null))

    await store.editVideo('abc', { title: 'Nuevo' })

    expect(authenticatedApiCall).toHaveBeenCalledWith('edit_user_video', { youtubeId: 'abc', title: 'Nuevo' })
    expect(store.videos[0]).toEqual({ id: 7, youtube_id: 'abc', title: 'Nuevo' })
  })

  it('createTag usa el color por defecto declarado en el registry', async () => {
    authenticatedApiCall.mockResolvedValue(ok({ id: 1, name: 'Tutoriales' }))
    await useVideosStore().createTag('Tutoriales')

    expect(authenticatedApiCall).toHaveBeenCalledWith('create_user_video_tag', {
      name: 'Tutoriales',
      color: '#c0392b'
    })
  })

  it('un error del backend deja `error` puesto y no rompe la acción', async () => {
    authenticatedApiCall.mockResolvedValue({ data: { status: 'error', message: 'boom' } })
    const store = useVideosStore()

    const result = await store.deleteVideo('abc')

    expect(result.success).toBe(false)
    expect(store.error).toBeTruthy()
    expect(store.isLoading).toBe(false)
  })
})

describe('createMediaStore — los cinco medios', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    authenticatedApiCall.mockReset()
    fetchLibraryItems.mockReset()
  })

  const medios = [
    ['book', useBooksStore, 'books', 'Books', 'Book'],
    ['movie', useMoviesStore, 'movies', 'Movies', 'Movie'],
    ['game', useGamesStore, 'games', 'Games', 'Game'],
    ['album', useAlbumsStore, 'albums', 'Albums', 'Album'],
    ['video', useVideosStore, 'videos', 'Videos', 'Video']
  ]

  it.each(medios)('%s expone estado y alias con su propio nombre', (media, useStore, collection, Many, One) => {
    const store = useStore()

    expect(store[collection]).toEqual([])
    expect(store.allowedStatuses).toEqual([])
    expect(typeof store[`fetch${Many}`]).toBe('function')
    expect(typeof store[`add${One}`]).toBe('function')
    expect(typeof store[`delete${One}`]).toBe('function')
    expect(typeof store[`update${One}Rating`]).toBe('function')
    expect(typeof store[`update${One}Statuses`]).toBe('function')
    expect(typeof store[`edit${One}`]).toBe('function')
    expect(typeof store[`update${One}Tags`]).toBe('function')
    expect(store[`total${Many}`]).toBe(0)
    expect(store[`has${Many}`]).toBe(false)
  })

  it('los cinco estados son independientes: llenar uno no toca a los demás', () => {
    const stores = medios.map(([, useStore]) => useStore())
    stores[0].books.push({ isbn: '1' })

    expect(stores[0].books).toHaveLength(1)
    expect(stores[1].movies).toHaveLength(0)
    expect(stores[2].games).toHaveLength(0)
    expect(stores[3].albums).toHaveLength(0)
    expect(stores[4].videos).toHaveLength(0)
  })

  it('books y movies leen de _libraryCache, no del backend', async () => {
    fetchLibraryItems.mockResolvedValue({
      books: [{ isbn: '1', title: 'Dune' }],
      movies: [{ isbn: 'tt1', title: 'Alien' }]
    })

    await Promise.all([useBooksStore().fetchBooks(), useMoviesStore().fetchMovies()])

    // Una sola llamada al caché por store, y CERO llamadas sueltas al backend:
    // la deduplicación en vuelo la sigue haciendo _libraryCache.
    expect(authenticatedApiCall).not.toHaveBeenCalled()
    expect(useBooksStore().books[0].itemType).toBe('book')
    expect(useMoviesStore().movies[0].itemType).toBe('movie')
  })

  it.each([
    ['game', useGamesStore, 'get_games'],
    ['album', useAlbumsStore, 'get_albums'],
    ['video', useVideosStore, 'get_videos']
  ])('%s tiene acción propia de listado', async (media, useStore, action) => {
    authenticatedApiCall.mockResolvedValue(ok([]))
    await useStore().fetch()

    expect(authenticatedApiCall).toHaveBeenCalledWith(action, { filters: {} })
  })

  it('la búsqueda de libros elige entre ISBN y título', async () => {
    authenticatedApiCall.mockResolvedValue(ok([]))
    const store = useBooksStore()

    await store.searchBooks('9788445071410')
    expect(authenticatedApiCall).toHaveBeenLastCalledWith('search_book_isbn', { isbn: '9788445071410' })

    await store.searchBooks('Dune')
    expect(authenticatedApiCall).toHaveBeenLastCalledWith('search_book_name', { name: 'Dune' })
  })

  it.each([
    ['book', useBooksStore, 'add_book', 'book'],
    ['movie', useMoviesStore, 'add_movie', 'movie'],
    ['game', useGamesStore, 'add_game', 'game'],
    ['album', useAlbumsStore, 'add_album', 'album']
  ])('%s anida el payload de alta bajo su clave', async (media, useStore, action, key) => {
    authenticatedApiCall.mockResolvedValue(ok({}))
    await useStore().add({ isbn: '1', imdbID: 'tt1', id: 1, title: 'X' }, [])

    const llamada = authenticatedApiCall.mock.calls.find((c) => c[0] === action)
    expect(llamada[1]).toHaveProperty(key)
  })

  it('el alta de vídeos va plana, sin clave envolvente', async () => {
    authenticatedApiCall.mockResolvedValue(ok({}))
    await useVideosStore().addVideo({ id: 'abc', title: 'X' }, [])

    expect(authenticatedApiCall.mock.calls[0][1]).toHaveProperty('youtube_id')
    expect(authenticatedApiCall.mock.calls[0][1]).not.toHaveProperty('video')
  })

  it.each([
    ['book', useBooksStore, 'delete_book', { isbn: '1', itemType: 'book' }, 'deleteBook', '1'],
    ['movie', useMoviesStore, 'delete_movie', { isbn: 'tt1', itemType: 'movie' }, 'deleteMovie', 'tt1'],
    ['game', useGamesStore, 'delete_game', { gameId: 5, itemType: 'game' }, 'deleteGame', 5],
    ['album', useAlbumsStore, 'delete_album', { albumId: 9 }, 'deleteAlbum', 9]
  ])('%s borra con su clave de id y su itemType', async (media, useStore, action, payload, alias, id) => {
    authenticatedApiCall.mockResolvedValue(ok(null))
    await useStore()[alias](id)

    expect(authenticatedApiCall).toHaveBeenCalledWith(action, payload)
  })

  it('libros y películas agrupan por nombre de estado; el resto por status_id', () => {
    const books = useBooksStore()
    books.books.push({ isbn: '1', userStatuses: ['leyendo'] })

    // Objeto ya calculado, no una función.
    expect(books.booksByStatus).toEqual({ leyendo: [{ isbn: '1', userStatuses: ['leyendo'] }] })
    expect(books.bookCountByStatus).toEqual({ leyendo: 1 })

    const games = useGamesStore()
    games.games.push({ id: 1, userStatuses: [{ status_id: 3 }] })

    // Función que recibe el status_id.
    expect(typeof games.gamesByStatus).toBe('function')
    expect(games.gamesByStatus(3)).toHaveLength(1)
  })

  it('cada medio casa el ítem con su propio identificador', () => {
    const movies = useMoviesStore()
    movies.movies.push({ id: 'x', imdbID: 'tt1', isbn: 'tt1' })
    expect(movies.getMovieById('tt1')).toBeDefined()
    expect(movies.isMovieInLibrary('tt1')).toBe(true)

    const games = useGamesStore()
    games.games.push({ id: 1, rawgId: 99, gameId: 42 })
    expect(games.getGameById(99)).toBeDefined()
    expect(games.getGameById(42)).toBeDefined()

    const books = useBooksStore()
    books.books.push({ isbn: '1' })
    expect(books.getBookByIsbn('1')).toBeDefined()

    const albums = useAlbumsStore()
    albums.albums.push({ id: 3, spotify_id: 'sp' })
    expect(albums.getAlbumBySpotifyId('sp')).toBeDefined()
  })

  it('el color por defecto de etiqueta es #007bff salvo en vídeos', async () => {
    authenticatedApiCall.mockResolvedValue(ok({ id: 1 }))

    await useBooksStore().createTag('X')
    expect(authenticatedApiCall).toHaveBeenLastCalledWith('create_user_book_tag', { name: 'X', color: '#007bff' })

    await useVideosStore().createTag('X')
    expect(authenticatedApiCall).toHaveBeenLastCalledWith('create_user_video_tag', { name: 'X', color: '#c0392b' })
  })
})

describe('createMediaStore — lo que destructuran los composables sigue estando', () => {
  beforeEach(() => setActivePinia(createPinia()))

  // Extraído de los storeToRefs y los destructuring de useBooks / useMovies /
  // useGames / useAlbums: si la factoría deja de generar uno de estos nombres,
  // el composable rompe en tiempo de ejecución y no en el test que lo mira.
  const comunes = [
    'allowedStatuses', 'userTags', 'isLoading', 'error', 'searchResults',
    'isSearching', 'lastSearchQuery', 'hasSearchResults', 'averageRating',
    'fetchAllowedStatuses', 'fetchUserTags', 'createTag',
    'clearSearchResults', 'clearError'
  ]

  it.each([
    [useBooksStore, ['books', 'totalBooks', 'hasBooks', 'booksWithRating', 'booksByStatus',
      'bookCountByStatus', 'fetchBooks', 'searchBooks', 'updateBookTags']],
    [useMoviesStore, ['movies', 'totalMovies', 'hasMovies', 'moviesWithRating', 'moviesByStatus',
      'movieCountByStatus', 'fetchMovies', 'searchMovies', 'updateMovieTags']],
    [useGamesStore, ['games', 'totalGames', 'hasGames', 'gamesWithRating', 'gamesByStatus',
      'gameCountByStatus', 'fetchGames', 'searchGames', 'updateGameTags']],
    [useAlbumsStore, ['albums', 'totalAlbums', 'hasAlbums', 'albumsWithRating', 'albumsByStatus',
      'albumCountByStatus', 'fetchAlbums', 'searchAlbums', 'updateAlbumTags']]
  ])('el store expone todo lo que su composable destructura', (useStore, propios) => {
    const store = useStore()
    ;[...comunes, ...propios].forEach((name) => expect(store[name], name).toBeDefined())
  })
})
