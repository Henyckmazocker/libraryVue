<template>
  <div class="library-container">
    <h1 class="title">My Saved Books</h1>
    
    <div class="controls-container">
      <div class="filter-checkboxes filter-checkboxes-row">
        <label class="filter-checkbox-pill"><input type="checkbox" v-model="showBooks" /> <i class="fas fa-book"></i></label>
        <label class="filter-checkbox-pill"><input type="checkbox" v-model="showMovies" /> <i class="fas fa-film"></i></label>
        <label class="filter-checkbox-pill"><input type="checkbox" v-model="showGames" /> <i class="fas fa-gamepad"></i></label>
        <label class="filter-checkbox-pill"><input type="checkbox" v-model="showAlbums" /> <i class="fas fa-music"></i></label>
        <button @click="openImportModal" class="import-button">
          <i class="fas fa-folder-open"></i>
        </button>
      </div>
      <div class="search-sort-row">
        <input 
          type="text" 
          v-model="searchQuery" 
          placeholder="Search by title or author..." 
          class="search-input"
        />
        <div class="sort-buttons">
          <button 
            @click="toggleSort('title')"
            :class="['sort-button', { active: sortField === 'title' }]"
          >
            Title
            <i v-if="sortField === 'title'" :class="sortDirection === 'asc' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"></i>
          </button>
          <button 
            @click="toggleSort('author')"
            :class="['sort-button', { active: sortField === 'author' }]"
          >
            Author
            <i v-if="sortField === 'author'" :class="sortDirection === 'asc' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"></i>
          </button>
          <button 
            @click="toggleSort('rating')"
            :class="['sort-button', { active: sortField === 'rating' }]"
          >
            Rating
            <i v-if="sortField === 'rating'" :class="sortDirection === 'asc' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"></i>
          </button>
          <button 
            @click="toggleSort('date')"
            :class="['sort-button', { active: sortField === 'date' }]"
          >
            Date
            <i v-if="sortField === 'date'" :class="sortDirection === 'asc' ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"></i>
          </button>
        </div>
      </div>
    </div>

    <div v-if="isLoading" class="loading-message">
      <i class="fas fa-spinner fa-spin"></i> Cargando biblioteca...
    </div>
    <div v-if="fetchError" class="error-message">{{ fetchError }}</div>

    <div v-if="!isLoading && !fetchError && displayedItems.length === 0" class="empty-library-message">
      Your library is currently empty. Add some books from the ISBN Finder!
    </div>

    <div v-if="displayedItems.length > 0" class="book-list">
      <template v-for="item in displayedItems" :key="item.itemType + '-' + (item.isbn || item.imdbID || item.id || item.rawgId)">
        <BookListItem
          v-if="item.itemType === 'book'"
          :book="item"
          :allowedStatuses="allowedUserStatusesList('book')"
          @click="navigateToBookDetail(item)"
          class="book-item"
        />
        <MovieListItem
          v-else-if="item.itemType === 'movie'"
          :movie="item"
          :allowedStatuses="allowedUserStatusesList('movie', item.media_type || item.mediaType)"
          @click="navigateToMovieDetail(item)"
          class="book-item"
        />
        <GameListItem
          v-else-if="item.itemType === 'game'"
          :game="item"
          :allowedStatuses="allowedUserStatusesList('game')"
          @click="navigateToGameDetail(item)"
          class="book-item"
        />
        <AlbumListItem
          v-else-if="item.itemType === 'album'"
          :album="item"
          :allowedStatuses="allowedUserStatusesList('album')"
          @click="navigateToAlbumDetail(item)"
          class="book-item"
        />
      </template>
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
import { useSearch } from '@/composables/useSearch';
import { useUIStore } from '@/store/ui';
import { useAuth } from '@/composables/useAuth';
import Logger from '@/utils/logger';
import BookListItem from './Books/BookListItem.vue';
import MovieListItem from './Movies/MovieListItem.vue';
import GameListItem from './Games/GameListItem.vue';
import AlbumListItem from './Albums/AlbumListItem.vue';
import ImportModal from './ImportModal.vue';

// Composables
const router = useRouter();
const { isAuthenticated } = useAuth();
const booksComposable = useBooks();
const moviesComposable = useMovies();
  const gamesComposable = useGames();
  const albumsComposable = useAlbums();
  const uiStore = useUIStore();
const searchSystem = useSearch({
  debounceDelay: 300,
  minQueryLength: 2
});

// Estados locales del componente
const showBooks = ref(true);
const showMovies = ref(true);
const showGames = ref(true);
const showAlbums = ref(true);
const fetchError = ref("");
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
  booksComposable.isLoading.value || moviesComposable.isLoading.value || gamesComposable.isLoading.value || albumsComposable.isLoading.value
);

const items = computed(() => {
  const books = booksComposable.books.value.map(book => ({ ...book, itemType: 'book' }));
  const movies = moviesComposable.movies.value.map(movie => ({ ...movie, itemType: 'movie' }));
  const games = gamesComposable.games.value.map(game => ({ ...game, itemType: 'game' }));
  const albums = albumsComposable.albums.value.map(album => ({ ...album, itemType: 'album' }));
  return [...books, ...movies, ...games, ...albums];
});

const allowedBookUserStatuses = computed(() => booksComposable.allowedStatuses.value);
const allowedMovieUserStatuses = computed(() => moviesComposable.allowedStatuses.value);
const allowedGameUserStatuses = computed(() => gamesComposable.allowedStatuses.value);
const allowedAlbumUserStatuses = computed(() => albumsComposable.allowedStatuses.value);

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
  return allowedBookUserStatuses.value;
};

const fetchLibrary = async () => {
  fetchError.value = "";
  try {
    // Cargar libros, películas y juegos en paralelo usando los composables
    await Promise.all([
      booksComposable.fetchBooks(),
      moviesComposable.fetchMovies(),
      gamesComposable.fetchGames(),
      albumsComposable.fetchAlbums(),
      booksComposable.fetchAllowedStatuses(),
      moviesComposable.fetchAllowedStatuses(),
      gamesComposable.fetchAllowedStatuses(),
      albumsComposable.fetchAllowedStatuses()
    ]);

    // Verificar errores de los composables
    if (booksComposable.error.value || moviesComposable.error.value || gamesComposable.error.value || albumsComposable.error.value) {
      const errors = [booksComposable.error.value, moviesComposable.error.value, gamesComposable.error.value, albumsComposable.error.value].filter(Boolean);
      fetchError.value = errors.join('; ');
    }
  } catch (error) {
    Logger.error("Error fetching library:", error);
    fetchError.value = "Error connecting to backend to fetch library.";
  }
};

const displayedItems = computed(() => {
  let processed = [...items.value];
  
  // Filtrar por tipo según los checkboxes
  processed = processed.filter(item => {
    if (item.itemType === 'book' && !showBooks.value) return false;
    if (item.itemType === 'movie' && !showMovies.value) return false;
    if (item.itemType === 'game' && !showGames.value) return false;
    if (item.itemType === 'album' && !showAlbums.value) return false;
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
@media (max-width: 1400px) {
  :deep(.book-item) {
    flex-basis: calc(25% - 12px); /* 4 items por fila */
    max-width: calc(25% - 12px);
  }
}

@media (max-width: 1200px) {
  :deep(.book-item) {
    flex-basis: calc(33.333% - 12px); /* 3 items por fila */
    max-width: calc(33.333% - 12px);
  }
}

@media (max-width: 768px) {
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

@media (max-width: 480px) {
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
  accent-color: #007bff;
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
  accent-color: #007bff;
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