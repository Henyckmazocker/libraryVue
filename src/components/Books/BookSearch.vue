<template>
  <div class="hello-container">
    <h1 class="title">ISBN Book Finder</h1>
    <div class="input-group">
      <input type="text" class="isbn-input" placeholder="Enter ISBN manually" v-model="decodedText" @keyup.enter="triggerFetchBookInfo" required />
      <button @click="triggerFetchBookInfo" class="search-button">Buscar por ISBN</button>
    </div>
    <div class="input-group">
      <input type="text" class="isbn-input" placeholder="Buscar por nombre de libro" v-model="bookName" @keyup.enter="triggerFetchBookByName" />
      <button @click="triggerFetchBookByName" class="search-button">Buscar por nombre</button>
    </div>
    <div v-if="foundBooks.length > 0" class="search-results-container">
      <h3 class="results-title">Resultados por nombre:</h3>
      <div class="results-list">
        <div v-for="book in foundBooks" :key="book.key" class="result-card">
          <div class="result-info">
            <div class="result-title">{{ book.title }} ({{ book.isbn }})</div>
            <div class="result-author">{{ book.author.join(', ') }}</div>
            <div v-if="book.publisher && book.publisher.length > 0" class="result-publishers">
              <span class="result-pub-label">Editorial:</span>
              <span class="result-pub-list">{{ book.publisher.join(', ') }}</span>
            </div>
          </div>
          <button class="result-details-btn" @click="selectBookFromList(book)">Ver detalles</button>
        </div>
      </div>
    </div>

    <div v-if="currentBook.title">
      <LibraryBookItem
        :book="currentBook"
        :allowedUserStatuses="allowedUserStatusesList"
        :editable="true"
        @update-rating="onUpdateRating"
        @update-statuses="onUpdateStatuses"
        @save-book="addBookToLibrary"
      />
    </div>

    <div v-if="searchError" class="error-message">
      <p>{{ searchError }}</p>
    </div>
    <div v-if="addBookMessage" :class="['add-book-message', addBookStatus]">
      <p>{{ addBookMessage }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from "vue";
import axios from 'axios';
// import StatusSelector from '../StatusSelector.vue';
// Maneja la actualización de estados desde LibraryBookItem
const onUpdateStatuses = ({ statuses }) => {
  currentBook.userStatuses = [...statuses];
};
import LibraryBookItem from './LibraryBookItem.vue';
// Maneja la actualización de rating desde LibraryBookItem
const onUpdateRating = ({ rating }) => {
  currentBook.rating = rating;
};

const decodedText = ref("");
const currentBook = reactive({
  isbn: "",
  title: "",
  author: "",
  coverUrl: "",
  publishers: ""
});
const searchError = ref("");
const addBookMessage = ref("");
const addBookStatus = ref(""); // 'success' or 'error'
const allowedUserStatuses = ref([]);
const allowedUserStatusesList = computed(() => {
  return Array.isArray(allowedUserStatuses.value) ? allowedUserStatuses.value : [];
});
const bookName = ref("");
const foundBooks = ref([]);

const clearBookDetails = () => {
  currentBook.isbn = "";
  currentBook.title = "";
  currentBook.author = "";
  currentBook.coverUrl = "";
  currentBook.publishers = "";
  addBookMessage.value = "";
  addBookStatus.value = "";
};

// Renamed from onDecode, which was specific to the old structure
const triggerFetchBookInfo = () => {
  clearBookDetails();
  searchError.value = "";
  fetchBookInfo();
}

const fetchBookInfo = async () => {
  // searchError and book details are cleared by the calling functions (handleIsbnScanned or triggerFetchBookInfo)
  const isbn = decodedText.value.trim();
  currentBook.isbn = isbn;

  if (!isbn) {
    searchError.value = "Please enter or scan an ISBN.";
    return;
  }

  try {
    const apiUrl = `https://openlibrary.org/isbn/${isbn}.json`;
    const response = await axios.get(apiUrl);
    const data = response.data;

    if (!data.error) {
      const details = data;
      currentBook.title = details.title || "Title not found";
      currentBook.author = (details.authors && details.authors.length > 0) ? details.authors[0].name : "Author not found";
      currentBook.coverUrl = (details.covers && details.covers.length > 0) ? `https://covers.openlibrary.org/b/id/${details.covers[0]}-L.jpg` : "";
      currentBook.publishers = (details.publishers && details.publishers.length > 0) ? details.publishers : [];
      if (currentBook.title === "Title not found" && currentBook.author === "Author not found") {
        searchError.value = "Book details not found for this ISBN.";
      }
    } else {
      searchError.value = "Book not found for this ISBN.";
    }
  } catch (error) {
    console.error("Error fetching book information:", error);
    if (error.response) {
      console.error("API Error Response:", error.response.data);
      if (error.response.status === 503) {
        searchError.value = "The book information service (OpenLibrary) is temporarily unavailable (503). Please try again later.";
      } else if (error.response.status === 404) {
        searchError.value = "Book not found for this ISBN (404 error from API).";
      } else if (error.response.status === 429) {
        searchError.value = "Too many requests to book API. Please try again later.";
      } else {
        searchError.value = `Failed to fetch book information. API returned status ${error.response.status}.`;
      }
    } else if (error.request) {
      searchError.value = "No response from book API. Check your internet connection or try again later.";
    } else {
      searchError.value = "Error setting up request to book API: " + error.message;
    }
    // Clear book details on error as well, so no stale info is shown
    currentBook.title = "";
    currentBook.author = "";
    currentBook.coverUrl = "";
  }
};

const triggerFetchBookByName = async () => {
  foundBooks.value = [];
  // Limpiar currentBook para ocultar detalles si había uno cargado
  clearBookDetails();
  if (!bookName.value.trim()) return;
  try {
    const apiUrl = `https://openlibrary.org/search.json?title=${encodeURIComponent(bookName.value.trim())}`;
    const response = await axios.get(apiUrl);
    let docs = response.data.docs || [];
    if (docs.length === 0) {
      searchError.value = "No books found with that name.";
      return;
    }
    let books = await axios.get(`https://openlibrary.org${docs[0].key}/editions.json`);
    books = books.data.entries.slice(0, 5); // Limit to first 5 results to avoid too much load
    books.forEach(async (edition) => {
      let authors = [];
      if (edition.isbn_13 && edition.isbn_13.length > 0) {
          const apiUrl = `https://openlibrary.org/isbn/${edition.isbn_13[0]}.json`;
          const response = await axios.get(apiUrl);
          const data = response.data;
          if(data.authors && data.authors.length > 0) {
            data.authors.forEach(async (author) => {
              if (author.key) {
                const authorData = await axios.get(`https://openlibrary.org${author.key}.json`);
                authors.push(authorData.data.name);
              }
            });
          }
        foundBooks.value.push({
          isbn: edition.isbn_13[0],
          title: data.title,
          author: authors,
          cover_i: edition.covers[0] ?? "",
          publisher: data.publishers,
          key: edition.key
        });
      }
    });
    if (docs.length === 0) {
      searchError.value = "No se encontraron libros con ese nombre.";
    } else {
      searchError.value = "";
    }
  } catch (error) {
    searchError.value = "Error buscando libros por nombre.";
    foundBooks.value = [];
  }
};

const selectBookFromList = (book) => {
  clearBookDetails();
  currentBook.isbn = book.isbn;
  currentBook.title = book.title || "Title not found";
  currentBook.author = book.author ? book.author.join(', ') : "Author not found";
  currentBook.coverUrl = book.cover_i ? `https://covers.openlibrary.org/b/id/${book.cover_i}-L.jpg` : "";
  foundBooks.value = [];
};

const addBookToLibrary = async (bookDetailsWithStatuses) => {
  const { book, statuses } = bookDetailsWithStatuses;

  if (!book.title || book.title === "Title not found") {
    addBookMessage.value = "Cannot add book: valid details not found.";
    addBookStatus.value = "error";
    return;
  }
  if (!statuses || statuses.length === 0) {
    addBookMessage.value = "Cannot add book: at least one user status must be selected.";
    addBookStatus.value = "error";
    return;
  }

  try {
    const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
    console.log("Attempting to POST to backend at:", backendApiUrl);
    console.log("Book details being sent:", book);
    console.log("User statuses being sent:", statuses);
    const response = await axios.post(backendApiUrl, {
      action: 'add_book',
      book: { 
        ...book,
        userStatuses: statuses,
        allowedStatuses: allowedUserStatusesList.value // Include allowed statuses for validation
      }
    });
    if (response.data && response.data.status === 'success') {
      addBookMessage.value = response.data.message || "Book added successfully!";
      addBookStatus.value = "success";
    } else {
      addBookMessage.value = response.data.message || "Failed to add book. Unknown error.";
      addBookStatus.value = "error";
    }
  } catch (error) {
    console.error("Error adding book to library:", error);
    addBookMessage.value = "Error connecting to backend to add book.";
    addBookStatus.value = "error";
    if (error.response) console.error("Backend Error Response:", error.response.data);
  }
};

// Load allowed user statuses from backend on component mount
onMounted(async () => {
  const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
  const response = await axios.post(backendApiUrl, {
    action: 'get_book_allowed_statuses'
  });
  allowedUserStatuses.value = Array.isArray(response.data.data) ? response.data.data : [];
});
</script>

<style>
/* Styles for elements directly within BookSearch.vue */
.hello-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px;
  width: 100%;
  max-width: 500px;
  margin: auto;
}

.title {
  font-size: 2.5rem;
  font-weight: 700;
  color: #e0e0e0;
  margin-bottom: 40px;
}

.input-group {
  display: flex;
  width: 100%;
  margin-bottom: 30px;
}

.isbn-input {
  flex-grow: 1;
  padding: 15px 20px;
  font-size: 1rem;
  color: #e0e0e0;
  background-color: #2c2c2c;
  border: 1px solid #444;
  border-radius: 30px 0 0 30px;
  outline: none;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.isbn-input::placeholder {
  color: #888;
}

.isbn-input:focus {
  border-color: #007bff;
  box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
}

.search-button {
  padding: 15px 30px;
  font-size: 1rem;
  font-weight: 500;
  color: #ffffff;
  background-color: #007bff;
  border: 1px solid #007bff;
  border-radius: 0 30px 30px 0;
  cursor: pointer;
  outline: none;
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

.search-button:hover {
  background-color: #0056b3;
  border-color: #0056b3;
}

.error-message {
  margin-top: 20px;
  color: #ff4d4f;
  font-size: 0.9rem;
  background-color: rgba(255, 77, 79, 0.1);
  padding: 10px 15px;
  border-radius: 15px;
  width: 100%;
}

.add-book-message {
  margin-top: 15px;
  padding: 10px 15px;
  border-radius: 15px;
  width: 100%;
  font-size: 0.9rem;
}

.add-book-message.success {
  background-color: rgba(40, 167, 69, 0.15);
  color: #28a745;
}

.add-book-message.error {
  background-color: rgba(255, 77, 79, 0.1);
  color: #ff4d4f;
}
/* Resultados de búsqueda por nombre (estilo tipo MovieSearch) */
.search-results-container {
  width: 100%;
  margin-top: 30px;
}

.results-title {
  color: #e0e0e0;
  font-size: 1.2rem;
  margin-bottom: 18px;
  font-weight: 600;
}

.results-list {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.result-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #23272f;
  border: 1.5px solid #444a57;
  border-radius: 18px;
  padding: 18px 22px;
  box-shadow: 0 2px 8px 0 rgba(0,0,0,0.10);
  transition: border 0.2s, box-shadow 0.2s;
}

.result-card:hover {
  border-color: #007bff;
  box-shadow: 0 4px 16px 0 rgba(0,123,255,0.10);
}

.result-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.result-title {
  color: #e0e0e0;
  font-size: 1.1rem;
  font-weight: 600;
}

.result-author {
  color: #b0b0b0;
  font-size: 0.98rem;
}

.result-publishers {
  color: #b0b0b0;
  font-size: 0.95rem;
  margin-top: 2px;
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
}

.result-pub-label {
  color: #888;
  font-weight: 500;
}

.result-pub-list {
  color: #b0b0b0;
}

.result-details-btn {
  padding: 8px 18px;
  font-size: 1rem;
  font-weight: 500;
  color: #fff;
  background-color: #007bff;
  border: none;
  border-radius: 999px;
  cursor: pointer;
  transition: background 0.2s;
}

.result-details-btn:hover {
  background-color: #0056b3;
}
</style>