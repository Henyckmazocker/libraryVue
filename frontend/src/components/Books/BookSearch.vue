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

    <!-- Lista de resultados simplificada sin acordeón -->
    <div v-if="nameSearch.results.value && nameSearch.results.value.length" class="search-results-list">
      <BookListItem
        v-for="result in nameSearch.results.value"
        :key="result.key"
        :book="transformSearchResultToBook(result)"
        :allowedStatuses="allowedUserStatusesList"
        @click="goToBookDetail"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRouter } from 'vue-router';
import axios from 'axios';
import BookListItem from '@/components/Books/BookListItem.vue';
import { useBooks } from '@/composables/useBooks';
import { useSearch } from '@/composables/useSearch';
import { useLibraryNotifications } from '@/composables/useLibraryNotifications';
import Logger from '@/utils/logger';

// Composables
const router = useRouter();
const booksComposable = useBooks();
const notifications = useLibraryNotifications();

// Configurar búsqueda para búsqueda por nombre
const nameSearch = useSearch({
  debounceDelay: 500,
  minQueryLength: 3
});

// Estados locales del componente
const decodedText = ref("");
const isbnSearchError = ref("");

// Estados computados
const allowedUserStatusesList = computed(() => 
  Array.isArray(booksComposable.allowedStatuses.value) ? booksComposable.allowedStatuses.value : []
);

// Transformar resultado de búsqueda a formato de libro
const transformSearchResultToBook = (result) => {
  const isbn = Array.isArray(result.isbn) ? result.isbn[0] : result.isbn;
  return {
    isbn: isbn,
    title: result.title || 'Título no disponible',
    author: Array.isArray(result.author) ? result.author.join(', ') : (result.author || 'Autor desconocido'),
    coverUrl: getBookCoverUrl(result.cover_i),
    user_rating: 0,
    userStatuses: []
  };
};

// Navegación a página de detalle
const goToBookDetail = (book) => {
  // Extraer el ISBN, puede ser un string o un array
  const isbn = Array.isArray(book.isbn) ? book.isbn[0] : book.isbn;
  
  if (!isbn) {
    Logger.warn('[BookSearch] Book has no ISBN, cannot navigate to detail');
    notifications.showError('Este libro no tiene ISBN disponible');
    return;
  }
  
  Logger.debug('[BookSearch] Navigating to book detail:', isbn);
  
  // Transformar datos del libro al formato esperado
  const bookData = {
    isbn: isbn,
    title: book.title || 'Título no disponible',
    author: Array.isArray(book.author) ? book.author.join(', ') : (book.author || 'Autor no disponible'),
    publisher: Array.isArray(book.publisher) ? book.publisher.join(', ') : (book.publisher || ''),
    publicationDate: book.publicationDate || '',
    coverUrl: getBookCoverUrl(book.cover_i),
    pages: book.pages || null,
    description: book.description || '',
    publishers: Array.isArray(book.publisher) ? book.publisher : (book.publisher ? [book.publisher] : []),
    rating: null,
    user_rating: null,
    userStatuses: [],
    genres: book.genres || []
  };
  
  router.push({
    name: 'BookDetail',
    params: { isbn: isbn },
    state: { book: bookData }
  });
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



// Búsqueda por ISBN simplificada - navega directamente
const triggerFetchBookInfo = () => {
  isbnSearchError.value = "";
  const isbn = decodedText.value.trim();
  
  if (!isbn) {
    isbnSearchError.value = "Por favor ingresa un ISBN.";
    return;
  }
  
  Logger.debug('[BookSearch] Navigating to book detail by ISBN:', isbn);
  router.push({
    name: 'BookDetail',
    params: { isbn: isbn }
  });
}

// Simplificar búsqueda por nombre - solo preparar lista
const triggerFetchBookByName = async () => {
  // Limpiar estado previo
  
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
            genres: book.categories || [], // Extraer géneros/categorías
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
            genres: book.categories || [], // Extraer géneros/categorías (fallback)
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

/* Lista de resultados simplificada */
.search-results-list {
  width: 100%;
  max-width: 600px;
  margin-top: 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.search-list-item {
  display: flex;
  align-items: center;
  background: #232323;
  border-radius: 10px;
  padding: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05);
  border: 1px solid transparent;
}

.search-list-item:hover {
  background: #282c34;
  border-color: #007bff;
  box-shadow: 0 2px 8px rgba(0,123,255,0.15);
  transform: translateX(4px);
}

.search-list-poster {
  width: 50px;
  height: 75px;
  object-fit: cover;
  border-radius: 4px;
  margin-right: 16px;
  border: 1px solid #444;
  flex-shrink: 0;
}

.search-list-info {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.search-list-title {
  color: #e0e0e0;
  font-size: 1rem;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.search-list-subtitle {
  color: #888;
  font-size: 0.85rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.search-list-arrow {
  font-size: 1.2rem;
  color: #88aaff;
  margin-left: 10px;
  flex-shrink: 0;
  transition: transform 0.2s ease;
}

.search-list-item:hover .search-list-arrow {
  transform: translateX(4px);
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