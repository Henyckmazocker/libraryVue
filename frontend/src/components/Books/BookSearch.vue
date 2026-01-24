<template>
  <div class="book-search-container">
      <!-- Search Section -->
    <GenericSearch :config="searchConfig" />
    <!-- Trending Books Section -->
    <TrendingCarousel
      :items="trendingBooks"
      :is-loading="isLoadingTrending"
      :error="errorTrending"
      type="books"
      :item-component="BookCarouselItem"
      title="Libros Populares"
      subtitle="Los libros más populares en nuestra comunidad"
      @item-click="handleTrendingClick"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import GenericSearch from '@/components/shared/GenericSearch.vue';
import TrendingCarousel from '@/components/TrendingCarousel.vue';
import BookCarouselItem from '@/components/Books/BookCarouselItem.vue';
import { useBooks } from '@/composables/useBooks';
import { useTrending } from '@/composables/useTrending';
import { useAuthStore } from '@/store/auth';
import { useBooksStore } from '@/store/books';
import { useUIStore } from '@/store/ui';
import { storeToRefs } from 'pinia';
import Logger from '@/utils/logger';

// Router
const router = useRouter();

// Composables & Stores
const booksComposable = useBooks();
const authStore = useAuthStore();
const booksStore = useBooksStore();
const uiStore = useUIStore();
const { isAuthenticated } = storeToRefs(authStore);
const { 
  trendingBooks, 
  isLoadingBooks: isLoadingTrending, 
  errorBooks: errorTrending,
  fetchTrendingBooks 
} = useTrending();

// Cargar libros del usuario y trending al montar (solo si está autenticado)
onMounted(async () => {
  if (isAuthenticated.value) {
    // Cargar libros de la biblioteca para poder verificar qué items tiene el usuario
    if (booksStore.books.length === 0) {
      await booksStore.fetchBooks();
    }
    // Cargar trending books
    fetchTrendingBooks(10, 90); // 10 libros, últimos 90 días
  }
});

// Cargar libros cuando se autentique
watch(isAuthenticated, async (newValue) => {
  if (newValue) {
    // Cargar biblioteca del usuario
    if (booksStore.books.length === 0) {
      Logger.debug('[BookSearch] User authenticated, fetching user books...');
      await booksStore.fetchBooks();
    }
    // Cargar trending books
    if (trendingBooks.value.length === 0) {
      Logger.debug('[BookSearch] Fetching trending books...');
      fetchTrendingBooks(10, 90);
    }
  }
});

// Función para detectar si es ISBN o búsqueda por nombre
const detectSearchType = (query) => {
  const cleanQuery = query.replace(/[-\s]/g, ''); // Remover guiones y espacios
  const isNumeric = /^\d+$/.test(cleanQuery);
  const isValidISBN = isNumeric && (cleanQuery.length === 10 || cleanQuery.length === 13);
  
  return {
    type: isValidISBN ? 'direct' : 'name',
    isDirect: isValidISBN
  };
};

// Función para obtener URL de portada
const getBookCoverUrl = (cover_i) => {
  if (!cover_i) return '';
  
  if (cover_i.startsWith('https://')) {
    return cover_i; // Google Books URL
  } else {
    return `https://covers.openlibrary.org/b/id/${cover_i}-L.jpg`; // OpenLibrary ID
  }
};

// Handler de búsqueda para libros
const searchBooks = async (query, searchType) => {
  if (searchType === 'name') {
    // Búsqueda por nombre usando Google Books y OpenLibrary
    try {
      Logger.debug("Searching by name with Google Books API...");
      const googleApiUrl = `https://www.googleapis.com/books/v1/volumes?q=intitle:${encodeURIComponent(query)}&maxResults=10`;
      const response = await axios.get(googleApiUrl);
      const data = response.data;

      if (data.items && data.items.length > 0) {
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
              genres: book.categories || [],
              key: item.id
            };
          } catch (error) {
            Logger.warn(`Failed to get details for book ${item.id}:`, error.message);
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
              genres: book.categories || [],
              key: item.id
            };
          }
        });
        
        const books = await Promise.all(booksPromises);
        Logger.debug(`Found ${books.length} books with Google Books API`);
        return books;
      }
    } catch (error) {
      Logger.warn("Google Books name search failed, trying OpenLibrary fallback:", error.message);
    }

    // Fallback a OpenLibrary
    try {
      Logger.debug("Searching by name with OpenLibrary as fallback...");
      const apiUrl = `https://openlibrary.org/search.json?title=${encodeURIComponent(query)}`;
      const response = await axios.get(apiUrl);
      let docs = response.data.docs || [];
      
      if (docs.length === 0) {
        return [];
      }
      
      docs = docs.slice(0, 10);
      const books = [];
      
      for (const doc of docs) {
        try {
          let authors = [];
          let isbnSearch = (Array.isArray(doc.isbn) && doc.isbn.length > 0) ? doc.isbn[0] : '';
          
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
              books.push({
                isbn: isbnSearch,
                title: doc.title,
                author: doc.author_name || ['Author not available'],
                cover_i: (Array.isArray(doc.cover_i) && doc.cover_i.length > 0) ? doc.cover_i[0] : "",
                publisher: doc.publisher ? [doc.publisher[0]] : [],
                pages: null,
                key: doc.key || `ol-${Date.now()}-${Math.random()}`
              });
            }
          } else {
            books.push({
              isbn: '',
              title: doc.title,
              author: doc.author_name || ['Author not available'],
              cover_i: (Array.isArray(doc.cover_i) && doc.cover_i.length > 0) ? doc.cover_i[0] : "",
              publisher: doc.publisher ? [doc.publisher[0]] : [],
              pages: null,
              key: doc.key || `ol-${Date.now()}-${Math.random()}`
            });
          }
        } catch (error) {
          Logger.warn(`Failed to process OpenLibrary doc:`, error.message);
        }
      }
      
      Logger.debug(`Found ${books.length} books with OpenLibrary fallback`);
      return books;
    } catch (error) {
      Logger.error("Both name search APIs failed:", error);
      throw new Error("Error searching books by name in all available databases.");
    }
  }
  
  return [];
};

// Transformar resultado de búsqueda
const transformResult = (result) => {
  const isbn = Array.isArray(result.isbn) ? result.isbn[0] : result.isbn;
  return {
    isbn: isbn,
    title: result.title || 'Título no disponible',
    author: Array.isArray(result.author) ? result.author.join(', ') : (result.author || 'Autor desconocido'),
    coverUrl: getBookCoverUrl(result.cover_i),
    cover_i: result.cover_i,
    publisher: result.publisher,
    pages: result.pages,
    genres: result.genres,
    user_rating: 0,
    userStatuses: []
  };
};

// Navegación a detalle
const navigateToDetail = (router, book) => {
  const isbn = Array.isArray(book.isbn) ? book.isbn[0] : book.isbn;
  
  if (!isbn) {
    Logger.warn('[BookSearch] Book has no ISBN, cannot navigate to detail');
    uiStore.showError('Este libro no tiene ISBN disponible');
    return;
  }
  
  Logger.debug('[BookSearch] Navigating to book detail:', isbn);
  
  const bookData = {
    isbn: isbn,
    title: book.title || 'Título no disponible',
    authors: Array.isArray(book.author) ? book.author : (book.author ? [book.author] : ['Autor desconocido']),
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

// Handler para clicks en trending
const handleTrendingClick = (book) => {
  Logger.debug('Trending book clicked:', book);
  
  // Navegar a detalle del libro trending
  const bookData = {
    isbn: book.isbn,
    title: book.title,
    authors: [book.author],
    publicationDate: '',
    coverUrl: book.cover_url,
    pages: null,
    description: '',
    publishers: [],
    rating: book.avg_rating || null,
    user_rating: null,
    userStatuses: [],
    genres: []
  };
  
  router.push({
    name: 'BookDetail',
    params: { isbn: book.isbn },
    state: { book: bookData }
  });
};

// Obtener clave única del resultado
const getResultKey = (result) => {
  return result.key || result.isbn || `book-${Date.now()}-${Math.random()}`;
};

// Cargar estados permitidos
const fetchAllowedStatuses = async () => {
  await booksComposable.fetchAllowedStatuses();
  return Array.isArray(booksComposable.allowedStatuses.value) 
    ? booksComposable.allowedStatuses.value 
    : [];
};

// Configuración del componente genérico
const searchConfig = computed(() => ({
  title: 'Book Finder (Google Books + OpenLibrary)',
  inputs: [
    {
      type: 'auto',
      placeholder: 'Buscar por ISBN o título del libro...',
      buttonText: '',
      idField: 'isbn',
      emptyMessage: 'Introduce un ISBN o título para buscar.',
      errorMessage: 'Error al buscar el libro.'
    }
  ],
  carouselItemComponent: BookCarouselItem,
  itemProp: 'book',
  searchHandler: searchBooks,
  transformResult: transformResult,
  navigateToDetail: navigateToDetail,
  getResultKey: getResultKey,
  fetchAllowedStatuses: fetchAllowedStatuses,
  detectSearchType: detectSearchType
}));
</script>

<style scoped>
.book-search-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 40px;
}
</style>
