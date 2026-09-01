/**
 * mediaRegistry — una entrada por medio.
 *
 * Los componentes genéricos de `components/shared/` (MediaNotes y los que
 * vengan) se configuran desde aquí en vez de existir cinco veces. El patrón es
 * el mismo que GenericSearch.vue ya usa con los cinco *Search.vue: un objeto de
 * configuración por medio, y el componente por medio queda como wrapper.
 *
 * Cada campo recoge la realidad actual del código, no un ideal: donde el
 * backend impone un nombre, el nombre se respeta y se comenta.
 */

import tmdbLogo from '@/assets/tmdbLogo.svg'
// Los rótulos salen del catálogo y se leen con getters, no con valores: este módulo
// se importa ANTES de que el catálogo esté cargado, así que un valor se congelaría
// con el catálogo vacío. Un getter se evalúa al leerlo y además queda suscrito, así
// que la interfaz cambia de idioma sin recargar.
import { t } from '@/config/i18n'
import { formatDate } from '@/utils/dates'

const NOTE_TYPES_DEFAULT = [
  { get label() { return t('media.noteTypes.note'); }, value: 'note' },
  { get label() { return t('media.noteTypes.review'); }, value: 'review' },
  { get label() { return t('media.noteTypes.thought'); }, value: 'thought' }
]

// Libros no comparten los tipos de nota con el resto: son notas de lectura.
const NOTE_TYPES_EDITION = [
  { get label() { return t('media.noteTypes.note'); }, value: 'note' },
  { get label() { return t('media.noteTypes.quote'); }, value: 'quote' },
  { get label() { return t('media.noteTypes.reflection'); }, value: 'thought' },
  { get label() { return t('media.noteTypes.question'); }, value: 'question' },
  { get label() { return t('media.noteTypes.summary'); }, value: 'summary' },
  { get label() { return t('media.noteTypes.progress'); }, value: 'progress' },
  { get label() { return t('media.noteTypes.general'); }, value: 'general' }
]

const NOTE_TYPE_ICONS_DEFAULT = {
  note: 'pi-file-edit',
  review: 'pi-star',
  thought: 'pi-lightbulb'
}

const NOTE_TYPE_ICONS_EDITION = {
  note: 'pi-pencil',
  quote: 'pi-comment',
  thought: 'pi-lightbulb',
  question: 'pi-question-circle',
  summary: 'pi-list',
  progress: 'pi-chart-line',
  general: 'pi-file'
}

/** Año de una fecha en cualquiera de los formatos que devuelven las APIs. */
function yearOf (date) {
  if (!date) return ''
  return date.toString().substring(0, 4)
}

/** Une sujeto y detalle con el separador que ya usaban los cinco *ListItem. */
function joinSubtitle (subject, detail) {
  return detail ? `${subject} • ${detail}` : subject
}

/** ¿Es una serie? El campo llega con tres nombres distintos según el origen. */
function isSeries (item) {
  const type = item.media_type || item.mediaType || item.type || 'movie'
  return type === 'series'
}

/** Plataformas de un juego, recortadas a dos y en una sola cadena. */
function platformsText (game) {
  if (typeof game.platforms === 'string') {
    const platforms = game.platforms.split(',').map((p) => p.trim())
    return platforms.length > 2 ? `${platforms.slice(0, 2).join(', ')}...` : game.platforms
  }
  if (Array.isArray(game.platforms)) {
    const names = game.platforms
      .map((p) => (typeof p === 'string' ? p : p.platform?.name || p.name))
      .filter(Boolean)
    return names.length > 2 ? `${names.slice(0, 2).join(', ')}...` : names.join(', ')
  }
  return ''
}

/** Icono de marca de la primera plataforma reconocida. */
function platformIcon (text) {
  const platforms = text.toLowerCase()
  if (platforms.includes('playstation') || platforms.includes('ps')) return 'fab fa-playstation'
  if (platforms.includes('xbox')) return 'fab fa-xbox'
  if (platforms.includes('nintendo') || platforms.includes('switch')) return 'fas fa-gamepad'
  if (platforms.includes('pc') || platforms.includes('windows')) return 'fab fa-windows'
  if (platforms.includes('linux')) return 'fab fa-linux'
  if (platforms.includes('mac')) return 'fab fa-apple'
  if (platforms.includes('android') || platforms.includes('ios')) return 'fas fa-mobile-alt'
  return 'fas fa-gamepad'
}

/** Une una lista de strings u objetos {name} con comas. Tres medios la tenían. */
function joinNames (value) {
  if (typeof value === 'string') return value
  if (Array.isArray(value)) {
    return value.map((v) => (typeof v === 'string' ? v : v.platform?.name || v.name)).filter(Boolean).join(', ')
  }
  return ''
}

/** Etiqueta del formato de propiedad, que llega en camelCase o snake_case. */
function ownershipLabel (item) {
  return item?.ownershipFormat?.label ?? item?.ownership_format?.label ?? ''
}

/** Duración de un álbum: de milisegundos a `1h 12m` o `43:07`. */
function albumDuration (album) {
  const ms = album?.duration_ms || album?.durationMs
  if (!ms) return ''
  const totalSec = Math.floor(ms / 1000)
  const hours = Math.floor(totalSec / 3600)
  const minutes = Math.floor((totalSec % 3600) / 60)
  const seconds = totalSec % 60
  if (hours > 0) return `${hours}h ${minutes}m`
  return `${minutes}:${String(seconds).padStart(2, '0')}`
}

/** Clase del badge de Metacritic. Solo los juegos lo pintan. */
function metacriticClass (score) {
  if (score >= 75) return 'score-high'
  if (score >= 50) return 'score-medium'
  return 'score-low'
}

/** Pasa la respuesta de IGDB a la forma que usa la ficha de juego. */
export function transformIgdbGame (igdbGame) {
  const developers = igdbGame.involved_companies
    ?.filter((ic) => ic.developer)
    .map((ic) => ({ name: ic.company?.name || 'Unknown' })) || []

  const publishers = igdbGame.involved_companies
    ?.filter((ic) => ic.publisher)
    .map((ic) => ({ name: ic.company?.name || 'Unknown' })) || []

  const releaseDate = igdbGame.first_release_date
    ? new Date(igdbGame.first_release_date * 1000).toISOString().split('T')[0]
    : null

  // La clasificación por edad. Se prefiere PEGI, que es la que aplica en España, y se
  // cae a ESRB si el juego no la tiene; las demás organizaciones de IGDB (3 CERO,
  // 4 USK, 5 GRAC, 6 CLASS_IND, 7 ACB) no se usan. No se elige por idioma activo:
  // `en` no dice si es Reino Unido (PEGI) o Estados Unidos (ESRB).
  //
  // Antes se filtraba por `r.category === 1`, y `category` es un campo que IGDB retiró:
  // como la API **ignora en silencio** lo que no conoce, la clasificación llegaba
  // siempre sin él, el `find` no encontraba nada y el badge de
  // `GameDetailView.vue:31-35` no se pintaba nunca.
  const ORGANISMOS_EDAD = [2, 1] // PEGI primero, ESRB de reserva
  const clasificacion = ORGANISMOS_EDAD
    .map((id) => igdbGame.age_ratings?.find((r) => r.organization?.id === id))
    .find(Boolean)
  // Con el organismo delante: el valor de PEGI es un número desnudo («16») y solo no
  // significa nada. `organization.name` viene en la misma respuesta.
  const esrbRating = clasificacion?.rating_category?.rating
    ? `${clasificacion.organization?.name ?? ''} ${clasificacion.rating_category.rating}`.trim()
    : ''

  return {
    id: igdbGame.id,
    igdbId: igdbGame.id,
    gameId: igdbGame.id,
    title: igdbGame.name,
    name: igdbGame.name,
    originalTitle: igdbGame.name,
    releaseDate,
    released: releaseDate,
    coverUrl: igdbGame.cover?.url ? `https:${igdbGame.cover.url.replace('t_thumb', 't_cover_big')}` : null,
    background_image: igdbGame.cover?.url ? `https:${igdbGame.cover.url.replace('t_thumb', 't_1080p')}` : null,
    description: igdbGame.summary || '',
    description_raw: igdbGame.summary || '',
    rating: igdbGame.rating ? Math.round(igdbGame.rating / 20) : null,
    ratings_count: igdbGame.rating_count || 0,
    // Los dos llevan la CADENA («E10+», «M»), no el objeto: es lo que guarda la columna
    // `games.esrb_rating` (varchar) y lo que el badge sabe pintar. La rama de objeto que
    // queda en `GameDetailView.vue:35` es tolerancia a lo guardado antes, no a esto.
    esrb_rating: esrbRating,
    esrbRating,
    platforms: igdbGame.platforms || [],
    genres: igdbGame.genres || [],
    developers,
    publishers,
    websites: igdbGame.websites || [],
    user_rating: null,
    userStatuses: [],
    itemType: 'game'
  }
}

/** Pasa la respuesta de OMDb a la forma que usan las fichas de película y serie. */
function transformOmdb (omdb) {
  if (!omdb) return null

  const genres = omdb.Genre && omdb.Genre !== 'N/A'
    ? omdb.Genre.split(', ').map((g) => g.trim())
    : []

  return {
    isbn: omdb.imdbID,
    imdbID: omdb.imdbID,
    title: omdb.Title,
    originalTitle: omdb.Title,
    director: omdb.Director !== 'N/A' ? omdb.Director : null,
    author: omdb.Director !== 'N/A' ? omdb.Director : null,
    year: omdb.Year,
    coverUrl: omdb.Poster !== 'N/A' ? omdb.Poster : null,
    user_rating: 0,
    userStatuses: [],
    itemType: 'movie',
    genres,
    plot: omdb.Plot,
    imdbRating: omdb.imdbRating,
    imdbVotes: omdb.imdbVotes,
    metascore: omdb.Metascore,
    rated: omdb.Rated,
    runtime: omdb.Runtime,
    language: omdb.Language,
    country: omdb.Country,
    awards: omdb.Awards,
    actors: omdb.Actors,
    writer: omdb.Writer,
    production: omdb.Production,
    boxOffice: omdb.BoxOffice,
    released: omdb.Released,
    dvd: omdb.DVD,
    website: omdb.Website,
    type: omdb.Type ? omdb.Type.toLowerCase() : 'movie',
    totalSeasons: omdb.totalSeasons && omdb.totalSeasons !== 'N/A'
      ? parseInt(omdb.totalSeasons)
      : null,
    ratings: omdb.Ratings || []
  }
}

/** Campos de biblioteca que película y serie mezclan en el ítem cargado. */
function omdbMergeFields (existing) {
  return {
    user_rating: existing.user_rating,
    userStatuses: existing.userStatuses || [],
    ownershipFormat: existing.ownershipFormat ?? existing.ownership_format ?? null,
    ownership_format: existing.ownership_format ?? existing.ownershipFormat ?? null,
    ownership_format_id: existing.ownership_format_id ?? existing.ownershipFormat?.id ?? null,
    tags: existing.tags ?? null
  }
}

/** Datos de OpenLibrary con los que se completa una ficha de libro. */
function openLibraryFields (olData, edition) {
  const fields = {}

  if (edition?.works?.length > 0) {
    // "/works/OL123456W" → "OL123456W"
    fields.work_key = edition.works[0].key.split('/').pop()
  }
  if (!olData) return fields

  if (olData.subjects?.length > 0) fields.subjects = olData.subjects
  if (olData.url) fields.openLibraryUrl = olData.url
  if (olData.classifications?.lc_classifications) {
    fields.classifications = { lc: olData.classifications.lc_classifications }
  }
  const pages = olData.number_of_pages ||
    (olData.pagination ? (parseInt(String(olData.pagination)) || null) : null)
  if (pages) fields.pages = pages

  return fields
}

// ─── Búsqueda: de la forma del proveedor a la que pinta la lista ────────────
//
// Estas seis viven aquí y no dentro de cada `*Search.vue` porque el buscador
// general las necesita todas a la vez, y tenerlas en los SFC obligaría a
// escribirlas dos veces. Son las mismas que estaban en los `transformResult` de
// los cinco wrappers, movidas sin cambiarlas.
//
// NO se fusionan con `transformOmdb` ni con `transformIgdbGame`, que son las de
// la FICHA: aquellas devuelven la forma rica del detalle y `transformOmdb`
// además fija `itemType: 'movie'`, con lo que perdería el `type` de OMDb
// —`movie` | `series` | `episode`— que es justo por donde se separan películas
// y series.

/**
 * La portada de un libro llega como URL de Google Books o como id de OpenLibrary.
 * Se exporta porque `BookSearch.vue` la necesita también para navegar a la ficha,
 * y tener dos copias de la misma regla es cómo se desincronizan.
 */
export function bookCoverUrl (coverI) {
  if (!coverI) return ''
  const s = String(coverI)
  return s.startsWith('http') ? s : `https://covers.openlibrary.org/b/id/${s}-L.jpg`
}

function searchTransformBook (result) {
  const isbn = Array.isArray(result.isbn) ? result.isbn[0] : result.isbn
  return {
    isbn,
    title: result.title || t('media.book.titleUnavailable'),
    author: Array.isArray(result.author) ? result.author.join(', ') : (result.author || t('book.unknownAuthor')),
    coverUrl: bookCoverUrl(result.cover_i),
    cover_i: result.cover_i,
    publisher: result.publisher,
    pages: result.pages,
    genres: result.genres,
    user_rating: 0,
    userStatuses: []
  }
}

/** Películas y series salen de la MISMA respuesta; `type` es lo que las separa. */
function searchTransformOmdb (result) {
  return {
    isbn: result.imdbID,
    imdbID: result.imdbID,
    title: result.Title,
    Title: result.Title,
    year: result.Year,
    Year: result.Year,
    coverUrl: result.Poster !== 'N/A' ? result.Poster : null,
    Poster: result.Poster,
    user_rating: 0,
    userStatuses: [],
    type: result.Type || 'movie'   // 'movie' | 'series' | 'episode'
  }
}

function searchTransformGame (result) {
  const developers = result.involved_companies
    ?.filter((ic) => ic.developer)
    .map((ic) => ({ name: ic.company?.name || 'Unknown' })) || []

  const publishers = result.involved_companies
    ?.filter((ic) => ic.publisher)
    .map((ic) => ({ name: ic.company?.name || 'Unknown' })) || []

  const releaseDate = result.first_release_date
    ? new Date(result.first_release_date * 1000).toISOString().split('T')[0]
    : null

  const cover = result.cover?.url
    ? `https:${result.cover.url.replace('t_thumb', 't_cover_big')}`
    : null

  return {
    id: result.id,
    igdbId: result.id,
    gameId: result.id,
    title: result.name,
    name: result.name,
    originalTitle: result.name,
    releaseDate,
    released: releaseDate,
    coverUrl: cover,
    background_image: cover,
    rating: result.rating ? Math.round(result.rating / 20) : null, // IGDB va de 0 a 100
    platforms: result.platforms || [],
    genres: result.genres || [],
    developers,
    publishers,
    description: result.summary || '',
    user_rating: null,
    userStatuses: [],
    itemType: 'game'
  }
}

function searchTransformAlbum (result) {
  const cover = result.images?.[0]?.url || result.cover_url || result.coverUrl || null
  return {
    id: result.id,
    spotify_id: result.id || result.spotify_id,
    spotifyId: result.id || result.spotify_id,
    title: result.name || result.title,
    name: result.name || result.title,
    artist: result.artists?.[0]?.name || result.artist || '',
    artist_id: result.artists?.[0]?.id || result.artist_id || '',
    release_date: result.release_date || result.releaseDate || '',
    release_date_precision: result.release_date_precision || 'year',
    cover_url: cover,
    coverUrl: cover,
    genres: result.genres || [],
    label: result.label || '',
    total_tracks: result.total_tracks || result.totalTracks || 0,
    album_type: result.album_type || result.albumType || 'album',
    duration_ms: result.duration_ms || result.durationMs || 0,
    popularity: result.popularity || 0,
    external_url: result.external_urls?.spotify || result.external_url || '',
    upc: result.upc || '',
    user_rating: null,
    userStatuses: [],
    itemType: 'album'
  }
}

function searchTransformVideo (result) {
  const id = result.id || result.youtube_id || result.youtubeId
  const cover = result.thumbnail || result.cover_url || result.coverUrl || null
  return {
    id,
    youtube_id: id,
    youtubeId: id,
    title: result.title || result.name || '',
    channel_name: result.channel_name || result.channelName || '',
    channel_id: result.channel_id || result.channelId || '',
    cover_url: cover,
    coverUrl: cover,
    duration: result.duration || '',
    duration_seconds: result.duration_seconds || result.durationSeconds || 0,
    view_count: result.view_count || result.viewCount || 0,
    like_count: result.like_count || result.likeCount || 0,
    published_at: result.published_at || result.publishedAt || '',
    description: result.description || '',
    categories: result.categories || [],
    user_rating: null,
    userStatuses: [],
    itemType: 'video'
  }
}

export const mediaRegistry = {
  book: {
    key: 'book',
    get label() { return t('media.book.label'); },
    get labelPlural () { return t('media.book.labelPlural') },
    // La ficha de libro trabaja con ediciones, no con obras: el identificador
    // es el de la edición del usuario.
    idProp: 'userEditionId',
    idPayloadKey: 'userEditionId',
    idType: Number,
    routeName: 'BookDetail',
    accentVar: '--color-card-book-accent',
    /**
     * El eje de progreso dentro de un club. `axis` dice CÓMO se mide el avance
     * y `completedStatuses` cuándo se da por acabado; los dos los usa la
     * pantalla del club y, en el M4, la regla de spoiler.
     *
     * El eje viene **resuelto del servidor** en `get_club_progress` —solo él
     * sabe que un `entity_type: 'movie'` es en realidad una serie, mirando
     * `movie.media_type`— y esto es la parte de presentación: la etiqueta y la
     * unidad con las que se pinta.
     */
    progress: {
      axis: 'page',
      get label() { return t('media.book.fields.page'); },
      get unit () { return t('media.book.unit') },
      completedStatuses: ['read']
    },
    detail: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 220, height: 330 },
      stateKey: 'book',
      routeParam: 'isbn',
      get backText () { return t('media.book.back') },
      backRoute: 'Books',
      get loadingText () { return t('media.book.loading') },
      get notFoundText () { return t('media.book.notFound') },
      get errorText () { return t('media.book.error') },
      get emptyText() { return t('media.book.emptyText'); },
      placeholderIcon: 'fas fa-book',
      librarySectionClass: 'library-form-section',
      libraryTitleIcon: true,
      get libraryTitleNew () { return t('media.book.libraryNew') },
      get libraryTitleExisting () { return t('media.book.libraryExisting') },
      divider: true,
      hasNotes: false,
      statusesAsNames: false,
      // Google Books puede devolver un ISBN-13 distinto del guardado, por eso
      // se busca también por el de la ruta.
      existingOf: (store, item, routeId) => store.getBookByIsbn(item.isbn) || store.getBookByIsbn(routeId),
      /**
       * Dos fuentes: Google Books manda y OpenLibrary completa (materias,
       * clasificaciones, work_key). Si Google Books falla, OpenLibrary es el
       * plan B y sirve la ficha entera.
       */
      enrich: async (routeId, apiCall, current) => {
        // Los datos de usuario ya cargados se preservan para que la ficha no
        // parpadee a cero estrellas durante el enriquecimiento en segundo plano.
        const previo = current || {}
        const delUsuario = {
          user_rating: previo.user_rating ?? null,
          userStatuses: previo.userStatuses ?? [],
          currentPage: previo.currentPage,
          totalPages: previo.totalPages,
          ownershipFormat: previo.ownershipFormat ?? previo.ownership_format ?? null,
          ownership_format: previo.ownership_format ?? previo.ownershipFormat ?? null,
          ownership_format_id: previo.ownership_format_id ?? null,
          tags: previo.tags ?? null
        }

        let google = null
        try {
          const response = await apiCall('search_google_books_isbn', { isbn: routeId })
          if (response.data?.status === 'success' && response.data?.data) google = response.data.data
        } catch {
          google = null
        }

        if (google) {
          const item = {
            isbn: google.isbn_13 || routeId,
            isbn10: google.isbn_10 || null,
            title: google.title || t('media.book.titleUnavailable'),
            author: google.authors?.length > 0 ? google.authors.join(', ') : t('media.book.authorUnavailable'),
            publisher: google.publisher || '',
            publicationDate: google.published_date || '',
            coverUrl: google.cover_url_large || google.cover_url_medium || google.cover_url_small || '',
            pages: google.page_count || null,
            description: google.description || '',
            genres: google.categories || [],
            publishers: google.publisher ? [google.publisher] : [],
            language: google.language || null,
            previewLink: google.preview_link || null,
            infoLink: google.info_link || null,
            rating: null,
            ...delUsuario
          }

          try {
            const ol = await apiCall('get_openlibrary_book_by_isbn', { isbn: routeId })
            if (ol.data?.status === 'success' && ol.data?.data) {
              const { edition, book: olData } = ol.data.data
              const extra = openLibraryFields(olData, edition)
              Object.assign(item, extra)
              if (extra.subjects && (!item.genres || item.genres.length === 0)) {
                item.genres = extra.subjects.slice(0, 5).map((sub) => sub.name)
              }
              // Google Books manda en las páginas si las trae.
              if (google.page_count) item.pages = google.page_count
            }
          } catch {
            // El enriquecimiento es opcional: la ficha ya es válida sin él.
          }

          return { item }
        }

        // Plan B: OpenLibrary sirve la ficha completa.
        const ol = await apiCall('get_openlibrary_book_by_isbn', { isbn: routeId })
        if (ol.data?.status !== 'success' || !ol.data?.data) return null

        const { edition, book: olData } = ol.data.data
        if (!olData) return null

        return {
          item: {
            isbn: olData.identifiers?.isbn_13?.[0] || routeId,
            isbn10: olData.identifiers?.isbn_10?.[0] || null,
            title: olData.title || t('media.book.titleUnavailable'),
            author: olData.authors?.length > 0 ? olData.authors.map((a) => a.name).join(', ') : t('media.book.authorUnavailable'),
            publisher: olData.publishers?.length > 0 ? olData.publishers.map((pub) => pub.name).join(', ') : '',
            publicationDate: olData.publish_date || '',
            coverUrl: olData.cover?.large || olData.cover?.medium || olData.cover?.small || '',
            pages: olData.number_of_pages ||
              (olData.pagination ? (parseInt(String(olData.pagination)) || null) : null) || null,
            description: olData.notes || '',
            genres: olData.subjects ? olData.subjects.slice(0, 5).map((sub) => sub.name) : [],
            publishers: olData.publishers?.length > 0 ? olData.publishers.map((pub) => pub.name) : [],
            rating: null,
            ...delUsuario,
            ...openLibraryFields(olData, edition)
          }
        }
      },
      mergeFields: (existing) => ({
        user_rating: existing.user_rating,
        userStatuses: existing.userStatuses || [],
        currentPage: existing.currentPage,
        totalPages: existing.totalPages,
        ownershipFormat: existing.ownershipFormat ?? existing.ownership_format ?? null,
        ownership_format: existing.ownership_format ?? existing.ownershipFormat ?? null,
        ownership_format_id: existing.ownership_format_id ?? existing.ownershipFormat?.id ?? null,
        tags: existing.tags ?? null
      }),
      itemForModal: (item, stored) => ({ ...item, ...stored, isbn: stored.isbn ?? item?.isbn }),
      unwrapSave: (payload) => [payload.book, payload.statuses],
      unwrapDelete: (payload) => payload.isbn,
      get savedMessage () { return t('media.book.saved') },
      get deleteConfirm () { return t('media.book.deleteConfirm') },
      get deletedMessage () { return t('media.book.deleted') },
      get deleteErrorMessage () { return t('media.book.deleteError') }
    },
    // Ficha de biblioteca (LibraryMediaItem). Cada campo conserva la clase que
    // tenía en LibraryBookItem, porque el mixin `library-item` estiliza
    // `.#{$entity}-*` y las demás quedan como enganches sin estilo.
    libraryItem: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 80, height: 120 },
      get coverAlt() { return t('media.book.coverAlt'); },
      idOf: (i) => i.isbn,
      coverOf: (i) => i.coverUrl,
      titleOf: (i) => i.title,
      // La excepción que hubo aquí —libros, películas y juegos rotulando en
      // inglés y álbumes y vídeos en español— dejó de existir el 2026-08-31: todo
      // sale del catálogo.
      get statusLabel() { return t('media.book.statusLabel'); },
      defaultStatus: 'owned',
      ratingFallback: 0,
      fields: [
        { cls: 'book-author', get label() { return t('media.book.fields.author'); }, value: (i) => i.author },
        {
          cls: 'book-publisher',
          get label() { return t('media.book.fields.publisher'); },
          value: (i) => (Array.isArray(i.publishers) && i.publishers.length > 0
            ? i.publishers.join(', ')
            : i.publisher)
        },
        { cls: 'book-publication-date', get label() { return t('media.book.fields.publicationDate'); }, value: (i) => i.publicationDate }
      ],
      // Libros y películas sacan el formato como `<p>` suelto; los otros tres
      // lo meten en el bloque `.readonly-fields`.
      extrasWrapped: false,
      extras: [
        { cls: 'book-field', get label() { return t('media.book.fields.format'); }, value: ownershipLabel, badge: true }
      ],
      // El botón de historial solo existe en libros.
      extraActions: [
        { cls: 'btn--secondary', icon: 'fas fa-history', get label() { return t('media.book.fields.history'); }, get title() { return t('media.book.fields.historyTitle'); }, event: 'show-history', onlyExisting: true }
      ],
      savePayload: (item, statuses) => ({ book: item, statuses, itemType: 'book' }),
      deletePayload: (item) => ({ isbn: item.isbn, itemType: 'book' }),
      editPayload: (item) => [item, 'book']
    },
    store: {
      id: 'books',
      collection: 'books',
      One: 'Book',
      Many: 'Books',
      // ⚠ El payload del store manda `isbn`. El `idPayloadKey` del bloque raíz
      // (`userEditionId`) es el de las NOTAS, que trabajan con ediciones.
      idPayloadKey: 'isbn',
      matches: (item, id) => item.isbn === id,
      // Único getter por id que no se llama `get{One}ById` (books.js:50).
      byIdGetterName: 'getBookByIsbn',
      // Libros y películas vacían searchResults al fallar la búsqueda; el resto no.
      clearSearchOnError: true,
      ratingField: 'user_rating',
      tagsIdKey: 'isbn',
      tagDefaultColor: '#007bff',
      // ⚠ booksByStatus y bookCountByStatus agrupan por NOMBRE de estado sobre
      // los ítems (books.js:36-65) y devuelven un objeto; game/album/video
      // filtran por status_id contra allowedStatuses. No son la misma función.
      statusMode: 'byName',
      // delete_book manda un `itemType` extra que las otras acciones no llevan.
      deleteExtra: { itemType: 'book' },
      addPayloadKey: 'book',
      // addBook se asegura de tener los estados permitidos antes de componer el
      // payload, porque los mete dentro (books.js:150-152).
      addNeedsAllowedStatuses: true,
      // ...y empuja al array un objeto construido en local, no la respuesta.
      addPushes: 'local',
      toAddPayload: (book, statuses, allowedStatuses) => ({
        isbn: book.isbn,
        title: book.title,
        author: book.author || '',
        coverUrl: book.coverUrl || '',
        publisher: Array.isArray(book.publishers) && book.publishers.length > 0
          ? book.publishers.join(', ')
          : (book.publisher || ''),
        publicationDate: book.publicationDate || '',
        pages: book.pages || null,
        description: book.description || '',
        userStatuses: statuses,
        allowedStatuses,
        rating: book.user_rating || null,
        genres: book.genres || [],
        ownership_format_id: book.ownership_format_id || null
      }),
      toLocalItem: (book, statuses) => ({
        ...book,
        userStatuses: statuses,
        user_rating: book.user_rating || null,
        itemType: 'book'
      })
    },
    api: {
      // fetchBooks no llama al backend: get_library_items lo comparte con
      // películas a través de _libraryCache.js, y de ahí sale `data.books`.
      list: { fromLibraryCache: 'books', stamp: { itemType: 'book' } },
      search: {
        // La acción REAL, verificada contra `backend/config/routes.php:175`.
        // Lo que había antes eran dos acciones inventadas que nunca existieron
        // en el backend, y nadie lo notó porque su único consumidor
        // —`createMediaStore.search()`— no lo llamaba nadie.
        action: 'search_works',
        // Los seis medios llaman distinto al mismo parámetro; aquí es donde esa
        // diferencia deja de propagarse. `q` lo lee `BookController.php:477`.
        payload: (query, limit = 20) => ({ q: query, limit }),
        transform: searchTransformBook,
        titleOf: (item) => item.title
      },
      // Su búsqueda depende de una API viva sin dump abierto, así que el
      // backend puede servir caché caducada y `GenericSearch` tiene que
      // decirlo. Películas y álbumes NO lo declaran: los sirve el mirror
      // local y no pueden ser rancios.
      supportsStale: true,
      add: 'add_book',
      remove: 'delete_book',
      rating: 'update_book_rating',
      statuses: 'update_book_user_statuses',
      edit: 'edit_user_book',
      allowedStatuses: 'get_book_allowed_statuses',
      tags: {
        // `get` son las etiquetas de UN ítem; `list`, todas las del usuario.
        get: 'get_book_tags',
        list: 'get_user_book_tags',
        create: 'create_user_book_tag',
        update: 'update_book_tags'
      }
    },
    /**
     * Lo propio de libros que `createMediaComposable` no deduce del bloque
     * `store`. Las sesiones de lectura NO se declaran aquí: son un extra del
     * medio y viven en el cuerpo de `useBooks`.
     */
    composable: {
      // Único medio cuyo helper por id no se llama `find{One}ById`.
      findByIdName: 'findBookByISBN',
      deleteIdLabel: 'ISBN',
      get deleteWarning () { return t('media.book.deleteWarning') },
      editSyncFields: ['currentPage']
    },
    list: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 50, height: 75 },
      // El placeholder de portada va en FontAwesome, no en PrimeIcons.
      iconOf: () => 'fas fa-book',
      aspect: '2/3',
      width: '75px',
      idOf: (i) => i.isbn,
      coverOf: (i) => i.coverUrl,
      titleOf: (i) => i.title,
      subtitleOf: (i) => joinSubtitle(i.author || t('book.unknownAuthor'), yearOf(i.publicationDate))
    },
    notes: {
      get title() { return t('media.book.notesTitle'); },
      emptyIcon: 'pi pi-book',
      get emptyHint () { return t('media.book.notesHint') },
      types: NOTE_TYPES_EDITION,
      typeIcons: NOTE_TYPE_ICONS_EDITION,
      // Ediciones caían a 'pi-file' y al propio `type`; los otros cuatro,
      // a 'pi-file-edit' y a 'Nota'. Se conserva la diferencia.
      typeFallbackIcon: 'pi-file',
      typeFallbackLabel: null,
      // `pageNumber` es obligatorio en add_edition_note (routes.php:228).
      hasPageNumber: true,
      // Las notas de lectura se leen en orden de página, no de recencia
      // (EditionNotes.vue:197-205). El resto ordena por fecha descendente.
      sortBy: 'page',
      actions: {
        list: 'get_edition_notes',
        get: 'get_edition_note',   // singular: solo existe para ediciones
        add: 'add_edition_note',
        update: 'update_edition_note',
        delete: 'delete_edition_note'
      }
    }
  },

  movie: {
    key: 'movie',
    get label() { return t('media.movie.label'); },
    get labelPlural () { return t('media.movie.labelPlural') },
    idProp: 'imdbId',
    // ⚠ El backend exige la clave `movieIsbn` en el payload
    // (routes.php:481,483). Es herencia de haber copiado el esquema de libros:
    // la PK de la tabla `movie` se llama `isbn` y contiene el ID de IMDb.
    // Renombrarla es trabajo de backend, fuera del alcance de este plan.
    idPayloadKey: 'movieIsbn',
    idType: String,
    routeName: 'MovieDetail',
    accentVar: '--color-card-movie-accent',
    // Sin eje: `hours_played`, minuto o porcentaje no existen como columna, y
    // añadirlos era «Fuera» del plan. Aquí el progreso es binario.
    progress: {
      axis: null,
      get label() { return t('media.movie.fields.seen'); },
      unit: null,
      completedStatuses: ['viewed']
    },
    detail: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 220, height: 330 },
      stateKey: 'movie',
      routeParam: 'imdbId',
      get backText () { return t('media.movie.back') },
      backRoute: 'Movies',
      get loadingText () { return t('media.movie.loading') },
      get notFoundText () { return t('media.movie.notFound') },
      get errorText () { return t('media.movie.error') },
      get emptyText() { return t('media.movie.emptyText'); },
      // La atribución de TMDB NO es decorativa ni opcional: sus condiciones de
      // uso exigen mostrar el logo y esta frase en cualquier pantalla que use
      // datos suyos. Solo la llevan película y serie, que son los dos medios
      // enriquecidos con TMDB.
      attribution: {
        logo: tmdbLogo,
        get alt () { return t('brand.tmdb') },
        href: 'https://www.themoviedb.org/',
        get text () { return t('brand.tmdbNotice') }
      },
      placeholderIcon: 'fas fa-film',
      coverClass: 'movie-poster-large',
      coverImageClass: 'poster-image-large',
      placeholderClass: 'poster-placeholder',
      librarySectionClass: 'library-form-section',
      libraryTitleIcon: true,
      get libraryTitleNew () { return t('media.movie.libraryNew') },
      get libraryTitleExisting () { return t('media.movie.libraryExisting') },
      divider: true,
      hasNotes: false,
      statusesAsNames: false,
      // Las películas esconden los estados que solo tienen sentido en series.
      allowedStatusesFilter: (all) => all.filter((s) => !['watching', 'on-hold', 'dropped'].includes(s)),
      existingOf: (store, item, routeId) => store.getMovieById(item.imdbID || routeId),
      enrich: async (routeId, apiCall) => {
        const response = await apiCall('get_movie_details_omdb', { imdbId: routeId, plot: 'full' })
        if (response.data?.status !== 'success' || !response.data?.data) return null
        return { item: transformOmdb(response.data.data) }
      },
      mergeFields: omdbMergeFields,
      itemForModal: (item, stored) => ({
        ...item,
        ...omdbMergeFields(stored),
        isbn: stored.isbn ?? item?.isbn,
        imdbID: stored.imdbID ?? item?.imdbID
      }),
      unwrapSave: (payload) => [payload.movie, payload.statuses],
      unwrapDelete: (payload) => payload.isbn,
      get savedMessage () { return t('media.movie.saved') },
      // ⚠ Antes el botón de borrar no hacía nada (handler vacío en
      // MovieDetailView.vue:362). Ahora borra de verdad, con confirmación.
      get deleteConfirm () { return t('media.movie.deleteConfirm') },
      get deletedMessage () { return t('media.movie.deleted') },
      get deleteErrorMessage () { return t('media.movie.deleteError') }
    },
    libraryItem: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 80, height: 120 },
      get coverAlt() { return t('media.movie.coverAlt'); },
      idOf: (i) => i.imdbID,
      coverOf: (i) => i.coverUrl,
      titleOf: (i) => i.title,
      get statusLabel() { return t('media.movie.statusLabel'); },
      defaultStatus: 'owned',
      ratingFallback: 0,
      fields: [
        {
          cls: 'movie-original-title',
          get label() { return t('media.movie.fields.originalTitle'); },
          value: (i) => (i.originalTitle && i.originalTitle !== i.title ? i.originalTitle : '')
        },
        // `i.author` NO va aquí: es la misma persona que `i.director`. La tabla `movie`
        // heredó del esquema de libros una columna `author`, y el mapeo de OMDb rellena
        // las dos con `omdb.Director` (`:187-188`), así que declararlas las dos pintaba
        // la fila «Director» dos veces seguidas. La columna se queda: atraviesa mappers,
        // DTOs y stores. Lo que se va es enseñarla.
        { cls: 'movie-director', get label() { return t('media.movie.fields.director'); }, value: (i) => i.director },
        { cls: 'movie-year', get label() { return t('media.movie.fields.year'); }, value: (i) => i.year },
        // Sin `v-if`: la ficha de película siempre pinta el IMDb ID.
        { cls: 'movie-isbn', get label() { return t('media.movie.fields.imdbId'); }, value: (i) => i.isbn, always: true }
      ],
      extrasWrapped: false,
      extras: [
        { cls: 'movie-field', get label() { return t('media.movie.fields.format'); }, value: ownershipLabel, badge: true }
      ],
      savePayload: (item, statuses) => ({ movie: item, statuses, itemType: 'movie' }),
      // ⚠ `imdbID` va con el valor de `isbn`, no con el de `imdbID`: es lo que
      // hacía LibraryMovieItem.vue:135 y el padre depende de ello.
      deletePayload: (item) => ({ isbn: item.isbn, imdbID: item.isbn, itemType: 'movie' }),
      editPayload: (item) => [item, 'movie']
    },
    store: {
      id: 'movies',
      collection: 'movies',
      One: 'Movie',
      Many: 'Movies',
      // ⚠ El store manda `isbn`, NO `movieIsbn`: `idPayloadKey` del bloque raíz
      // es el de las notas (routes.php:481,483). La PK de `movie` se llama
      // `isbn` y contiene el ID de IMDb.
      idPayloadKey: 'isbn',
      // Tres nombres para el mismo identificador, según de dónde venga el ítem.
      matches: (item, id) => item.id === id || item.imdbID === id || item.isbn === id,
      clearSearchOnError: true,
      // isMovieInLibrary NO mira `id` (movies.js:53); se declara aparte.
      inLibrary: (item, id) => item.imdbID === id || item.isbn === id,
      ratingField: 'user_rating',
      tagsIdKey: 'isbn',
      tagDefaultColor: '#007bff',
      statusMode: 'byName',
      deleteExtra: { itemType: 'movie' },
      addPayloadKey: 'movie',
      addPushes: 'local',
      toAddPayload: (movie, statuses) => ({
        id: movie.isbn || movie.imdbID,
        isbn: movie.isbn || movie.imdbID,
        imdbID: movie.imdbID,
        title: movie.title,
        originalTitle: movie.originalTitle || movie.title,
        director: movie.director || '',
        author: movie.author || movie.director || '',
        year: movie.year || '',
        genre: movie.genre || '',
        plot: movie.plot || '',
        coverUrl: movie.coverUrl || '',
        userStatuses: statuses,
        user_rating: movie.user_rating || 0,
        itemType: 'movie',
        genres: movie.genres || [],
        ownership_format_id: movie.ownership_format_id || null,
        media_type: movie.type || movie.media_type || 'movie',
        mediaType: movie.type || movie.media_type || 'movie',
        total_seasons: movie.totalSeasons || null,
        totalSeasons: movie.totalSeasons || null
      }),
      // El ítem local se construye sobre el PAYLOAD, no sobre el original.
      toLocalItem: (movie, statuses, payload) => ({
        ...payload,
        userStatuses: statuses,
        user_rating: movie.user_rating || 0
      })
    },
    api: {
      list: { fromLibraryCache: 'movies', stamp: { itemType: 'movie' } },
      search: {
        // `search_movies_omdb` se llama así por historia: OMDb se retiró y hoy
        // sirve el mirror local vía `FallbackMovieCatalog`. `routes.php:399`.
        action: 'search_movies_omdb',
        payload: (query, limit = 20) => ({ title: query, limit }),  // `title`: MovieController.php:347
        transform: searchTransformOmdb,
        titleOf: (item) => item.title
      },
      add: 'add_movie',
      remove: 'delete_movie',
      rating: 'update_movie_rating',
      statuses: 'update_movie_user_statuses',
      edit: 'edit_user_movie',
      allowedStatuses: 'get_movie_allowed_statuses',
      tags: {
        get: 'get_movie_tags',
        list: 'get_user_movie_tags',
        create: 'create_user_movie_tag',
        update: 'update_movie_tags'
      }
    },
    /**
     * Lo propio de películas. El seguimiento de temporadas es un extra del
     * medio y vive en el cuerpo de `useMovies`.
     */
    composable: {
      editSyncFields: ['ownership_format_id', 'ownershipFormat']
    },
    list: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 50, height: 75 },
      // Series y películas comparten componente y color, pero no icono.
      iconOf: (i) => (isSeries(i) ? 'fas fa-tv' : 'fas fa-film'),
      aspect: '2/3',
      width: '75px',
      idOf: (i) => i.imdbID,
      coverOf: (i) => i.coverUrl,
      titleOf: (i) => i.title,
      subtitleOf: (i) => joinSubtitle(i.director || t('media.movie.unknownDirector'), i.year ? String(i.year) : ''),
      // Único medio con badge: distingue serie de película (MovieListItem.vue:16-22).
      badgeOf: (i) => ({
        icon: isSeries(i) ? 'fas fa-tv' : 'fas fa-film',
        text: isSeries(i) ? t('media.series.label') : t('media.movie.label'),
        modifier: isSeries(i) ? 'is-series' : 'is-movie'
      })
    },
    notes: {
      get title() { return t('media.movie.notesTitle'); },
      emptyIcon: 'pi pi-video',
      get emptyHint () { return t('media.movie.notesHint') },
      types: NOTE_TYPES_DEFAULT,
      typeIcons: NOTE_TYPE_ICONS_DEFAULT,
      typeFallbackIcon: 'pi-file-edit',
      typeFallbackLabel: 'Nota',
      hasPageNumber: false,
      sortBy: 'date',
      actions: {
        list: 'get_movie_notes',
        add: 'add_movie_note',
        update: 'update_movie_note',
        delete: 'delete_movie_note'
      }
    }
  },

  game: {
    key: 'game',
    get label() { return t('media.game.label'); },
    get labelPlural () { return t('media.game.labelPlural') },
    idProp: 'gameId',
    idPayloadKey: 'gameId',
    idType: Number,
    routeName: 'GameDetail',
    accentVar: '--color-card-game-accent',
    progress: {
      axis: null,
      get label() { return t('media.game.fields.completed'); },
      unit: null,
      completedStatuses: ['completed']
    },
    detail: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 280, height: 373 },
      stateKey: 'game',
      librarySectionClass: 'library-section',
      libraryTitleIcon: false,
      routeParam: 'gameId',
      get backText () { return t('media.game.back') },
      backRoute: 'Games',
      get loadingText () { return t('media.game.loading') },
      get notFoundText () { return t('media.game.notFound') },
      get errorText () { return t('media.game.error') },
      get emptyText() { return t('media.game.emptyText'); },
      placeholderIcon: 'fas fa-gamepad',
      coverOf: (i) => i.coverUrl || i.background_image,
      get libraryTitleNew () { return t('media.game.libraryNew') },
      get libraryTitleExisting () { return t('media.game.libraryExisting') },
      divider: false,
      hasNotes: true,
      statusesAsNames: false,
      existingOf: (store, item, routeId) => store.getGameById(
        item.id || item.igdbId || item.gameId || Number(routeId)
      ),
      enrich: async (routeId, apiCall) => {
        const response = await apiCall('get_igdb_game_details', { gameId: parseInt(routeId) })
        if (response.data.status !== 'success' || !response.data.data) return null

        const data = response.data.data
        return {
          item: transformIgdbGame(data),
          context: {
            screenshots: (data.detailed_screenshots || []).map((s) => ({
              image: `https:${s.url.replace('t_thumb', 't_screenshot_med')}`
            }))
          }
        }
      },
      mergeFields: (existing) => ({
        user_rating: existing.user_rating,
        userStatuses: existing.userStatuses || [],
        hoursPlayed: existing.hoursPlayed ?? existing.hours_played ?? 0,
        notes: existing.notes ?? '',
        dateStarted: existing.dateStarted ?? existing.date_started ?? '',
        dateFinished: existing.dateFinished ?? existing.date_finished ?? '',
        ownershipFormat: existing.ownershipFormat ?? existing.ownership_format ?? null,
        ownership_format: existing.ownership_format ?? existing.ownershipFormat ?? null,
        ownership_format_id: existing.ownership_format_id ?? existing.ownershipFormat?.id ?? null,
        tags: existing.tags ?? null
      }),
      itemForModal: (item, stored) => ({ ...item, ...stored }),
      unwrapSave: (payload) => [payload.game, payload.statuses],
      unwrapDelete: (payload) => payload.gameId,
      deleteConfirm: null,
      get deletedMessage () { return t('media.game.deleted') },
      get deleteErrorMessage () { return t('media.game.deleteError') }
    },
    libraryItem: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 80, height: 120 },
      get coverAlt() { return t('media.game.coverAlt'); },
      idOf: (i) => i.id || i.rawgId || i.gameId,
      coverOf: (i) => i.coverUrl,
      titleOf: (i) => i.title || i.name,
      get statusLabel() { return t('media.game.statusLabel'); },
      defaultStatus: 'owned',
      ratingFallback: 0,
      fields: [
        {
          cls: 'game-original-title',
          get label() { return t('media.game.fields.originalTitle'); },
          value: (i) => (i.originalTitle && i.originalTitle !== (i.title || i.name) ? i.originalTitle : '')
        },
        { cls: 'game-developer', get label() { return t('media.game.fields.developer'); }, value: (i) => i.developer || joinNames(i.developers) },
        { cls: 'game-publisher', get label() { return t('media.game.fields.publisher'); }, value: (i) => i.publisher || joinNames(i.publishers) },
        { cls: 'game-release', get label() { return t('media.game.fields.released'); }, value: (i) => i.releaseDate || i.released },
        { cls: 'game-platforms', get label() { return t('media.game.fields.platforms'); }, value: (i) => joinNames(i.platforms) },
        { cls: 'game-genres', get label() { return t('media.game.fields.genres'); }, value: (i) => joinNames(i.genres) },
        {
          cls: 'game-metacritic',
          get label() { return t('media.game.fields.metacritic'); },
          value: (i) => i.metacriticScore || i.metacritic,
          valueClass: (i) => metacriticClass(i.metacriticScore || i.metacritic)
        },
        { cls: 'game-id', get label() { return t('media.game.fields.rawgId'); }, value: (i) => i.id || i.rawgId || i.gameId, always: true }
      ],
      extrasWrapped: true,
      extras: [
        { cls: 'game-field', get label() { return t('media.game.fields.hoursPlayed'); }, value: (i) => i.hoursPlayed || i.hours_played || 0 },
        { cls: 'game-field', get label() { return t('media.game.fields.startedOn'); }, value: (i) => i.dateStarted || i.date_started || '' },
        { cls: 'game-field', get label() { return t('media.game.fields.finishedOn'); }, value: (i) => i.dateFinished || i.date_finished || '' },
        { cls: 'game-field', get label() { return t('media.game.fields.notes'); }, value: (i) => i.notes || '' },
        { cls: 'game-field', get label() { return t('media.game.fields.format'); }, value: ownershipLabel, badge: true }
      ],
      // Guardar y editar añaden los campos propios del juego al ítem.
      withOwnFields: (item) => ({
        ...item,
        hoursPlayed: item.hoursPlayed || item.hours_played || 0,
        notes: item.notes || '',
        dateStarted: item.dateStarted || item.date_started || '',
        dateFinished: item.dateFinished || item.date_finished || ''
      }),
      savePayload: (item, statuses) => ({ game: item, statuses, itemType: 'game' }),
      deletePayload: (item) => ({ gameId: item.id || item.rawgId || item.gameId, itemType: 'game' }),
      editPayload: (item) => [item, 'game']
    },
    store: {
      id: 'games',
      collection: 'games',
      One: 'Game',
      Many: 'Games',
      idPayloadKey: 'gameId',
      // El id de IGDB llega con tres nombres distintos según la fuente
      // (respuesta del backend, búsqueda de IGDB, datos heredados de RAWG).
      matches: (item, id) => item.id === id || item.rawgId === id || item.gameId === id,
      ratingField: 'user_rating',
      tagsIdKey: 'gameId',
      tagDefaultColor: '#007bff',
      statusMode: 'byId',
      deleteExtra: { itemType: 'game' },
      addPayloadKey: 'game',
      addPushes: 'response',
      toAddPayload: (game, statuses) => ({
        id: game.id || game.gameId || game.rawgId,
        gameId: game.gameId || game.rawgId || game.id,
        rawgId: game.rawgId || game.id,
        title: game.title || game.name,
        originalTitle: game.originalTitle || game.original_name || game.title || game.name,
        developer: game.developer || game.developers?.[0]?.name || '',
        publisher: game.publisher || game.publishers?.[0]?.name || '',
        releaseDate: game.releaseDate || game.released || game.release_date || '',
        genres: Array.isArray(game.genres)
          ? game.genres.map((g) => (typeof g === 'string' ? g : g.name)).join(', ')
          : (game.genres || ''),
        platforms: Array.isArray(game.platforms)
          ? game.platforms.map((pl) => (typeof pl === 'string' ? pl : pl.platform?.name || pl.name)).join(', ')
          : (game.platforms || ''),
        description: game.description || game.plot || '',
        coverUrl: game.coverUrl || game.background_image || game.cover || '',
        metacriticScore: game.metacriticScore || game.metacritic || null,
        esrbRating: game.esrbRating || game.esrb_rating?.name || '',
        playtime: game.playtime || 0,
        userStatuses: statuses,
        user_rating: game.user_rating || null,
        hoursPlayed: game.hoursPlayed || game.hours_played || 0,
        notes: game.notes || '',
        dateStarted: game.dateStarted || game.date_started || null,
        dateFinished: game.dateFinished || game.date_finished || null,
        ownership_format_id: game.ownership_format_id || null
      })
    },
    api: {
      list: 'get_games',
      listPayload: { filters: {} },
      search: {
        action: 'search_igdb_games',                                 // routes.php:1013
        payload: (query, limit = 20) => ({ query, limit }),          // GameController.php:350
        transform: searchTransformGame,
        titleOf: (item) => item.title
      },
      // Su búsqueda depende de una API viva sin dump abierto, así que el
      // backend puede servir caché caducada y `GenericSearch` tiene que
      // decirlo. Películas y álbumes NO lo declaran: los sirve el mirror
      // local y no pueden ser rancios.
      supportsStale: true,
      add: 'add_game',
      remove: 'delete_game',
      rating: 'update_game_rating',
      statuses: 'update_game_user_statuses',
      edit: 'edit_user_game',
      allowedStatuses: 'get_game_allowed_statuses',
      tags: {
        get: 'get_game_tags',
        list: 'get_user_game_tags',
        create: 'create_user_game_tag',
        update: 'update_game_tags'
      }
    },
    /** Lo propio de juegos. */
    composable: {
      editSyncFields: [
        'hoursPlayed',
        'platformPlayed',
        'dateStarted',
        'dateFinished',
        'personalNotes',
        'ownership_format_id',
        'ownershipFormat'
      ]
    },
    list: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 60, height: 60 },
      iconOf: () => 'fas fa-gamepad',
      aspect: '1/1',
      width: '60px',
      idOf: (i) => i.id || i.rawgId || i.gameId,
      coverOf: (i) => i.coverUrl,
      titleOf: (i) => i.title || i.name,
      subtitleOf: (i) => {
        const developer = i.developer ||
          (Array.isArray(i.developers) && i.developers.length > 0
            ? i.developers[0].name || i.developers[0]
            : '')
        const year = i.releaseDate
          ? new Date(i.releaseDate).getFullYear()
          : (i.released ? new Date(i.released).getFullYear() : '')

        if (developer && year) return `${developer} • ${year}`
        if (developer) return developer
        if (year) return String(year)
        return t('media.game.unknownDeveloper')
      },
      // Único medio con línea extra: las plataformas (GameListItem.vue:19-22).
      extraOf: (i) => {
        const text = platformsText(i)
        return text ? { icon: platformIcon(text), text } : null
      }
    },
    notes: {
      get title() { return t('media.game.notesTitle'); },
      emptyIcon: 'pi pi-desktop',
      get emptyHint () { return t('media.game.notesHint') },
      types: NOTE_TYPES_DEFAULT,
      typeIcons: NOTE_TYPE_ICONS_DEFAULT,
      typeFallbackIcon: 'pi-file-edit',
      typeFallbackLabel: 'Nota',
      hasPageNumber: false,
      sortBy: 'date',
      actions: {
        list: 'get_game_notes',
        add: 'add_game_note',
        update: 'update_game_note',
        delete: 'delete_game_note'
      }
    }
  },

  album: {
    key: 'album',
    get label() { return t('media.album.label'); },
    get labelPlural () { return t('media.album.labelPlural') },
    idProp: 'albumId',
    idPayloadKey: 'albumId',
    idType: Number,
    routeName: 'AlbumDetail',
    accentVar: '--color-card-album-accent',
    progress: {
      axis: null,
      get label() { return t('media.album.fields.listened'); },
      unit: null,
      completedStatuses: ['listened']
    },
    detail: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 240, height: 240 },
      stateKey: 'album',
      librarySectionClass: 'library-section',
      libraryTitleIcon: false,
      routeParam: 'albumId',
      get backText () { return t('media.album.back') },
      backRoute: 'Albums',
      get loadingText () { return t('media.album.loading') },
      get notFoundText () { return t('media.album.notFound') },
      get errorText () { return t('media.album.error') },
      get emptyText() { return t('media.album.emptyText'); },
      placeholderIcon: 'fas fa-music',
      get libraryTitleNew () { return t('media.album.libraryNew') },
      get libraryTitleExisting () { return t('media.album.libraryExisting') },
      divider: false,
      hasNotes: true,
      statusesAsNames: false,
      // ⚠ El id de la ruta puede ser el entero de la BD (viene así desde
      // trending), por eso se prefiere el spotify_id del ítem ya cargado.
      existingOf: (store, item, routeId) => store.getAlbumBySpotifyId(item.spotify_id || item.id || routeId) ||
        store.getAlbumById(Number(item.id ?? routeId)),
      enrich: async (routeId, apiCall, current, store) => {
        // El parámetro de ruta puede ser el entero de la tabla `albums` —llega así
        // desde trending—, y el catálogo enruta por la FORMA del id: MBID al mirror,
        // base62 a Spotify (`AlbumController::getSpotifyAlbum`). Un entero no es
        // ninguno de los dos y devolvía 404 con la ficha entera en estado de error.
        // Cuando la ruta trae un entero, el id bueno sale de la fila que ya está en
        // el store.
        const guardado = /^\d+$/.test(String(routeId))
          ? store?.getAlbumById?.(Number(routeId))
          : null
        const spotifyId = current?.spotify_id || current?.id ||
          guardado?.mb_release_group_gid || guardado?.spotify_id || routeId
        const response = await apiCall('get_spotify_album', { spotifyId })
        if (response.data.status !== 'success' || !response.data.data) return null

        const data = response.data.data.album || response.data.data
        const artists = data.artists || []
        const coverUrl = data.images?.[0]?.url || data.cover_url || ''
        const totalDurationMs = data.tracks?.items?.reduce((sum, t) => sum + (t.duration_ms || 0), 0) ||
          data.duration_ms || 0

        let tracks = data.tracks?.items || []
        if (tracks.length === 0) {
          // Las pistas van en una llamada aparte cuando el detalle no las trae.
          try {
            const extra = await apiCall('get_spotify_album_tracks', { spotifyId })
            if (extra.data.status === 'success' && extra.data.data) {
              tracks = extra.data.data.tracks || extra.data.data.items || []
            }
          } catch {
            tracks = []
          }
        }

        return {
          item: {
            id: data.id,
            spotify_id: data.id,
            title: data.name,
            name: data.name,
            artist: artists.map((a) => a.name).join(', '),
            artist_id: artists[0]?.id || '',
            artists,
            release_date: data.release_date,
            release_date_precision: data.release_date_precision,
            cover_url: coverUrl,
            coverUrl,
            genres: data.genres || [],
            label: data.label,
            total_tracks: data.total_tracks,
            album_type: data.album_type,
            duration_ms: totalDurationMs,
            popularity: data.popularity,
            external_url: data.external_urls?.spotify,
            upc: data.external_ids?.upc,
            user_rating: null,
            userStatuses: [],
            itemType: 'album'
          },
          context: { tracks }
        }
      },
      mergeFields: (existing) => ({
        id: existing.id,
        user_rating: existing.user_rating,
        userStatuses: existing.userStatuses || [],
        listenCount: existing.listenCount || existing.listen_count || null,
        favoriteTrack: existing.favoriteTrack || existing.favorite_track || '',
        dateStarted: existing.dateStarted || existing.date_started || '',
        dateFinished: existing.dateFinished || existing.date_finished || '',
        personalNotes: existing.personalNotes || existing.personal_notes || '',
        ownershipFormat: existing.ownershipFormat ?? existing.ownership_format ?? null,
        ownership_format: existing.ownership_format ?? existing.ownershipFormat ?? null,
        ownership_format_id: existing.ownership_format_id ?? existing.ownershipFormat?.id ?? null,
        tags: existing.tags ?? null
      }),
      // La portada de Spotify se conserva si la entrada de biblioteca no trae.
      mergeForLibrary: (item, existing) => ({
        ...item,
        ...existing,
        cover_url: existing.cover_url || item?.cover_url
      }),
      itemForModal: (item, stored) => ({
        ...item,
        ...stored,
        cover_url: stored.cover_url || item?.cover_url,
        user_rating: stored.user_rating ?? null,
        userStatuses: Array.isArray(stored.userStatuses) ? [...stored.userStatuses] : [],
        ownershipFormat: stored.ownershipFormat ?? stored.ownership_format ?? null,
        ownership_format: stored.ownership_format ?? stored.ownershipFormat ?? null,
        ownership_format_id: stored.ownershipFormat?.id ?? stored.ownership_format?.id ?? null,
        tags: stored.tags ?? null
      }),
      modalProps: (context) => ({ albumTracks: context.tracks || [] }),
      unwrapSave: (payload) => [payload, payload.userStatuses || []],
      unwrapDelete: (id) => id,
      get deleteConfirm () { return t('media.album.deleteConfirm') },
      get deletedMessage () { return t('media.album.deleted') },
      get deleteErrorMessage () { return t('media.album.deleteError') }
    },
    libraryItem: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 120, height: 120 },
      get coverAlt() { return t('media.album.coverAlt'); },
      idOf: (i) => i.id || i.spotify_id,
      coverOf: (i) => i.cover_url || i.coverUrl,
      titleOf: (i) => i.title || i.name,
      get statusLabel() { return t('media.album.statusLabel'); },
      defaultStatus: 'owned',
      ratingFallback: null,
      fields: [
        { cls: 'album-artist', get label() { return t('media.album.fields.artist'); }, value: (i) => i.artist || i.artists?.[0]?.name || '' },
        { cls: 'album-release', get label() { return t('media.album.fields.released'); }, value: (i) => i.release_date || i.releaseDate },
        { cls: 'album-genres', get label() { return t('media.album.fields.genres'); }, value: (i) => joinNames(i.genres) },
        { cls: 'album-label', get label() { return t('media.album.fields.label'); }, value: (i) => i.label },
        { cls: 'album-tracks', get label() { return t('media.album.fields.tracks'); }, value: (i) => i.total_tracks || i.totalTracks },
        { cls: 'album-duration', get label() { return t('media.album.fields.duration'); }, value: albumDuration },
        // No se llama «Spotify ID» porque muy a menudo no lo es: `:1418` rellena
        // `spotify_id` con `album.id` cuando no hay id de Spotify, y en un álbum del
        // mirror de MusicBrainz eso es un MBID. El rótulo dice de dónde sale el id sin
        // mentir sobre cuál de los dos catálogos lo emitió.
        { cls: 'album-id', get label() { return t('media.album.fields.catalogId'); }, value: (i) => i.spotify_id }
      ],
      extrasWrapped: true,
      extras: [
        { cls: 'album-field', get label() { return t('media.album.fields.favouriteTrack'); }, value: (i) => i.favoriteTrack ?? i.favorite_track ?? '' },
        { cls: 'album-field', get label() { return t('media.album.fields.firstListen'); }, value: (i) => i.dateStarted ?? i.date_started ?? '' },
        { cls: 'album-field', get label() { return t('media.album.fields.lastListen'); }, value: (i) => i.dateFinished ?? i.date_finished ?? '' },
        { cls: 'album-field', get label() { return t('media.album.fields.notes'); }, value: (i) => i.personalNotes ?? i.personal_notes ?? '' },
        { cls: 'album-field', get label() { return t('media.album.fields.format'); }, value: ownershipLabel, badge: true }
      ],
      // Álbumes y vídeos emiten el ítem entero, no un objeto envolvente.
      savePayload: (item, statuses, rating) => ({ ...item, userStatuses: statuses, user_rating: rating }),
      deletePayload: (item) => item?.id || item?.spotify_id,
      editPayload: (item) => [item]
    },
    store: {
      id: 'albums',
      collection: 'albums',
      One: 'Album',
      Many: 'Albums',
      idPayloadKey: 'albumId',
      matches: (item, id) => item.id === id,
      altGetter: { name: 'getAlbumBySpotifyId', key: 'spotify_id' },
      ratingField: 'user_rating',
      tagsIdKey: 'albumId',
      tagDefaultColor: '#007bff',
      statusMode: 'byId',
      addPayloadKey: 'album',
      addPushes: 'response',
      toAddPayload: (album, statuses) => ({
        spotify_id: album.spotify_id || album.spotifyId || album.id,
        title: album.title || album.name,
        artist: album.artist || album.artists?.[0]?.name || '',
        artist_id: album.artist_id || album.artists?.[0]?.id || '',
        release_date: album.release_date || album.releaseDate || '',
        release_date_precision: album.release_date_precision || album.releaseDatePrecision || 'year',
        cover_url: album.cover_url || album.coverUrl || album.images?.[0]?.url || '',
        genres: Array.isArray(album.genres)
          ? album.genres.map((g) => (typeof g === 'string' ? g : g.name))
          : [],
        label: album.label || '',
        total_tracks: album.total_tracks || album.totalTracks || 0,
        album_type: album.album_type || album.albumType || 'album',
        duration_ms: album.duration_ms || album.durationMs || 0,
        popularity: album.popularity || 0,
        external_url: album.external_url || album.externalUrl || album.external_urls?.spotify || '',
        upc: album.upc || '',
        userStatuses: statuses,
        ownership_format_id: album.ownership_format_id || null
      })
    },
    api: {
      // ⚠ Álbumes tiene acción propia: NO comparte get_library_items, al
      // contrario de lo que decía el snippet de arquitectura de este plan.
      list: 'get_albums',
      listPayload: { filters: {} },
      search: {
        action: 'search_spotify_albums',                             // routes.php:1210
        payload: (query, limit = 20) => ({ query, limit }),          // AlbumController.php:260
        transform: searchTransformAlbum,
        titleOf: (item) => item.title
      },
      add: 'add_album',
      remove: 'delete_album',
      rating: 'update_album_rating',
      statuses: 'update_album_user_statuses',
      edit: 'edit_user_album',
      allowedStatuses: 'get_album_allowed_statuses',
      tags: {
        get: 'get_album_tags',
        list: 'get_user_album_tags',
        create: 'create_user_album_tag',
        update: 'update_album_tags'
      }
    },
    /**
     * Lo que `createMediaComposable` no puede deducir del bloque `store`: los
     * campos que `editUserAlbum` propaga al array local tras el OK del backend.
     * El resto sale de `store` y `api`.
     */
    composable: {
      editSyncFields: [
        'listenCount',
        'favoriteTrack',
        'dateStarted',
        'dateFinished',
        'personalNotes',
        'ownership_format_id',
        'ownershipFormat'
      ]
    },
    list: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 56, height: 56 },
      iconOf: () => 'fas fa-music',
      // Portada cuadrada por convención musical, no por descuido.
      aspect: '1/1',
      width: '56px',
      idOf: (i) => i.id || i.spotify_id,
      coverOf: (i) => i.cover_url,
      titleOf: (i) => i.title || i.name,
      subtitleOf: (i) => joinSubtitle(
        i.artist || i.artists?.[0]?.name || t('media.album.unknownArtist'),
        yearOf(i.release_date || i.releaseDate)
      )
    },
    notes: {
      get title() { return t('media.album.notesTitle'); },
      emptyIcon: 'pi pi-headphones',
      get emptyHint () { return t('media.album.notesHint') },
      types: NOTE_TYPES_DEFAULT,
      typeIcons: NOTE_TYPE_ICONS_DEFAULT,
      typeFallbackIcon: 'pi-file-edit',
      typeFallbackLabel: 'Nota',
      hasPageNumber: false,
      sortBy: 'date',
      actions: {
        list: 'get_album_notes',
        add: 'add_album_note',
        update: 'update_album_note',
        delete: 'delete_album_note'
      }
    }
  },

  video: {
    key: 'video',
    get label() { return t('media.video.label'); },
    get labelPlural () { return t('media.video.labelPlural') },
    idProp: 'youtubeId',
    idPayloadKey: 'youtubeId',
    idType: String,
    routeName: 'VideoDetail',
    accentVar: '--color-card-video-accent',
    progress: {
      axis: null,
      get label() { return t('media.video.fields.seen'); },
      unit: null,
      completedStatuses: ['watched']
    },
    // Ficha de detalle (MediaDetailView).
    detail: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 320, height: 180 },
      stateKey: 'video',
      librarySectionClass: 'library-section',
      libraryTitleIcon: false,
      routeParam: 'youtubeId',
      get backText () { return t('media.video.back') },
      backRoute: 'Videos',
      get loadingText () { return t('media.video.loading') },
      get notFoundText () { return t('media.video.notFound') },
      get errorText () { return t('media.video.error') },
      get emptyText() { return t('media.video.emptyText'); },
      placeholderIcon: 'fab fa-youtube',
      get libraryTitleNew () { return t('media.video.libraryNew') },
      get libraryTitleExisting () { return t('media.video.libraryExisting') },
      divider: false,
      hasNotes: true,
      // El selector de estados de los vídeos trabaja con nombres, no con los
      // objetos que devuelve el backend.
      statusesAsNames: true,
      // Hasta el 2026-08-27 esto era `null` y la ficha salía solo del store, así
      // que un vídeo que no tuvieras guardado daba `notFoundText`
      // (MediaDetailView.vue:362-372) y no había dónde guardarlo. La acción
      // expone un método que ya existía y cachea 24 h.
      enrich: async (routeId, apiCall) => {
        const response = await apiCall('get_youtube_video_details', { youtubeId: routeId })
        if (response.data?.status !== 'success' || !response.data?.data) return null
        return { item: response.data.data.video || response.data.data }
      },
      existingOf: (store, item, routeId) => store.getVideoByYouTubeId(
        routeId || item.youtube_id || item.youtubeId
      ),
      mergeFields: (existing) => ({ ...existing }),
      itemForModal: (item, stored) => ({ ...item, ...stored }),
      unwrapSave: (payload) => [payload, payload.userStatuses || []],
      unwrapDelete: (id) => id,
      get deleteConfirm () { return t('media.video.deleteConfirm') }
    },
    libraryItem: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 120, height: 68 },
      get coverAlt() { return t('media.video.coverAlt'); },
      idOf: (i) => i.youtube_id || i.youtubeId,
      coverOf: (i) => i.cover_url || i.coverUrl,
      titleOf: (i) => i.title,
      get statusLabel() { return t('media.video.statusLabel'); },
      // ⚠ Único medio que NO preselecciona 'owned' al añadir.
      defaultStatus: null,
      ratingFallback: null,
      fields: [
        { cls: 'video-channel', get label() { return t('media.video.fields.channel'); }, value: (i) => i.channel_name || i.channelName },
        { cls: 'video-duration', get label() { return t('media.video.fields.duration'); }, value: (i) => i.duration },
        // YouTube manda la fecha en ISO-8601 con hora y zona, y sin esto se pintaba
        // tal cual: «2026-08-10T20:54:12Z».
        { cls: 'video-published', get label() { return t('media.video.fields.published'); }, value: (i) => formatDate(i.published_at || i.publishedAt) },
        { cls: 'video-id', get label() { return t('media.video.fields.youtubeId'); }, value: (i) => i.youtube_id || i.youtubeId }
      ],
      extrasWrapped: true,
      extras: [
        { cls: 'video-field', get label() { return t('media.video.fields.timesWatched'); }, value: (i) => i.watchCount ?? i.watch_count ?? null },
        { cls: 'video-field', get label() { return t('media.video.fields.notes'); }, value: (i) => i.personalNotes ?? i.personal_notes ?? '' }
      ],
      savePayload: (item, statuses, rating) => ({ ...item, userStatuses: statuses, user_rating: rating }),
      deletePayload: (item) => item?.youtube_id || item?.youtubeId || item?.id,
      editPayload: (item) => [item]
    },
    // Todo lo que createMediaStore necesita para generar el store de vídeos y,
    // sobre todo, los alias con nombre de medio que usan hoy sus consumidores.
    store: {
      // Se conserva el id que ya tenía defineStore: no hay persistencia que
      // dependa de él, pero renombrarlo solo despistaría en devtools.
      id: 'videos',
      collection: 'videos',
      One: 'Video',
      Many: 'Videos',
      // Coincide con el `idPayloadKey` del bloque raíz, pero se declara aquí
      // igual que en los otros cuatro: en `movie` y `book` NO coinciden.
      idPayloadKey: 'youtubeId',
      // Borrar, valorar y editar casan contra el id de YouTube, que es lo que
      // viaja en el payload...
      matches: (item, id) => item.youtube_id === id,
      // ...pero getVideoById casa contra la PK de la tabla (videos.js:50).
      byId: (item, id) => item.id === id,
      // Getter que no sigue el patrón y hay que conservar por nombre.
      altGetter: { name: 'getVideoByYouTubeId', key: 'youtube_id' },
      ratingField: 'user_rating',
      // ⚠ update_video_tags es la única acción del medio que NO manda
      // `youtubeId`: espera `videoId` (store/videos.js:395-398).
      tagsIdKey: 'videoId',
      tagDefaultColor: '#c0392b',
      /**
       * Normaliza al payload de add_video lo que llega de la búsqueda de
       * YouTube, que mezcla snake_case y camelCase según el origen.
       */
      toAddPayload: (video, statuses) => ({
        youtube_id: video.youtube_id || video.youtubeId || video.id,
        title: video.title || video.name || '',
        channel_name: video.channel_name || video.channelName || '',
        channel_id: video.channel_id || video.channelId || '',
        cover_url: video.cover_url || video.coverUrl || video.thumbnail || '',
        duration: video.duration || '',
        duration_seconds: video.duration_seconds || video.durationSeconds || 0,
        view_count: video.view_count || video.viewCount || 0,
        like_count: video.like_count || video.likeCount || 0,
        published_at: video.published_at || video.publishedAt || '',
        description: video.description || '',
        categories: Array.isArray(video.categories) ? video.categories : [],
        userStatuses: statuses
      })
    },
    api: {
      list: 'get_videos',
      listPayload: { filters: {} },
      search: {
        action: 'search_youtube_videos',                             // routes.php:1429
        // ⚠ El único que NO llama `limit` al límite: `VideoController.php:111`
        // lee `maxResults`. Mandar `limit` aquí lo dejaría en su valor por
        // defecto de 10 sin dar ningún error.
        payload: (query, limit = 20) => ({ query, maxResults: limit }),
        transform: searchTransformVideo,
        titleOf: (item) => item.title
      },
      // Su búsqueda depende de una API viva sin dump abierto, así que el
      // backend puede servir caché caducada y `GenericSearch` tiene que
      // decirlo. Películas y álbumes NO lo declaran: los sirve el mirror
      // local y no pueden ser rancios.
      supportsStale: true,
      add: 'add_video',
      remove: 'delete_video',
      rating: 'update_video_rating',
      statuses: 'update_video_user_statuses',
      edit: 'edit_user_video',
      allowedStatuses: 'get_video_allowed_statuses',
      tags: {
        get: 'get_video_tags',
        list: 'get_user_video_tags',
        create: 'create_user_video_tag',
        update: 'update_video_tags'
      }
    },
    /**
     * Lo propio de vídeos.
     *
     * `editSyncFields` lleva **solo** `personalNotes` a propósito. `EditItemModal`
     * manda además `ownership_format_id` para los cinco medios, pero
     * `EditUserVideoCommand` no lo lee y el repositorio de vídeos no lo guarda:
     * sincronizarlo en el array local enseñaría un formato de propiedad que
     * desaparece en el siguiente refresco.
     */
    composable: {
      editSyncFields: ['personalNotes']
    },
    list: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 50, height: 56 },
      iconOf: () => 'fab fa-youtube',
      aspect: '16/9',
      width: '56px',
      idOf: (i) => i.youtube_id || i.youtubeId,
      coverOf: (i) => i.cover_url,
      titleOf: (i) => i.title,
      subtitleOf: (i) => joinSubtitle(
        i.channel_name || i.channelName || t('media.video.unknownChannel'),
        i.duration || ''
      )
    },
    notes: {
      get title() { return t('media.video.notesTitle'); },
      // Único medio que usa FontAwesome aquí (VideoNotes.vue:62). Se reproduce
      // tal cual: `pi pi-youtube` existe, pero cambiarlo cambiaría el glifo.
      emptyIcon: 'fab fa-youtube',
      get emptyHint () { return t('media.video.notesHint') },
      types: NOTE_TYPES_DEFAULT,
      typeIcons: NOTE_TYPE_ICONS_DEFAULT,
      typeFallbackIcon: 'pi-file-edit',
      typeFallbackLabel: 'Nota',
      hasPageNumber: false,
      sortBy: 'date',
      actions: {
        list: 'get_video_notes',
        add: 'add_video_note',
        update: 'update_video_note',
        delete: 'delete_video_note'
      }
    }
  },
  // Las series son un medio propio de cara a la ficha: tienen su ruta, su
  // enriquecimiento y su tracker de temporadas. **No tienen store**: comparten
  // el de películas, que es la misma entidad en el backend. Por eso este bloque
  // no declara `store` ni `api`, y createMediaStore('series') falla a propósito.
  series: {
    key: 'series',
    get label() { return t('media.series.label'); },
    get labelPlural () { return t('media.series.labelPlural') },
    idProp: 'imdbId',
    idPayloadKey: 'movieIsbn',
    idType: String,
    routeName: 'SeriesDetail',
    // Comparte identidad visual con películas.
    accentVar: '--color-card-movie-accent',
    /**
     * `series` es la sexta entrada y necesita su bloque aunque no tenga store:
     * al declarar esto hay que iterar `mediaKeys` (los seis) y NO
     * `storeMediaKeys` (los cinco), que es el error fácil.
     *
     * `completedStatuses` es `viewed` y no algo derivado de las temporadas: es
     * la misma fuente que usa `ClubCompletion` en el backend
     * (`user_movie_statuses`). Contar temporadas aquí haría que la pantalla
     * contradijera al cierre automático.
     */
    progress: {
      axis: 'season',
      get label() { return t('media.series.fields.season'); },
      get unit () { return t('media.series.unit') },
      completedStatuses: ['viewed']
    },
    api: {
      // El sexto bloque, y el que se olvida: `series` no tiene store ni acción
      // propia, pero SÍ sale de una búsqueda. Comparte respuesta con películas
      // —la misma `search_movies_omdb`— y se separan en el cliente con
      // `isSeries()`, que mira el `type` que `searchTransformOmdb` conserva.
      // Quien itere esto tiene que recorrer `mediaKeys` (los seis) y NO
      // `storeMediaKeys` (los cinco): es el error fácil que avisa este fichero.
      search: {
        action: 'search_movies_omdb',
        payload: (query, limit = 20) => ({ title: query, limit }),
        transform: searchTransformOmdb,
        titleOf: (item) => item.title,
        // Marca que esta entrada NO añade una petición: se sirve de la de
        // películas. El buscador general tiene que deduplicar por aquí o pedirá
        // dos veces lo mismo.
        sharesActionWith: 'movie',
        belongsHere: (item) => isSeries(item)
      }
    },
    detail: {
      // Dimensiones intrínsecas de la portada en esta familia, en px: el navegador las usa
      // para reservar la caja antes de que cargue la imagen. Salen de los mixins SCSS de la
      // familia y tienen que ir sincronizadas con ellos.
      coverAspect: { width: 220, height: 330 },
      stateKey: 'movie',
      routeParam: 'imdbId',
      get backText () { return t('media.series.back') },
      backRoute: 'Movies',
      get loadingText () { return t('media.series.loading') },
      get notFoundText () { return t('media.series.notFound') },
      get errorText () { return t('media.series.error') },
      get emptyText() { return t('media.series.emptyText'); },
      // La atribución de TMDB NO es decorativa ni opcional: sus condiciones de
      // uso exigen mostrar el logo y esta frase en cualquier pantalla que use
      // datos suyos. Solo la llevan película y serie, que son los dos medios
      // enriquecidos con TMDB.
      attribution: {
        logo: tmdbLogo,
        get alt () { return t('brand.tmdb') },
        href: 'https://www.themoviedb.org/',
        get text () { return t('brand.tmdbNotice') }
      },
      placeholderIcon: 'fas fa-tv',
      coverClass: 'series-poster-large',
      coverImageClass: 'poster-image-large',
      placeholderClass: 'poster-placeholder',
      librarySectionClass: 'library-form-section',
      libraryTitleIcon: true,
      get libraryTitleNew () { return t('media.series.libraryNew') },
      get libraryTitleExisting () { return t('media.series.libraryExisting') },
      divider: false,
      hasNotes: false,
      statusesAsNames: false,
      // La ficha de biblioteca y el modal son los de películas.
      libraryMedia: 'movie',
      // Al revés que las películas: la serie descarta 'abandoned' y usa 'dropped'.
      allowedStatusesFilter: (all) => all.filter((s) => s !== 'abandoned'),
      existingOf: (store, item, routeId) => store.getMovieById(item.imdbID || routeId),
      enrich: async (routeId, apiCall) => {
        const response = await apiCall('get_movie_details_omdb', { imdbId: routeId, plot: 'full' })
        if (response.data?.status !== 'success' || !response.data?.data) return null
        return { item: transformOmdb(response.data.data) }
      },
      mergeFields: omdbMergeFields,
      itemForModal: (item, stored) => ({
        ...item,
        ...omdbMergeFields(stored),
        isbn: stored.isbn ?? item?.isbn,
        imdbID: stored.imdbID ?? item?.imdbID
      }),
      unwrapSave: (payload) => [payload.movie, payload.statuses],
      unwrapDelete: (payload) => payload.isbn,
      get savedMessage () { return t('media.series.saved') },
      // ⚠ Igual que en películas, antes el borrado no hacía nada
      // (SeriesDetailView.vue:319).
      get deleteConfirm () { return t('media.series.deleteConfirm') },
      get deletedMessage () { return t('media.series.deleted') },
      get deleteErrorMessage () { return t('media.series.deleteError') }
    }
  }
}

// `series` toma de `movie` los dos bloques que no declara, y fuera del literal
// porque dentro no puede referirse a otra entrada del mismo objeto. Le valen
// **verbatim**: las series salen de la MISMA respuesta que las películas
// —`search_movies_omdb`, forma OMDb— y se separan por su `Type`, así que
// `titleOf: i.Title`, `coverOf: i.Poster` e `idOf: i.imdbID` leen lo mismo.
//
// `list` se añadió el 2026-09-01 al meter series en el buscador general: sin él,
// `MediaListItem` revienta con «config.value.list is undefined» en cuanto una
// serie entra en una lista mezclada.
mediaRegistry.series.libraryItem = mediaRegistry.movie.libraryItem
mediaRegistry.series.list = mediaRegistry.movie.list

/** Los medios que el registry conoce, en el orden en que se declararon. */

export const mediaKeys = Object.keys(mediaRegistry)

/** Los medios con store propio: los cinco de siempre, sin `series`. */
export const storeMediaKeys = mediaKeys.filter((key) => Boolean(mediaRegistry[key].store))

/**
 * Devuelve la configuración de un medio, fallando ruidosamente si no existe:
 * un medio mal escrito en una prop tiene que romper en desarrollo, no pintar
 * un panel vacío.
 */
export function getMediaConfig (media) {
  const config = mediaRegistry[media]
  if (!config) {
    throw new Error(`[mediaRegistry] Medio desconocido: "${media}". Válidos: ${mediaKeys.join(', ')}`)
  }
  return config
}

/**
 * El destino de la ficha de detalle de un ítem, para `router.push()` o el `to`
 * de un `<router-link>`.
 *
 * Las dos mitades del destino ya estaban declaradas por medio —`routeName` en la
 * raíz del bloque y `detail.routeParam`—, así que esto **no declara nada nuevo**:
 * las compone. Un campo `detailRoute` por entrada habría sido una tercera copia
 * del nombre de la ruta y del parámetro, con dos sitios que pueden divergir.
 *
 * A diferencia de `getMediaConfig()`, aquí un medio desconocido **no revienta**:
 * quien llama es la tarjeta del feed, y `feed_events.entity_type` es NULLable y
 * tiene en su ENUM tipos que hoy no emite nadie (`achievement`). Devolver `null`
 * es lo que deja pintar la tarjeta sin enlace en vez de romper el feed entero.
 *
 * @param {string} media clave del registry: 'book' | 'movie' | 'game' | 'album' | 'video' | 'series'
 * @param {string|number} entityId la clave natural del medio, que es la que la ruta espera
 * @returns {{name: string, params: Object}|null} null si el medio no existe o no hay id
 */
export function detailRouteFor (media, entityId) {
  const config = mediaRegistry[media]
  if (!config || entityId === null || entityId === undefined || entityId === '') {
    return null
  }

  return {
    name: config.routeName,
    params: { [config.detail.routeParam]: entityId }
  }
}
