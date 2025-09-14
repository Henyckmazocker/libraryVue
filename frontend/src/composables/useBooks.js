import { ref, computed } from 'vue';
import { useAuth } from './useAuth';
import { useAuthStore } from '@/store/auth';
import Logger from '@/utils/logger';

// Estados globales compartidos (singleton)
const books = ref([]);
const allowedStatuses = ref([]);
const userTags = ref([]);
const isLoading = ref(false);
const error = ref(null);
const lastSearchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);

/**
 * Composable para gestión de libros
 * Proporciona funcionalidades CRUD para libros y gestión de estados
 * Implementado como singleton para compartir estado entre componentes
 */
export function useBooks() {
  const { authenticatedApiCall } = useAuth();
  const authStore = useAuthStore();

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
      
      // Normalizar publisher - manejar tanto publishers (array) como publisher (string)
      let publisherValue = '';
      if (book.publishers && Array.isArray(book.publishers) && book.publishers.length > 0) {
        publisherValue = book.publishers.join(', ');
      } else if (book.publisher) {
        publisherValue = book.publisher;
      }
      
      const bookData = {
        isbn: book.isbn,
        title: book.title,
        author: book.author || '',
        coverUrl: book.coverUrl || '',
        publisher: publisherValue,
        publicationDate: book.publicationDate || '',
        pages: book.pages || null,
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

      Logger.debug('[useBooks] Backend response:', response);

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
        Logger.error('[useBooks] Backend returned error:', response.data);
        throw new Error(response.data.message || 'Failed to add book');
      }
    } catch (err) {
      // Manejar diferentes tipos de errores
      let errorMessage = 'Failed to add book';
      
      if (err.response) {
        // Error de respuesta HTTP del servidor
        const status = err.response.status;
        const data = err.response.data;
        
        if (status === 401) {
          errorMessage = 'Authentication required. Please login again.';
        } else if (status === 403) {
          errorMessage = 'Invalid CSRF token. Please refresh the page and try again.';
        } else if (data && data.message) {
          errorMessage = data.message;
        } else {
          errorMessage = `Server error (${status})`;
        }
      } else if (err.request) {
        // Error de red
        errorMessage = 'Network error. Please check your connection.';
      } else if (err.message) {
        // Error general
        errorMessage = err.message;
      }
      
      error.value = errorMessage;
      Logger.error('[useBooks] Error adding book:', err);
      Logger.error('[useBooks] Full error details:', {
        message: err.message,
        response: err.response?.data,
        status: err.response?.status,
        config: err.config
      });
      return { success: false, message: errorMessage };
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
   * Edita todos los aspectos de un user_book (datos, tags, notas)
   * @param {string} isbn
   * @param {number} userId
   * @param {object} data
   * @param {Array} tags
   * @param {Array} notes
   */
  const editUserBook = async (isbn, userId, data = {}, tags = [], notes = []) => {
    try {
      Logger.debug('[useBooks] Editando user_book:', { isbn, userId, data, tags, notes });
      const response = await authenticatedApiCall('edit_user_book', {
        isbn,
        userId,
        data,
        tags,
        notes
      });
      if (response.data.status === 'success') {
        // Actualizar el libro en el estado local
        const bookIndex = books.value.findIndex(book => book.isbn === isbn);
        if (bookIndex !== -1) {
          books.value[bookIndex] = {
            ...books.value[bookIndex],
            user_rating: data.personalRating !== undefined ? data.personalRating : books.value[bookIndex].user_rating,
            userStatuses: data.statuses || books.value[bookIndex].userStatuses,
            currentPage: data.currentPage !== undefined ? data.currentPage : books.value[bookIndex].currentPage
          };
        }
        
        Logger.debug('[useBooks] User book editado correctamente');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Error al editar user_book');
      }
    } catch (err) {
      error.value = err.message || 'Error al editar user_book';
      Logger.error('[useBooks] Error editando user_book:', err);
      return { success: false, message: err.message };
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
   * Obtiene todos los tags del usuario
   */
  const fetchUserTags = async () => {
    try {
      Logger.debug('[useBooks] Obteniendo tags del usuario');
      const response = await authenticatedApiCall('get_user_book_tags');

      if (response.data.status === 'success') {
        userTags.value = response.data.data || [];
        Logger.debug('[useBooks] Tags obtenidos:', userTags.value);
        return { success: true, data: userTags.value };
      } else {
        throw new Error(response.data.message || 'Error al obtener tags');
      }
    } catch (error) {
      Logger.error('[useBooks] Error obteniendo tags:', error);
      return { success: false, message: error.message };
    }
  };

  /**
   * Crea un nuevo tag para el usuario
   */
  const createUserTag = async (tagName, color = '#1976d2') => {
    try {
      Logger.debug('[useBooks] Creando nuevo tag:', { tagName, color });
      const response = await authenticatedApiCall('create_user_book_tag', {
        name: tagName, 
        color
      });

      if (response.data.status === 'success') {
        const newTag = response.data.data;
        userTags.value.push(newTag);
        Logger.debug('[useBooks] Tag creado:', newTag);
        return { success: true, data: newTag };
      } else {
        throw new Error(response.data.message || 'Error al crear tag');
      }
    } catch (error) {
      Logger.error('[useBooks] Error creando tag:', error);
      return { success: false, message: error.message };
    }
  };

  /**
   * Obtiene los tags de un libro específico
   */
  const getBookTags = async (isbn) => {
    try {
      Logger.debug('[useBooks] Obteniendo tags del libro:', isbn);
      const response = await authenticatedApiCall('get_book_tags', {
        isbn: isbn
      });

      if (response.data.status === 'success') {
        Logger.debug('[useBooks] Tags del libro obtenidos:', response.data.data);
        return { success: true, data: response.data.data || [] };
      } else {
        throw new Error(response.data.message || 'Error al obtener tags del libro');
      }
    } catch (error) {
      Logger.error('[useBooks] Error obteniendo tags del libro:', error);
      return { success: false, message: error.message };
    }
  };

  /**
   * Actualiza el progreso de lectura de un libro (página actual)
   */
  const updateReadingProgress = async (isbn, currentPage) => {
    try {
      Logger.debug('[useBooks] Actualizando progreso de lectura:', { isbn, currentPage });
      
      if (!authStore.user?.id) {
        throw new Error('Usuario no autenticado');
      }

      const response = await authenticatedApiCall('edit_user_book', {
        isbn,
        userId: authStore.user.id,
        data: { 
          currentPage: parseInt(currentPage) || 0 
        },
        tags: [],
        notes: []
      });

      if (response.data.status === 'success') {
        // Actualizar el libro en el estado local
        const bookIndex = books.value.findIndex(book => book.isbn === isbn);
        if (bookIndex !== -1) {
          books.value[bookIndex] = {
            ...books.value[bookIndex],
            currentPage: parseInt(currentPage) || 0
          };
        }
        
        Logger.debug('[useBooks] Progreso de lectura actualizado correctamente');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Error al actualizar progreso de lectura');
      }
    } catch (error) {
      Logger.error('[useBooks] Error actualizando progreso de lectura:', error);
      return { success: false, message: error.message };
    }
  };

  /**
   * Obtiene el progreso de lectura de un libro específico
   */
  const getReadingProgress = (isbn) => {
    const book = books.value.find(book => book.isbn === isbn);
    return {
      currentPage: book?.currentPage || 0,
      totalPages: book?.pages || 0,
      progressPercentage: book?.pages ? Math.round(((book?.currentPage || 0) / book.pages) * 100) : 0
    };
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
    userTags,
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
    editUserBook, 
    deleteBook,
    updateBookRating,
    updateBookStatuses,
    fetchAllowedStatuses,

    // Métodos de tags
    fetchUserTags,
    createUserTag,
    getBookTags,

    // Métodos de progreso de lectura
    updateReadingProgress,
    getReadingProgress,

    // Métodos de utilidad
    findBookByISBN,
    filterBooks,
    clearSearchResults,
    clearError,
    reset
  };
}
