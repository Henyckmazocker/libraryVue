// El catálogo de eventos de producto (Plan «Catálogo de Eventos de Producto», M1).
//
// Es la ÚNICA lista de lo que la app puede mandar a Augur con track(): un nombre que no esté aquí,
// una prop que su entrada no declare o un valor fuera de su tipo NO se envía (analytics/index.js).
// Y es también lo que tools/augur-catalog.sh declara en el proyecto `libraryvue` de Augur, para que
// el dashboard no los liste como `undeclared`.
//
// 🔴 La regla que manda: NINGÚN dato personal ni contenido. Por eso las props solo tienen tres
// tipos, todos cerrados:
//   { enum: [...] }  una lista de valores conocidos (el medio, el estado, la action del API…)
//   'int'            un número entero (un código, un recuento, una longitud)
//   'bool'           verdadero o falso
// NO existe el tipo string libre, y no se añade: un título, una nota o un nombre de usuario no
// caben en ninguno de los tres. Si un evento necesita de verdad una cadena, se declara como enum de
// valores conocidos o se reduce a su longitud (`query_length`). Nunca un array: Augur no agrupa por
// ellos (`fields` es CUÁNTOS campos, no cuáles).
//
// Lo vigila tests/unit/analytics-catalog.spec.js: tipos cerrados, nombres válidos para Augur, que
// todo track('…') de src/ esté declarado y que API_ACTIONS siga al ActionRouter del backend.
//
// Cómo se añade un evento: entrada aquí → track('nombre', {...}) tras el éxito → correr
// tools/augur-catalog.sh contra cada Augur (dev y prod).
//
// Este fichero es DATOS puros, sin imports: lo lee también tools/augur-catalog-export.cjs con Node,
// fuera de webpack y sin el alias `@`.

/** Los medios de la biblioteca: las claves de `config/mediaRegistry.js` (un test lo comprueba). */
export const MEDIA = ['book', 'movie', 'series', 'game', 'album', 'video']

/**
 * Las actions del `match` de `backend/src/Router/ActionRouter.php` (desde su línea 283), en su
 * orden. Estática a propósito: el frontend no ve el backend en tiempo de ejecución. Si el router
 * gana o pierde una, el test lo dice nombrándola.
 */
export const API_ACTIONS = [
  'login', 'logout', 'check_auth', 'update_user_profile', 'log_frontend', 'log_frontend_batch',
  'add_book', 'delete_book', 'update_book_rating', 'update_book_user_statuses', 'edit_user_book',
  'get_book_allowed_statuses', 'get_library', 'get_trending_books', 'search_works', 'get_work',
  'get_work_editions', 'validate_isbn', 'search_google_books_isbn', 'get_openlibrary_book_by_isbn',
  'get_user_book_tags', 'create_user_book_tag', 'get_book_tags', 'update_book_tags',
  'add_edition_note', 'update_edition_note', 'delete_edition_note', 'get_edition_notes',
  'get_edition_note', 'add_movie', 'delete_movie', 'update_movie_rating',
  'update_movie_user_statuses', 'edit_user_movie', 'get_movie_allowed_statuses', 'get_movies',
  'get_trending_movies', 'get_user_movie_tags', 'create_user_movie_tag', 'get_movie_tags',
  'update_movie_tags', 'get_movie_notes', 'add_movie_note', 'update_movie_note',
  'delete_movie_note', 'track_series_season', 'get_series_progress', 'search_movies_omdb',
  'get_movie_details_omdb', 'get_season_episodes_omdb', 'add_game', 'delete_game',
  'update_game_rating', 'update_game_user_statuses', 'edit_user_game', 'get_game_allowed_statuses',
  'get_games', 'get_trending_games', 'get_user_game_tags', 'create_user_game_tag',
  'delete_user_game_tag', 'get_game_tags', 'assign_tag_to_game', 'remove_tag_from_game',
  'update_game_tags', 'get_game_notes', 'add_game_note', 'update_game_note', 'delete_game_note',
  'get_igdb_config', 'get_igdb_token', 'search_igdb_games', 'search_catalog_local',
  'search_catalog_remote', 'get_igdb_game_by_id', 'get_igdb_game_details', 'add_album',
  'delete_album', 'update_album_rating', 'update_album_user_statuses', 'edit_user_album',
  'get_album_allowed_statuses', 'get_albums', 'get_trending_albums', 'get_user_album_tags',
  'create_user_album_tag', 'delete_user_album_tag', 'get_album_tags', 'update_album_tags',
  'get_album_notes', 'add_album_note', 'update_album_note', 'delete_album_note',
  'search_spotify_albums', 'get_spotify_album', 'get_spotify_artist', 'get_spotify_album_tracks',
  'get_spotify_new_releases', 'get_listening_stats', 'get_library_items', 'save_library',
  'import_data', 'ping', 'get_ownership_formats', 'libraryx_get_urls', 'libraryx_update_urls',
  'get_book_stats', 'get_movie_stats', 'get_game_stats', 'get_album_stats', 'get_video_stats',
  'create_reading_session', 'get_active_reading_session', 'complete_reading_session',
  'update_reading_progress', 'update_reading_progress_with_session', 'get_reading_session_history',
  'get_progress_history', 'get_session_progress', 'get_user_active_reading_sessions',
  'pause_reading_session', 'resume_reading_session', 'delete_reading_session',
  'get_book_reading_summary', 'get_detailed_progress_history', 'get_user_reading_stats',
  'get_current_reading_sessions', 'add_video', 'delete_video', 'update_video_rating',
  'update_video_user_statuses', 'edit_user_video', 'get_video_allowed_statuses', 'get_videos',
  'get_trending_videos', 'get_user_video_tags', 'create_user_video_tag', 'delete_user_video_tag',
  'get_video_tags', 'update_video_tags', 'get_video_notes', 'add_video_note', 'update_video_note',
  'delete_video_note', 'search_youtube_videos', 'get_youtube_video_details', 'send_friend_request',
  'accept_friend_request', 'reject_friend_request', 'remove_friend', 'get_friends',
  'get_friend_requests', 'search_users', 'get_public_profile', 'get_feed', 'get_privacy_settings',
  'get_journal', 'get_journal_calendar', 'get_user_journal', 'add_journal_entry',
  'update_journal_entry', 'delete_journal_entry', 'update_privacy_settings',
  'update_analytics_consent', 'create_list', 'update_list', 'delete_list', 'get_my_lists',
  'get_list', 'get_user_lists', 'add_list_item', 'invite_collaborator', 'accept_collaboration',
  'remove_collaborator', 'remove_list_item', 'create_club', 'get_my_clubs', 'get_club',
  'invite_to_club', 'accept_club_invitation', 'leave_club', 'set_club_pick', 'finish_club_pick',
  'get_club_progress', 'get_club_notes', 'propose_club_item', 'vote_club_proposal',
  'open_club_vote', 'close_club_vote', 'send_recommendation', 'get_inbox', 'get_inbox_count',
  'resolve_recommendation',
]

/** Los `name` de las rutas de `router/index.js` (un test lo comprueba). */
export const ROUTE_NAMES = [
  'Home', 'Books', 'Movies', 'Games', 'Albums', 'GeneralSearch', 'MyLibrary', 'UnifiedDashboard',
  'UserProfile', 'BookDetail', 'MovieDetail', 'SeriesDetail', 'GameDetail', 'AlbumDetail', 'Videos',
  'VideoDetail', 'Friends', 'Lists', 'ListDetail', 'Clubs', 'ClubDetail', 'Journal', 'Inbox',
  'PublicProfile', 'Privacy', 'NotFound',
]

/**
 * Los diálogos con formulario que miden apertura, abandono y envío (el prop `analyticsName` de
 * `BaseModal.vue`, M2). Los diez de la lista del M2 del plan.
 */
export const DIALOGS = [
  'list_form', 'club_form', 'journal_entry', 'recommend', 'invite_to_club', 'invite_collaborator',
  'add_to_list', 'add_to_club', 'edit_item', 'import',
]

/**
 * Los estados de la biblioteca como SLUG (las claves `status.*` de `locales/es.yaml`), más `none`
 * (se quitaron todos) y `unknown`. El backend manda algunos con espacio (`'to read'`): quien
 * dispare `item_status_changed` lo normaliza a slug antes.
 */
export const STATUSES = [
  '100-completed', 'abandoned', 'backlog', 'completed', 'dropped', 'favorite', 'in-watchlist',
  'in-wishlist', 'listened', 'listening', 'on-hold', 'owned', 'paused', 'played', 'playing',
  're-listening', 're-reading', 're-watching', 'read', 'reading', 'saved', 'to-read', 'viewed',
  'want-to-buy', 'want-to-listen', 'want-to-watch', 'watched', 'watching', 'none', 'unknown',
]

/**
 * Los `value` de los tipos de nota de `config/mediaRegistry.js` (un test lo comprueba), más
 * `unknown` para un tipo que el backend devuelva y el registry no conozca (M3).
 */
export const NOTE_TYPES = ['note', 'review', 'thought', 'quote', 'question', 'summary', 'progress', 'general', 'unknown']

/** Los `id` de `availableServices` de `composables/useFileImport.js`; `unknown` sin servicio elegido. */
export const IMPORT_SERVICES = ['palomitacas', 'letterboxd', 'goodreads', 'serialized', 'unknown']

/** Las escrituras de `store/clubs.js` que pasan por `_write`. */
export const CLUB_WRITE_ACTIONS = [
  'create_club', 'invite_to_club', 'leave_club', 'set_club_pick', 'finish_club_pick',
  'propose_club_item', 'vote_club_proposal', 'open_club_vote', 'close_club_vote',
]

/** Las escrituras de `store/lists.js` que pasan por `_write`. */
export const LIST_WRITE_ACTIONS = [
  'create_list', 'update_list', 'delete_list', 'add_list_item', 'remove_list_item',
  'invite_collaborator', 'remove_collaborator',
]

/** `components/Lists/visibility.js`. */
export const VISIBILITIES = ['private', 'public', 'collaborative']

const MEDIA_PROP = { enum: MEDIA }
const API_ACTION_OR_UNKNOWN = [...API_ACTIONS, 'unknown']
const CODE = 'int'

/**
 * Qué significa cada prop, por nombre. Es lo que ve quien monta una gráfica en Augur (la
 * `description` de cada propiedad); el tipo y los valores los añade el export.
 */
export const PROP_DOCS = {
  action: 'Action del backend',
  code: 'Código de error del backend (http_code), 0 si no hubo respuesta',
  dialog: 'Diálogo',
  failed_items: 'Ítems que no se importaron',
  failed_media: 'Cuántos medios fallaron en la búsqueda remota',
  fields: 'Cuántos campos cambiaron',
  filter: 'Filtro de la bandeja',
  form: 'Formulario',
  forced: 'La sesión se cerró sola (401), no por el usuario',
  had_input: 'El formulario tenía algo escrito al cerrarse',
  has_comment: 'Lleva comentario',
  has_rating: 'Lleva valoración',
  is_friend: 'El perfil es de un amigo',
  is_private: 'La nota es privada',
  kind: 'Tipo',
  media: 'Medio',
  method: 'Cómo inició sesión',
  n_events: 'Eventos recibidos en la página',
  n_items: 'Ítems cargados',
  n_tags: 'Etiquetas asignadas tras el cambio',
  network: 'Fallo de red: no hubo respuesta del servidor',
  note_type: 'Tipo de nota',
  ok: 'El envío terminó bien',
  ok_items: 'Ítems importados',
  op: 'Operación',
  page: 'Página del feed (desde 1)',
  pages: 'Páginas leídas en la sesión',
  phase: 'Fase de la ronda tras cerrar la votación',
  query_length: 'Longitud del texto buscado (nunca el texto)',
  rating: 'Valoración en medias estrellas, de 0 a 10 (7 = 3,5 estrellas)',
  reason: 'Motivo del cierre',
  resolution: 'Qué hizo con la recomendación',
  results: 'Resultados obtenidos',
  retry_after: 'Segundos que pide esperar el backend',
  search_type: 'Tipo de búsqueda',
  season: 'Número de temporada',
  service: 'Servicio de origen del fichero',
  source: 'Desde dónde',
  stale: 'Resultados servidos de caché degradada',
  stale_media: 'Cuántos medios llegaron de caché degradada',
  status: 'Código HTTP (api_error, login_failed) o estado de la biblioteca como slug (item_status_changed)',
  to: 'Ruta que pedía sesión',
  view: 'Vista del calendario',
  visibility: 'Visibilidad de la lista',
}

/**
 * `{ nombre: { description, props: { prop: tipo } } }`. Nombres `objeto_verbo` en pasado y
 * snake_case, dentro del patrón de Augur `^[A-Za-z0-9_.:-]{1,64}$`. `form_submit` y `search`
 * reutilizan los nombres del catálogo por defecto de Augur para que sus gráficas funcionen solas.
 */
export const CATALOG = {
  // ── Transversales (M2) ───────────────────────────────────────────────────────────────────────
  api_error: {
    description: 'Una acción del API ha fallado (HTTP o error de negocio)',
    // `unknown`: lo cuentan los puntos de errores de negocio (`apiError`, `handleStoreError`),
    // que no saben qué action falló, y una action que el router todavía no conoce (M2).
    props: { action: { enum: API_ACTION_OR_UNKNOWN }, status: 'int', code: CODE, network: 'bool' },
  },
  api_rate_limited: {
    description: 'El backend ha limitado una acción (429)',
    props: { action: { enum: API_ACTION_OR_UNKNOWN }, retry_after: 'int' },
  },
  error_shown: {
    description: 'Se ha enseñado un aviso de error o advertencia',
    props: { source: { enum: [...ROUTE_NAMES, 'unknown'] } },
  },
  dialog_opened: {
    description: 'Se ha abierto un diálogo con formulario',
    props: { dialog: { enum: DIALOGS } },
  },
  dialog_dismissed: {
    description: 'Se ha cerrado un diálogo sin enviarlo',
    props: { dialog: { enum: DIALOGS }, had_input: 'bool' },
  },
  form_submit: {
    description: 'Se ha enviado un formulario',
    props: { form: { enum: DIALOGS }, ok: 'bool' },
  },
  confirm_cancelled: {
    description: 'Se ha cancelado una confirmación',
    props: { kind: { enum: ['default', 'danger', 'warning', 'success', 'info', 'unknown'] } },
  },
  auth_redirected: {
    description: 'Una ruta que pide sesión ha mandado a la portada',
    props: { to: { enum: [...ROUTE_NAMES, 'unknown'] } },
  },
  logged_in: {
    description: 'Sesión iniciada',
    props: { method: { enum: ['google_web', 'google_native'] } },
  },
  login_failed: {
    description: 'El inicio de sesión ha fallado',
    props: { status: 'int' },
  },
  logged_out: {
    description: 'Sesión cerrada',
    props: { forced: 'bool' },
  },
  profile_updated: {
    description: 'Perfil actualizado',
    props: { fields: 'int' },
  },

  // ── Biblioteca (M3) ──────────────────────────────────────────────────────────────────────────
  library_loaded: {
    description: 'Biblioteca de un medio cargada',
    props: { media: MEDIA_PROP, n_items: 'int' },
  },
  library_item_added: {
    description: 'Ítem añadido a la biblioteca',
    props: {
      media: MEDIA_PROP,
      source: { enum: ['search', 'detail', 'import', 'recommendation', 'list', 'unknown'] },
    },
  },
  library_item_add_failed: {
    description: 'No se ha podido añadir un ítem a la biblioteca',
    props: { media: MEDIA_PROP, code: CODE },
  },
  library_item_removed: {
    description: 'Ítem quitado de la biblioteca',
    props: { media: MEDIA_PROP },
  },
  item_rated: {
    description: 'Ítem valorado',
    props: { media: MEDIA_PROP, rating: 'int' },
  },
  item_status_changed: {
    description: 'Estado de un ítem cambiado',
    props: { media: MEDIA_PROP, status: { enum: STATUSES } },
  },
  item_edited: {
    description: 'Ítem editado',
    props: { media: MEDIA_PROP, fields: 'int' },
  },
  tag_created: {
    description: 'Etiqueta creada',
    props: { media: MEDIA_PROP },
  },
  item_tags_updated: {
    description: 'Etiquetas de un ítem cambiadas',
    props: { media: MEDIA_PROP, n_tags: 'int' },
  },
  series_season_tracked: {
    description: 'Temporada de una serie marcada',
    props: { season: 'int' },
  },

  // ── Notas (M3) ───────────────────────────────────────────────────────────────────────────────
  note_added: {
    description: 'Nota añadida',
    props: { media: MEDIA_PROP, note_type: { enum: NOTE_TYPES }, is_private: 'bool' },
  },
  note_updated: {
    description: 'Nota editada',
    props: { media: MEDIA_PROP, note_type: { enum: NOTE_TYPES } },
  },
  note_deleted: {
    description: 'Nota borrada',
    props: { media: MEDIA_PROP },
  },
  note_empty_rejected: {
    description: 'Se ha intentado guardar una nota vacía',
    props: { media: MEDIA_PROP },
  },

  // ── Sesiones de lectura (M3) ─────────────────────────────────────────────────────────────────
  reading_session_started: { description: 'Sesión de lectura iniciada', props: {} },
  reading_session_completed: {
    description: 'Sesión de lectura terminada',
    props: { reason: { enum: ['completed', 'abandoned', 'unknown'] }, pages: 'int' },
  },
  reading_session_paused: { description: 'Sesión de lectura en pausa', props: {} },
  reading_session_resumed: { description: 'Sesión de lectura reanudada', props: {} },
  reading_session_abandoned: { description: 'Sesión de lectura abandonada', props: {} },
  reading_session_deleted: { description: 'Sesión de lectura borrada', props: {} },

  // ── Diario (M3) ──────────────────────────────────────────────────────────────────────────────
  // `unknown` en el medio: editar y borrar solo llevan el id de la entrada, y el medio se busca
  // entre las cargadas (M3).
  journal_entry_saved: {
    description: 'Entrada del diario guardada',
    props: { op: { enum: ['add', 'update'] }, media: { enum: [...MEDIA, 'unknown'] }, has_rating: 'bool' },
  },
  journal_entry_deleted: {
    description: 'Entrada del diario borrada',
    props: { media: { enum: [...MEDIA, 'unknown'] } },
  },
  journal_save_failed: {
    description: 'No se ha podido guardar el diario',
    props: { op: { enum: ['add', 'update', 'delete'] }, code: CODE },
  },
  journal_filter_changed: {
    description: 'Filtro por medio del diario cambiado',
    props: { media: { enum: [...MEDIA, 'all'] } },
  },
  journal_calendar_viewed: {
    description: 'Calendario del diario consultado',
    props: { view: { enum: ['month', 'year'] } },
  },

  // ── Búsqueda (M3) ────────────────────────────────────────────────────────────────────────────
  search: {
    description: 'Búsqueda en un medio',
    props: {
      media: MEDIA_PROP,
      search_type: { enum: ['auto', 'name', 'title', 'id', 'direct', 'unknown'] },
      query_length: 'int',
      results: 'int',
      stale: 'bool',
    },
  },
  search_failed: {
    description: 'Una búsqueda ha fallado',
    props: { media: MEDIA_PROP },
  },
  search_empty_submitted: {
    description: 'Se ha buscado sin escribir nada',
    props: { media: MEDIA_PROP },
  },
  search_direct_navigated: {
    description: 'La búsqueda ha ido directa a la ficha por identificador',
    props: { media: MEDIA_PROP, kind: { enum: ['isbn', 'imdb', 'unknown'] } },
  },
  catalog_searched: {
    description: 'Búsqueda general en todo el catálogo',
    props: { query_length: 'int', results: 'int', failed_media: 'int', stale_media: 'int' },
  },
  user_searched: {
    description: 'Búsqueda de usuarios',
    props: { query_length: 'int', results: 'int' },
  },

  // ── Importación (M3) ─────────────────────────────────────────────────────────────────────────
  import_started: {
    description: 'Importación iniciada',
    props: { service: { enum: IMPORT_SERVICES } },
  },
  import_completed: {
    description: 'Importación terminada',
    props: { service: { enum: IMPORT_SERVICES }, ok_items: 'int', failed_items: 'int' },
  },
  import_failed: {
    description: 'La importación ha fallado',
    props: { service: { enum: IMPORT_SERVICES } },
  },
  import_file_rejected: {
    description: 'Fichero de importación rechazado',
    props: { service: { enum: IMPORT_SERVICES } },
  },
  import_cancelled: {
    description: 'Importación cancelada',
    props: { service: { enum: IMPORT_SERVICES } },
  },

  // ── Clubs (M4) ───────────────────────────────────────────────────────────────────────────────
  club_created: { description: 'Club creado', props: {} },
  club_viewed: { description: 'Club consultado', props: {} },
  club_view_failed: {
    description: 'No se ha podido abrir un club',
    props: { code: CODE },
  },
  club_member_invited: { description: 'Invitación a un club enviada', props: {} },
  club_left: { description: 'Se ha salido de un club', props: {} },
  club_pick_set: {
    description: 'Lectura del club fijada',
    props: { media: MEDIA_PROP },
  },
  club_pick_finished: { description: 'Lectura del club terminada', props: {} },
  club_item_proposed: {
    description: 'Propuesta añadida a la ronda del club',
    props: { media: MEDIA_PROP },
  },
  club_proposal_voted: { description: 'Propuesta del club votada', props: {} },
  club_vote_opened: { description: 'Votación del club abierta', props: {} },
  club_vote_closed: {
    description: 'Votación del club cerrada',
    props: { phase: { enum: ['proposing', 'voting', 'closed', 'unknown'] } },
  },
  club_action_failed: {
    description: 'Una escritura de clubs ha fallado',
    props: { action: { enum: CLUB_WRITE_ACTIONS }, code: CODE },
  },

  // ── Listas (M4) ──────────────────────────────────────────────────────────────────────────────
  list_created: {
    description: 'Lista creada',
    props: { visibility: { enum: VISIBILITIES } },
  },
  list_updated: {
    description: 'Lista editada',
    props: { fields: 'int', visibility: { enum: VISIBILITIES } },
  },
  list_deleted: { description: 'Lista borrada', props: {} },
  list_item_added: {
    description: 'Ítem añadido a una lista',
    props: { media: MEDIA_PROP },
  },
  list_item_removed: { description: 'Ítem quitado de una lista', props: {} },
  list_collaborator_invited: { description: 'Colaborador invitado a una lista', props: {} },
  list_collaborator_removed: { description: 'Colaborador quitado de una lista', props: {} },
  list_viewed: { description: 'Lista consultada', props: {} },
  list_view_failed: {
    description: 'No se ha podido abrir una lista',
    props: { code: CODE },
  },
  list_action_failed: {
    description: 'Una escritura de listas ha fallado',
    props: { action: { enum: LIST_WRITE_ACTIONS }, code: CODE },
  },

  // ── Social, bandeja y perfil (M5) ────────────────────────────────────────────────────────────
  friend_request_sent: {
    description: 'Solicitud de amistad enviada',
    props: { source: { enum: ['user_search', 'public_profile', 'unknown'] } },
  },
  friend_request_accepted: { description: 'Solicitud de amistad aceptada', props: {} },
  friend_request_rejected: { description: 'Solicitud de amistad rechazada', props: {} },
  friend_removed: { description: 'Amistad eliminada', props: {} },
  feed_loaded: {
    description: 'Página del feed cargada',
    props: { page: 'int', n_events: 'int' },
  },
  privacy_settings_updated: {
    description: 'Ajustes de privacidad cambiados',
    props: { fields: 'int' },
  },
  public_profile_viewed: {
    description: 'Perfil público consultado',
    props: { is_friend: 'bool' },
  },
  public_profile_view_failed: {
    description: 'No se ha podido abrir un perfil público',
    props: { code: CODE },
  },
  recommendation_sent: {
    description: 'Recomendación enviada',
    props: { media: MEDIA_PROP, has_comment: 'bool' },
  },
  recommendation_resolved: {
    description: 'Recomendación resuelta desde la bandeja',
    props: { resolution: { enum: ['added', 'dismissed'] }, media: MEDIA_PROP },
  },
  inbox_viewed: {
    description: 'Bandeja consultada',
    props: { filter: { enum: ['pending', 'added', 'dismissed'] }, n_items: 'int' },
  },
  list_collaboration_accepted: { description: 'Invitación a colaborar en una lista aceptada', props: {} },
}
