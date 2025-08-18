# Composables para Gestión de Biblioteca

Este documento describe los composables de Vue 3 para la gestión de biblioteca del frontend. Los composables proporcionan lógica reutilizable y reactiva para diferentes aspectos de la aplicación.

## 📋 Tabla de Contenidos

1. [Composables de Autenticación](#composables-de-autenticación)
   - [useAuth](#useauth) - Composable principal de autenticación
   - [useGoogleAuth](#usegoogleauth) - Composable para Google OAuth
   - [usePermissions](#usepermissions) - Composable para permisos y rutas protegidas
2. [Composables de Biblioteca](#composables-de-biblioteca)
   - [useBooks](#usebooks) - Gestión de libros
   - [useMovies](#usemovies) - Gestión de películas
   - [useFileImport](#usefileimport) - Importación de archivos
   - [useSearch](#usesearch) - Sistema de búsqueda avanzado
3. [Ejemplos de Uso](#ejemplos-de-uso)
4. [Mejores Prácticas](#mejores-prácticas)

## 🔐 Composables de Autenticación

### useAuth

Composable principal que proporciona funcionalidades básicas de autenticación.

#### Estados Reactivos

```javascript
const {
  // Estados del usuario
  user,              // Object: Datos del usuario autenticado
  isAuthenticated,   // Boolean: Si el usuario está autenticado
  isLoggedIn,        // Boolean: Si el usuario está logueado
  userName,          // String: Nombre del usuario
  userEmail,         // String: Email del usuario
  userPicture,       // String: URL de la imagen del usuario
  
  // Estados de la aplicación
  isLoading,         // Boolean: Si hay operaciones en curso
  error,             // String: Mensaje de error actual
  csrfToken,         // String: Token CSRF para operaciones protegidas
  jwtToken,          // String: Token JWT
  lastLoginAttempt,  // Date: Fecha del último intento de login
} = useAuth();
```

#### Métodos

```javascript
// Inicializar autenticación
await initializeAuth();

// Login con token
await login(token);

// Logout
await logout();

// Verificar autenticación
await checkAuth();

// Refrescar datos del usuario
await refreshUserData();

// Obtener token válido
const token = await getValidToken();
```

#### Ejemplo de Uso

```vue
<template>
  <div>
    <div v-if="isAuthenticated">
      <p>Bienvenido, {{ userName }}!</p>
      <button @click="logout">Cerrar Sesión</button>
    </div>
    <div v-else>
      <button @click="login">Iniciar Sesión</button>
    </div>
    <div v-if="error" class="error">{{ error }}</div>
  </div>
</template>

<script setup>
import { useAuth } from '@/composables/useAuth';

const {
  isAuthenticated,
  userName,
  error,
  login,
  logout
} = useAuth();
</script>
```

### useGoogleAuth

Composable especializado para autenticación con Google OAuth.

#### Estados Reactivos

```javascript
const {
  isInitialized,     // Boolean: Si Google OAuth está inicializado
  isSigningIn,       // Boolean: Si hay un proceso de login en curso
  googleUser,        // Object: Datos del usuario de Google
  error             // String: Errores específicos de Google OAuth
} = useGoogleAuth();
```

#### Métodos

```javascript
// Inicializar Google OAuth
await initializeGoogleAuth();

// Login con Google
await signInWithGoogle();

// Logout de Google
await signOutFromGoogle();

// Verificar estado de Google Auth
const isSignedIn = await checkGoogleAuthStatus();
```

### usePermissions

Composable para gestión de permisos y rutas protegidas.

#### Estados Reactivos

```javascript
const {
  permissions,       // Array: Lista de permisos del usuario
  userRole,         // String: Rol del usuario actual
  canAccess         // Function: Función para verificar acceso
} = usePermissions();
```

#### Métodos

```javascript
// Verificar si el usuario puede acceder a una ruta
const hasAccess = canAccess('/admin');

// Verificar permiso específico
const canEdit = hasPermission('edit_books');

// Verificar rol
const isAdmin = hasRole('admin');
```

## 📚 Composables de Biblioteca

### useBooks

Composable para gestión completa de libros.

#### Estados Reactivos

```javascript
const {
  books,             // Array: Lista de libros del usuario
  isLoading,         // Boolean: Estado de carga
  error,             // String: Mensajes de error
  allowedStatuses    // Array: Estados permitidos para libros
} = useBooks();
```

#### Métodos

```javascript
// Cargar libros del usuario
await loadUserBooks();

// Agregar libro a la biblioteca
const result = await addBook(bookData, statuses);

// Eliminar libro
const success = await deleteBook(isbn);

// Actualizar estados de un libro
await updateBookStatuses(isbn, newStatuses);

// Actualizar calificación de un libro
await updateBookRating(isbn, rating);

// Buscar libros
const searchResults = await searchBooks(query);
```

#### Ejemplo de Uso

```vue
<template>
  <div>
    <h2>Mi Biblioteca</h2>
    <div v-if="isLoading">Cargando libros...</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <div v-else>
      <div v-for="book in books" :key="book.isbn" class="book-item">
        <h3>{{ book.title }}</h3>
        <p>{{ book.author }}</p>
        <button @click="removeBook(book.isbn)">Eliminar</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useBooks } from '@/composables/useBooks';

const {
  books,
  isLoading,
  error,
  loadUserBooks,
  deleteBook
} = useBooks();

const removeBook = async (isbn) => {
  await deleteBook(isbn);
  await loadUserBooks(); // Recargar lista
};

onMounted(() => {
  loadUserBooks();
});
</script>
```

### useMovies

Composable para gestión completa de películas.

#### Estados Reactivos

```javascript
const {
  movies,            // Array: Lista de películas del usuario
  isLoading,         // Boolean: Estado de carga
  error,             // String: Mensajes de error
  allowedStatuses    // Array: Estados permitidos para películas
} = useMovies();
```

#### Métodos

```javascript
// Cargar películas del usuario
await loadUserMovies();

// Agregar película a la biblioteca
const result = await addMovie(movieData, statuses);

// Eliminar película
const success = await deleteMovie(tmdbId);

// Actualizar estados de una película
await updateMovieStatuses(tmdbId, newStatuses);

// Actualizar calificación de una película
await updateMovieRating(tmdbId, rating);

// Buscar películas
const searchResults = await searchMovies(query);
```

### useFileImport

Composable para importación de archivos (CSV, JSON, etc.).

#### Estados Reactivos

```javascript
const {
  isImporting,       // Boolean: Si hay una importación en curso
  importProgress,    // Number: Progreso de importación (0-100)
  importResults,     // Object: Resultados de la importación
  error             // String: Errores de importación
} = useFileImport();
```

#### Métodos

```javascript
// Importar archivo
const result = await importFile(file, options);

// Validar archivo antes de importar
const isValid = await validateFile(file);

// Obtener preview de datos del archivo
const preview = await getFilePreview(file);
```

### useSearch

Composable para sistema de búsqueda avanzado con debouncing y caché.

#### Estados Reactivos

```javascript
const {
  query,             // String: Consulta de búsqueda actual
  results,           // Array: Resultados de búsqueda
  isSearching,       // Boolean: Si hay una búsqueda en curso
  error,             // String: Errores de búsqueda
  searchHistory,     // Array: Historial de búsquedas
  hasQuery,          // Boolean: Si hay una consulta válida
  hasResults,        // Boolean: Si hay resultados
  canSearch          // Boolean: Si se puede buscar
} = useSearch(options);
```

#### Configuración

```javascript
const searchOptions = {
  debounceDelay: 300,        // Delay para debouncing (ms)
  minQueryLength: 2,         // Longitud mínima de consulta
  maxCacheSize: 50,          // Tamaño máximo de caché
  cacheExpiration: 300000,   // Expiración de caché (ms)
  historyMaxSize: 20         // Tamaño máximo del historial
};

const search = useSearch(searchOptions);
```

#### Métodos

```javascript
// Configurar función de búsqueda
setSearchFunction(async (query) => {
  return await apiCall(query);
});

// Búsqueda inmediata
await searchImmediate(query);

// Búsqueda con debouncing
searchDebounced(query);

// Navegación en resultados
navigateResults('up'|'down');

// Seleccionar resultado
const result = selectResult(index);

// Limpiar resultados
clearResults();

// Limpiar historial
clearHistory();

// Limpiar caché
clearCache();
```

#### Ejemplo de Uso

```vue
<template>
  <div>
    <input
      v-model="query"
      type="text"
      placeholder="Buscar libros..."
      @keydown.arrow-down="navigateResults('down')"
      @keydown.arrow-up="navigateResults('up')"
      @keydown.enter="selectCurrentResult"
    />
    
    <div v-if="isSearching">Buscando...</div>
    
    <div v-if="hasResults" class="results">
      <div
        v-for="(result, index) in results"
        :key="result.id"
        :class="{ active: selectedResultIndex === index }"
        @click="selectResult(index)"
      >
        {{ result.title }}
      </div>
    </div>
    
    <div v-if="error" class="error">{{ error }}</div>
  </div>
</template>

<script setup>
import { useSearch } from '@/composables/useSearch';
import { bookApi } from '@/services/api';

const {
  query,
  results,
  isSearching,
  error,
  hasResults,
  selectedResultIndex,
  setSearchFunction,
  navigateResults,
  selectResult,
  getSelectedResult
} = useSearch({
  debounceDelay: 500,
  minQueryLength: 3
});

// Configurar función de búsqueda
setSearchFunction(async (searchQuery) => {
  return await bookApi.search(searchQuery);
});

const selectCurrentResult = () => {
  const selected = getSelectedResult();
  if (selected) {
    // Hacer algo con el resultado seleccionado
    console.log('Selected:', selected);
  }
};
</script>
```

## 📝 Ejemplos de Uso

### Ejemplo Completo: Página de Búsqueda de Libros

```vue
<template>
  <div class="book-search-page">
    <!-- Búsqueda -->
    <div class="search-section">
      <input
        v-model="search.query.value"
        type="text"
        placeholder="Buscar libros..."
        class="search-input"
      />
      <div v-if="search.isSearching.value">Buscando...</div>
    </div>

    <!-- Resultados de búsqueda -->
    <div v-if="search.hasResults.value" class="search-results">
      <h3>Resultados de búsqueda</h3>
      <div
        v-for="book in search.results.value"
        :key="book.isbn"
        class="search-result"
      >
        <h4>{{ book.title }}</h4>
        <p>{{ book.author }}</p>
        <button
          @click="addToLibrary(book)"
          :disabled="books.isLoading.value"
        >
          Agregar a mi biblioteca
        </button>
      </div>
    </div>

    <!-- Mi biblioteca -->
    <div class="library-section">
      <h3>Mi Biblioteca</h3>
      <div v-if="books.isLoading.value">Cargando biblioteca...</div>
      <div v-else-if="books.books.value.length === 0">
        No tienes libros en tu biblioteca
      </div>
      <div v-else>
        <div
          v-for="book in books.books.value"
          :key="book.isbn"
          class="library-book"
        >
          <h4>{{ book.title }}</h4>
          <p>{{ book.author }}</p>
          <p>Estados: {{ book.userStatuses?.join(', ') }}</p>
          <button @click="removeFromLibrary(book.isbn)">
            Eliminar
          </button>
        </div>
      </div>
    </div>

    <!-- Mensajes de error -->
    <div v-if="search.error.value || books.error.value" class="error">
      {{ search.error.value || books.error.value }}
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useSearch } from '@/composables/useSearch';
import { useBooks } from '@/composables/useBooks';
import { bookApi } from '@/services/api';

// Composables
const search = useSearch({
  debounceDelay: 300,
  minQueryLength: 2
});

const books = useBooks();

// Configurar búsqueda
search.setSearchFunction(async (query) => {
  return await bookApi.search(query);
});

// Métodos
const addToLibrary = async (book) => {
  const result = await books.addBook(book, ['owned']);
  if (result.success) {
    alert('Libro agregado a tu biblioteca');
  }
};

const removeFromLibrary = async (isbn) => {
  if (confirm('¿Eliminar este libro de tu biblioteca?')) {
    await books.deleteBook(isbn);
  }
};

// Inicialización
onMounted(() => {
  books.loadUserBooks();
});
</script>

<style scoped>
.book-search-page {
  max-width: 800px;
  margin: 0 auto;
  padding: 20px;
}

.search-input {
  width: 100%;
  padding: 12px;
  font-size: 16px;
  border: 1px solid #ddd;
  border-radius: 4px;
  margin-bottom: 20px;
}

.search-result,
.library-book {
  border: 1px solid #eee;
  padding: 15px;
  margin-bottom: 10px;
  border-radius: 4px;
}

.error {
  color: red;
  padding: 10px;
  background: #ffebee;
  border-radius: 4px;
  margin-top: 10px;
}
</style>
```

## 📋 Mejores Prácticas

### 1. Estructura y Organización

```javascript
// ✅ Correcto: Usar destructuring para obtener solo lo necesario
const { isAuthenticated, userName, logout } = useAuth();

// ❌ Incorrecto: Importar todo el composable
const auth = useAuth();
```

### 2. Manejo de Estados de Carga

```javascript
// ✅ Correcto: Verificar estados de carga
const { isLoading, error, data } = useBooks();

// En el template
if (isLoading.value) {
  // Mostrar spinner
} else if (error.value) {
  // Mostrar error
} else {
  // Mostrar datos
}
```

### 3. Manejo de Errores

```javascript
// ✅ Correcto: Manejar errores apropiadamente
const addBookToLibrary = async (book) => {
  try {
    const result = await books.addBook(book, ['owned']);
    if (result.success) {
      showSuccessMessage('Libro agregado exitosamente');
    } else {
      showErrorMessage(result.message);
    }
  } catch (error) {
    showErrorMessage('Error inesperado al agregar libro');
    console.error('Error:', error);
  }
};
```

### 4. Optimización de Rendimiento

```javascript
// ✅ Correcto: Usar debouncing para búsquedas
const search = useSearch({
  debounceDelay: 300  // Esperar 300ms antes de buscar
});

// ✅ Correcto: Cargar datos solo cuando sea necesario
onMounted(() => {
  if (isAuthenticated.value) {
    loadUserBooks();
  }
});

// ✅ Correcto: Limpiar recursos al desmontar
onUnmounted(() => {
  search.cleanup();
});
```

### 5. Composición de Composables

```javascript
// ✅ Correcto: Combinar múltiples composables
const setup = () => {
  const auth = useAuth();
  const books = useBooks();
  const search = useSearch();
  
  // Lógica que combina los composables
  const searchAndAdd = async (query) => {
    if (!auth.isAuthenticated.value) {
      router.push('/login');
      return;
    }
    
    search.setSearchFunction(bookApi.search);
    await search.search(query);
  };
  
  return {
    ...auth,
    ...books,
    search,
    searchAndAdd
  };
};
```

### 6. Testing

```javascript
// Ejemplo de test para composable
import { describe, it, expect, vi } from 'vitest';
import { useBooks } from '@/composables/useBooks';

describe('useBooks', () => {
  it('should load user books', async () => {
    const { books, loadUserBooks } = useBooks();
    
    // Mock API
    vi.mock('@/services/api', () => ({
      bookApi: {
        getUserBooks: vi.fn().mockResolvedValue([
          { isbn: '123', title: 'Test Book' }
        ])
      }
    }));
    
    await loadUserBooks();
    
    expect(books.value).toHaveLength(1);
    expect(books.value[0].title).toBe('Test Book');
  });
});
```

## 🔄 Migración de Stores a Composables

Si estás migrando de Pinia stores a composables:

### Antes (Pinia Store)

```javascript
// stores/auth.js
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isAuthenticated: false
  }),
  actions: {
    async login(token) {
      // lógica de login
    }
  }
});

// En componente
const authStore = useAuthStore();
authStore.login(token);
```

### Después (Composable)

```javascript
// composables/useAuth.js
export function useAuth() {
  const user = ref(null);
  const isAuthenticated = computed(() => !!user.value);
  
  const login = async (token) => {
    // lógica de login
  };
  
  return {
    user,
    isAuthenticated,
    login
  };
}

// En componente
const { user, isAuthenticated, login } = useAuth();
await login(token);
```

### Pasos para Migración

1. **Identificar estado:**
   ```javascript
   // Store state → Composable refs
   const state = reactive({ user: null });
   // ↓
   const user = ref(null);
   ```

2. **Convertir getters:**
   ```javascript
   // Store getters → Composable computed
   getters: { isLoggedIn: (state) => !!state.user }
   // ↓
   const isLoggedIn = computed(() => !!user.value);
   ```

3. **Convertir actions:**
   ```javascript
   // Store actions → Composable functions
   actions: { async login() { ... } }
   // ↓
   const login = async () => { ... };
   ```

4. **Actualizar imports:**
   ```javascript
   // Antes
   import { useAuthStore } from '@/stores/auth';
   const authStore = useAuthStore();
   
   // Después
   import { useAuth } from '@/composables/useAuth';
   const { login } = useAuth();
   ```

5. **Actualizar template:**
   ```vue
   <!-- Antes -->
   <div v-if="authStore.isAuthenticated">
   
   <!-- Después -->
   <div v-if="isAuthenticated">
   ```

## 📁 Estructura de Archivos

```
src/
├── composables/
│   ├── index.js           # Re-exports y useAuthSystem
│   ├── useAuth.js         # Composable principal
│   ├── useGoogleAuth.js   # Composable de Google OAuth
│   ├── usePermissions.js  # Composable de permisos
│   ├── useBooks.js        # Gestión de libros
│   ├── useMovies.js       # Gestión de películas
│   ├── useFileImport.js   # Importación de archivos
│   └── useSearch.js       # Sistema de búsqueda
└── components/
    └── examples/
        └── ComposableExamples.vue # Ejemplos de uso
```

## 🚀 Beneficios

1. **Reutilización:** Los composables pueden ser usados en cualquier componente
2. **Testabilidad:** Cada composable puede ser testeado independientemente
3. **Separación de responsabilidades:** Cada composable tiene una función específica
4. **Composición:** Pueden combinarse según las necesidades del componente
5. **TypeScript Ready:** Preparados para migración a TypeScript
6. **Mantenibilidad:** Código más limpio y fácil de mantener
7. **Tree Shaking:** Solo se importa lo que se necesita
8. **SSR Friendly:** Compatible con renderizado del lado del servidor

---

*Documentación actualizada el 18 de agosto de 2025*
