<template>
  <div class="library-container">
    <h1 class="title">
      {{ t('library.title') }}
    </h1>
    
    <div class="controls-container">
      <div class="filter-checkboxes filter-checkboxes-row">
        <label class="filter-checkbox-pill"><input
          v-model="showBooks"
          type="checkbox"
        > <i
          class="fas fa-book"
          aria-hidden="true"
        /><span class="u-sr-only">{{ t('library.filters.books') }}</span></label>
        <label class="filter-checkbox-pill"><input
          v-model="showMovies"
          type="checkbox"
        > <i
          class="fas fa-film"
          aria-hidden="true"
        /><span class="u-sr-only">{{ t('library.filters.movies') }}</span></label>
        <label class="filter-checkbox-pill"><input
          v-model="showGames"
          type="checkbox"
        > <i
          class="fas fa-gamepad"
          aria-hidden="true"
        /><span class="u-sr-only">{{ t('library.filters.games') }}</span></label>
        <label class="filter-checkbox-pill"><input
          v-model="showAlbums"
          type="checkbox"
        > <i
          class="fas fa-music"
          aria-hidden="true"
        /><span class="u-sr-only">{{ t('library.filters.albums') }}</span></label>
        <label class="filter-checkbox-pill"><input
          v-model="showVideos"
          type="checkbox"
        > <i
          class="fab fa-youtube"
          aria-hidden="true"
        /><span class="u-sr-only">{{ t('library.filters.videos') }}</span></label>
        <button
          class="btn btn--primary import-button"
          :title="t('library.import.title')"
          :aria-label="t('library.import.aria')"
          @click="openImportModal"
        >
          <i
            class="fas fa-folder-open"
            aria-hidden="true"
          />
        </button>
      </div>
      <div class="search-sort-row">
        <input 
          v-model="searchQuery" 
          type="text" 
          :aria-label="t('library.search.aria')"
          :placeholder="t('library.search.placeholder')" 
          class="search-input"
        >
        <div class="sort-buttons">
          <button 
            :class="['sort-button', { active: sortField === 'title' }]"
            @click="toggleSort('title')"
          >
            {{ t('library.sort.title') }}
            <i
              v-if="sortField === 'title'"
              :class="sortDirection === 'asc' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"
            />
          </button>
          <button 
            :class="['sort-button', { active: sortField === 'author' }]"
            @click="toggleSort('author')"
          >
            {{ t('library.sort.author') }}
            <i
              v-if="sortField === 'author'"
              :class="sortDirection === 'asc' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"
            />
          </button>
          <button 
            :class="['sort-button', { active: sortField === 'rating' }]"
            @click="toggleSort('rating')"
          >
            {{ t('library.sort.rating') }}
            <i
              v-if="sortField === 'rating'"
              :class="sortDirection === 'asc' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"
            />
          </button>
          <button 
            :class="['sort-button', { active: sortField === 'date' }]"
            @click="toggleSort('date')"
          >
            {{ t('library.sort.date') }}
            <i
              v-if="sortField === 'date'"
              :class="sortDirection === 'asc' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"
            />
          </button>
        </div>
      </div>
    </div>

    <!-- Se retira con el PRIMER medio que responde, no con el último: la lista
         se rellena sola conforme llegan los otros cuatro. -->
    <MediaSkeleton
      v-if="isLoading && displayedItems.length === 0"
      variant="list-item"
      :count="8"
      :label="t('library.loading')"
    />
    <div
      v-if="fetchError"
      class="error-message"
    >
      {{ fetchError }}
    </div>

    <EmptyState
      v-if="!isLoading && !fetchError && displayedItems.length === 0"
      icon="fas fa-book-open"
      :title="t('library.empty.title')"
      :message="t('library.empty.message')"
    />

    <div
      v-if="displayedItems.length > 0"
      class="book-list"
    >
      <MediaListItem
        v-for="item in displayedItems"
        :key="item.itemType + '-' + (item.isbn || item.imdbID || item.id || item.rawgId)"
        :media="item.itemType"
        :item="item"
        :allowed-statuses="statusesFor(item)"
        class="book-item"
        @click="navigateToDetail(item)"
      />
    </div>

    <!-- Import Modal Component -->
    <ImportModal 
      :show="showImportModal" 
      @close="closeImportModal"
      @import-success="handleImportSuccess"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import EmptyState from '@/components/common/EmptyState.vue'
import { useI18n } from '@/composables/useI18n'
import { useRouter } from 'vue-router';
import { useBooks } from '@/composables/useBooks';
import { useMovies } from '@/composables/useMovies';
import { useGames } from '@/composables/useGames';
import { useAlbums } from '@/composables/useAlbums';
import { useVideos } from '@/composables/useVideos';
import { getMediaConfig, storeMediaKeys } from '@/config/mediaRegistry';
import { useSearch } from '@/composables/useSearch';
import { useUIStore } from '@/store/ui';
import { useAuth } from '@/composables/useAuth';
import Logger from '@/utils/logger';
import MediaListItem from './shared/MediaListItem.vue';
import MediaSkeleton from './shared/MediaSkeleton.vue';
import ImportModal from './ImportModal.vue';

// Composables
const { t } = useI18n();
const router = useRouter();
const { isAuthenticated } = useAuth();
const booksComposable = useBooks();
const moviesComposable = useMovies();
  const gamesComposable = useGames();
  const albumsComposable = useAlbums();
  const uiStore = useUIStore();
const videosComposable = useVideos();
const searchSystem = useSearch({
  debounceDelay: 300,
  minQueryLength: 2
});

// Estados locales del componente
const showBooks = ref(true);
const showMovies = ref(true);
const showGames = ref(true);
const showAlbums = ref(true);
const showVideos = ref(true);
// Un fallo por medio, no una cadena única: así el aviso puede decir cuál cayó
// y los otros cuatro siguen enseñando lo suyo.
const loadErrors = ref({});
const noteError = (key, message) => {
  loadErrors.value = { ...loadErrors.value, [key]: message };
};
const fetchError = computed(() => Object.entries(loadErrors.value)
  .filter(([, message]) => message)
  .map(([key, message]) => `${getMediaConfig(key).labelPlural}: ${message}`)
  .join('; '));
const sortField = ref('date');
const sortDirection = ref('desc');
const showImportModal = ref(false);

// Función para toggle de ordenación
const toggleSort = (field) => {
  if (sortField.value === field) {
    // Si es el mismo campo, cambiar dirección
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
  } else {
    // Si es campo nuevo, establecer dirección por defecto
    sortField.value = field;
    if (field === 'date' || field === 'rating') {
      sortDirection.value = 'desc'; // Más reciente/alto primero
    } else {
      sortDirection.value = 'asc'; // A-Z por defecto
    }
  }
};

// Estados computados combinados
const isLoading = computed(() =>
  booksComposable.isLoading.value || moviesComposable.isLoading.value || gamesComposable.isLoading.value || albumsComposable.isLoading.value || videosComposable.isLoading.value
);

const items = computed(() => {
  const books = booksComposable.books.value.map(book => ({ ...book, itemType: 'book' }));
  const movies = moviesComposable.movies.value.map(movie => ({ ...movie, itemType: 'movie' }));
  const games = gamesComposable.games.value.map(game => ({ ...game, itemType: 'game' }));
  const albums = albumsComposable.albums.value.map(album => ({ ...album, itemType: 'album' }));
  const videos = videosComposable.videos.value.map(video => ({ ...video, itemType: 'video' }));
  return [...books, ...movies, ...games, ...albums, ...videos];
});

const allowedBookUserStatuses = computed(() => booksComposable.allowedStatuses.value);
const allowedMovieUserStatuses = computed(() => moviesComposable.allowedStatuses.value);
const allowedGameUserStatuses = computed(() => gamesComposable.allowedStatuses.value);
const allowedAlbumUserStatuses = computed(() => albumsComposable.allowedStatuses.value);
const allowedVideoUserStatuses = computed(() => videosComposable.allowedStatuses.value.map(s => (typeof s === 'object' && s !== null) ? s.name : s));

const allowedUserStatusesList = (itemType, mediaType = null) => {
  if (itemType === 'movie') {
    const allStatuses = allowedMovieUserStatuses.value;
    if (mediaType === 'series') {
      // Series: quitar 'abandoned' (tiene 'dropped' como equivalente)
      return allStatuses.filter(s => s !== 'abandoned');
    }
    // Película: quitar estados exclusivos de series
    return allStatuses.filter(s => !['watching', 'on-hold', 'dropped'].includes(s));
  }
  if (itemType === 'game') return allowedGameUserStatuses.value;
  if (itemType === 'album') return allowedAlbumUserStatuses.value;
  if (itemType === 'video') return allowedVideoUserStatuses.value;
  return allowedBookUserStatuses.value;
};

const composables = {
  book: booksComposable,
  movie: moviesComposable,
  game: gamesComposable,
  album: albumsComposable,
  video: videosComposable
};

/**
 * Carga los cinco medios sin esperar unos por otros.
 *
 * Cada uno entra en la lista en cuanto responde —`items` es reactivo— y su
 * fallo se anota **por separado**: antes los cuatro errores se juntaban en una
 * sola cadena que no decía cuál había caído, se calculaban solo cuando había
 * vuelto la última de las diez llamadas, y vídeos ni siquiera se contaba.
 *
 * Sigue devolviendo una promesa de «todo cargado» porque tres llamantes hacen
 * `await fetchLibrary()` para refrescar; lo que ya no hace es retener el
 * pintado hasta entonces.
 */
const fetchLibrary = () => {
  loadErrors.value = {};

  return Promise.all(storeMediaKeys.map((key) => {
    const composable = composables[key];
    const { Many } = getMediaConfig(key).store;

    // Las dos llamadas del medio se encadenan por separado a propósito: si la
    // lista falla, el aviso sale ya, sin esperar a que vuelvan sus estados
    // permitidos.
    const anotar = (error) => {
      Logger.error(`[MyLibrary] Error cargando ${key}:`, error);
      noteError(key, error.message || t('toasts.backendOffline'));
    };
    // El store se traga sus propios errores y los deja en `error`.
    const revisar = () => {
      if (composable.error.value) noteError(key, composable.error.value);
    };

    return Promise.all([
      composable[`fetch${Many}`]().then(revisar).catch(anotar),
      composable.fetchAllowedStatuses().then(revisar).catch(anotar)
    ]);
  }));
};

const displayedItems = computed(() => {
  let processed = [...items.value];
  
  // Filtrar por tipo según los checkboxes
  processed = processed.filter(item => {
    if (item.itemType === 'book' && !showBooks.value) return false;
    if (item.itemType === 'movie' && !showMovies.value) return false;
    if (item.itemType === 'game' && !showGames.value) return false;
    if (item.itemType === 'album' && !showAlbums.value) return false;
    if (item.itemType === 'video' && !showVideos.value) return false;
    return true;
  });
  
  // Filtrar por búsqueda si hay query
  if (searchSystem.query.value.trim() !== "") {
    const lowerSearchQuery = searchSystem.query.value.toLowerCase();
    processed = processed.filter(item =>
      (item.title && item.title.toLowerCase().includes(lowerSearchQuery)) ||
      (item.name && item.name.toLowerCase().includes(lowerSearchQuery)) ||
      (item.author && item.author.toLowerCase().includes(lowerSearchQuery)) ||
      (item.director && item.director.toLowerCase().includes(lowerSearchQuery)) ||
      (item.developer && item.developer.toLowerCase().includes(lowerSearchQuery))
    );
  }
  
  // Ordenar según selección
  const sortKey = `${sortField.value}-${sortDirection.value}`;
  switch (sortKey) {
    case 'title-asc':
      processed.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
      break;
    case 'title-desc':
      processed.sort((a, b) => (b.title || '').localeCompare(a.title || ''));
      break;
    case 'author-asc':
      processed.sort((a, b) => (a.author || a.director || a.developer || '').localeCompare(b.author || b.director || b.developer || ''));
      break;
    case 'author-desc':
      processed.sort((a, b) => (b.author || b.director || b.developer || '').localeCompare(a.author || a.director || a.developer || ''));
      break;
    case 'rating-desc':
      processed.sort((a, b) => {
        const aRating = a.user_rating !== null && a.user_rating !== undefined ? a.user_rating : (a.rating || 0);
        const bRating = b.user_rating !== null && b.user_rating !== undefined ? b.user_rating : (b.rating || 0);
        return bRating - aRating;
      });
      break;
    case 'rating-asc':
      processed.sort((a, b) => {
        const aRating = a.user_rating !== null && a.user_rating !== undefined ? a.user_rating : (a.rating || 0);
        const bRating = b.user_rating !== null && b.user_rating !== undefined ? b.user_rating : (b.rating || 0);
        return aRating - bRating;
      });
      break;
    case 'date-desc':
      processed.sort((a, b) => (b.addedTimestamp || 0) - (a.addedTimestamp || 0));
      break;
    case 'date-asc':
      processed.sort((a, b) => (a.addedTimestamp || 0) - (b.addedTimestamp || 0));
      break;
  }
  return processed;
});



// Navigate to book detail page
const navigateToBookDetail = (book) => {
  router.push({
    name: 'BookDetail',
    params: { isbn: book.isbn },
    state: { book: JSON.parse(JSON.stringify(book)) }
  });
};

// Navigate to movie or series detail page
const navigateToMovieDetail = (movie) => {
  const mediaType = movie.media_type || movie.mediaType || 'movie';
  const routeName = mediaType === 'series' ? 'SeriesDetail' : 'MovieDetail';
  router.push({
    name: routeName,
    params: { imdbId: movie.imdbID || movie.isbn },
    state: { movie: JSON.parse(JSON.stringify(movie)) }
  });
};

// Navigate to game detail page
const navigateToGameDetail = (game) => {
  router.push({
    name: 'GameDetail',
    params: { gameId: game.id || game.rawgId || game.gameId },
    state: { game: JSON.parse(JSON.stringify(game)) }
  })
};

// Navigate to album detail page
const navigateToAlbumDetail = (album) => {
  router.push({
    name: 'AlbumDetail',
    params: { albumId: album.spotify_id || album.id },
    state: { album: JSON.parse(JSON.stringify(album)) }
  })
};

const navigateToVideoDetail = (video) => {
  router.push({
    name: 'VideoDetail',
    params: { youtubeId: video.youtube_id || video.youtubeId },
    state: { video: JSON.parse(JSON.stringify(video)) }
  })
};

// El v-for pasa por un solo MediaListItem, así que el despacho por medio vive
// aquí. Cada medio tiene su nombre de ruta y su parámetro, y las películas
// eligen entre MovieDetail y SeriesDetail: no se unifican todavía.
const navigateToDetail = (item) => {
  switch (item.itemType) {
    case 'book': return navigateToBookDetail(item);
    case 'movie': return navigateToMovieDetail(item);
    case 'game': return navigateToGameDetail(item);
    case 'album': return navigateToAlbumDetail(item);
    case 'video': return navigateToVideoDetail(item);
    default:
      Logger.warn('No hay detalle para este tipo de ítem', { itemType: item.itemType });
  }
};

// Solo las películas necesitan el segundo argumento, para distinguir los
// estados de serie de los de película.
const statusesFor = (item) => {
  return item.itemType === 'movie'
    ? allowedUserStatusesList('movie', item.media_type || item.mediaType)
    : allowedUserStatusesList(item.itemType);
};

// Import functionality methods
const openImportModal = () => {
  showImportModal.value = true;
};

const closeImportModal = () => {
  showImportModal.value = false;
};

const handleImportSuccess = async (importData) => {
  // Show success message in the main library
  uiStore.showSuccess(
    t('toasts.imported', { servicio: importData.service, fichero: importData.fileName })
  );
  
  // Refresh the library to show imported items
  await fetchLibrary();
};

// Montar componente
onMounted(async () => {
  // Wait for authentication before fetching library
  if (isAuthenticated.value) {
    await fetchLibrary();
  }
});

// Watch for authentication changes and fetch library when authenticated
watch(isAuthenticated, async (newValue) => {
  if (newValue && displayedItems.value.length === 0) {
    Logger.debug('[MyLibrary] User authenticated, fetching library...');
    await fetchLibrary();
  }
});

// Exponer searchQuery para el template
const searchQuery = searchSystem.query;

</script>

<style lang="scss">
@use '@/assets/styles/abstracts' as *;

.library-container {
  display: flex;
  flex-direction: column;
  padding: spacing(2xs) spacing(md);
  padding-top: spacing(md); /* Pegado arriba a propósito, no centrado */
  width: 100%;
  max-width: 1600px; /* Aumentado de 1400px a 1600px para aprovechar más espacio */
  margin: auto;
  box-sizing: border-box;
}

.title {
  font-size: var(--font-size-2xl);
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: spacing(md);
  text-align: center;
}

.loading-message,
.error-message,
.status-message {
  font-size: var(--font-size-lg);
  color: var(--color-text-secondary);
  margin: spacing(md) auto;
  width: 100%;
  max-width: 600px;
  text-align: center;
}

.error-message,
.status-message {
  font-size: var(--font-size-base);
  padding: spacing(sm) spacing(md);
  border-radius: radius(xl);
  box-sizing: border-box;
}

.error-message {
  color: var(--color-error);
  background-color: var(--color-error-bg);
}

.status-message.success {
  color: var(--color-success);
  background-color: var(--color-success-bg);
}

.status-message.error {
  color: var(--color-error);
  background-color: var(--color-error-bg);
}

.book-list {
  display: grid;
  // `min(240px, 100%)` es la pieza clave: sin ella una pista de 240px desborda en
  // cuanto el contenedor mide menos, que es el bug de móvil con otro disfraz.
  // `auto-fill` y no `auto-fit`: con pocos ítems, `auto-fit` los estira a todo el ancho.
  grid-template-columns: repeat(auto-fill, minmax(min(240px, 100%), 1fr));
  gap: spacing(sm);
  width: 100%;
  padding: 0;
}

@include responsive-below(md) {
  .library-container {
    padding: spacing(2xs) spacing(xs); /* Reducido padding lateral también en móvil */
    padding-top: spacing(md);
  }

  .controls-container {
    justify-content: center;
    margin-bottom: spacing(sm);
  }
}

// Los seis controles de filtro no caben en una fila por debajo de `sm`, así que
// envuelven; con un hueco mayor que `spacing(md)` se irían a tres filas en vez de dos.
@include responsive-below(sm) {
  .filter-checkboxes {
    gap: spacing(xs);
  }
}

.controls-container {
  display: flex;
  flex-direction: column;
  width: 100%;
  margin-bottom: spacing(md);
  gap: spacing(xs);
}

.search-sort-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: spacing(md);
}

.search-input {
  padding: spacing(sm) spacing(md);
  font-size: var(--font-size-base);
  border: 1px solid var(--color-border);
  border-radius: radius(xl);
  background-color: var(--color-background-mute);
  color: var(--color-text);
  flex-grow: 1;
  // Acotado al contenedor, como los seis diálogos de Listas y Clubs: un `min-width`
  // fijo empuja la fila fuera del viewport en cuanto la pantalla baja de 360px.
  min-width: min(200px, 100%);
}

.search-input::placeholder {
  color: var(--color-text-muted);
}

.sort-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: spacing(xs);
}

.sort-button {
  display: flex;
  align-items: center;
  gap: spacing(xs);
  padding: spacing(sm) spacing(md);
  font-size: var(--font-size-sm);
  font-weight: 500;
  border: 2px solid var(--color-border);
  border-radius: radius(xl);
  background-color: var(--color-background-mute);
  color: var(--color-text);
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.sort-button:hover {
  background-color: var(--color-background-soft);
  border-color: var(--color-primary);
}

.sort-button.active {
  background-color: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
}

.sort-button i {
  font-size: var(--font-size-xs);
}

.sort-dropdown {
  padding: spacing(sm) spacing(md);
  font-size: var(--font-size-base);
  border: 1px solid var(--color-border);
  border-radius: radius(xl);
  background-color: var(--color-background-mute);
  color: var(--color-text);
  cursor: pointer;
  min-width: min(200px, 100%);
}

/* Checkboxes para filtro de tipo */
.filter-checkboxes {
  display: flex;
  flex-wrap: wrap;
  gap: spacing(md);
  align-items: center;
  margin-bottom: spacing(2xs);
  margin-right: 0;
}

.filter-checkboxes-row {
  justify-content: flex-start;
}

.filter-checkbox-pill {
  display: flex;
  align-items: center;
  background: var(--color-background-soft);
  border: 1.5px solid var(--color-border);
  border-radius: radius(full);
  padding: spacing(xs) spacing(md) spacing(xs) spacing(sm);
  font-size: var(--font-size-base);
  color: var(--color-text);
  box-shadow: var(--shadow-light);
  transition: var(--transition-fast);
  cursor: pointer;
  user-select: none;
}

.filter-checkbox-pill input[type="checkbox"] {
  accent-color: var(--color-primary);
  margin-right: spacing(xs);
  width: 18px;
  height: 18px;
}

.filter-checkboxes label {
  color: var(--color-text);
  font-size: var(--font-size-base);
  cursor: pointer;
  user-select: none;
}

.filter-checkboxes input[type="checkbox"] {
  accent-color: var(--color-primary);
  margin-right: spacing(2xs);
  width: 18px;
  height: 18px;
}

/* Import button */
.import-button {
  // Píldora: es el único control redondo de la fila de filtros.
  border-radius: radius(full);
  box-shadow: shadow(medium);

  &:hover { transform: translateY(-1px); }
}

</style> 