<template>
  <div class="library-container">
    <h1 class="title">My Saved Books</h1>
    
    <div class="controls-container">
      <div class="filter-checkboxes filter-checkboxes-row">
        <label class="filter-checkbox-pill"><input type="checkbox" v-model="showBooks" /> Libros</label>
        <label class="filter-checkbox-pill"><input type="checkbox" v-model="showMovies" /> Películas</label>
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

    <div v-if="isLoading" class="loading-message">Loading library...</div>
    <div v-if="fetchError" class="error-message">{{ fetchError }}</div>
    <div :class="['status-message', overallStatus]" aria-live="polite" style="min-height: 2.5em;">
      <span v-if="statusMessage">{{ statusMessage }}</span>
    </div>

    <div v-if="!isLoading && !fetchError && displayedItems.length === 0 && !statusMessage" class="empty-library-message">
      Your library is currently empty. Add some books from the ISBN Finder!
    </div>

    <div v-if="displayedItems.length > 0" class="book-list">
      <template v-for="item in displayedItems" :key="item.itemType + '-' + (item.isbn || item.imdbID)">
        <LibraryBookItem
          v-if="item.itemType === 'book'"
          :book="item"
          :allowedUserStatuses="allowedUserStatusesList('book')"
          @delete-book="handleDeleteBook"
          @update-rating="handleUpdateRating"
          @update-statuses="handleUpdateStatuses"
          class="book-item"
        />
        <LibraryMovieItem
          v-else-if="item.itemType === 'movie'"
          :movie="item"
          :allowedUserStatuses="allowedUserStatusesList('movie')"
          @delete-movie="handleDeleteBook"
          @update-rating="handleUpdateRating"
          @update-statuses="handleUpdateStatuses"
          class="book-item"
        />
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import LibraryBookItem from './Books/LibraryBookItem.vue';
import LibraryMovieItem from './Movies/LibraryMovieItem.vue';

const items = ref([]);
const showBooks = ref(true);
const showMovies = ref(true);
const isLoading = ref(true);
const fetchError = ref("");
const statusMessage = ref("");
const overallStatus = ref("");
const searchQuery = ref("");
const allowedBookUserStatuses = ref([]);
const allowedMovieUserStatuses = ref([]);
const allowedUserStatusesList = (itemType) => {
  if (itemType === 'movie') return allowedMovieUserStatuses.value;
  return allowedBookUserStatuses.value;
};
const currentSort = ref('date-desc');

const setStatus = (message, type) => {
  statusMessage.value = message;
  overallStatus.value = type;
  // Optional: clear message after some time
  // setTimeout(() => { statusMessage.value = ""; overallStatus.value = ""; }, 5000);
};

const fetchLibrary = async () => {
  isLoading.value = true;
  fetchError.value = "";
  try {
    const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
    const response = await axios.get(backendApiUrl + '?action=get_library_items');
    if (response.data && response.data.status === 'success') {
      items.value = response.data.data || [];
    } else {
      fetchError.value = response.data.message || "Failed to load library. Unknown error.";
      items.value = [];
    }
  } catch (error) {
    console.error("Error fetching library:", error);
    fetchError.value = "Error connecting to backend to fetch library.";
    items.value = [];
    if (error.response) console.error("Backend Error Response:", error.response.data);
  }
  isLoading.value = false;
};

const displayedItems = computed(() => {
  let processed = [...items.value];
  // Filtrar por tipo según los checkboxes
  processed = processed.filter(item => {
    if (item.itemType === 'book' && !showBooks.value) return false;
    if (item.itemType === 'movie' && !showMovies.value) return false;
    return true;
  });
  // Filtro por búsqueda
  if (searchQuery.value.trim() !== "") {
    const lowerSearchQuery = searchQuery.value.toLowerCase();
    processed = processed.filter(item =>
      (item.title && item.title.toLowerCase().includes(lowerSearchQuery)) ||
      (item.author && item.author.toLowerCase().includes(lowerSearchQuery))
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
      processed.sort((a, b) => (a.author || '').localeCompare(b.author || ''));
      break;
    case 'author-desc':
      processed.sort((a, b) => (b.author || '').localeCompare(a.author || ''));
      break;
    case 'rating-desc':
      processed.sort((a, b) => (b.rating === null ? -1 : (a.rating === null ? 1 : b.rating - a.rating)));
      break;
    case 'rating-asc':
      processed.sort((a, b) => (a.rating === null ? 1 : (b.rating === null ? -1 : a.rating - b.rating)));
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
  setStatus("", "");
  try {
    const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
    let action, idField, idValue;
    if (itemType === 'movie') {
      action = 'delete_movie';
      idField = imdbID ? 'imdbID' : 'isbn';
      idValue = imdbID || isbn;
    } else {
      action = 'delete_book';
      idField = 'isbn';
      idValue = isbn;
    }
    const response = await axios.post(backendApiUrl, {
      action,
      [idField]: idValue
    });
    if (response.data && response.data.status === 'success') {
      setStatus(response.data.message || `${itemType === 'movie' ? 'Movie' : 'Book'} deleted successfully.`, "success");
      items.value = items.value.filter(i => (itemType === 'movie' ? i.imdbID !== idValue : i.isbn !== idValue));
    } else {
      setStatus(response.data.message || `Failed to delete ${itemType === 'movie' ? 'movie' : 'book'}.`, "error");
    }
  } catch (error) {
    console.error("Error deleting item:", error);
    setStatus("Error connecting to backend to delete item.", "error");
    if (error.response) console.error("Backend Error Response:", error.response.data);
  }
};

const handleUpdateRating = async ({ isbn, rating, itemType }) => {
  setStatus("", "");
  try {
    const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
    let action;
    if (itemType === 'movie') {
      action = 'update_movie_rating';
    } else {
      action = 'update_book_rating';
    }
    const response = await axios.post(backendApiUrl, {
      action,
      'isbn': isbn,
      rating
    });
    if (response.data && response.data.status === 'success') {
      setStatus(response.data.message || "Rating updated successfully.", "success");
      let idx;
      idx = items.value.findIndex(i => i.isbn === isbn);
      if (idx !== -1) {
        items.value[idx].rating = rating;
      }
    } else {
      setStatus(response.data.message || "Failed to update rating.", "error");
    }
  } catch (error) {
    console.error("Error updating rating:", error);
    setStatus("Error connecting to backend to update rating.", "error");
    if (error.response) console.error("Backend Error Response:", error.response.data);
  }
};
onMounted(async() => {
  const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
  const [bookRes, movieRes] = await Promise.all([
    axios.post(backendApiUrl, { action: 'get_book_allowed_statuses' }),
    axios.post(backendApiUrl, { action: 'get_movie_allowed_statuses' })
  ]);
  allowedBookUserStatuses.value = Array.isArray(bookRes.data.data) ? bookRes.data.data : [];
  allowedMovieUserStatuses.value = Array.isArray(movieRes.data.data) ? movieRes.data.data : [];
  fetchLibrary();
});

// Manejar actualización de estados de usuario
const handleUpdateStatuses = async ({ isbn, statuses, itemType }) => {
  setStatus("", "");
  try {
    const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
    let action;
    if (itemType === 'movie') {
      action = 'update_movie_user_statuses';
    } else {
      action = 'update_book_user_statuses';
    }
    const response = await axios.post(backendApiUrl, {
      action,
      'isbn': isbn,
      statuses
    });
    if (response.data && response.data.status === 'success') {
      setStatus(response.data.message || "Estados actualizados correctamente.", "success");
      const idx = items.value.findIndex(i => (itemType === 'movie' ? i.imdbID === isbn : i.isbn === isbn));
      if (idx !== -1) {
        items.value[idx].userStatuses = [...statuses];
      }
    } else {
      setStatus(response.data.message || "No se pudieron actualizar los estados.", "error");
    }
  } catch (error) {
    console.error("Error actualizando estados:", error);
    setStatus("Error conectando con el backend para actualizar estados.", "error");
    if (error.response) console.error("Backend Error Response:", error.response.data);
  }
};

</script>

<style>
.library-container {
  display: flex;
  flex-direction: column;
  /* align-items: center; /* Removed to allow full width for book-list */
  padding: 20px; /* Adjusted padding */
  padding-top: 100px; 
  width: 100%;
  max-width: 1200px; /* Wider for grid view */
  margin: auto;
  box-sizing: border-box;
}

.title {
  font-size: 2.5rem;
  font-weight: 700;
  color: #e0e0e0;
  margin-bottom: 30px;
  text-align: center; /* Center title if container is not aligning items center */
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
  flex-wrap: wrap; /* Allow items to wrap to the next line */
  justify-content: flex-start; /* Start items from the left */
  gap: 20px; /* Space between items (rows and columns) */
  width: 100%;
  padding: 0; /* Remove padding if items have their own */
}

/* Class applied to LibraryBookItem component instances */
/* Styles here will affect the root element of LibraryBookItem.vue */
/* LibraryBookItem.vue already defines its own width: 100% and background/padding */
/* For a grid, we need to control its basis/max-width here. */
:deep(.book-item) { 
  /* Using :deep to target the root element of LibraryBookItem if it's scoped */
  /* Alternatively, ensure LibraryBookItem.vue's root has these directly or expect this class */
  flex-basis: calc(25% - 20px); /* Example: 4 items per row, subtracting gap. Adjust as needed. */
  /* flex-basis: 220px; /* Fixed width approach */
  max-width: 10px; /* Ensure it doesn't grow too large if only a few items */
  /* min-width: 180px; /* Minimum width before wrapping or shrinking too much */
  box-sizing: border-box; 
  /* The internal .library-book-item-container already has padding, background etc. */
  /* We let LibraryBookItem style itself, this class here mostly for layout within the flex grid */
}

/* Responsive adjustments for the grid */
@media (max-width: 1200px) {
  :deep(.book-item) {
    flex-basis: calc(33.333% - 20px); /* 3 items per row */
  }
}

@media (max-width: 768px) {
  .controls-container {
    justify-content: center; /* Center dropdown on smaller screens */
  }
  :deep(.book-item) {
    flex-basis: calc(50% - 15px); /* 2 items per row, slightly smaller gap consideration */
  }
}

@media (max-width: 480px) {
  :deep(.book-item) {
    flex-basis: 100%; /* 1 item per row */
  }
  .book-list {
    gap: 15px; /* Adjust gap for single column */
  }
}

.controls-container {
  display: flex;
  flex-direction: column;
  width: 100%;
  margin-bottom: 25px;
  gap: 10px;
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
</style> 