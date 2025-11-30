# 🗄️ Análisis Arquitectónico: Frontend State Management

**Fecha**: 30 de noviembre de 2025  
**Sistema actual**: Pinia + Composables Singleton  
**Problema**: Estado duplicado en dos sistemas

---

## 📊 Estado Actual

### Sistema Dual de Estado

```
Frontend State Management:

1️⃣ Pinia Store (store/auth.js - 192 líneas)
   ├── user (autenticación)
   ├── isAuthenticated
   ├── csrfToken
   └── apiCall() method

2️⃣ Composables Singleton (17 archivos - ~4,200 líneas)
   ├── useBooks: books, allowedStatuses, userTags, isLoading, error
   ├── useMovies: movies, allowedStatuses, userTags, isLoading, error
   ├── useReadingSessions: activeSession, sessionHistory
   └── ... (14 composables más con estado global)
```

---

## 🔴 PROBLEMA: Dos Sistemas de Estado

### 1. Pinia Store (Solo Auth)

```javascript
// ✅ store/auth.js - ÚNICO store Pinia
import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isAuthenticated: false,
    csrfToken: null
  }),

  getters: {
    isLoggedIn: (state) => state.isAuthenticated,
    currentUser: (state) => state.user,
    getCSRFToken: (state) => state.csrfToken
  },

  actions: {
    async initializeAuth() {
      const response = await this.apiCall('check_auth');
      if (response.data.status === 'success') {
        this.user = response.data.user;
        this.isAuthenticated = true;
        this.csrfToken = response.data.csrfToken;
      }
    },

    async login(googleToken) {
      const response = await this.apiCall('login', { token: googleToken });
      if (response.data.status === 'success') {
        this.user = response.data.user;
        this.isAuthenticated = true;
        this.csrfToken = response.data.csrfToken;
      }
    },

    async logout() {
      await this.apiCall('logout');
      this.user = null;
      this.isAuthenticated = false;
      this.csrfToken = null;
    },

    // ⚠️ MÉTODO CRÍTICO: apiCall usado por todos los composables
    async apiCall(action, data = {}) {
      const backendApiUrl = process.env.VUE_APP_API_URL || 'http://localhost:8888';
      
      const requestData = {
        action: action,
        inputData: data
      };

      const config = {
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': this.csrfToken || ''
        },
        withCredentials: true
      };

      return await axios.post(backendApiUrl, requestData, config);
    },

    async authenticatedApiCall(action, data = {}) {
      if (!this.isAuthenticated) {
        throw new Error('User not authenticated');
      }
      return await this.apiCall(action, data);
    }
  }
});
```

**Observaciones**:
- ✅ **Único store Pinia** en toda la aplicación
- ✅ **Bien estructurado** (state, getters, actions)
- ✅ **CSRF token management** centralizado
- ⚠️ **apiCall() usado por composables** (acoplamiento)

---

### 2. Composables Singleton (Estado Global sin Pinia)

```javascript
// ❌ useBooks.js - Estado global fuera de Pinia
const books = ref([]);              // Estado global nivel módulo
const allowedStatuses = ref([]);
const userTags = ref([]);
const isLoading = ref(false);
const error = ref(null);

export function useBooks() {
  // Todos los componentes comparten el mismo estado
  return {
    books,           // Ref compartido
    allowedStatuses,
    userTags,
    isLoading,
    error,
    fetchBooks,
    addBook,
    // ...
  };
}

// ❌ useMovies.js - DUPLICA el patrón
const movies = ref([]);             // ❌ Otro estado global
const allowedStatuses = ref([]);    // ❌ Duplicado
const userTags = ref([]);           // ❌ Duplicado
const isLoading = ref(false);       // ❌ Duplicado
const error = ref(null);            // ❌ Duplicado

export function useMovies() {
  return {
    movies,
    allowedStatuses,
    userTags,
    isLoading,
    error,
    fetchMovies,
    addMovie,
    // ...
  };
}
```

**Problemas**:
- ❌ **Estado global sin Pinia**: Refs a nivel de módulo
- ❌ **No hay DevTools support**: Pinia DevTools no ve estos estados
- ❌ **Dificulta SSR**: Estado compartido entre requests
- ❌ **Duplicación de patrón**: Books, Movies, Sessions, Progress (mismo patrón 4 veces)
- ❌ **No testable**: Estado persiste entre tests

---

## 🔍 Análisis de Dependencias

### Flujo de Estado Actual

```
Component
   ↓
useBooks() composable
   ↓
const books = ref([])  ← Estado global (fuera de Pinia)
   ↓
authenticatedApiCall()
   ↓
useAuthStore.apiCall()  ← Pinia store
   ↓
Backend API
```

**Observaciones**:
- ⚠️ **Mezcla de sistemas**: Composables usan Pinia para API, pero estado propio para datos
- ⚠️ **Confusión**: ¿Dónde poner nuevo estado? ¿Pinia o composable?
- ⚠️ **No hay convención clara**

---

## ✅ SOLUCIÓN PROPUESTA: Migrar a Pinia Completo

### Arquitectura Target

```
stores/
├── auth.js          ✅ (Ya existe)
├── books.js         🆕 Migrar desde useBooks
├── movies.js        🆕 Migrar desde useMovies
├── sessions.js      🆕 Migrar desde useReadingSessions
└── ui.js            🆕 Estado UI global (modales, notificaciones)

composables/
├── useAuth.js       ♻️ Wrapper de store/auth (mantener)
├── useBooks.js      ♻️ Helpers sin estado (mantener lógica, mover estado)
├── useMovies.js     ♻️ Helpers sin estado
└── ... (utilities sin estado)
```

---

### Migración: useBooks → Pinia Store

**ANTES (useBooks.js - Composable con estado)**:
```javascript
// ❌ Estado global
const books = ref([]);
const isLoading = ref(false);

export function useBooks() {
  const { authenticatedApiCall } = useAuth();
  
  const fetchBooks = async () => {
    isLoading.value = true;
    const response = await authenticatedApiCall('get_library_items');
    books.value = response.data.data.books;
    isLoading.value = false;
  };

  return { books, isLoading, fetchBooks };
}
```

**DESPUÉS (stores/books.js - Pinia Store)**:
```javascript
// ✅ Pinia Store
import { defineStore } from 'pinia';
import { useAuthStore } from './auth';

export const useBooksStore = defineStore('books', {
  state: () => ({
    books: [],
    allowedStatuses: [],
    userTags: [],
    isLoading: false,
    error: null,
    searchResults: [],
    isSearching: false
  }),

  getters: {
    totalBooks: (state) => state.books.length,
    hasBooks: (state) => state.books.length > 0,
    booksWithRating: (state) => 
      state.books.filter(b => b.user_rating && b.user_rating > 0),
    booksByStatus: (state) => {
      const statusGroups = {};
      state.books.forEach(book => {
        if (book.userStatuses && Array.isArray(book.userStatuses)) {
          book.userStatuses.forEach(status => {
            if (!statusGroups[status]) statusGroups[status] = [];
            statusGroups[status].push(book);
          });
        }
      });
      return statusGroups;
    },
    averageRating: (state) => {
      const rated = state.books.filter(b => b.user_rating > 0);
      if (rated.length === 0) return 0;
      return rated.reduce((acc, b) => acc + b.user_rating, 0) / rated.length;
    }
  },

  actions: {
    async fetchBooks() {
      this.isLoading = true;
      this.error = null;
      
      try {
        const authStore = useAuthStore();
        const response = await authStore.authenticatedApiCall('get_library_items');
        
        if (response.data.status === 'success') {
          const data = response.data.data || {};
          this.books = Array.isArray(data.books) ? data.books : [];
        } else {
          throw new Error(response.data.message || 'Failed to fetch books');
        }
      } catch (err) {
        this.error = err.message || 'Failed to fetch books';
        this.books = [];
      } finally {
        this.isLoading = false;
      }
    },

    async addBook(bookData, statuses = []) {
      this.isLoading = true;
      this.error = null;

      try {
        const authStore = useAuthStore();
        const payload = { book: { ...bookData, userStatuses: statuses } };
        
        const response = await authStore.authenticatedApiCall('add_book', payload);

        if (response.data.status === 'success') {
          this.books.push(payload.book);
          return { success: true };
        } else {
          throw new Error(response.data.message || 'Failed to add book');
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to add book');
        return { success: false, message: this.error };
      } finally {
        this.isLoading = false;
      }
    },

    async deleteBook(isbn) {
      this.isLoading = true;
      
      try {
        const authStore = useAuthStore();
        const response = await authStore.authenticatedApiCall('delete_book', {
          isbn,
          itemType: 'book'
        });

        if (response.data.status === 'success') {
          this.books = this.books.filter(book => book.isbn !== isbn);
          return { success: true };
        } else {
          throw new Error(response.data.message);
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to delete book');
        return { success: false, message: this.error };
      } finally {
        this.isLoading = false;
      }
    },

    _handleError(err, defaultMessage) {
      if (err.response?.status === 401) {
        return 'Authentication required. Please login again.';
      } else if (err.response?.status === 403) {
        return 'Invalid CSRF token. Please refresh the page.';
      } else if (err.response?.data?.message) {
        return err.response.data.message;
      } else if (err.request) {
        return 'Network error. Please check your connection.';
      }
      return err.message || defaultMessage;
    }
  }
});
```

**NUEVO (composables/useBooks.js - Solo helpers)**:
```javascript
// ✅ Composable sin estado (solo helpers)
import { storeToRefs } from 'pinia';
import { useBooksStore } from '@/stores/books';

export function useBooks() {
  const booksStore = useBooksStore();
  
  // Exponer estado reactivo con storeToRefs
  const {
    books,
    allowedStatuses,
    userTags,
    isLoading,
    error,
    totalBooks,
    hasBooks,
    booksWithRating,
    booksByStatus,
    averageRating
  } = storeToRefs(booksStore);

  // Exponer actions
  const {
    fetchBooks,
    addBook,
    deleteBook,
    updateBookRating,
    updateBookStatuses
  } = booksStore;

  // ✅ Helpers específicos que NO son estado (pueden quedar aquí)
  const validateBookData = (bookData) => {
    if (!bookData.isbn) throw new Error('ISBN is required');
    if (!bookData.title) throw new Error('Title is required');
    // ... validaciones
  };

  const transformGoogleBookData = (googleBook) => {
    return {
      isbn: googleBook.id,
      title: googleBook.volumeInfo.title,
      // ... transformación
    };
  };

  return {
    // Estado (reactivo via storeToRefs)
    books,
    allowedStatuses,
    userTags,
    isLoading,
    error,
    
    // Getters
    totalBooks,
    hasBooks,
    booksWithRating,
    booksByStatus,
    averageRating,
    
    // Actions
    fetchBooks,
    addBook,
    deleteBook,
    updateBookRating,
    updateBookStatuses,
    
    // Helpers (sin estado)
    validateBookData,
    transformGoogleBookData
  };
}
```

---

## 📊 Comparación Antes/Después

| Aspecto | ANTES (Composables) | DESPUÉS (Pinia) | Mejora |
|---------|-------------------|-----------------|--------|
| **Estado global** | Refs nivel módulo | Pinia state | ✅ Centralizado |
| **DevTools** | ❌ No visible | ✅ Visible en Vue DevTools | ✅ Debugging |
| **SSR Support** | ❌ No compatible | ✅ Compatible | ✅ Futuro |
| **Time-travel debugging** | ❌ No | ✅ Sí | ✅ Debugging |
| **Persistencia** | ⚠️ Manual (localStorage) | ✅ Plugin pinia-plugin-persistedstate | ✅ Fácil |
| **Testing** | ⚠️ Difícil (estado compartido) | ✅ Fácil (store mockeable) | ✅ Tests |
| **Code splitting** | ❌ Todos los composables se cargan | ✅ Stores lazy-loaded | ✅ Performance |
| **Convención clara** | ❌ No hay | ✅ Sí (siempre Pinia) | ✅ Consistencia |

---

## 🎯 Plan de Migración

### Fase 1: Crear Stores Base (Semana 1-2)

```bash
# Crear stores/books.js
# Migrar estado de useBooks → Pinia
# Mantener useBooks como wrapper (backward compatibility)

# Crear stores/movies.js
# Migrar estado de useMovies → Pinia
# Mantener useMovies como wrapper

# Crear stores/sessions.js
# Migrar estado de useReadingSessions → Pinia
```

### Fase 2: Migrar Componentes (Semana 3)

```javascript
// Componente ANTES
import { useBooks } from '@/composables/useBooks';
const { books, fetchBooks } = useBooks();

// Componente DESPUÉS (opción 1 - directo)
import { useBooksStore } from '@/stores/books';
import { storeToRefs } from 'pinia';
const booksStore = useBooksStore();
const { books } = storeToRefs(booksStore);
const { fetchBooks } = booksStore;

// Componente DESPUÉS (opción 2 - wrapper)
import { useBooks } from '@/composables/useBooks';
const { books, fetchBooks } = useBooks(); // ✅ Mismo API, diferente implementación
```

### Fase 3: Deprecar Composables con Estado (Semana 4)

- Eliminar estado global de composables
- Dejar solo helpers y transformadores
- Actualizar documentación
- Migration guide

---

## 📝 Conclusión

**Problemas actuales**:
❌ Estado duplicado en 2 sistemas (Pinia + Composables)  
❌ No hay convención clara sobre dónde poner estado  
❌ DevTools no ve estado de composables  
❌ Dificulta testing y SSR  

**Solución propuesta**:
✅ **TODO el estado en Pinia stores**  
✅ **Composables solo para helpers/utilities sin estado**  
✅ **DevTools completo** (time-travel, state inspection)  
✅ **Testing simplificado** (stores mockeables)  
✅ **SSR ready** para futuro  

**Esfuerzo estimado**: 4 semanas  
**Reducción de complejidad**: 30% menos código duplicado
