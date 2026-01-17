import { storeToRefs } from 'pinia';
import { useBooksStore } from '@/store/books';
import { useSessionsStore } from '@/store/sessions';
import { useAuthStore } from '@/store/auth';
import { useConfirmationModal } from './useConfirmationModal';
import Logger from '@/utils/logger';

/**
 * Composable para gestión de libros
 * Wrapper ligero del store Pinia useBooksStore
 * Proporciona helpers adicionales y lógica específica de UI (confirmaciones, validaciones)
 * 
 * REFACTORIZADO: La lógica de negocio está en el store, aquí solo helpers de UI
 */
export function useBooks() {
  const booksStore = useBooksStore();
  const sessionsStore = useSessionsStore();
  const authStore = useAuthStore();
  
  // ✅ Estado reactivo via storeToRefs (directamente del store)
  const {
    books,
    allowedStatuses,
    userTags,
    isLoading,
    error,
    searchResults,
    isSearching,
    lastSearchQuery,
    // Getters computados
    totalBooks,
    hasBooks,
    hasSearchResults,
    booksWithRating,
    averageRating,
    booksByStatus,
    bookCountByStatus
  } = storeToRefs(booksStore);

  // ✅ Actions del store (delegación directa - sin lógica adicional)
  const {
    fetchBooks,
    searchBooks: searchBooksStore,
    fetchAllowedStatuses,
    fetchUserTags,
    createTag: createTagStore,
    updateBookTags,
    clearSearchResults,
    clearError
  } = booksStore;

  /**
   * Agrega un libro con validación de estados permitidos
   * Wrapper que añade pre-carga de estados si no existen
   */
  const addBook = async (book, statuses = []) => {
    // Pre-cargar estados permitidos si no existen
    if (allowedStatuses.value.length === 0) {
      await fetchAllowedStatuses();
    }
    
    return await booksStore.addBook(book, statuses);
  };

  /**
   * Elimina un libro CON confirmación modal
   * Wrapper que añade confirmación de usuario
   */
  const deleteBook = async (isbn, skipConfirmation = false) => {
    const { confirmDelete } = useConfirmationModal();
    
    try {
      const book = books.value.find(b => b.isbn === isbn);
      const bookTitle = book ? book.title : `ISBN: ${isbn}`;

      // Mostrar confirmación si no se omite
      if (!skipConfirmation) {
        const confirmed = await confirmDelete(
          bookTitle,
          'También se eliminarán todas las sesiones de lectura asociadas'
        );
        
        if (!confirmed) {
          return { success: false, cancelled: true };
        }
      }

      return await booksStore.deleteBook(isbn);
    } catch (err) {
      Logger.error('[useBooks] Error in deleteBook wrapper:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Actualiza la calificación de un libro
   * Delegación directa al store
   */
  const updateBookRating = async (isbn, rating) => {
    return await booksStore.updateBookRating(isbn, rating);
  };

  /**
   * Actualiza los estados de un libro CON lógica de sesiones
   * Wrapper con validación de sesiones y notificaciones
   */
  const updateBookStatuses = async (isbn, statuses) => {
    try {
      Logger.debug(`[useBooks] Updating book statuses: ${isbn}`, statuses);
      
      const book = books.value.find(b => b.isbn === isbn);
      if (!book) {
        throw new Error('Book not found');
      }

      const previousStatuses = book.userStatuses || [];
      
      // Detectar transiciones de estado
      const transitions = {
        startedReading: statuses.includes('reading') && !previousStatuses.includes('reading'),
        completedBook: statuses.includes('read') && !previousStatuses.includes('read'),
        pausedBook: statuses.includes('paused') && !previousStatuses.includes('paused'),
        abandonedBook: statuses.includes('abandoned') && !previousStatuses.includes('abandoned')
      };

      // Verificar si hay sesión activa
      const activeSession = sessionsStore.getActiveSessionByBook(isbn);
      let sessionInfo = null;
      
      if (activeSession) {
        sessionInfo = {
          hasActiveSession: true,
          sessionNumber: activeSession.session_number || 1,
          currentPage: book.current_page || 0,
          totalPages: book.pages || 0,
          startedAt: activeSession.started_at
        };
      }

      // Confirmar cambios críticos si hay sesión activa
      if ((transitions.completedBook || transitions.abandonedBook) && sessionInfo) {
        const { confirmStatusChangeWithSession } = useConfirmationModal();
        const newStatus = transitions.completedBook ? 'read' : 'abandoned';
        
        const confirmed = await confirmStatusChangeWithSession(
          book.title,
          newStatus,
          sessionInfo
        );

        if (!confirmed) {
          Logger.debug('[useBooks] Status change cancelled by user');
          return { success: false, cancelled: true };
        }
      }

      // Delegar actualización al store
      const result = await booksStore.updateBookStatuses(isbn, statuses);

      // NOTIFICACIONES AUTOMÁTICAS (lógica de UI)
      if (result.success) {
        const { useSessionFeedback } = await import('./useSessionFeedback');
        const sessionFeedback = useSessionFeedback();
        
        if (transitions.startedReading) {
          sessionFeedback.notifyAutoSessionStart(book.title);
        }
        if (transitions.completedBook) {
          sessionFeedback.notifyAutoSessionComplete(book.title);
        }
        if (transitions.pausedBook) {
          sessionFeedback.notifyAutoSessionPause(book.title);
        }
        if (transitions.abandonedBook) {
          sessionFeedback.notifyAutoSessionAbandoned(book.title);
        }
      }

      return result;
    } catch (err) {
      // Validación especial para error de página incompleta
      if (err.message && err.message.includes('Debes marcar la última página')) {
        const { confirm } = useConfirmationModal();
        const currentBook = books.value.find(b => b.isbn === isbn);
        
        const match = err.message.match(/página \((\d+)\)/);
        const lastPage = match ? parseInt(match[1]) : (currentBook?.pages || 0);
        
        const confirmed = await confirm(
          'Completar última página',
          `${err.message}\n\n¿Deseas actualizar automáticamente a la página ${lastPage} y marcar el libro como leído?`,
          {
            confirmText: 'Sí, actualizar y completar',
            cancelText: 'Cancelar',
            type: 'warning'
          }
        );
        
        if (confirmed) {
          await updateReadingProgress(isbn, lastPage);
          return await updateBookStatuses(isbn, statuses);
        } else {
          return { success: false, cancelled: true };
        }
      }
      
      Logger.error('[useBooks] Error updating book statuses:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Edita un user_book completo (datos, tags, notas)
   * Delegación con formateo de datos
   */
  const editUserBook = async (isbn, userId, data = {}, tags = [], notes = []) => {
    try {
      Logger.debug('[useBooks] Editing user_book:', { isbn, userId, data, tags, notes });
      
      const response = await authStore.authenticatedApiCall('edit_user_book', {
        isbn,
        userId,
        data,
        tags,
        notes
      });
      
      if (response.data.status === 'success') {
        // Actualizar libro en el store local
        const bookIndex = books.value.findIndex(book => book.isbn === isbn);
        if (bookIndex !== -1) {
          books.value[bookIndex] = {
            ...books.value[bookIndex],
            user_rating: data.personalRating !== undefined ? data.personalRating : books.value[bookIndex].user_rating,
            userStatuses: data.statuses || books.value[bookIndex].userStatuses,
            currentPage: data.currentPage !== undefined ? data.currentPage : books.value[bookIndex].currentPage
          };
        }
        
        Logger.debug('[useBooks] User book edited successfully');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Error editing user_book');
      }
    } catch (err) {
      Logger.error('[useBooks] Error editing user_book:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Crea un nuevo tag CON validación
   */
  const createUserTag = async (tagName, color = '#1976d2') => {
    if (!tagName || tagName.trim().length === 0) {
      return { success: false, message: 'Tag name cannot be empty' };
    }
    
    return await createTagStore(tagName, color);
  };

  /**
   * Obtiene los tags de un libro específico
   */
  const getBookTags = async (isbn) => {
    try {
      const response = await authStore.authenticatedApiCall('get_book_tags', { isbn });
      
      if (response.data.status === 'success') {
        return { success: true, data: response.data.data || [] };
      } else {
        throw new Error(response.data.message || 'Error getting book tags');
      }
    } catch (err) {
      Logger.error('[useBooks] Error getting book tags:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Actualiza el progreso de lectura
   */
  const updateReadingProgress = async (isbn, currentPage) => {
    try {
      const response = await authStore.authenticatedApiCall('update_reading_progress', {
        isbn,
        currentPage
      });
      
      if (response.data.status === 'success') {
        // Actualizar en el store local
        const book = books.value.find(b => b.isbn === isbn);
        if (book) {
          book.current_page = currentPage;
        }
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Error updating progress');
      }
    } catch (err) {
      Logger.error('[useBooks] Error updating reading progress:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Obtiene el progreso de lectura
   */
  const getReadingProgress = async (isbn) => {
    try {
      const response = await authStore.authenticatedApiCall('get_reading_progress', { isbn });
      
      if (response.data.status === 'success') {
        return { success: true, data: response.data.data };
      } else {
        throw new Error(response.data.message || 'Error getting progress');
      }
    } catch (err) {
      Logger.error('[useBooks] Error getting reading progress:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Crea una sesión de lectura - DELEGA al sessions store
   */
  const createReadingSession = async (isbn, startPage = 1) => {
    return await sessionsStore.createSession(isbn, startPage);
  };

  /**
   * Completa una sesión de lectura - DELEGA al sessions store
   */
  const completeReadingSession = async (isbn, endPage, reason = 'completed') => {
    return await sessionsStore.completeSession(isbn, endPage, reason);
  };

  /**
   * Actualiza progreso con sesión activa
   */
  const updateReadingProgressWithSession = async (isbn, currentPage) => {
    return await sessionsStore.updateProgress(isbn, currentPage);
  };

  /**
   * Resetea el progreso de un libro
   */
  const resetBookProgress = async (isbn) => {
    try {
      const response = await authStore.authenticatedApiCall('reset_book_progress', { isbn });
      
      if (response.data.status === 'success') {
        const book = books.value.find(b => b.isbn === isbn);
        if (book) {
          book.current_page = 1;
        }
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Error resetting progress');
      }
    } catch (err) {
      Logger.error('[useBooks] Error resetting progress:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Inicia re-lectura de un libro
   */
  const startReReading = async (isbn, startPage = 1) => {
    return await createReadingSession(isbn, startPage);
  };

  /**
   * Obtiene sesiones activas del usuario - DELEGA al sessions store
   */
  const getUserActiveSessions = async () => {
    return await sessionsStore.fetchAllActiveSessions();
  };

  /**
   * Obtiene el historial de sesiones de un libro - DELEGA al sessions store
   */
  const getBookSessionHistory = async (isbn) => {
    return await sessionsStore.loadHistory(isbn);
  };

  // ==========================================
  // HELPERS DE UTILIDAD (sin estado - solo funciones)
  // ==========================================

  /**
   * Busca un libro específico por ISBN
   */
  const findBookByISBN = (isbn) => {
    return books.value.find(book => book.isbn === isbn);
  };

  /**
   * Filtra libros por criterios
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
   * Getter especial para búsquedas (alias)
   */
  const searchBooksWrapper = async (query) => {
    return await searchBooksStore(query);
  };

  return {
    // ===== ESTADO REACTIVO (desde store) =====
    books,
    searchResults,
    allowedStatuses,
    userTags,
    isLoading,
    isSearching,
    error,
    lastSearchQuery,

    // ===== GETTERS COMPUTADOS (desde store) =====
    totalBooks,
    hasBooks,
    hasSearchResults,
    booksWithRating,
    averageRating,
    booksByStatus,
    bookCountByStatus,

    // ===== MÉTODOS PRINCIPALES (con lógica de UI) =====
    fetchBooks,                           // Directo del store
    searchBooks: searchBooksWrapper,      // Alias
    addBook,                              // Wrapper con validación
    editUserBook,                         // Wrapper con formateo
    deleteBook,                           // Wrapper con confirmación
    updateBookRating,                     // Directo del store
    updateBookStatuses,                   // Wrapper con sesiones y notificaciones
    fetchAllowedStatuses,                 // Directo del store

    // ===== TAGS =====
    fetchUserTags,                        // Directo del store
    createUserTag,                        // Wrapper con validación
    getBookTags,                          // Método específico
    updateBookTags,                       // Directo del store

    // ===== PROGRESO DE LECTURA =====
    updateReadingProgress,                // Método específico
    getReadingProgress,                   // Método específico

    // ===== SESIONES DE LECTURA (delegan al sessions store) =====
    createReadingSession,                 // Delega a sessionsStore
    completeReadingSession,               // Delega a sessionsStore
    updateReadingProgressWithSession,     // Delega a sessionsStore
    resetBookProgress,                    // Método específico
    startReReading,                       // Wrapper
    getUserActiveSessions,                // Delega a sessionsStore
    getBookSessionHistory,                // Delega a sessionsStore

    // ===== UTILIDADES (funciones puras) =====
    findBookByISBN,                       // Helper puro
    filterBooks,                          // Helper puro
    clearSearchResults,                   // Directo del store
    clearError                            // Directo del store
  };
}
