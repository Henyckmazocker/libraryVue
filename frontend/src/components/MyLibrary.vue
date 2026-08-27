<template>
  <div class="library-container">
    <h1 class="title">
      Mi biblioteca
    </h1>
    
    <div class="controls-container">
      <div class="filter-checkboxes filter-checkboxes-row">
        <label class="filter-checkbox-pill"><input
          v-model="showBooks"
          type="checkbox"
        > <i
          class="fas fa-book"
          aria-hidden="true"
        /><span class="u-sr-only">Libros</span></label>
        <label class="filter-checkbox-pill"><input
          v-model="showMovies"
          type="checkbox"
        > <i
          class="fas fa-film"
          aria-hidden="true"
        /><span class="u-sr-only">Películas</span></label>
        <label class="filter-checkbox-pill"><input
          v-model="showGames"
          type="checkbox"
        > <i
          class="fas fa-gamepad"
          aria-hidden="true"
        /><span class="u-sr-only">Videojuegos</span></label>
        <label class="filter-checkbox-pill"><input
          v-model="showAlbums"
          type="checkbox"
        > <i
          class="fas fa-music"
          aria-hidden="true"
        /><span class="u-sr-only">Álbumes</span></label>
        <label class="filter-checkbox-pill"><input
          v-model="showVideos"
          type="checkbox"
        > <i
          class="fab fa-youtube"
          aria-hidden="true"
        /><span class="u-sr-only">Vídeos</span></label>
        <button
          class="import-button"
          title="Importar datos"
          aria-label="Importar datos desde un fichero"
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
          aria-label="Buscar en tu biblioteca por título o autor"
          placeholder="Buscar por título o autor..." 
          class="search-input"
        >
        <div class="sort-buttons">
          <button 
            :class="['sort-button', { active: sortField === 'title' }]"
            @click="toggleSort('title')"
          >
            Título
            <i
              v-if="sortField === 'title'"
              :class="sortDirection === 'asc' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"
            />
          </button>
          <button 
            :class="['sort-button', { active: sortField === 'author' }]"
            @click="toggleSort('author')"
          >
            Autor
            <i
              v-if="sortField === 'author'"
              :class="sortDirection === 'asc' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"
            />
          </button>
          <button 
            :class="['sort-button', { active: sortField === 'rating' }]"
            @click="toggleSort('rating')"
          >
            Valoración
            <i
              v-if="sortField === 'rating'"
              :class="sortDirection === 'asc' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"
            />
          </button>
          <button 
            :class="['sort-button', { active: sortField === 'date' }]"
            @click="toggleSort('date')"
          >
            Fecha
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
      label="Cargando biblioteca…"
    />
    <div
      v-if="fetchError"
      class="error-message"
    >
      {{ fetchError }}
    </div>

    <div
      v-if="!isLoading && !fetchError && displayedItems.length === 0"
      class="empty-library-message"
    >
      Tu biblioteca está vacía. Añade algo desde los buscadores.
    </div>

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
      noteError(key, error.message || 'Error de conexión con el backend');
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
    `Datos importados correctamente desde ${importData.service}. Archivo: ${importData.fileName}`
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
  padding: 5px 15px; /* Reducido padding lateral de 10px a 15px */
  padding-top: 20px; /* Reducido de 100px a 20px para estar más pegado arriba */
  width: 100%;
  max-width: 1600px; /* Aumentado de 1400px a 1600px para aprovechar más espacio */
  margin: auto;
  box-sizing: border-box;
}

.title {
  font-size: 1.8rem;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 15px;
  text-align: center;
}

.loading-message,
.empty-library-message,
.error-message,
.status-message {
  font-size: 1.2rem;
  color: var(--color-text-secondary);
  margin: 20px auto;
  width: 100%;
  max-width: 600px;
  text-align: center;
}

.error-message,
.status-message {
  font-size: 1rem;
  padding: 10px 15px;
  border-radius: 15px;
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
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-start;
  gap: 12px; /* Reducido de 20px */
  width: 100%;
  padding: 0;
}

/* Optimizar para mostrar más items por fila */
:deep(.book-item) { 
  flex-basis: calc(20% - 12px); /* 5 items por fila en pantallas grandes */
  max-width: calc(20% - 12px);
  min-width: 180px; /* Mínimo para que se vea bien */
  box-sizing: border-box; 
}

/* Responsive adjustments para optimizar espacio */
@include responsive-below(2xl) {
  :deep(.book-item) {
    flex-basis: calc(25% - 12px); /* 4 items por fila */
    max-width: calc(25% - 12px);
  }
}

@include responsive-below(xl) {
  :deep(.book-item) {
    flex-basis: calc(33.333% - 12px); /* 3 items por fila */
    max-width: calc(33.333% - 12px);
  }
}

@include responsive-below(md) {
  .library-container {
    padding: 5px 8px; /* Reducido padding lateral también en móvil */
    padding-top: 15px;
  }
  
  .controls-container {
    justify-content: center;
    margin-bottom: 12px;
  }
  
  :deep(.book-item) {
    flex-basis: calc(50% - 10px); /* 2 items por fila */
    max-width: calc(50% - 10px);
  }
  
  .book-list {
    gap: 10px;
  }
}

@include responsive-below(sm) {
  :deep(.book-item) {
    flex-basis: 100%; /* 1 item por fila */
    max-width: 100%;
  }
  
  .book-list {
    gap: 8px;
  }
}

.controls-container {
  display: flex;
  flex-direction: column;
  width: 100%;
  margin-bottom: 15px; /* Reducido de 25px */
  gap: 8px; /* Reducido de 10px */
}

.search-sort-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 15px;
}

.search-input {
  padding: 10px 15px;
  font-size: 1rem;
  border: 1px solid var(--color-border);
  border-radius: 20px;
  background-color: var(--color-background-mute);
  color: var(--color-text);
  flex-grow: 1;
  min-width: 200px;
}

.search-input::placeholder {
  color: var(--color-text-muted);
}

.sort-buttons {
  display: flex;
  gap: 8px;
}

.sort-button {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  font-size: 0.9rem;
  font-weight: 500;
  border: 2px solid var(--color-border);
  border-radius: 20px;
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
  font-size: 0.75rem;
}

.sort-dropdown {
  padding: 10px 15px;
  font-size: 1rem;
  border: 1px solid var(--color-border);
  border-radius: 20px;
  background-color: var(--color-background-mute);
  color: var(--color-text);
  cursor: pointer;
  min-width: 200px;
}

/* Checkboxes para filtro de tipo */
.filter-checkboxes {
  display: flex;
  gap: 18px;
  align-items: center;
  margin-bottom: 5px;
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
  border-radius: 999px;
  padding: 7px 18px 7px 10px;
  font-size: 1rem;
  color: var(--color-text);
  box-shadow: var(--shadow-light);
  transition: var(--transition-fast);
  cursor: pointer;
  user-select: none;
}

.filter-checkbox-pill input[type="checkbox"] {
  accent-color: var(--color-primary);
  margin-right: 8px;
  width: 18px;
  height: 18px;
}

.filter-checkboxes label {
  color: var(--color-text);
  font-size: 1rem;
  cursor: pointer;
  user-select: none;
}

.filter-checkboxes input[type="checkbox"] {
  accent-color: var(--color-primary);
  margin-right: 5px;
  width: 18px;
  height: 18px;
}

/* Import button */
.import-button {
  background: linear-gradient(135deg, var(--color-success), var(--color-primary-light));
  color: var(--color-text-light);
  border: none;
  border-radius: 999px;
  padding: 8px 20px;
  font-size: 1rem;
  font-weight: 500;
  cursor: pointer;
  transition: var(--transition-fast);
  box-shadow: var(--shadow-medium);
}

.import-button:hover {
  background: linear-gradient(135deg, var(--color-primary), var(--color-primary-hover));
  transform: translateY(-1px);
  box-shadow: var(--shadow-heavy);
}
</style> 