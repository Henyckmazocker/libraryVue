<template>
  <div class="library-container">
    <h1 class="title">My Saved Books</h1>
    
    <div class="controls-container">
      <div class="filter-checkboxes filter-checkboxes-row">
        <label class="filter-checkbox-pill"><input type="checkbox" v-model="showBooks" /> <i class="fas fa-book"></i></label>
        <label class="filter-checkbox-pill"><input type="checkbox" v-model="showMovies" /> <i class="fas fa-film"></i></label>
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
        <select v-model="currentSort" @change="sortLibrary" class="sort-dropdown">
          <option value="title-asc">Title (A-Z)</option>
          <option value="title-desc">Title (Z-A)</option>
          <option value="author-asc">Author (A-Z)</option>
          <option value="author-desc">Author (Z-A)</option>
          <option value="rating-desc">Rating (Highest First)</option>
          <option value="rating-asc">Rating (Lowest First)</option>
          <option value="date-desc">Date Added (Newest First)</option>
          <option value="date-asc">Date Added (Oldest First)</option>
        </select>
      </div>
    </div>

    <div v-if="isLoading" class="loading-message">
      <i class="fas fa-spinner fa-spin"></i> Cargando biblioteca...
    </div>
    <div v-if="fetchError" class="error-message">{{ fetchError }}</div>
    <div :class="['status-message', notifications.statusType.value]" aria-live="polite" style="min-height: 2.5em;">
      <span v-if="notifications.statusMessage.value">{{ notifications.statusMessage.value }}</span>
    </div>

    <div v-if="!isLoading && !fetchError && displayedItems.length === 0 && !notifications.statusMessage.value" class="empty-library-message">
      Your library is currently empty. Add some books from the ISBN Finder!
    </div>

    <div v-if="displayedItems.length > 0" class="book-list">
      <template v-for="item in displayedItems" :key="item.itemType + '-' + (item.isbn || item.imdbID)">
        <LibraryBookItem
          v-if="item.itemType === 'book'"
          :book="item"
          :allowedUserStatuses="allowedUserStatusesList('book')"
          :editable="true"
          :readonly="true"
          @delete-book="handleDeleteBook"
          @update-rating="handleUpdateRating"
          @update-statuses="handleUpdateStatuses"
          @update-progress="handleUpdateProgress"
          @edit-item="handleEditItem"
          class="book-item"
        />
        <LibraryMovieItem
          v-else-if="item.itemType === 'movie'"
          :movie="item"
          :allowedUserStatuses="allowedUserStatusesList('movie')"
          :editable="true"
          :readonly="true"
          @delete-movie="handleDeleteBook"
          @update-rating="handleUpdateRating"
          @update-statuses="handleUpdateStatuses"
          @edit-item="handleEditItem"
          class="book-item"
        />
      </template>
    </div>

    <!-- Unified Edit Modal -->
    <EditItemModal
      v-if="modal.isOpen.value"
      :item="modal.currentItem.value"
      :item-type="modal.itemType.value"
      :allowed-statuses="allowedUserStatusesList(modal.itemType.value)"
      @close="modal.closeModal"
      @saved="handleModalSaved"
    />

    <!-- Import Modal Component -->
    <ImportModal 
      :show="showImportModal" 
      @close="closeImportModal"
      @import-success="handleImportSuccess"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, provide } from 'vue';
import { useBooks } from '@/composables/useBooks';
import { useMovies } from '@/composables/useMovies';
import { useSearch } from '@/composables/useSearch';
import { useLibraryNotifications } from '@/composables/useLibraryNotifications';
import { useItemModal } from '@/composables/useItemModal';
import Logger from '@/utils/logger';
import LibraryBookItem from './Books/LibraryBookItem.vue';
import LibraryMovieItem from './Movies/LibraryMovieItem.vue';
import EditItemModal from './EditItemModal.vue';
import ImportModal from './ImportModal.vue';

// Composables
const booksComposable = useBooks();
const moviesComposable = useMovies();
const notifications = useLibraryNotifications();
const modal = useItemModal();

// Provide notifications to child components
provide('notifications', notifications);

// Configurar búsqueda con debouncing
const searchSystem = useSearch({
  debounceDelay: 300,
  minQueryLength: 2
});

// Estados locales del componente
const showBooks = ref(true);
const showMovies = ref(true);
const fetchError = ref("");
const currentSort = ref('date-desc');
const showImportModal = ref(false);

// Estados computados combinados
const isLoading = computed(() => 
  booksComposable.isLoading.value || moviesComposable.isLoading.value
);

const items = computed(() => {
  const books = booksComposable.books.value.map(book => ({ ...book, itemType: 'book' }));
  const movies = moviesComposable.movies.value.map(movie => ({ ...movie, itemType: 'movie' }));
  return [...books, ...movies];
});

const allowedBookUserStatuses = computed(() => booksComposable.allowedStatuses.value);
const allowedMovieUserStatuses = computed(() => moviesComposable.allowedStatuses.value);

const allowedUserStatusesList = (itemType) => {
  if (itemType === 'movie') return allowedMovieUserStatuses.value;
  return allowedBookUserStatuses.value;
};

const fetchLibrary = async () => {
  fetchError.value = "";
  try {
    // Cargar libros y películas en paralelo usando los composables
    await Promise.all([
      booksComposable.fetchBooks(),
      moviesComposable.fetchMovies(),
      booksComposable.fetchAllowedStatuses(),
      moviesComposable.fetchAllowedStatuses()
    ]);

    // Verificar errores de los composables
    if (booksComposable.error.value || moviesComposable.error.value) {
      const errors = [booksComposable.error.value, moviesComposable.error.value].filter(Boolean);
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
    return true;
  });
  
  // Filtrar por búsqueda si hay query
  if (searchSystem.query.value.trim() !== "") {
    const lowerSearchQuery = searchSystem.query.value.toLowerCase();
    processed = processed.filter(item =>
      (item.title && item.title.toLowerCase().includes(lowerSearchQuery)) ||
      (item.author && item.author.toLowerCase().includes(lowerSearchQuery)) ||
      (item.director && item.director.toLowerCase().includes(lowerSearchQuery))
    );
  }
  
  // Ordenar según selección
  switch (currentSort.value) {
    case 'title-asc':
      processed.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
      break;
    case 'title-desc':
      processed.sort((a, b) => (b.title || '').localeCompare(a.title || ''));
      break;
    case 'author-asc':
      processed.sort((a, b) => (a.author || a.director || '').localeCompare(b.author || b.director || ''));
      break;
    case 'author-desc':
      processed.sort((a, b) => (b.author || b.director || '').localeCompare(a.author || a.director || ''));
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

const handleDeleteBook = async (payload) => {
  // payload: { isbn, imdbID, itemType }
  const { isbn, imdbID, itemType } = typeof payload === 'object' ? payload : { isbn: payload, itemType: 'book' };
  let confirmMsg = itemType === 'movie'
    ? `Are you sure you want to delete the movie with ID: ${imdbID || isbn}?`
    : `Are you sure you want to delete the book with ISBN: ${isbn}?`;
  if (!confirm(confirmMsg)) return;
  
  try {
    let result;
    if (itemType === 'movie') {
      const tmdbId = imdbID || isbn; // Usar el ID apropiado
      result = await moviesComposable.deleteMovie(tmdbId);
    } else {
      result = await booksComposable.deleteBook(isbn);
    }
    
    if (result.success) {
      notifications.showSuccess(`${itemType === 'movie' ? 'Movie' : 'Book'} deleted successfully.`);
      // Eliminar del array local
      if (itemType === 'movie') {
        const idx = moviesComposable.movies.value.findIndex(m => m.imdbID === (imdbID || isbn));
        if (idx !== -1) moviesComposable.movies.value.splice(idx, 1);
      } else {
        const idx = booksComposable.books.value.findIndex(b => b.isbn === isbn);
        if (idx !== -1) booksComposable.books.value.splice(idx, 1);
      }
    } else {
      notifications.showError(result.message || `Failed to delete ${itemType === 'movie' ? 'movie' : 'book'}.`);
    }
  } catch (error) {
    Logger.error("Error deleting item:", error);
    notifications.showError("Error connecting to backend to delete item.");
  }
};

const handleUpdateRating = async ({ isbn, rating, itemType }) => {
  try {
    let result;
    if (itemType === 'movie') {
      // Para películas, usar directamente el ID que viene (imdbID)
      result = await moviesComposable.updateMovieRating(isbn, rating);
    } else {
      result = await booksComposable.updateBookRating(isbn, rating);
    }
    
    if (result.success) {
      notifications.showSuccess("Rating updated successfully.");
    } else {
      notifications.showError(result.message || "Failed to update rating.");
    }
  } catch (error) {
    Logger.error("Error updating rating:", error);
    notifications.showError("Error connecting to backend to update rating.");
  }
};

// Manejar actualización de estados de usuario
const handleUpdateStatuses = async ({ isbn, statuses, itemType }) => {
  try {
    let result;
    if (itemType === 'movie') {
      // Para películas, usar directamente el ID que viene (imdbID)
      result = await moviesComposable.updateMovieStatuses(isbn, statuses);
    } else {
      result = await booksComposable.updateBookStatuses(isbn, statuses);
    }
    
    if (result.success) {
      notifications.showSuccess("Estados actualizados correctamente.");
    } else {
      notifications.showError(result.message || "No se pudieron actualizar los estados.");
    }
  } catch (error) {
    Logger.error("Error actualizando estados:", error);
    notifications.showError("Error conectando con el backend para actualizar estados.");
  }
};

// Manejar actualización de progreso de lectura
const handleUpdateProgress = async ({ isbn, updates }) => {
  // No limpiar el estado para updates silenciosos como el progreso de lectura
  try {
    // Encontrar el libro en el array original de libros y actualizarlo inmediatamente
    const bookIndex = booksComposable.books.value.findIndex(book => book.isbn === isbn);
    
    if (bookIndex !== -1) {
      // Actualizar los campos localmente para reactividad inmediata
      Object.keys(updates).forEach(key => {
        booksComposable.books.value[bookIndex][key] = updates[key];
      });
      
      Logger.debug('Book progress updated locally:', { isbn, updates });
      // No mostrar mensaje de éxito para actualizaciones automáticas como currentPage
      // ya que estas son frecuentes y podrían ser molestas para el usuario
    } else {
      Logger.warn('Book not found for progress update:', isbn);
    }
  } catch (error) {
    Logger.error("Error updating book progress locally:", error);
    notifications.showError("Error actualizando el progreso del libro localmente.");
  }
};

// Manejar apertura del modal de edición
const handleEditItem = (item, itemType) => {
  modal.openModal(item, itemType);
};

// Manejar cierre del modal de edición
const handleModalSaved = (updatedItem) => {
  Logger.debug('Item saved from modal:', updatedItem);
  
  // El singleton useMovies/useBooks ya actualiza los datos localmente
  // No necesitamos actualizar aquí porque ambos componentes comparten el mismo estado
  Logger.debug('Modal saved - singleton composables already updated the data');
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
  notifications.showSuccess(
    `Datos importados correctamente desde ${importData.service}. Archivo: ${importData.fileName}`
  );
  
  // Refresh the library to show imported items
  await fetchLibrary();
};

// Montar componente
onMounted(async () => {
  await fetchLibrary();
});

// Exponer searchQuery para el template
const searchQuery = searchSystem.query;

</script>

<style>
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
  font-size: 1.8rem; /* Reducido de 2.5rem */
  font-weight: 600; /* Reducido de 700 */
  color: #e0e0e0;
  margin-bottom: 15px; /* Reducido de 30px */
  text-align: center;
}

.loading-message,
.empty-library-message,
.error-message,
.status-message {
  font-size: 1.2rem;
  color: #aaa;
  margin: 20px auto; /* Center these messages */
  width: 100%;
  max-width: 600px; /* Max width for messages */
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
  color: #ff4d4f;
  background-color: rgba(255, 77, 79, 0.1);
}

.status-message.success {
  color: #28a745; 
  background-color: rgba(40, 167, 69, 0.1);
}

.status-message.error {
  color: #dc3545; 
  background-color: rgba(220, 53, 69, 0.1);
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
  border: 1px solid #555;
  border-radius: 20px;
  background-color: #3a3a3a;
  color: #e0e0e0;
  flex-grow: 1; /* Allow search input to take available space */
  min-width: 200px; /* Minimum width for search */
}

.search-input::placeholder {
  color: #888;
}

.sort-dropdown {
  padding: 10px 15px;
  font-size: 1rem;
  border: 1px solid #555;
  border-radius: 20px;
  background-color: #3a3a3a;
  color: #e0e0e0;
  cursor: pointer;
  min-width: 200px; /* Consistent minimum width */
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
  background: #23272f;
  border: 1.5px solid #444a57;
  border-radius: 999px;
  padding: 7px 18px 7px 10px;
  font-size: 1rem;
  color: #e0e0e0;
  box-shadow: 0 1px 4px 0 rgba(0,0,0,0.08);
  transition: border 0.2s, background 0.2s;
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
  color: #e0e0e0;
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
  background: linear-gradient(135deg, #28a745, #20c997);
  color: white;
  border: none;
  border-radius: 999px;
  padding: 8px 20px;
  font-size: 1rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
}

.import-button:hover {
  background: linear-gradient(135deg, #218838, #1ea080);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}
</style> 