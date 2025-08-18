import { ref, computed } from 'vue';
import { useAuth } from './useAuth';
import { useAuthStore } from '@/store/auth';
import Logger from '@/utils/logger';

/**
 * Composable para gestión de libros
 * Proporciona funcionalidades CRUD para libros y gestión de estados
 */
export function useBooks() {
  const { authenticatedApiCall } = useAuth();
  const authStore = useAuthStore();
  
  // Estados reactivos
  const books = ref([]);
  const allowedStatuses = ref([]);
  const isLoading = ref(false);
  const error = ref(null);
  const lastSearchQuery = ref('');
  const searchResults = ref([]);
  const isSearching = ref(false);

  // Estados computados
  const totalBooks = computed(() => books.value.length);
  const hasBooks = computed(() => books.value.length > 0);
  const hasSearchResults = computed(() => searchResults.value.length > 0);
  const booksWithRating = computed(() => 
    books.value.filter(book => book.user_rating && book.user_rating > 0)
  );
  const booksByStatus = computed(() => {
    const statusGroups = {};
    books.value.forEach(book => {
      if (book.userStatuses && Array.isArray(book.userStatuses)) {
        book.userStatuses.forEach(status => {
          if (!statusGroups[status]) statusGroups[status] = [];
          statusGroups[status].push(book);
        });
      }
    });
    return statusGroups;
  });

  /**
   * Obtiene todos los libros del usuario
   */
  const fetchBooks = async () => {
    isLoading.value = true;
    error.value = null;
    
    try {
      Logger.debug('[useBooks] Fetching user books...');
      const response = await authenticatedApiCall('get_library_items');
      
      if (response.data.status === 'success') {
        Logger.debug('[useBooks] Response data:', response.data);
        
        // El backend devuelve { books: [], movies: [] }
        const data = response.data.data || {};
        const booksArray = Array.isArray(data.books) ? data.books : [];
        
        // Asignar directamente los libros ya que vienen filtrados del backend
        books.value = booksArray.map(book => ({
          ...book,
          itemType: 'book'
        }));
        
        Logger.debug(`[useBooks] Fetched ${books.value.length} books`);
      } else {
        throw new Error(response.data.message || 'Failed to fetch books');
      }
    } catch (err) {
      error.value = err.message || 'Failed to fetch books';
      Logger.error('[useBooks] Error fetching books:', err);
      books.value = [];
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Busca libros por ISBN o título
   * @param {string} query - Consulta de búsqueda
   * @returns {Promise<Array>} - Resultados de la búsqueda
   */
  const searchBooks = async (query) => {
    if (!query || query.trim().length === 0) {
      searchResults.value = [];
      return [];
    }

    isSearching.value = true;
    error.value = null;
    lastSearchQuery.value = query;

    try {
      Logger.debug(`[useBooks] Searching books with query: ${query}`);
      
      // Determinar si es búsqueda por ISBN o por nombre
      const isISBN = /^\d{10}(\d{3})?$/.test(query.replace(/[-\s]/g, ''));
      const action = isISBN ? 'search_book_isbn' : 'search_book_name';
      const param = isISBN ? 'isbn' : 'name';
      
      const response = await authenticatedApiCall(action, {
        [param]: query
      });

      if (response.data.status === 'success') {
        searchResults.value = response.data.data || [];
        Logger.debug(`[useBooks] Found ${searchResults.value.length} book results`);
        return searchResults.value;
      } else {
        throw new Error(response.data.message || 'Search failed');
      }
    } catch (err) {
      error.value = err.message || 'Search failed';
      Logger.error('[useBooks] Error searching books:', err);
      searchResults.value = [];
      return [];
    } finally {
      isSearching.value = false;
    }
  };

  /**
   * Agrega un libro a la biblioteca del usuario
   * @param {Object} book - Datos del libro
   * @param {Array} statuses - Estados del libro
   */
  const addBook = async (book, statuses = []) => {
    isLoading.value = true;
    error.value = null;

    try {
      Logger.debug('[useBooks] Adding book to library:', book.isbn);
      
      // Ensure we have allowed statuses available
      if (allowedStatuses.value.length === 0) {
        await fetchAllowedStatuses();
      }
      
      const bookData = {
        isbn: book.isbn,
        title: book.title,
        author: book.author || '',
        coverUrl: book.coverUrl || '',
        publisher: book.publisher || '',
        publicationDate: book.publicationDate || '',
        description: book.description || '',
        userStatuses: statuses,
        allowedStatuses: allowedStatuses.value,
        // Include user rating if present
        rating: book.user_rating || null
      };

      // El backend espera los datos del libro en la propiedad 'book'
      const payload = {
        book: bookData
      };

      const response = await authenticatedApiCall('add_book', payload);

      if (response.data.status === 'success') {
        // Agregar el libro a la lista local con los datos actualizados
        const newBook = {
          ...book,
          userStatuses: statuses,
          user_rating: book.user_rating || null,
          itemType: 'book'
        };
        books.value.push(newBook);
        
        Logger.debug('[useBooks] Book added successfully');
        return { success: true, book: newBook };
      } else {
        throw new Error(response.data.message || 'Failed to add book');
      }
    } catch (err) {
      error.value = err.message || 'Failed to add book';
      Logger.error('[useBooks] Error adding book:', err);
      return { success: false, message: err.message };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Elimina un libro de la biblioteca
   * @param {string} isbn - ISBN del libro
   */
  const deleteBook = async (isbn) => {
    isLoading.value = true;
    error.value = null;

    try {
      Logger.debug('[useBooks] Deleting book:', isbn);
      
      const response = await authenticatedApiCall('delete_book', {
        isbn: isbn,
        itemType: 'book'
      });

      if (response.data.status === 'success') {
        // Remover el libro de la lista local
        books.value = books.value.filter(book => book.isbn !== isbn);
        Logger.debug('[useBooks] Book deleted successfully');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to delete book');
      }
    } catch (err) {
      error.value = err.message || 'Failed to delete book';
      Logger.error('[useBooks] Error deleting book:', err);
      return { success: false, message: err.message };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Actualiza la calificación de un libro
   * @param {string} isbn - ISBN del libro
   * @param {number} rating - Nueva calificación (0-5)
   */
  const updateBookRating = async (isbn, rating) => {
    try {
      Logger.debug(`[useBooks] Updating book rating: ${isbn} -> ${rating}`);
      
      const response = await authenticatedApiCall('update_book_rating', {
        isbn: isbn,
        rating: rating
      });

      if (response.data.status === 'success') {
        // Actualizar la calificación en la lista local
        const book = books.value.find(b => b.isbn === isbn);
        if (book) {
          book.user_rating = rating;
        }
        Logger.debug('[useBooks] Book rating updated successfully');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to update rating');
      }
    } catch (err) {
      error.value = err.message || 'Failed to update rating';
      Logger.error('[useBooks] Error updating book rating:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Actualiza los estados de un libro
   * @param {string} isbn - ISBN del libro
   * @param {Array} statuses - Nuevos estados
   */
  const updateBookStatuses = async (isbn, statuses) => {
    try {
      Logger.debug(`[useBooks] Updating book statuses: ${isbn}`, statuses);
      
      const response = await authenticatedApiCall('update_book_user_statuses', {
        isbn: isbn,
        statuses: statuses
      });

      if (response.data.status === 'success') {
        // Actualizar los estados en la lista local
        const book = books.value.find(b => b.isbn === isbn);
        if (book) {
          book.userStatuses = [...statuses];
        }
        Logger.debug('[useBooks] Book statuses updated successfully');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to update statuses');
      }
    } catch (err) {
      error.value = err.message || 'Failed to update statuses';
      Logger.error('[useBooks] Error updating book statuses:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Obtiene los estados permitidos para libros
   */
  const fetchAllowedStatuses = async () => {
    try {
      Logger.debug('[useBooks] Fetching allowed book statuses...');
      
      // Use apiCall instead of authenticatedApiCall since this endpoint doesn't require auth
      const response = await authStore.apiCall('get_book_allowed_statuses');
      Logger.debug('[useBooks] Response from get_book_allowed_statuses:', response);

      if (response.data.status === 'success') {
        allowedStatuses.value = response.data.data || [];
        Logger.debug(`[useBooks] Successfully fetched ${allowedStatuses.value.length} allowed statuses:`, allowedStatuses.value);
        return allowedStatuses.value;
      } else {
        Logger.error('[useBooks] Error response from backend:', response.data);
        throw new Error(response.data.message || 'Failed to fetch allowed statuses');
      }
    } catch (err) {
      error.value = err.message || 'Failed to fetch allowed statuses';
      Logger.error('[useBooks] Error fetching allowed statuses:', err);
      Logger.error('[useBooks] Full error details:', {
        message: err.message,
        response: err.response?.data,
        status: err.response?.status
      });
      allowedStatuses.value = [];
      return [];
    }
  };

  /**
   * Busca un libro específico por ISBN
   * @param {string} isbn - ISBN del libro
   */
  const findBookByISBN = (isbn) => {
    return books.value.find(book => book.isbn === isbn);
  };

  /**
   * Filtra libros por criterios específicos
   * @param {Object} criteria - Criterios de filtrado
   */
  const filterBooks = (criteria) => {
    return books.value.filter(book => {
      let matches = true;

      if (criteria.status) {
        matches = matches && book.userStatuses && book.userStatuses.includes(criteria.status);
      }

      if (criteria.rating !== undefined) {
        matches = matches && book.user_rating === criteria.rating;
      }

      if (criteria.hasRating !== undefined) {
        matches = matches && (criteria.hasRating ? book.user_rating > 0 : !book.user_rating || book.user_rating === 0);
      }

      if (criteria.author) {
        matches = matches && book.author && book.author.toLowerCase().includes(criteria.author.toLowerCase());
      }

      if (criteria.title) {
        matches = matches && book.title && book.title.toLowerCase().includes(criteria.title.toLowerCase());
      }

      return matches;
    });
  };

  /**
   * Limpia los resultados de búsqueda
   */
  const clearSearchResults = () => {
    searchResults.value = [];
    lastSearchQuery.value = '';
  };

  /**
   * Limpia los errores
   */
  const clearError = () => {
    error.value = null;
  };

  /**
   * Reinicia todos los estados
   */
  const reset = () => {
    books.value = [];
    searchResults.value = [];
    allowedStatuses.value = [];
    error.value = null;
    lastSearchQuery.value = '';
    isLoading.value = false;
    isSearching.value = false;
  };

  return {
    // Estados
    books,
    searchResults,
    allowedStatuses,
    isLoading,
    isSearching,
    error,
    lastSearchQuery,

    // Estados computados
    totalBooks,
    hasBooks,
    hasSearchResults,
    booksWithRating,
    booksByStatus,

    // Métodos de API
    fetchBooks,
    searchBooks,
    addBook,
    deleteBook,
    updateBookRating,
    updateBookStatuses,
    fetchAllowedStatuses,

    // Métodos de utilidad
    findBookByISBN,
    filterBooks,
    clearSearchResults,
    clearError,
    reset
  };
}
