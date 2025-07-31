<template>
  <div class="hello-container">
    <h1 class="title">Book Finder (Google Books + OpenLibrary)</h1>
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
  publishers: "",
  userStatuses: []
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
  currentBook.userStatuses = [];
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

  // Try Google Books API first
  try {
    console.log("Trying Google Books API...");
    const googleApiUrl = `https://www.googleapis.com/books/v1/volumes?q=isbn:${isbn}`;
    const response = await axios.get(googleApiUrl);
    const data = response.data;

    if (data.items && data.items.length > 0) {
      const bookId = data.items[0].id;
      console.log("Found book ID:", bookId, "- Getting full details...");
      
      // Get full book details using the book ID
      const detailsResponse = await axios.get(`https://www.googleapis.com/books/v1/volumes/${bookId}`);
      const book = detailsResponse.data.volumeInfo;
      
      console.log("Fetched complete book details from Google Books:", book);
      currentBook.title = book.title || "Title not found";
      currentBook.author = (book.authors && book.authors.length > 0) ? book.authors.join(', ') : "Author not found";
      currentBook.coverUrl = book.imageLinks?.large?.replace('http:', 'https:') ||
                            book.imageLinks?.medium?.replace('http:', 'https:') ||
                            book.imageLinks?.thumbnail?.replace('http:', 'https:') || 
                            book.imageLinks?.smallThumbnail?.replace('http:', 'https:') || "";
      currentBook.publishers = book.publisher ? [book.publisher] : [];
      
      console.log("Book found with Google Books API (full details):", currentBook.title);
      
      // Auto-save the book when found by ISBN
      await autoSaveBookFromISBN();
      
      return; // Success, no need to try fallback
    }
  } catch (error) {
    console.warn("Google Books API failed, trying fallback:", error.message);
  }

  // Fallback to OpenLibrary API
  try {
    console.log("Trying OpenLibrary API as fallback...");
    const openLibraryUrl = `https://openlibrary.org/isbn/${isbn}.json`;
    const response = await axios.get(openLibraryUrl);
    const data = response.data;

    if (!data.error) {
      const details = data;
      currentBook.title = details.title || "Title not found";
      console.log("Fetched book details from OpenLibrary:", details);  
      currentBook.author = (details.authors && details.authors.length > 0) ? details.authors[0].name : "Author not found";
      currentBook.coverUrl = (details.covers && details.covers.length > 0) ? `https://covers.openlibrary.org/b/id/${details.covers[0]}-L.jpg` : "";
      currentBook.publishers = (details.publishers && details.publishers.length > 0) ? details.publishers : [];
      
      // Auto-save the book when found by ISBN (fallback case)
      await autoSaveBookFromISBN();
      
      if (currentBook.title === "Title not found" && currentBook.author === "Author not found") {
        searchError.value = "Book details not found for this ISBN in any available database.";
      }
    } else {
      searchError.value = "Book not found for this ISBN in any available database.";
    }
  } catch (error) {
    console.error("Both APIs failed. Error with OpenLibrary:", error);
    if (error.response) {
      console.error("API Error Response:", error.response.data);
      if (error.response.status === 503) {
        searchError.value = "Book information services are temporarily unavailable (503). Please try again later.";
      } else if (error.response.status === 404) {
        searchError.value = "Book not found for this ISBN in any available database.";
      } else if (error.response.status === 429) {
        searchError.value = "Too many requests to book APIs. Please try again later.";
      } else {
        searchError.value = `Failed to fetch book information. Last API returned status ${error.response.status}.`;
      }
    } else if (error.request) {
      searchError.value = "No response from book APIs. Check your internet connection or try again later.";
    } else {
      searchError.value = "Error setting up request to book APIs: " + error.message;
    }
    // Clear book details on error as well, so no stale info is shown
    currentBook.title = "";
    currentBook.author = "";
    currentBook.coverUrl = "";
  }
};

const triggerFetchBookByName = async () => {
  foundBooks.value = [];
  clearBookDetails();
  if (!bookName.value.trim()) return;
  
  // Try Google Books API first for name search
  try {
    console.log("Searching by name with Google Books API...");
    const googleApiUrl = `https://www.googleapis.com/books/v1/volumes?q=intitle:${encodeURIComponent(bookName.value.trim())}&maxResults=5`;
    const response = await axios.get(googleApiUrl);
    const data = response.data;

    if (data.items && data.items.length > 0) {
      // Get full details for each book
      const booksPromises = data.items.map(async (item) => {
        try {
          const detailsResponse = await axios.get(`https://www.googleapis.com/books/v1/volumes/${item.id}`);
          const book = detailsResponse.data.volumeInfo;
          const isbn = book.industryIdentifiers?.find(id => id.type === 'ISBN_13')?.identifier ||
                      book.industryIdentifiers?.find(id => id.type === 'ISBN_10')?.identifier ||
                      '';
          
          return {
            isbn: isbn,
            title: book.title || 'Title not available',
            author: book.authors || ['Author not available'],
            cover_i: book.imageLinks?.large?.replace('http:', 'https:') ||
                    book.imageLinks?.medium?.replace('http:', 'https:') ||
                    book.imageLinks?.thumbnail?.replace('http:', 'https:') || '',
            publisher: book.publisher ? [book.publisher] : [],
            key: item.id
          };
        } catch (error) {
          console.warn(`Failed to get details for book ${item.id}:`, error.message);
          // Fallback to basic info if detailed call fails
          const book = item.volumeInfo;
          const isbn = book.industryIdentifiers?.find(id => id.type === 'ISBN_13')?.identifier ||
                      book.industryIdentifiers?.find(id => id.type === 'ISBN_10')?.identifier ||
                      '';
          
          return {
            isbn: isbn,
            title: book.title || 'Title not available',
            author: book.authors || ['Author not available'],
            cover_i: book.imageLinks?.thumbnail?.replace('http:', 'https:') || '',
            publisher: book.publisher ? [book.publisher] : [],
            key: item.id
          };
        }
      });
      
      const books = await Promise.all(booksPromises);
      foundBooks.value = books;
      console.log(`Found ${books.length} books with Google Books API (with full details)`);
      return; // Success with Google Books
    }
  } catch (error) {
    console.warn("Google Books name search failed, trying OpenLibrary fallback:", error.message);
  }

  // Fallback to OpenLibrary search
  try {
    console.log("Searching by name with OpenLibrary as fallback...");
    const apiUrl = `https://openlibrary.org/search.json?title=${encodeURIComponent(bookName.value.trim())}`;
    const response = await axios.get(apiUrl);
    let docs = response.data.docs || [];
    if (docs.length === 0) {
      searchError.value = "No books found with that name in any available database.";
      return;
    }
    let books = await axios.get(`https://openlibrary.org${docs[0].key}/editions.json`);
    books = books.data.entries.slice(0, 5); // Limit to first 5 results to avoid too much load
    books.forEach(async (edition) => {
      let authors = [];
      let isbnSearch = (Array.isArray(edition.isbn_13) && edition.isbn_13.length > 0)
            ? edition.isbn_13[0]
            : (Array.isArray(edition.isbn_10) && edition.isbn_10.length > 0)
              ? edition.isbn_10[0]
              : null;
      if (isbnSearch && isbnSearch.length > 0) {
          const apiUrl = `https://openlibrary.org/isbn/${isbnSearch}.json`;
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
          isbn: isbnSearch,
          title: data.title,
          author: authors,
          cover_i: (Array.isArray(edition.covers) && edition.covers.length > 0) ? edition.covers[0] : "",
          publisher: data.publishers,
          key: edition.key
        });
      }
    });
    if (foundBooks.value.length === 0) {
      searchError.value = "No books found with that name in any available database.";
    } else {
      searchError.value = "";
    }
  } catch (error) {
    console.error("Both name search APIs failed:", error);
    searchError.value = "Error searching books by name in all available databases.";
    foundBooks.value = [];
  }
};

const autoSaveBookFromISBN = async () => {
  // Only auto-save if we have valid book details
  if (!currentBook.title || currentBook.title === "Title not found") {
    console.log("Cannot auto-save: invalid book details");
    return;
  }

  // Check if book already exists in library
  try {
    const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
    const checkResponse = await axios.post(backendApiUrl, {
      action: 'get_library'
    });
    
    const existingBooks = Array.isArray(checkResponse.data.data) ? checkResponse.data.data : [];
    const bookExists = existingBooks.some(book => book.isbn === currentBook.isbn);
    
    if (bookExists) {
      console.log("Book already exists in library, skipping auto-save");
      addBookMessage.value = "Book already exists in your library.";
      addBookStatus.value = "info";
      
      // Clear message after 3 seconds
      setTimeout(() => {
        addBookMessage.value = "";
        addBookStatus.value = "";
      }, 3000);
      return;
    }

    // Auto-save with "owned" as default status using existing addBookToLibrary function
    const defaultStatuses = allowedUserStatusesList.value.includes('owned') ? ['owned'] : 
                           allowedUserStatusesList.value.length > 0 ? [allowedUserStatusesList.value[0]] : [];
    
    if (defaultStatuses.length === 0) {
      console.log("Cannot auto-save: no allowed statuses available");
      return;
    }

    console.log("Auto-saving book with default statuses:", defaultStatuses);
    
    // Reuse the existing addBookToLibrary function
    await addBookToLibrary({ 
      book: { ...currentBook }, 
      statuses: defaultStatuses 
    });
    
    // Update the message to indicate it was auto-saved
    if (addBookStatus.value === "success") {
      addBookMessage.value = `Book automatically saved to library with status: ${defaultStatuses.join(', ')}`;
      
      // Set userStatuses on currentBook to reflect that it's now saved
      currentBook.userStatuses = [...defaultStatuses];
      
      // Clear message after 3 seconds
      setTimeout(() => {
        addBookMessage.value = "";
        addBookStatus.value = "";
      }, 3000);
    }
  } catch (error) {
    console.error("Error during auto-save:", error);
    // Don't show error to user for auto-save failures, just log it
  }
};

const selectBookFromList = (book) => {
  clearBookDetails();
  currentBook.isbn = book.isbn;
  currentBook.title = book.title || "Title not found";
  currentBook.author = book.author ? book.author.join(', ') : "Author not found";
  
  // Handle cover URL for both Google Books and OpenLibrary
  if (book.cover_i) {
    if (book.cover_i.startsWith('https://')) {
      // Google Books URL
      currentBook.coverUrl = book.cover_i;
    } else {
      // OpenLibrary ID
      currentBook.coverUrl = `https://covers.openlibrary.org/b/id/${book.cover_i}-L.jpg`;
    }
  } else {
    currentBook.coverUrl = "";
  }
  
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

.add-book-message.info {
  background-color: rgba(0, 123, 255, 0.15);
  color: #007bff;
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