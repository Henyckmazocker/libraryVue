import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createMediaComposable } from '@/composables/createMediaComposable'
import { useAlbums } from '@/composables/useAlbums'
import { useBooks } from '@/composables/useBooks'
import { useMovies } from '@/composables/useMovies'
import { useGames } from '@/composables/useGames'
import { useVideos } from '@/composables/useVideos'
import { useAlbumsStore } from '@/store/albums'

// Ni un test toca la red: la factoría llama al backend por authenticatedApiCall.
const authenticatedApiCall = vi.fn()

vi.mock('@/store/auth', () => ({
  useAuthStore: () => ({ authenticatedApiCall })
}))

// La confirmación de borrado abre un modal; aquí solo interesa su respuesta.
const confirmDelete = vi.fn()

vi.mock('@/composables/useConfirmationModal', () => ({
  useConfirmationModal: () => ({ confirmDelete })
}))

const ok = (data) => ({ data: { status: 'success', data } })

describe('createMediaComposable — la superficie pública no cambia', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    authenticatedApiCall.mockReset()
    confirmDelete.mockReset()
  })

  it('useAlbums expone la superficie que le quedó tras la poda del M6', () => {
    const composable = useAlbums()

    // La sentencia `return` del useAlbums.js de 283 líneas, menos lo que el
    // M6 podó por no tener consumidor: `filterAlbums` y `findAlbumBySpotifyId`.
    expect(Object.keys(composable).sort()).toEqual([
      'addAlbum',
      'albumCountByStatus',
      'albums',
      'albumsByStatus',
      'albumsWithRating',
      'allowedStatuses',
      'averageRating',
      'clearError',
      'clearSearchResults',
      'createUserTag',
      'deleteAlbum',
      'editUserAlbum',
      'error',
      'fetchAlbums',
      'fetchAllowedStatuses',
      'fetchUserTags',
      'findAlbumById',
      'getAlbumTags',
      'hasAlbums',
      'hasSearchResults',
      'isLoading',
      'isSearching',
      'lastSearchQuery',
      'searchAlbums',
      'searchResults',
      'totalAlbums',
      'updateAlbumRating',
      'updateAlbumStatuses',
      'updateAlbumTags',
      'userTags'
    ])
  })

  it('el estado llega por refs vivas del store, no por una copia', async () => {
    const { albums, totalAlbums, hasAlbums } = useAlbums()
    const store = useAlbumsStore()

    expect(albums.value).toEqual([])
    expect(hasAlbums.value).toBe(false)

    authenticatedApiCall.mockResolvedValue(ok([{ id: 1, title: 'Kind of Blue', artist: 'Miles Davis' }]))
    await store.fetchAlbums()

    expect(albums.value).toHaveLength(1)
    expect(totalAlbums.value).toBe(1)
  })

  it('un medio no comparte estado con otro: dos invocaciones ven su propio array', () => {
    const a = useAlbums()
    const b = createMediaComposable('video')

    a.albums.value.push({ id: 1 })

    expect(a.albums.value).toHaveLength(1)
    expect(b.videos.value).toHaveLength(0)
  })

  it('deleteAlbum pide confirmación con el título del álbum y se rinde si se cancela', async () => {
    const { albums, deleteAlbum } = useAlbums()
    albums.value.push({ id: 7, title: 'Bitches Brew' })
    confirmDelete.mockResolvedValue(false)

    const result = await deleteAlbum(7)

    expect(confirmDelete).toHaveBeenCalledWith('Bitches Brew', 'Esta acción no se puede deshacer')
    expect(result).toEqual({ success: false, cancelled: true })
    expect(authenticatedApiCall).not.toHaveBeenCalled()
  })

  it('editUserAlbum manda la clave de id del registry y sincroniza los campos declarados', async () => {
    const { albums, editUserAlbum } = useAlbums()
    albums.value.push({ id: 7, title: 'Bitches Brew', user_rating: 3, listenCount: 1 })
    authenticatedApiCall.mockResolvedValue(ok(null))

    const result = await editUserAlbum(7, 42, { personalRating: 5, listenCount: 9 })

    expect(result).toEqual({ success: true })
    expect(authenticatedApiCall).toHaveBeenCalledWith('edit_user_album', {
      albumId: 7,
      userId: 42,
      data: { personalRating: 5, listenCount: 9 },
      tags: [],
      notes: []
    })
    // Lo que no viene en `data` se conserva; lo que viene, se propaga.
    expect(albums.value[0]).toMatchObject({
      title: 'Bitches Brew',
      user_rating: 5,
      listenCount: 9
    })
  })

  it('getAlbumTags usa la acción `tags.get` del registry', async () => {
    const { getAlbumTags } = useAlbums()
    authenticatedApiCall.mockResolvedValue(ok([{ id: 1, name: 'jazz' }]))

    const result = await getAlbumTags(7)

    expect(authenticatedApiCall).toHaveBeenCalledWith('get_album_tags', { albumId: 7 })
    expect(result).toEqual({ success: true, data: [{ id: 1, name: 'jazz' }] })
  })

  it('createUserTag rechaza un nombre vacío sin llamar al backend', async () => {
    const { createUserTag } = useAlbums()

    expect(await createUserTag('   ')).toEqual({ success: false, message: 'Tag name cannot be empty' })
    expect(authenticatedApiCall).not.toHaveBeenCalled()
  })

  it('un medio sin bloque `store` no puede tener composable', () => {
    // `series` es la sexta entrada del registry y comparte el store de películas.
    expect(() => createMediaComposable('series')).toThrow(/no declara los bloques/)
  })
})

describe('createMediaComposable — cada medio conserva su superficie exacta', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    authenticatedApiCall.mockReset()
  })

  // Las tres listas salen de la sentencia `return` de los composables de 496,
  // 315 y 288 líneas —el commit anterior al refactor— menos lo que el M6 podó
  // por no tener ni un consumidor. Si la factoría pierde o inventa un miembro,
  // el consumidor no se enteraría hasta ejecutarlo: esto sí.
  it('useBooks conserva sus dos extras con consumidor y ninguno más', () => {
    expect(Object.keys(useBooks()).sort()).toEqual([
      'addBook',
      'allowedStatuses',
      'averageRating',
      'bookCountByStatus',
      'books',
      'booksByStatus',
      'booksWithRating',
      'clearError',
      'clearSearchResults',
      'createUserTag',
      'deleteBook',
      'editUserBook',
      'error',
      'fetchAllowedStatuses',
      'fetchBooks',
      'fetchUserTags',
      'findBookByISBN',
      'getBookTags',
      'hasBooks',
      'hasSearchResults',
      'isLoading',
      'isSearching',
      'lastSearchQuery',
      'searchBooks',
      'searchResults',
      'totalBooks',
      'updateBookRating',
      'updateBookStatuses',
      'updateBookTags',
      'updateReadingProgress',
      'userTags'
    ])
  })

  it('useMovies conserva el seguimiento de temporadas', () => {
    expect(Object.keys(useMovies()).sort()).toEqual([
      'addMovie',
      'allowedStatuses',
      'averageRating',
      'clearError',
      'clearSearchResults',
      'createUserTag',
      'deleteMovie',
      'editUserMovie',
      'error',
      'fetchAllowedStatuses',
      'fetchMovies',
      'fetchUserTags',
      'findMovieById',
      'getMovieTags',
      'getSeriesProgress',
      'hasMovies',
      'hasSearchResults',
      'isLoading',
      'isSearching',
      'lastSearchQuery',
      'movieCountByStatus',
      'movies',
      'moviesByStatus',
      'moviesWithRating',
      'searchMovies',
      'searchResults',
      'totalMovies',
      'trackSeriesSeason',
      'updateMovieRating',
      'updateMovieStatuses',
      'updateMovieTags',
      'userTags'
    ])
  })

  it('useGames queda sin extras tras la poda', () => {
    expect(Object.keys(useGames()).sort()).toEqual([
      'addGame',
      'allowedStatuses',
      'averageRating',
      'clearError',
      'clearSearchResults',
      'createUserTag',
      'deleteGame',
      'editUserGame',
      'error',
      'fetchAllowedStatuses',
      'fetchGames',
      'fetchUserTags',
      'findGameById',
      'gameCountByStatus',
      'games',
      'gamesByStatus',
      'gamesWithRating',
      'getGameTags',
      'hasGames',
      'hasSearchResults',
      'isLoading',
      'isSearching',
      'lastSearchQuery',
      'searchGames',
      'searchResults',
      'totalGames',
      'updateGameRating',
      'updateGameStatuses',
      'updateGameTags',
      'userTags'
    ])
  })

  it('useVideos es el quinto y nace sin extras, con la misma forma que los otros', () => {
    const keys = Object.keys(useVideos()).sort()

    // Exactamente los mismos que álbumes: ninguno de los dos tiene extras.
    expect(keys).toEqual([
      'addVideo',
      'allowedStatuses',
      'averageRating',
      'clearError',
      'clearSearchResults',
      'createUserTag',
      'deleteVideo',
      'editUserVideo',
      'error',
      'fetchAllowedStatuses',
      'fetchUserTags',
      'fetchVideos',
      'findVideoById',
      'getVideoTags',
      'hasSearchResults',
      'hasVideos',
      'isLoading',
      'isSearching',
      'lastSearchQuery',
      'searchResults',
      'searchVideos',
      'totalVideos',
      'updateVideoRating',
      'updateVideoStatuses',
      'updateVideoTags',
      'userTags',
      'videoCountByStatus',
      'videos',
      'videosByStatus',
      'videosWithRating'
    ])
  })

  it('editUserVideo emite el payload que EditUserVideoCommand espera', async () => {
    // El brazo de vídeos de `useItemEdit` pasó por aquí en el M5: el backend
    // lee el payload anidado y los `tags` de la raíz, y la clave del id es
    // `youtubeId`.
    const { videos, editUserVideo } = useVideos()
    videos.value.push({ id: 1, youtube_id: 'abc123', title: 'Charla', user_rating: 0, personalNotes: '' })
    authenticatedApiCall.mockResolvedValue(ok(null))

    const result = await editUserVideo('abc123', 42, { personalRating: 4, personalNotes: 'buena' }, [7], [])

    expect(result).toEqual({ success: true })
    expect(authenticatedApiCall).toHaveBeenCalledWith('edit_user_video', {
      youtubeId: 'abc123',
      userId: 42,
      data: { personalRating: 4, personalNotes: 'buena' },
      tags: [7],
      notes: []
    })
    expect(videos.value[0]).toMatchObject({ user_rating: 4, personalNotes: 'buena' })
  })

  it('el extra de un medio SUSTITUYE al miembro del núcleo cuando comparten nombre', async () => {
    // `updateBookStatuses` no es la delegación de tres líneas del núcleo: es la
    // máquina de transiciones, y tiene que ganar.
    const { updateBookStatuses } = useBooks()

    const result = await updateBookStatuses('9780000000001', ['read'])

    expect(result).toEqual({ success: false, message: 'Book not found' })
    expect(authenticatedApiCall).not.toHaveBeenCalled()
  })

  it('deleteBook avisa de las sesiones y numera el fallback con ISBN, no con ID', async () => {
    const { deleteBook } = useBooks()
    confirmDelete.mockResolvedValue(false)

    await deleteBook('9780000000001')

    expect(confirmDelete).toHaveBeenCalledWith(
      'ISBN: 9780000000001',
      'También se eliminarán todas las sesiones de lectura asociadas'
    )
  })

})
