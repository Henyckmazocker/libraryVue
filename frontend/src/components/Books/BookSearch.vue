<template>
  <div class="book-search-container">
    <h1 class="title">Book Finder (Google Books + OpenLibrary)</h1>
    <div class="input-group">
      <input type="text" class="book-input" placeholder="Enter ISBN manually" v-model="decodedText" @keyup.enter="triggerFetchBookInfo" required />
      <button @click="triggerFetchBookInfo" class="search-button">
        <i class="fas fa-search"></i>
        <span class="button-text">ISBN</span>
      </button>
    </div>
    <div class="input-group">
      <input type="text" class="book-input" placeholder="Buscar por nombre de libro" v-model="nameSearch.query.value" @keyup.enter="triggerFetchBookByName" />
      <button @click="triggerFetchBookByName" class="search-button">
        <i class="fas fa-search"></i>
        <span class="button-text">Nombre</span>
      </button>
    </div>
    
    <div v-if="nameSearch.error.value || isbnSearchError" class="error-message">{{ nameSearch.error.value || isbnSearchError }}</div>
    <div :class="['status-message', notifications.statusType.value]" aria-live="polite" style="min-height: 2.5em;">
      <span v-if="notifications.statusMessage.value">{{ notifications.statusMessage.value }}</span>
    </div>

    <!-- Lista de resultados con acordeón (igual que MovieSearch) -->
    <div v-if="nameSearch.results.value && nameSearch.results.value.length" class="search-results-list">
      <div v-for="result in nameSearch.results.value" :key="result.key" class="search-list-item-wrapper">
        <div class="search-list-item" :class="{ expanded: selectedBook && selectedBook.key === result.key }" @click="toggleBook(result.key)">
          <img v-if="result.cover_i" :src="getBookCoverUrl(result.cover_i)" alt="Portada" class="search-list-poster" />
          <div class="search-list-info">
            <span class="search-list-title">{{ result.title }} {{ result.isbn ? `(${result.isbn})` : '' }}</span>
            <span v-if="selectedBook && selectedBook.key === result.key" class="accordion-arrow">
              <i class="fas fa-chevron-up"></i>
            </span>
            <span v-else class="accordion-arrow">
              <i class="fas fa-chevron-down"></i>
            </span>
          </div>
        </div>
        <transition name="accordion">
          <div v-if="selectedBook && selectedBook.key === result.key" class="search-detail-below">
            <LibraryBookItem 
              v-if="allowedUserStatusesList.length > 0"
              :book="transformBookData(selectedBook)" 
              :allowedUserStatuses="allowedUserStatusesList" 
              :editable="true"
              @update-rating="onUpdateRating"
              @update-statuses="onUpdateStatuses"
              @save-book="addBookToLibrary"
            />
            <div v-else class="loading-statuses">
              Cargando estados disponibles...
            </div>
          </div>
        </transition>
      </div>
    </div>

    <!-- Resultado de búsqueda por ISBN -->
    <div v-if="currentBook.title && !isFromAccordion">
      <LibraryBookItem
        :book="currentBook"
        :allowedUserStatuses="allowedUserStatusesList"
        :editable="true"
        @update-rating="onUpdateRating"
        @update-statuses="onUpdateStatuses"
        @save-book="addBookToLibrary"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from "vue";
import axios from 'axios';
import { useBooks } from '@/composables/useBooks';
import { useSearch } from '@/composables/useSearch';
import { useLibraryNotifications } from '@/composables/useLibraryNotifications';
import LibraryBookItem from './LibraryBookItem.vue';
import Logger from '@/utils/logger';

// Composables
const booksComposable = useBooks();
const notifications = useLibraryNotifications();

// Configurar búsqueda para búsqueda por nombre (solo para estado, no para auto-búsqueda)
const nameSearch = useSearch({
  debounceDelay: 500,
  minQueryLength: 3
});

// Estados locales del componente
const decodedText = ref("");
const selectedBook = ref(null);
const isFromAccordion = ref(false);
const currentBook = reactive({
  isbn: "",
  title: "",
  author: "",
  publisher: "",
  publicationDate: "",
  coverUrl: "",
  pages: null,
  description: "",
  publishers: "",
  rating: null,
  user_rating: null,
  userStatuses: []
});
const isbnSearchError = ref("");

// Estados computados
const allowedUserStatusesList = computed(() => 
  Array.isArray(booksComposable.allowedStatuses.value) ? booksComposable.allowedStatuses.value : []
);

// Funciones del acordeón
const toggleBook = async (bookKey) => {
  const book = nameSearch.results.value.find(r => r.key === bookKey);
  if (!book) return;
  
  if (selectedBook.value && selectedBook.value.key === bookKey) {
    // Si ya está seleccionado, contraer
    selectedBook.value = null;
    isFromAccordion.value = false;
  } else {
    // Seleccionar nuevo libro
    selectedBook.value = book;
    isFromAccordion.value = true;
    clearBookDetails(); // Limpiar el libro actual para evitar conflictos
  }
};

// Función para obtener URL de portada
const getBookCoverUrl = (cover_i) => {
  if (!cover_i) return '';
  
  if (cover_i.startsWith('https://')) {
    // Google Books URL
    return cover_i;
  } else {
    // OpenLibrary ID
    return `https://covers.openlibrary.org/b/id/${cover_i}-L.jpg`;
  }
};

// Función para transformar datos del acordeón al formato esperado por LibraryBookItem
const transformBookData = (book) => {
  if (!book) return null;
  
  return {
    isbn: book.isbn || '',
    title: book.title || 'Title not available',
    author: Array.isArray(book.author) ? book.author.join(', ') : (book.author || 'Author not available'),
    publisher: Array.isArray(book.publisher) ? book.publisher.join(', ') : (book.publisher || ''),
    publicationDate: book.publicationDate || '',
    coverUrl: getBookCoverUrl(book.cover_i),
    pages: book.pages || null,
    description: book.description || '',
    publishers: Array.isArray(book.publisher) ? book.publisher : (book.publisher ? [book.publisher] : []),
    rating: null,
    user_rating: null,
    userStatuses: []
  };
};

// Maneja la actualización de estados desde LibraryBookItem
const onUpdateStatuses = ({ statuses }) => {
  currentBook.userStatuses = [...statuses];
};

// Maneja la actualización de rating desde LibraryBookItem
const onUpdateRating = ({ rating }) => {
  currentBook.user_rating = rating;
};

const clearBookDetails = () => {
  currentBook.isbn = "";
  currentBook.title = "";
  currentBook.author = "";
  currentBook.publisher = "";
  currentBook.publicationDate = "";
  currentBook.coverUrl = "";
  currentBook.pages = null;
  currentBook.description = "";
  currentBook.publishers = "";
  currentBook.rating = null;
  currentBook.user_rating = null;
  currentBook.userStatuses = [];
};

// Renamed from onDecode, which was specific to the old structure
const triggerFetchBookInfo = () => {
  clearBookDetails();
  isbnSearchError.value = "";
  selectedBook.value = null; // Limpiar selección del acordeón
  isFromAccordion.value = false;
  fetchBookInfo();
}

const fetchBookInfo = async () => {
  // searchError and book details are cleared by the calling functions (handleIsbnScanned or triggerFetchBookInfo)
  const isbn = decodedText.value.trim();
  currentBook.isbn = isbn;

  if (!isbn) {
    isbnSearchError.value = "Please enter or scan an ISBN.";
    return;
  }

  // Try Google Books API first
  try {
    Logger.debug("Trying Google Books API...");
    const googleApiUrl = `https://www.googleapis.com/books/v1/volumes?q=isbn:${isbn}`;
    const response = await axios.get(googleApiUrl);
    const data = response.data;

    if (data.items && data.items.length > 0) {
      const bookId = data.items[0].id;
      Logger.debug("Found book ID:", bookId, "- Getting full details...");
      
      // Get full book details using the book ID
      const detailsResponse = await axios.get(`https://www.googleapis.com/books/v1/volumes/${bookId}`);
      const book = detailsResponse.data.volumeInfo;
      
      Logger.debug("Fetched complete book details from Google Books:", book);
      currentBook.title = book.title || "Title not found";
      currentBook.author = (book.authors && book.authors.length > 0) ? book.authors.join(', ') : "Author not found";
      currentBook.publisher = book.publisher || "";
      currentBook.publicationDate = book.publishedDate || "";
      currentBook.coverUrl = book.imageLinks?.large?.replace('http:', 'https:') ||
                            book.imageLinks?.medium?.replace('http:', 'https:') ||
                            book.imageLinks?.thumbnail?.replace('http:', 'https:') || 
                            book.imageLinks?.smallThumbnail?.replace('http:', 'https:') || "";
      currentBook.pages = book.pageCount || null;
      currentBook.description = book.description || "";
      currentBook.publishers = book.publisher ? [book.publisher] : [];
      
      Logger.debug("Book found with Google Books API (full details):", currentBook.title);
      
      // Auto-save the book when found by ISBN
      await autoSaveBookFromISBN();
      
      return; // Success, no need to try fallback
    }
  } catch (error) {
    Logger.warn("Google Books API failed, trying fallback:", error.message);
  }

  // Fallback to OpenLibrary API
  try {
    Logger.debug("Trying OpenLibrary API as fallback...");
    const openLibraryUrl = `https://openlibrary.org/isbn/${isbn}.json`;
    const response = await axios.get(openLibraryUrl);
    const data = response.data;

    if (!data.error) {
      const details = data;
      currentBook.title = details.title || "Title not found";
      Logger.debug("Fetched book details from OpenLibrary:", details);  
      currentBook.author = (details.authors && details.authors.length > 0) ? details.authors[0].name : "Author not found";
      currentBook.publisher = (details.publishers && details.publishers.length > 0) ? details.publishers[0] : "";
      currentBook.publicationDate = details.publish_date || "";
      currentBook.coverUrl = (details.covers && details.covers.length > 0) ? `https://covers.openlibrary.org/b/id/${details.covers[0]}-L.jpg` : "";
      currentBook.pages = details.number_of_pages || null;
      currentBook.description = details.description || "";
      currentBook.publishers = (details.publishers && details.publishers.length > 0) ? details.publishers : [];
      
      // Auto-save the book when found by ISBN (fallback case)
      await autoSaveBookFromISBN();
      
      if (currentBook.title === "Title not found" && currentBook.author === "Author not found") {
        isbnSearchError.value = "Book details not found for this ISBN in any available database.";
      }
    } else {
      isbnSearchError.value = "Book not found for this ISBN in any available database.";
    }
  } catch (error) {
    Logger.error("Both APIs failed. Error with OpenLibrary:", error);
    if (error.response) {
      Logger.error("API Error Response:", error.response.data);
      if (error.response.status === 503) {
        isbnSearchError.value = "Book information services are temporarily unavailable (503). Please try again later.";
      } else if (error.response.status === 404) {
        isbnSearchError.value = "Book not found for this ISBN in any available database.";
      } else if (error.response.status === 429) {
        isbnSearchError.value = "Too many requests to book APIs. Please try again later.";
      } else {
        isbnSearchError.value = `Failed to fetch book information. Last API returned status ${error.response.status}.`;
      }
    } else if (error.request) {
      isbnSearchError.value = "No response from book APIs. Check your internet connection or try again later.";
    } else {
      isbnSearchError.value = "Error setting up request to book APIs: " + error.message;
    }
    // Clear book details on error as well, so no stale info is shown
    currentBook.title = "";
    currentBook.author = "";
    currentBook.coverUrl = "";
  }
};

const triggerFetchBookByName = async () => {
  // Clear current book details and accordion state when starting a new search
  clearBookDetails();
  selectedBook.value = null;
  isFromAccordion.value = false;
  
  if (!nameSearch.query.value.trim()) {
    nameSearch.error.value = "Introduce un título o palabra clave para buscar.";
    return;
  }
  
  // Clear previous results and errors
  nameSearch.results.value = [];
  nameSearch.error.value = "";
  
  try {
    // Try Google Books API first for name search
    Logger.debug("Searching by name with Google Books API...");
    const googleApiUrl = `https://www.googleapis.com/books/v1/volumes?q=intitle:${encodeURIComponent(nameSearch.query.value.trim())}&maxResults=5`;
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
            pages: book.pageCount || null,
            key: item.id
          };
        } catch (error) {
          Logger.warn(`Failed to get details for book ${item.id}:`, error.message);
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
            pages: book.pageCount || null,
            key: item.id
          };
        }
      });
      
      const books = await Promise.all(booksPromises);
      nameSearch.results.value = books;
      Logger.debug(`Found ${books.length} books with Google Books API (with full details)`);
      return; // Success with Google Books
    }
  } catch (error) {
    Logger.warn("Google Books name search failed, trying OpenLibrary fallback:", error.message);
  }

  // Fallback to OpenLibrary search
  try {
    Logger.debug("Searching by name with OpenLibrary as fallback...");
    const apiUrl = `https://openlibrary.org/search.json?title=${encodeURIComponent(nameSearch.query.value.trim())}`;
    const response = await axios.get(apiUrl);
    let docs = response.data.docs || [];
    if (docs.length === 0) {
      nameSearch.error.value = "No books found with that name in any available database.";
      return;
    }
    
    // Limit to first 5 results to avoid too much load
    docs = docs.slice(0, 5);
    const books = [];
    
    for (const doc of docs) {
      try {
        // Get detailed info for each book
        let authors = [];
        let isbnSearch = (Array.isArray(doc.isbn) && doc.isbn.length > 0)
              ? doc.isbn[0]
              : '';
        
        if (isbnSearch && isbnSearch.length > 0) {
          try {
            const apiUrl = `https://openlibrary.org/isbn/${isbnSearch}.json`;
            const response = await axios.get(apiUrl);
            const data = response.data;
            
            if(data.authors && data.authors.length > 0) {
              for (const author of data.authors) {
                if (author.key) {
                  try {
                    const authorData = await axios.get(`https://openlibrary.org${author.key}.json`);
                    authors.push(authorData.data.name);
                  } catch (e) {
                    Logger.warn(`Failed to get author data for ${author.key}`);
                  }
                }
              }
            }
            
            books.push({
              isbn: isbnSearch,
              title: data.title || doc.title,
              author: authors.length > 0 ? authors : [doc.author_name?.[0] || 'Author not available'],
              cover_i: (Array.isArray(doc.cover_i) && doc.cover_i.length > 0) ? doc.cover_i[0] : "",
              publisher: data.publishers || (doc.publisher ? [doc.publisher[0]] : []),
              pages: data.number_of_pages || null,
              key: doc.key || `ol-${Date.now()}-${Math.random()}`
            });
          } catch (error) {
            Logger.warn(`Failed to get ISBN details for ${isbnSearch}:`, error.message);
            // Add basic info without detailed lookup
            books.push({
              isbn: isbnSearch,
              title: doc.title,
              author: doc.author_name || ['Author not available'],
              cover_i: (Array.isArray(doc.cover_i) && doc.cover_i.length > 0) ? doc.cover_i[0] : "",
              publisher: doc.publisher ? [doc.publisher[0]] : [],
              pages: null, // No detailed info available
              key: doc.key || `ol-${Date.now()}-${Math.random()}`
            });
          }
        } else {
          // No ISBN found, use basic doc info
          books.push({
            isbn: '',
            title: doc.title,
            author: doc.author_name || ['Author not available'],
            cover_i: (Array.isArray(doc.cover_i) && doc.cover_i.length > 0) ? doc.cover_i[0] : "",
            publisher: doc.publisher ? [doc.publisher[0]] : [],
            pages: null, // No detailed info available
            key: doc.key || `ol-${Date.now()}-${Math.random()}`
          });
        }
      } catch (error) {
        Logger.warn(`Failed to process OpenLibrary doc:`, error.message);
      }
    }
    
    nameSearch.results.value = books;
    if (books.length === 0) {
      nameSearch.error.value = "No books found with that name in any available database.";
    } else {
      Logger.debug(`Found ${books.length} books with OpenLibrary fallback`);
    }
  } catch (error) {
    Logger.error("Both name search APIs failed:", error);
    nameSearch.error.value = "Error searching books by name in all available databases.";
    nameSearch.results.value = [];
  }
};

const autoSaveBookFromISBN = async () => {
  // Only auto-save if we have valid book details
  if (!currentBook.title || currentBook.title === "Title not found") {
    Logger.debug("Cannot auto-save: invalid book details");
    return;
  }

  // Check if book already exists in library using composable
  const existingBook = booksComposable.findBookByISBN(currentBook.isbn);
  
  if (existingBook) {
    Logger.debug("Book already exists in library, skipping auto-save");
    notifications.showMessage("Book already exists in your library.", "info");
    return;
  }

  // Ensure allowed statuses are loaded before attempting auto-save
  Logger.debug("Current allowed statuses:", allowedUserStatusesList.value);
  if (allowedUserStatusesList.value.length === 0) {
    Logger.debug("No allowed statuses available, fetching them first...");
    await booksComposable.fetchAllowedStatuses();
    Logger.debug("After fetching, allowed statuses:", allowedUserStatusesList.value);
  }

  // Auto-save with "owned" as default status using existing addBookToLibrary function
  const defaultStatuses = allowedUserStatusesList.value.includes('owned') ? ['owned'] : 
                           allowedUserStatusesList.value.length > 0 ? [allowedUserStatusesList.value[0]] : [];
    
  if (defaultStatuses.length === 0) {
    Logger.debug("Cannot auto-save: no allowed statuses available even after fetching");
    return;
  }

  try {
    Logger.debug("Auto-saving book with default statuses:", defaultStatuses);
    
    // Reuse the existing addBookToLibrary function
    await addBookToLibrary({ 
      book: { ...currentBook }, 
      statuses: defaultStatuses 
    });
    
    // Update the message to indicate it was auto-saved
    if (notifications.statusType.value === "success") {
      notifications.showSuccess(`Book automatically saved to library with status: ${defaultStatuses.join(', ')}`);
      
      // Set userStatuses on currentBook to reflect that it's now saved
      currentBook.userStatuses = [...defaultStatuses];
    }
  } catch (error) {
    Logger.error("Error during auto-save:", error);
    // Don't show error to user for auto-save failures, just log it
  }
};

const addBookToLibrary = async (bookDetailsWithStatuses) => {
  const { book, statuses } = bookDetailsWithStatuses;

  if (!book.title || book.title === "Title not found") {
    notifications.showError("Cannot add book: valid details not found.");
    return;
  }
  if (!statuses || statuses.length === 0) {
    notifications.showError("Cannot add book: at least one user status must be selected.");
    return;
  }

  // Ensure allowed statuses are loaded
  if (allowedUserStatusesList.value.length === 0) {
    Logger.debug("Allowed statuses not loaded, fetching them first...");
    await booksComposable.fetchAllowedStatuses();
  }

  // Validate that the statuses being sent are allowed
  const invalidStatuses = statuses.filter(status => !allowedUserStatusesList.value.includes(status));
  if (invalidStatuses.length > 0) {
    Logger.error("Invalid statuses detected:", invalidStatuses);
    Logger.error("Allowed statuses:", allowedUserStatusesList.value);
    notifications.showError(`Invalid status(es): ${invalidStatuses.join(', ')}. Allowed: ${allowedUserStatusesList.value.join(', ')}`);
    return;
  }

  // Check if book already exists in library
  const existingBook = booksComposable.findBookByISBN(book.isbn);
  if (existingBook) {
    Logger.debug("Book already exists in library, cannot add duplicate");
    notifications.showMessage("Book already exists in your library.", "info");
    return;
  }

  // Use the books composable to add the book
  Logger.debug("Book details being sent:", book);
  Logger.debug("User statuses being sent:", statuses);
  
  const result = await booksComposable.addBook(book, statuses);
  
  if (result.success) {
    notifications.showSuccess("Book added successfully!");
    
    // Set userStatuses on currentBook to reflect that it's now saved
    if (currentBook.isbn === book.isbn) {
      currentBook.userStatuses = [...statuses];
    }
  } else {
    notifications.showError(result.message || "Failed to add book. Unknown error.");
  }
};

// Load allowed user statuses from backend on component mount
onMounted(async () => {
  try {
    Logger.debug("BookSearch component mounted, fetching allowed statuses...");
    await booksComposable.fetchAllowedStatuses();
    Logger.debug("Allowed statuses loaded in BookSearch:", allowedUserStatusesList.value);
  } catch (error) {
    Logger.error("Error loading allowed statuses on mount:", error);
  }
});
</script>

<style>
/* Estilos idénticos a MovieSearch */
.book-search-container {
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
  font-size: 2rem;
  color: #e0e0e0;
  margin-bottom: 30px;
}

.input-group {
  display: flex;
  width: 100%;
  margin-bottom: 30px;
}

.book-input {
  flex-grow: 1;
  padding: 12px 18px;
  font-size: 1rem;
  color: #e0e0e0;
  background-color: #2c2c2c;
  border: 1px solid #444;
  border-radius: 30px 0 0 30px;
  outline: none;
}

.book-input::placeholder {
  color: #888;
}

.search-button {
  padding: 12px 24px;
  font-size: 1rem;
  color: #fff;
  background-color: #007bff;
  border: 1px solid #007bff;
  border-radius: 0 30px 30px 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.search-button:hover {
  background-color: #0056b3;
  border-color: #0056b3;
}

.search-button i {
  margin-right: 8px;
  font-size: 1.1rem;
}

.button-text {
  font-size: 0.9rem;
  font-weight: 500;
}

.error-message,
.status-message {
  padding: 10px 15px;
  border-radius: 12px;
  margin-bottom: 20px;
  width: 100%;
  text-align: center;
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

.status-message.info {
  color: #007bff;
  background-color: rgba(0, 123, 255, 0.1);
}

/* Lista de resultados de búsqueda idéntica a MovieSearch */
.search-results-list {
  width: 100%;
  max-width: 600px;
  margin-top: 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.search-list-item-wrapper {
  display: flex;
  flex-direction: column;
}

.search-list-item {
  display: flex;
  align-items: center;
  background: #232323;
  border-radius: 10px;
  padding: 10px;
  cursor: pointer;
  transition: background 0.2s, box-shadow 0.2s;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
  border: 1px solid transparent;
}

.search-list-poster {
  width: 50px;
  height: 75px;
  object-fit: cover;
  border-radius: 4px;
  margin-right: 16px;
  border: 1px solid #444;
}

.search-detail-below {
  margin-left: 0;
  margin-top: 0;
  padding-left: 0;
  box-sizing: border-box;
  width: 100%;
  max-width: 600px;
}

/* Estilos específicos para LibraryBookItem en contexto de búsqueda */
.search-detail-below .library-book-item-container {
  margin-top: 0;
  border-top-left-radius: 0;
  border-top-right-radius: 0;
  border-top: none;
  background: #232323;
  width: 100%;
  max-width: 600px;
  margin-left: 0;
  box-sizing: border-box;
}

/* Ajustar el layout para que se vea bien en el acordeón */
.search-detail-below .book-details {
  gap: 16px;
}

.search-detail-below .cover-image {
  width: 120px;
  height: auto;
}

.search-list-item-wrapper:not(:last-child) {
  margin-bottom: 10px;
}

.search-list-item.expanded {
  background: #282c34;
  border: 1px solid #007bff;
  box-shadow: 0 2px 8px rgba(0,123,255,0.08);
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
}

.search-list-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.search-list-title {
  color: #e0e0e0;
  font-size: 1rem;
  font-weight: 500;
}

.accordion-arrow {
  font-size: 1.2rem;
  color: #88aaff;
  margin-left: 10px;
  user-select: none;
}

/* Animaciones idénticas a MovieSearch */
.accordion-enter-active, .accordion-leave-active {
  transition: max-height 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.3s;
}

.accordion-enter-from, .accordion-leave-to {
  max-height: 0;
  opacity: 0;
}

.accordion-enter-to, .accordion-leave-from {
  max-height: 600px;
  opacity: 1;
}

.loading-statuses {
  padding: 20px;
  text-align: center;
  color: #888;
  background: #232323;
}

/* Responsive design */
@media (max-width: 768px) {
  .book-search-container {
    padding: 20px;
    max-width: 100%;
  }
  
  .title {
    font-size: 1.8rem;
    margin-bottom: 20px;
  }
  
  .search-list-item {
    padding: 8px;
  }
  
  .search-list-poster {
    width: 40px;
    height: 60px;
    margin-right: 12px;
  }
  
  .search-list-title {
    font-size: 0.9rem;
  }
}
</style>