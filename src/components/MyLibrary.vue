<template>
  <div class="library-container">
    <h1 class="title">My Saved Books</h1>
    
    <div class="controls-container">
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

    <div v-if="isLoading" class="loading-message">Loading library...</div>
    <div v-if="fetchError" class="error-message">{{ fetchError }}</div>
    <div v-if="statusMessage" :class="['status-message', overallStatus]">{{ statusMessage }}</div>

    <div v-if="!isLoading && !fetchError && displayedBooks.length === 0 && !statusMessage" class="empty-library-message">
      Your library is currently empty. Add some books from the ISBN Finder!
    </div>

    <div v-if="displayedBooks.length > 0" class="book-list">
      <LibraryBookItem 
        v-for="(book) in displayedBooks" 
        :key="book.isbn" 
        :book="book" 
        @delete-book="handleDeleteBook" 
        @update-rating="handleUpdateRating" 
        class="book-item" 
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import LibraryBookItem from './LibraryBookItem.vue';

const books = ref([]);
const isLoading = ref(true);
const fetchError = ref("");
const statusMessage = ref("");
const overallStatus = ref("");
const searchQuery = ref("");

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
  // statusMessage.value = ""; // Clear general status on full refresh, or let specific actions set it
  // overallStatus.value = "";
  try {
    const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
    const response = await axios.get(backendApiUrl, {
      params: { action: 'get_library' }
    });
    if (response.data && response.data.status === 'success') {
      books.value = response.data.data || [];
    } else {
      fetchError.value = response.data.message || "Failed to load library. Unknown error.";
      books.value = [];
    }
  } catch (error) {
    console.error("Error fetching library:", error);
    fetchError.value = "Error connecting to backend to fetch library.";
    books.value = [];
    if (error.response) console.error("Backend Error Response:", error.response.data);
  }
  isLoading.value = false;
};

const displayedBooks = computed(() => {
  let processedBooks = [...books.value];

  // Filter by search query
  if (searchQuery.value.trim() !== "") {
    const lowerSearchQuery = searchQuery.value.toLowerCase();
    processedBooks = processedBooks.filter(book => 
      (book.title && book.title.toLowerCase().includes(lowerSearchQuery)) ||
      (book.author && book.author.toLowerCase().includes(lowerSearchQuery))
    );
  }

  // Sort the filtered books
  switch (currentSort.value) {
    case 'title-asc':
      processedBooks.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
      break;
    case 'title-desc':
      processedBooks.sort((a, b) => (b.title || '').localeCompare(a.title || ''));
      break;
    case 'author-asc':
      processedBooks.sort((a, b) => (a.author || '').localeCompare(b.author || ''));
      break;
    case 'author-desc':
      processedBooks.sort((a, b) => (b.author || '').localeCompare(a.author || ''));
      break;
    case 'rating-desc':
      processedBooks.sort((a, b) => (b.rating === null ? -1 : (a.rating === null ? 1 : b.rating - a.rating)));
      break;
    case 'rating-asc':
      processedBooks.sort((a, b) => (a.rating === null ? 1 : (b.rating === null ? -1 : a.rating - b.rating)));
      break;
    case 'date-desc':
      processedBooks.sort((a, b) => (b.addedTimestamp || 0) - (a.addedTimestamp || 0));
      break;
    case 'date-asc':
      processedBooks.sort((a, b) => (a.addedTimestamp || 0) - (b.addedTimestamp || 0));
      break;
  }
  return processedBooks;
});

const handleDeleteBook = async (isbn) => {
  if (!confirm(`Are you sure you want to delete the book with ISBN: ${isbn}?`)) {
    return;
  }
  setStatus("", ""); // Clear previous messages
  try {
    const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
    const response = await axios.post(backendApiUrl, { 
      action: 'delete_book', 
      isbn: isbn 
    });
    if (response.data && response.data.status === 'success') {
      setStatus(response.data.message || "Book deleted successfully.", "success");
      books.value = books.value.filter(b => b.isbn !== isbn);
    } else {
      setStatus(response.data.message || "Failed to delete book.", "error");
    }
  } catch (error) {
    console.error("Error deleting book:", error);
    setStatus("Error connecting to backend to delete book.", "error");
    if (error.response) console.error("Backend Error Response:", error.response.data);
  }
};

const handleUpdateRating = async ({ isbn, rating }) => {
  setStatus("", ""); // Clear previous messages
  try {
    const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
    const response = await axios.post(backendApiUrl, {
      action: 'update_book_rating',
      isbn: isbn,
      rating: rating
    });
    if (response.data && response.data.status === 'success') {
      setStatus(response.data.message || "Rating updated successfully.", "success");
      const bookIndex = books.value.findIndex(b => b.isbn === isbn);
      if (bookIndex !== -1) {
        books.value[bookIndex].rating = rating;
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

onMounted(() => {
  fetchLibrary();
});
</script>

<style scoped>
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
  /* max-width: 220px; /* Ensure it doesn't grow too large if only a few items */
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
  justify-content: space-between; /* Align search and sort */
  align-items: center;
  width: 100%;
  margin-bottom: 25px;
  gap: 15px; /* Add some space between search and sort */
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
</style> 