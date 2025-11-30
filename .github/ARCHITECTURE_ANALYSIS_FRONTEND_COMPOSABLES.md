# 🧩 Análisis Arquitectónico: Frontend Composables

**Fecha**: 30 de noviembre de 2025  
**Archivos analizados**: 17 composables  
**Líneas totales**: ~4,200 líneas  
**Patrón dominante**: Singleton con estado global

---

## 📊 Inventory de Composables

| Composable | Líneas | Responsabilidad Principal | Dependencias | Estado |
|------------|--------|---------------------------|--------------|--------|
| **useBooks.js** | 1,014 | Books CRUD + Tags + Sessions | useAuth, useConfirmationModal, useSessionFeedback | 🔴 God Composable |
| useMovies.js | 520 | Movies CRUD + Tags | useAuth, useConfirmationModal | ⚠️ Grande |
| useReadingSessions.js | 430 | Reading Sessions CRUD | useAuth, useBooks | ⚠️ Grande |
| useConfirmationModal.js | 344 | Modal de confirmación | ninguna | ⚠️ Grande |
| useAuth.js | 210 | Auth + API wrapper | useAuthStore | ✅ Aceptable |
| useSearch.js | 180 | Búsqueda genérica | ninguna | ✅ Aceptable |
| useReadingProgress.js | ~170 | Historial de progreso | useAuth | ✅ Aceptable |
| useLibraryNotifications.js | ~150 | Notificaciones toast | ninguna | ✅ Aceptable |
| useSessionFeedback.js | ~140 | Feedback de sesiones | ninguna | ✅ Aceptable |
| useSidebarMenu.js | ~120 | Menú lateral | ninguna | ✅ Aceptable |
| usePermissions.js | ~100 | Guards de permisos | useAuthStore | ✅ Aceptable |
| useTheme.js | ~80 | Dark/Light mode | ninguna | ✅ Aceptable |
| useBookValidation.js | ~70 | Validación de libros | ninguna | ✅ Bien enfocado |
| useMovieValidation.js | ~60 | Validación de películas | ninguna | ✅ Bien enfocado |
| useDebounce.js | ~50 | Debouncing genérico | ninguna | ✅ Utility |
| useLocalStorage.js | ~40 | Persistencia local | ninguna | ✅ Utility |
| useClipboard.js | ~30 | Copy to clipboard | ninguna | ✅ Utility |

---

## 🔴 PROBLEMA CRÍTICO: God Composable Pattern

### useBooks.js - 1,014 Líneas, 28 Funciones Públicas

**Estructura completa**:
```javascript
// ==================== ESTADO GLOBAL (8 refs) ====================
const books = ref([]);              // Lista de libros del usuario
const allowedStatuses = ref([]);    // Estados permitidos (read, reading, etc.)
const userTags = ref([]);           // Tags personalizados del usuario
const isLoading = ref(false);       // Estado de carga
const error = ref(null);            // Último error
const lastSearchQuery = ref('');    // Última búsqueda
const searchResults = ref([]);      // Resultados de búsqueda
const isSearching = ref(false);     // Buscando activamente

// ==================== FUNCIÓN PRINCIPAL ====================
export function useBooks() {
  const { authenticatedApiCall } = useAuth();

  // -------------------- COMPUTEDS (6) --------------------
  const totalBooks = computed(() => books.value.length);
  const hasBooks = computed(() => books.value.length > 0);
  const hasSearchResults = computed(() => searchResults.value.length > 0);
  const booksWithRating = computed(() => 
    books.value.filter(b => b.user_rating && b.user_rating > 0)
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
  const averageRating = computed(() => {
    const rated = booksWithRating.value;
    if (rated.length === 0) return 0;
    const sum = rated.reduce((acc, book) => acc + book.user_rating, 0);
    return sum / rated.length;
  });

  // -------------------- 1️⃣ BOOKS CRUD (8 funciones) --------------------
  
  /**
   * Obtiene todos los libros del usuario (50 líneas)
   */
  const fetchBooks = async () => {
    isLoading.value = true;
    error.value = null;
    
    try {
      const response = await authenticatedApiCall('get_library_items');
      
      if (response.data.status === 'success') {
        const data = response.data.data || {};
        const booksArray = Array.isArray(data.books) ? data.books : [];
        books.value = booksArray.map(book => ({
          ...book,
          itemType: 'book'
        }));
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
   * Busca libro por ISBN (40 líneas)
   */
  const searchBookByISBN = async (isbn) => { /* ... */ };

  /**
   * Busca libro por nombre (40 líneas)
   */
  const searchBookByName = async (query) => { /* ... */ };

  /**
   * Agrega un libro (80 líneas con error handling completo)
   */
  const addBook = async (bookData, statuses = []) => {
    isLoading.value = true;
    error.value = null;

    try {
      const payload = {
        book: {
          isbn: bookData.isbn,
          title: bookData.title,
          author: bookData.author || '',
          year: bookData.year || '',
          pages: bookData.pages || 0,
          genre: bookData.genre || '',
          description: bookData.description || '',
          coverUrl: bookData.coverUrl || '',
          userStatuses: statuses,
          user_rating: bookData.user_rating || 0,
          itemType: 'book'
        }
      };
      
      Logger.debug('[useBooks] Adding book:', payload);
      
      const response = await authenticatedApiCall('add_book', payload);

      if (response.data.status === 'success') {
        books.value.push({
          ...payload.book,
          userStatuses: statuses,
          user_rating: bookData.user_rating || 0
        });
        Logger.debug('[useBooks] Book added successfully');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to add book');
      }
    } catch (err) {
      // ❌ ERROR HANDLING DUPLICADO (50 líneas)
      let errorMessage = 'Failed to add book';
      
      if (err.response) {
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
        errorMessage = 'Network error. Please check your connection.';
      } else if (err.message) {
        errorMessage = err.message;
      }
      
      error.value = errorMessage;
      Logger.error('[useBooks] Error adding book:', err);
      return { success: false, message: errorMessage };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Elimina un libro con confirmación (50 líneas)
   */
  const deleteBook = async (isbn, skipConfirmation = false) => {
    const { confirmDelete } = useConfirmationModal();
    
    try {
      const book = books.value.find(b => b.isbn === isbn);
      const bookTitle = book ? book.title : `ISBN: ${isbn}`;

      if (!skipConfirmation) {
        const confirmed = await confirmDelete(
          bookTitle,
          'También se eliminarán todas las sesiones de lectura asociadas'
        );
        
        if (!confirmed) {
          return { success: false, cancelled: true };
        }
      }

      isLoading.value = true;
      error.value = null;

      const response = await authenticatedApiCall('delete_book', {
        isbn: isbn,
        itemType: 'book'
      });

      if (response.data.status === 'success') {
        books.value = books.value.filter(book => book.isbn !== isbn);
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to delete book');
      }
    } catch (err) {
      error.value = err.message || 'Failed to delete book';
      return { success: false, message: err.message };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Actualiza calificación (30 líneas)
   */
  const updateBookRating = async (isbn, rating) => { /* ... */ };

  /**
   * Actualiza estados con sincronización de sesiones (120 líneas)
   * ⚠️ FUNCIÓN MÁS COMPLEJA - mezcla lógica de estados + sesiones
   */
  const updateBookStatuses = async (isbn, statuses) => {
    try {
      const book = books.value.find(b => b.isbn === isbn);
      if (!book) throw new Error('Book not found');

      const previousStatuses = book.userStatuses || [];
      
      // Detectar transiciones de estado
      const transitions = {
        startedReading: statuses.includes('reading') && !previousStatuses.includes('reading'),
        completedBook: statuses.includes('read') && !previousStatuses.includes('read'),
        pausedBook: statuses.includes('paused') && !previousStatuses.includes('paused'),
        abandonedBook: statuses.includes('abandoned') && !previousStatuses.includes('abandoned')
      };

      // Preparar información de sesión si existe
      let sessionInfo = null;
      if (book.active_reading_session_id) {
        sessionInfo = {
          hasActiveSession: true,
          sessionNumber: book.current_session_number || 1,
          currentPage: book.current_page || 0,
          totalPages: book.pages || 0,
          startedAt: book.session_started_at
        };
      }

      // Confirmación para acciones críticas con sesión activa
      if ((transitions.completedBook || transitions.abandonedBook) && sessionInfo) {
        const { confirmStatusChangeWithSession } = useConfirmationModal();
        const newStatus = transitions.completedBook ? 'read' : 'abandoned';
        
        const confirmed = await confirmStatusChangeWithSession(
          book.title,
          newStatus,
          sessionInfo
        );

        if (!confirmed) {
          return { success: false, cancelled: true };
        }
      }

      // Ejecutar actualización
      const response = await authenticatedApiCall('update_book_user_statuses', {
        isbn: isbn,
        statuses: statuses
      });

      if (response.data.status === 'success') {
        book.userStatuses = [...statuses];
        
        // NOTIFICACIONES AUTOMÁTICAS
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
        
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to update statuses');
      }
    } catch (err) {
      error.value = err.message || 'Failed to update statuses';
      return { success: false, message: err.message };
    }
  };

  /**
   * Edita un libro existente (60 líneas)
   */
  const editUserBook = async (isbn, userId, data, tags, notes) => { /* ... */ };

  // -------------------- 2️⃣ TAGS OPERATIONS (5 funciones) --------------------
  
  const getAllowedBookStatuses = async () => { /* 30 líneas */ };
  const getUserBookTags = async () => { /* 40 líneas */ };
  const createUserBookTag = async (name, color) => { /* 30 líneas */ };
  const getBookTags = async (isbn) => { /* 30 líneas */ };
  const updateBookTags = async (isbn, tags) => { /* 50 líneas */ };

  // -------------------- 3️⃣ READING SESSIONS (12 funciones) --------------------
  // ⚠️ ESTO DEBERÍA SER useReadingSessions.js
  
  const createReadingSession = async (isbn, startPage = null) => { /* 60 líneas */ };
  const getActiveReadingSession = async (isbn) => { /* 40 líneas */ };
  const completeReadingSession = async (sessionId, endPage, reason = 'completed') => { /* 50 líneas */ };
  const updateReadingProgressWithSession = async (isbn, currentPage, sessionId = null) => { /* 70 líneas */ };
  const getReadingSessionHistory = async (isbn) => { /* 40 líneas */ };
  const getSessionProgress = async (sessionId) => { /* 30 líneas */ };
  const getUserActiveReadingSessions = async () => { /* 40 líneas */ };
  const pauseReadingSession = async (sessionId) => { /* 30 líneas */ };
  const resumeReadingSession = async (sessionId) => { /* 30 líneas */ };
  const deleteReadingSession = async (sessionId) => { /* 30 líneas */ };
  const getBookReadingSummary = async (isbn) => { /* 50 líneas */ };
  const getDetailedProgressHistory = async (isbn) => { /* 60 líneas */ };

  // -------------------- RETURN (28 exports) --------------------
  return {
    // Estados
    books, allowedStatuses, userTags, isLoading, error,
    lastSearchQuery, searchResults, isSearching,
    
    // Computeds
    totalBooks, hasBooks, hasSearchResults, booksWithRating,
    booksByStatus, averageRating,
    
    // CRUD
    fetchBooks, searchBookByISBN, searchBookByName, addBook,
    deleteBook, updateBookRating, updateBookStatuses, editUserBook,
    
    // Tags
    getAllowedBookStatuses, getUserBookTags, createUserBookTag,
    getBookTags, updateBookTags,
    
    // Sessions
    createReadingSession, getActiveReadingSession, completeReadingSession,
    updateReadingProgressWithSession, getReadingSessionHistory,
    getSessionProgress, getUserActiveReadingSessions,
    pauseReadingSession, resumeReadingSession, deleteReadingSession,
    getBookReadingSummary, getDetailedProgressHistory
  };
}
```

**Violaciones identificadas**:
1. ❌ **Single Responsibility**: 4 bounded contexts (Books, Tags, Sessions, Stats)
2. ❌ **God Object**: 28 funciones públicas en un composable
3. ❌ **Tight Coupling**: Sessions dependen de Books (circular dependency)
4. ❌ **No testable**: Imposible aislar funciones para unit tests
5. ❌ **1,014 líneas**: 6.7x el límite recomendado (150L)

---

## 🔁 PROBLEMA: Duplicación Books vs Movies

### Análisis Comparativo

**useBooks.js (1,014L) vs useMovies.js (520L)**

| Aspecto | Código en useBooks | Código en useMovies | Similitud |
|---------|-------------------|---------------------|-----------|
| **Estado global** | 8 refs (books, allowedStatuses, userTags, isLoading, error, etc.) | 8 refs (movies, allowedStatuses, userTags, isLoading, error, etc.) | 95% |
| **Computeds** | 6 computeds (totalBooks, hasBooks, booksByStatus, etc.) | 6 computeds (totalMovies, hasMovies, moviesByStatus, etc.) | 90% |
| **fetchItems()** | 50 líneas (get_library_items → filter books) | 50 líneas (get_library_items → filter movies) | 85% |
| **addItem()** | 80 líneas (validación + API + error handling) | 80 líneas (validación + API + error handling) | 90% |
| **deleteItem()** | 50 líneas (confirmación + API + filter local) | 50 líneas (confirmación + API + filter local) | 95% |
| **updateRating()** | 30 líneas (API + update local) | 30 líneas (API + update local) | 95% |
| **updateStatuses()** | 120 líneas (transiciones + confirmación + API) | 120 líneas (transiciones + confirmación + API) | 85% |
| **Error handling** | 50 líneas (401/403/network/generic) | 50 líneas (401/403/network/generic) | 100% |

**Código duplicado estimado**:
- Estados: 8 refs × 5 líneas promedio = **40 líneas**
- Computeds: 6 × 10 líneas promedio = **60 líneas**
- CRUD operations: 8 funciones × 50 líneas × 90% similitud = **360 líneas**
- Error handling: 50 líneas × 100% = **50 líneas**
- **Total**: **510 líneas duplicadas** (50% de useMovies.js)

---

## 🏗️ SOLUCIÓN PROPUESTA: Composable Base Genérico

### useLibraryItem.js (Composable Base)

```javascript
/**
 * Composable genérico para gestionar items de biblioteca (Books/Movies)
 * Elimina 80% de duplicación entre useBooks y useMovies
 */
import { ref, computed } from 'vue';
import { useAuth } from './useAuth';
import { useConfirmationModal } from './useConfirmationModal';
import Logger from '@/utils/logger';

export function useLibraryItem(config) {
  // Configuración (Books vs Movies)
  const {
    itemType,          // 'book' | 'movie'
    itemName,          // 'libro' | 'película'
    itemNamePlural,    // 'libros' | 'películas'
    fetchAction,       // 'get_library_items'
    addAction,         // 'add_book' | 'add_movie'
    deleteAction,      // 'delete_book' | 'delete_movie'
    updateRatingAction,
    updateStatusesAction,
    idField = 'isbn'   // 'isbn' | 'tmdbId'
  } = config;

  const { authenticatedApiCall } = useAuth();
  const { confirmDelete } = useConfirmationModal();

  // ==================== ESTADO GLOBAL ====================
  const items = ref([]);
  const allowedStatuses = ref([]);
  const userTags = ref([]);
  const isLoading = ref(false);
  const error = ref(null);
  const searchResults = ref([]);
  const isSearching = ref(false);

  // ==================== COMPUTEDS GENÉRICOS ====================
  const totalItems = computed(() => items.value.length);
  const hasItems = computed(() => items.value.length > 0);
  const itemsWithRating = computed(() => 
    items.value.filter(item => item.user_rating && item.user_rating > 0)
  );
  const itemsByStatus = computed(() => {
    const statusGroups = {};
    items.value.forEach(item => {
      if (item.userStatuses && Array.isArray(item.userStatuses)) {
        item.userStatuses.forEach(status => {
          if (!statusGroups[status]) statusGroups[status] = [];
          statusGroups[status].push(item);
        });
      }
    });
    return statusGroups;
  });
  const averageRating = computed(() => {
    const rated = itemsWithRating.value;
    if (rated.length === 0) return 0;
    const sum = rated.reduce((acc, item) => acc + item.user_rating, 0);
    return sum / rated.length;
  });

  // ==================== ERROR HANDLING GENÉRICO ====================
  const handleApiError = (err, defaultMessage) => {
    let errorMessage = defaultMessage;
    
    if (err.response) {
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
      errorMessage = 'Network error. Please check your connection.';
    } else if (err.message) {
      errorMessage = err.message;
    }
    
    error.value = errorMessage;
    Logger.error(`[useLibraryItem:${itemType}] Error:`, err);
    return errorMessage;
  };

  // ==================== CRUD GENÉRICO ====================
  
  const fetchItems = async () => {
    isLoading.value = true;
    error.value = null;
    
    try {
      const response = await authenticatedApiCall(fetchAction);
      
      if (response.data.status === 'success') {
        const data = response.data.data || {};
        const itemsArray = Array.isArray(data[itemType + 's']) ? data[itemType + 's'] : [];
        items.value = itemsArray.map(item => ({
          ...item,
          itemType: itemType
        }));
      } else {
        throw new Error(response.data.message || `Failed to fetch ${itemNamePlural}`);
      }
    } catch (err) {
      handleApiError(err, `Failed to fetch ${itemNamePlural}`);
      items.value = [];
    } finally {
      isLoading.value = false;
    }
  };

  const addItem = async (itemData, statuses = []) => {
    isLoading.value = true;
    error.value = null;

    try {
      const payload = {
        [itemType]: {
          ...itemData,
          userStatuses: statuses,
          itemType: itemType
        }
      };
      
      const response = await authenticatedApiCall(addAction, payload);

      if (response.data.status === 'success') {
        items.value.push(payload[itemType]);
        return { success: true };
      } else {
        throw new Error(response.data.message || `Failed to add ${itemName}`);
      }
    } catch (err) {
      const message = handleApiError(err, `Failed to add ${itemName}`);
      return { success: false, message };
    } finally {
      isLoading.value = false;
    }
  };

  const deleteItem = async (itemId, skipConfirmation = false) => {
    try {
      const item = items.value.find(i => i[idField] === itemId);
      const itemTitle = item ? item.title : `ID: ${itemId}`;

      if (!skipConfirmation) {
        const confirmed = await confirmDelete(
          itemTitle,
          `Se eliminará permanentemente este ${itemName}`
        );
        
        if (!confirmed) {
          return { success: false, cancelled: true };
        }
      }

      isLoading.value = true;
      error.value = null;

      const response = await authenticatedApiCall(deleteAction, {
        [idField]: itemId,
        itemType: itemType
      });

      if (response.data.status === 'success') {
        items.value = items.value.filter(item => item[idField] !== itemId);
        return { success: true };
      } else {
        throw new Error(response.data.message || `Failed to delete ${itemName}`);
      }
    } catch (err) {
      const message = handleApiError(err, `Failed to delete ${itemName}`);
      return { success: false, message };
    } finally {
      isLoading.value = false;
    }
  };

  const updateRating = async (itemId, rating) => {
    try {
      const response = await authenticatedApiCall(updateRatingAction, {
        [idField]: itemId,
        rating: rating
      });

      if (response.data.status === 'success') {
        const item = items.value.find(i => i[idField] === itemId);
        if (item) {
          item.user_rating = rating;
        }
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to update rating');
      }
    } catch (err) {
      const message = handleApiError(err, 'Failed to update rating');
      return { success: false, message };
    }
  };

  const updateStatuses = async (itemId, statuses) => {
    try {
      const response = await authenticatedApiCall(updateStatusesAction, {
        [idField]: itemId,
        statuses: statuses
      });

      if (response.data.status === 'success') {
        const item = items.value.find(i => i[idField] === itemId);
        if (item) {
          item.userStatuses = [...statuses];
        }
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to update statuses');
      }
    } catch (err) {
      const message = handleApiError(err, 'Failed to update statuses');
      return { success: false, message };
    }
  };

  return {
    // Estados
    items,
    allowedStatuses,
    userTags,
    isLoading,
    error,
    searchResults,
    isSearching,
    
    // Computeds
    totalItems,
    hasItems,
    itemsWithRating,
    itemsByStatus,
    averageRating,
    
    // CRUD
    fetchItems,
    addItem,
    deleteItem,
    updateRating,
    updateStatuses
  };
}
```

### Uso Refactorizado

```javascript
// ✅ NUEVO: useBooks.js (200 líneas vs 1,014 líneas)
import { useLibraryItem } from './useLibraryItem';

export function useBooks() {
  // Delegar CRUD genérico al composable base
  const baseLibrary = useLibraryItem({
    itemType: 'book',
    itemName: 'libro',
    itemNamePlural: 'libros',
    fetchAction: 'get_library_items',
    addAction: 'add_book',
    deleteAction: 'delete_book',
    updateRatingAction: 'update_book_rating',
    updateStatusesAction: 'update_book_user_statuses',
    idField: 'isbn'
  });

  // ⚠️ Solo funciones específicas de Books (no genéricas)
  const searchBookByISBN = async (isbn) => { /* 40 líneas */ };
  const searchBookByName = async (query) => { /* 40 líneas */ };
  const editUserBook = async (isbn, data, tags, notes) => { /* 60 líneas */ };
  
  return {
    // Heredar funcionalidad base
    ...baseLibrary,
    
    // Agregar funcionalidad específica
    searchBookByISBN,
    searchBookByName,
    editUserBook
  };
}

// ✅ NUEVO: useMovies.js (150 líneas vs 520 líneas)
export function useMovies() {
  const baseLibrary = useLibraryItem({
    itemType: 'movie',
    itemName: 'película',
    itemNamePlural: 'películas',
    fetchAction: 'get_library_items',
    addAction: 'add_movie',
    deleteAction: 'delete_movie',
    updateRatingAction: 'update_movie_rating',
    updateStatusesAction: 'update_movie_user_statuses',
    idField: 'tmdbId'
  });

  // Solo funciones específicas de Movies
  const searchMovies = async (query) => { /* 40 líneas */ };
  
  return {
    ...baseLibrary,
    searchMovies
  };
}

// ✅ NUEVO: useBookTags.js (150 líneas - extraído de useBooks)
export function useBookTags() {
  const getAllowedBookStatuses = async () => { /* ... */ };
  const getUserBookTags = async () => { /* ... */ };
  const createUserBookTag = async (name, color) => { /* ... */ };
  const getBookTags = async (isbn) => { /* ... */ };
  const updateBookTags = async (isbn, tags) => { /* ... */ };
  
  return {
    getAllowedBookStatuses,
    getUserBookTags,
    createUserBookTag,
    getBookTags,
    updateBookTags
  };
}

// ✅ YA EXISTE: useReadingSessions.js (430 líneas - ya separado correctamente)
```

---

## 📊 Comparación Antes/Después

### Métricas de Refactorización

| Métrica | Antes | Después | Mejora |
|---------|-------|---------|--------|
| **useBooks.js** | 1,014 líneas | 200 líneas | -80% |
| **useMovies.js** | 520 líneas | 150 líneas | -71% |
| **useLibraryItem.js** | 0 líneas | 350 líneas | +350L (nuevo) |
| **useBookTags.js** | 0 líneas | 150 líneas | +150L (nuevo) |
| **Total líneas** | 1,534 líneas | 850 líneas | **-45%** |
| **Código duplicado** | 510 líneas (33%) | 0 líneas (0%) | **-100%** |
| **Bounded contexts mezclados** | 4 en useBooks | 1 por archivo | **-75%** |
| **Funciones por composable** | 28 (useBooks) | 8 promedio | **-71%** |

### Beneficios de la Refactorización

✅ **Eliminación de duplicación**: 510 líneas duplicadas → 0  
✅ **Separation of Concerns**: 4 contextos → 1 por archivo  
✅ **Testability**: God Composable → Composables pequeños y testeables  
✅ **Reusabilidad**: Lógica genérica reutilizable para future entities  
✅ **Mantenibilidad**: 200 líneas vs 1,014 líneas por archivo  

---

## 🔗 Composables Relacionados

### useReadingSessions.js (430 líneas)

**Estado**: ✅ Ya separado correctamente de useBooks  
**Responsabilidad**: Gestión de sesiones de lectura  
**Problema**: ⚠️ useBooks aún expone 12 funciones de sesiones (deprecated)

**Acción requerida**:
```javascript
// ❌ ELIMINAR de useBooks.js (12 funciones):
createReadingSession, getActiveReadingSession, completeReadingSession,
updateReadingProgressWithSession, getReadingSessionHistory,
getSessionProgress, getUserActiveReadingSessions,
pauseReadingSession, resumeReadingSession, deleteReadingSession,
getBookReadingSummary, getDetailedProgressHistory

// ✅ Usar useReadingSessions.js en su lugar
import { useReadingSessions } from '@/composables/useReadingSessions';
const { createSession, getActiveSession, ... } = useReadingSessions();
```

### useConfirmationModal.js (344 líneas)

**Estado**: ✅ Bien diseñado  
**Patrón**: Singleton con estado reactivo global  
**Uso**: Modales de confirmación desde cualquier componente

```javascript
// ✅ BUEN PATRÓN: Estado global justificado (UI global)
const modalState = reactive({
  isVisible: false,
  isProcessing: false,
  config: {},
  resolvePromise: null,
  rejectPromise: null
});

export function useConfirmationModal() {
  const showConfirmation = (config) => {
    return new Promise((resolve) => {
      modalState.config = config;
      modalState.isVisible = true;
      modalState.resolvePromise = resolve;
    });
  };
  
  // Métodos de conveniencia
  const confirmDelete = (itemName, additionalMessage) => { /* ... */ };
  const confirmStatusChangeWithSession = (bookTitle, newStatus, sessionInfo) => { /* ... */ };
  
  return {
    modalState,
    showConfirmation,
    confirmDelete,
    confirmStatusChangeWithSession,
    handleConfirm,
    handleCancel,
    closeModal
  };
}
```

**Justificación del patrón singleton**:
- ✅ Solo puede haber 1 modal visible a la vez (UI constraint)
- ✅ Debe ser accesible desde cualquier componente (global state)
- ✅ Promise-based API para sincronizar confirmaciones
- ✅ No afecta testability (se puede mockear fácilmente)

---

## 📝 Conclusiones y Próximos Pasos

### Problemas Críticos Identificados

1. 🔴 **God Composable**: useBooks.js (1,014L, 28 funciones, 4 bounded contexts)
2. 🔴 **Duplicación masiva**: 510 líneas duplicadas entre Books/Movies (33%)
3. ⚠️ **Mixing de Bounded Contexts**: Books + Tags + Sessions en mismo archivo
4. ⚠️ **No hay composable base**: Lógica genérica duplicada en cada composable

### Plan de Refactorización (Prioridad)

**Semana 1-2**: Crear useLibraryItem.js (composable base genérico)
- Implementar CRUD genérico
- Implementar error handling centralizado
- Implementar estado compartido genérico
- Tests unitarios para composable base

**Semana 3**: Refactorizar useBooks.js
- Migrar a useLibraryItem base
- Extraer useBookTags.js
- Eliminar funciones de sesiones (usar useReadingSessions)
- Reducir de 1,014L → 200L

**Semana 4**: Refactorizar useMovies.js
- Migrar a useLibraryItem base
- Extraer useMovieTags.js
- Reducir de 520L → 150L

**Semana 5**: Testing y documentación
- Unit tests para todos los composables
- Integration tests para flujos completos
- Documentación de APIs
- Migration guide para componentes

**Resultado esperado**:
- ✅ -684 líneas de código (-45%)
- ✅ 0% duplicación (vs 33% actual)
- ✅ 1 bounded context por archivo
- ✅ 100% test coverage
- ✅ Reusable para futuras entities (Series, Games, etc.)
