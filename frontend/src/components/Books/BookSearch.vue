<template>
  <div class="hello-container">
    <h1 class="title">Book Finder (Google Books + OpenLibrary)</h1>
    <div class="input-group">
      <input type="text" class="isbn-input" placeholder="Enter ISBN manually" v-model="decodedText" @keyup.enter="triggerFetchBookInfo" required />
      <button @click="triggerFetchBookInfo" class="search-button">
        <i class="fas fa-search"></i>
        <span class="button-text">ISBN</span>
      </button>
    </div>
    <div class="input-group">
      <input type="text" class="isbn-input" placeholder="Buscar por nombre de libro" v-model="nameSearch.query.value" @keyup.enter="triggerFetchBookByName" />
      <button @click="triggerFetchBookByName" class="search-button">
        <i class="fas fa-search"></i>
        <span class="button-text">Nombre</span>
      </button>
    </div>
    <div v-if="nameSearch.results.value.length > 0" class="search-results-container">
      <h3 class="results-title">Resultados por nombre:</h3>
      <div class="results-list">
        <div v-for="book in nameSearch.results.value" :key="book.key" class="result-card">
          <div class="result-info">
            <div class="result-title">{{ book.title }} ({{ book.isbn }})</div>
            <div class="result-author">{{ book.author.join(', ') }}</div>
            <div v-if="book.publisher && book.publisher.length > 0" class="result-publishers">
              <span class="result-pub-label">Editorial:</span>
              <span class="result-pub-list">{{ book.publisher.join(', ') }}</span>
            </div>
          </div>
          <button class="result-details-btn" @click="selectBookFromList(book)">
            <i class="fas fa-eye"></i>
          </button>
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

    <div v-if="nameSearch.error.value || isbnSearchError" class="error-message">
      <p>{{ nameSearch.error.value || isbnSearchError }}</p>
    </div>
    <div v-if="addBookMessage" :class="['add-book-message', addBookStatus]">
      <p>{{ addBookMessage }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from "vue";
import axios from 'axios';
import { useBooks } from '@/composables/useBooks';
import { useSearch } from '@/composables/useSearch';
import LibraryBookItem from './LibraryBookItem.vue';
import Logger from '@/utils/logger';

// Composables
const booksComposable = useBooks();

// Configurar búsqueda para búsqueda por nombre (solo para estado, no para auto-búsqueda)
const nameSearch = useSearch({
  debounceDelay: 500,
  minQueryLength: 3
});

// No configurar función de búsqueda automática ya que usamos función manual triggerFetchBookByName

// Estados locales del componente
const decodedText = ref("");
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
const addBookMessage = ref("");
const addBookStatus = ref(""); // 'success' or 'error'

// Estados computados
const allowedUserStatusesList = computed(() => 
  Array.isArray(booksComposable.allowedStatuses.value) ? booksComposable.allowedStatuses.value : []
);

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
  addBookMessage.value = "";
  addBookStatus.value = "";
};

// Renamed from onDecode, which was specific to the old structure
const triggerFetchBookInfo = () => {
  clearBookDetails();
  isbnSearchError.value = "";
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
  // Clear current book details when starting a new search
  clearBookDetails();
  
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
    addBookMessage.value = "Book already exists in your library.";
    addBookStatus.value = "info";
    
    // Clear message after 3 seconds
    setTimeout(() => {
      addBookMessage.value = "";
      addBookStatus.value = "";
    }, 3000);
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
    Logger.error("Error during auto-save:", error);
    // Don't show error to user for auto-save failures, just log it
  }
};

const selectBookFromList = (book) => {
  clearBookDetails();
  currentBook.isbn = book.isbn;
  currentBook.title = book.title || "Title not found";
  currentBook.author = book.author ? book.author.join(', ') : "Author not found";
  
  // Handle publisher - normalize from array or string
  if (book.publisher) {
    if (Array.isArray(book.publisher)) {
      currentBook.publisher = book.publisher.join(', ');
    } else {
      currentBook.publisher = book.publisher;
    }
  } else {
    currentBook.publisher = "";
  }
  
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
  
  // Clear search results after selection
  nameSearch.clearResults();
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
    addBookMessage.value = `Invalid status(es): ${invalidStatuses.join(', ')}. Allowed: ${allowedUserStatusesList.value.join(', ')}`;
    addBookStatus.value = "error";
    return;
  }

  // Check if book already exists in library
  const existingBook = booksComposable.findBookByISBN(book.isbn);
  if (existingBook) {
    Logger.debug("Book already exists in library, cannot add duplicate");
    addBookMessage.value = "Book already exists in your library.";
    addBookStatus.value = "info";
    
    // Clear message after 3 seconds
    setTimeout(() => {
      addBookMessage.value = "";
      addBookStatus.value = "";
    }, 3000);
    return;
  }

  // Use the books composable to add the book
  Logger.debug("Book details being sent:", book);
  Logger.debug("User statuses being sent:", statuses);
  
  const result = await booksComposable.addBook(book, statuses);
  
  if (result.success) {
    addBookMessage.value = "Book added successfully!";
    addBookStatus.value = "success";
    
    // Set userStatuses on currentBook to reflect that it's now saved
    currentBook.userStatuses = [...statuses];
    
    // Clear message after 3 seconds
    setTimeout(() => {
      addBookMessage.value = "";
      addBookStatus.value = "";
    }, 3000);
  } else {
    addBookMessage.value = result.message || "Failed to add book. Unknown error.";
    addBookStatus.value = "error";
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
  display: flex;
  align-items: center;
  justify-content: center;
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

.search-button i {
  margin-right: 8px;
  font-size: 1.1rem;
}

.button-text {
  font-size: 0.9rem;
  font-weight: 500;
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