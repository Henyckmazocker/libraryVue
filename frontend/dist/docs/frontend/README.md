# Frontend Documentation - Library Vue

## Overview

El frontend de Library Vue está construido con Vue.js 3, utilizando Composition API, Pinia para gestión de estado, y Vue Router para navegación. La arquitectura está diseñada para ser modular, reutilizable y fácil de mantener.

## Technology Stack

- **Vue.js 3**: Framework principal con Composition API
- **Pinia**: Gestión de estado centralizada
- **Vue Router**: Routing client-side
- **Vite**: Build tool y dev server
- **Axios**: Cliente HTTP para API calls
- **Tailwind CSS**: Framework CSS utilitario
- **TypeScript**: Gradual adoption para type safety

## Project Structure

```
frontend/
├── src/
│   ├── components/           # Componentes reutilizables
│   │   ├── ui/              # Componentes básicos de UI
│   │   │   ├── BaseButton.vue
│   │   │   ├── BaseInput.vue
│   │   │   ├── BaseModal.vue
│   │   │   └── LoadingSpinner.vue
│   │   ├── forms/           # Componentes de formularios
│   │   │   ├── BookForm.vue
│   │   │   ├── MovieForm.vue
│   │   │   └── SearchForm.vue
│   │   ├── layout/          # Componentes de layout
│   │   │   ├── AppHeader.vue
│   │   │   ├── AppSidebar.vue
│   │   │   ├── AppFooter.vue
│   │   │   └── MainLayout.vue
│   │   └── library/         # Componentes específicos de biblioteca
│   │       ├── BookCard.vue
│   │       ├── MovieCard.vue
│   │       ├── LibraryStats.vue
│   │       └── ImportModal.vue
│   ├── composables/         # Composition functions
│   │   ├── useAuth.js
│   │   ├── useBooks.js
│   │   ├── useMovies.js
│   │   ├── useSearch.js
│   │   └── useNotifications.js
│   ├── router/              # Vue Router configuration
│   │   ├── index.js
│   │   ├── guards.js
│   │   └── routes.js
│   ├── services/            # API service layer
│   │   ├── api.js
│   │   ├── authService.js
│   │   ├── bookService.js
│   │   ├── movieService.js
│   │   └── libraryService.js
│   ├── stores/              # Pinia stores
│   │   ├── auth.js
│   │   ├── books.js
│   │   ├── movies.js
│   │   ├── library.js
│   │   └── notifications.js
│   ├── types/               # TypeScript types
│   │   ├── api.ts
│   │   ├── book.ts
│   │   ├── movie.ts
│   │   └── user.ts
│   ├── utils/               # Utility functions
│   │   ├── dateUtils.js
│   │   ├── validators.js
│   │   ├── formatters.js
│   │   └── constants.js
│   └── views/               # Page components
│       ├── HomeView.vue
│       ├── LoginView.vue
│       ├── BooksView.vue
│       ├── MoviesView.vue
│       ├── LibraryView.vue
│       └── SettingsView.vue
├── public/                  # Static assets
│   ├── favicon.ico
│   ├── index.html
│   └── help.html
└── tests/                   # Test files
    ├── unit/
    └── integration/
```

## Component Architecture

### Composition API Usage

#### Basic Component Structure

```vue
<template>
  <div class="book-card">
    <img :src="book.cover_image" :alt="book.title" />
    <div class="book-info">
      <h3>{{ book.title }}</h3>
      <p>{{ book.author }}</p>
      <div class="book-actions">
        <BaseButton @click="updateStatus" :loading="updating">
          {{ statusText }}
        </BaseButton>
        <BaseButton @click="showDetails" variant="secondary">
          Details
        </BaseButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useBooks } from '@/composables/useBooks'
import BaseButton from '@/components/ui/BaseButton.vue'

// Props
const props = defineProps({
  book: {
    type: Object,
    required: true
  }
})

// Emits
const emit = defineEmits(['updated', 'details'])

// Composables
const { updateBookStatus } = useBooks()

// Reactive state
const updating = ref(false)

// Computed properties
const statusText = computed(() => {
  return props.book.status === 'read' ? 'Mark as Unread' : 'Mark as Read'
})

// Methods
const updateStatus = async () => {
  updating.value = true
  try {
    const newStatus = props.book.status === 'read' ? 'to-read' : 'read'
    await updateBookStatus(props.book.id, { status: newStatus })
    emit('updated')
  } catch (error) {
    console.error('Error updating status:', error)
  } finally {
    updating.value = false
  }
}

const showDetails = () => {
  emit('details', props.book)
}
</script>

<style scoped>
.book-card {
  @apply border rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow;
}

.book-info h3 {
  @apply font-semibold text-lg mb-1;
}

.book-actions {
  @apply flex gap-2 mt-3;
}
</style>
```

### Composables (Composition Functions)

#### useAuth Composable

```javascript
// composables/useAuth.js
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/authService'

export function useAuth() {
  const authStore = useAuthStore()
  const router = useRouter()
  const loading = ref(false)
  const error = ref(null)

  // Computed properties
  const user = computed(() => authStore.user)
  const isAuthenticated = computed(() => authStore.isAuthenticated)

  // Login method
  const login = async (credentials) => {
    loading.value = true
    error.value = null

    try {
      const response = await authService.login(credentials)
      authStore.setUser(response.data.user)
      authStore.setAuthenticated(true)
      
      // Redirect to intended route or home
      const redirect = router.currentRoute.value.query.redirect || '/'
      router.push(redirect)
      
      return response
    } catch (err) {
      error.value = err.response?.data?.error || 'Login failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Google login method
  const loginWithGoogle = async (googleToken) => {
    loading.value = true
    error.value = null

    try {
      const response = await authService.googleLogin(googleToken)
      authStore.setUser(response.data.user)
      authStore.setAuthenticated(true)
      
      router.push('/')
      return response
    } catch (err) {
      error.value = err.response?.data?.error || 'Google login failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Logout method
  const logout = async () => {
    try {
      await authService.logout()
      authStore.logout()
      router.push('/login')
    } catch (err) {
      // Even if logout fails, clear local state
      authStore.logout()
      router.push('/login')
    }
  }

  // Check authentication status
  const checkAuth = async () => {
    try {
      const response = await authService.checkSession()
      if (response.data.authenticated) {
        authStore.setUser(response.data.user)
        authStore.setAuthenticated(true)
      } else {
        authStore.logout()
      }
    } catch (err) {
      authStore.logout()
    }
  }

  return {
    // State
    user,
    isAuthenticated,
    loading: computed(() => loading.value),
    error: computed(() => error.value),

    // Methods
    login,
    loginWithGoogle,
    logout,
    checkAuth
  }
}
```

#### useBooks Composable

```javascript
// composables/useBooks.js
import { ref, computed } from 'vue'
import { useBooksStore } from '@/stores/books'
import { bookService } from '@/services/bookService'

export function useBooks() {
  const booksStore = useBooksStore()
  const loading = ref(false)
  const error = ref(null)

  // Computed properties
  const books = computed(() => booksStore.books)
  const totalBooks = computed(() => booksStore.totalBooks)
  const currentPage = computed(() => booksStore.currentPage)
  const hasMore = computed(() => booksStore.hasMore)

  // Fetch books with pagination
  const fetchBooks = async (params = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await bookService.getBooks(params)
      
      if (params.page && params.page > 1) {
        // Append to existing books for pagination
        booksStore.appendBooks(response.data.books)
      } else {
        // Replace books for new search/filter
        booksStore.setBooks(response.data.books)
      }
      
      booksStore.setPagination(response.data.pagination)
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch books'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Add new book
  const addBook = async (bookData) => {
    loading.value = true
    error.value = null

    try {
      const response = await bookService.addBook(bookData)
      booksStore.addBook(response.data.book)
      return response.data.book
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to add book'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Update book
  const updateBook = async (bookId, updates) => {
    try {
      const response = await bookService.updateBook(bookId, updates)
      booksStore.updateBook(response.data.book)
      return response.data.book
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update book'
      throw err
    }
  }

  // Delete book
  const deleteBook = async (bookId) => {
    try {
      await bookService.deleteBook(bookId)
      booksStore.removeBook(bookId)
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete book'
      throw err
    }
  }

  // Search books
  const searchBooks = async (query, filters = {}) => {
    loading.value = true
    error.value = null

    try {
      const response = await bookService.searchBooks({ q: query, ...filters })
      booksStore.setBooks(response.data.books)
      booksStore.setPagination(response.data.pagination)
      return response.data
    } catch (err) {
      error.value = err.response?.data?.error || 'Search failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Load more books (infinite scroll)
  const loadMore = async () => {
    if (!hasMore.value || loading.value) return

    const nextPage = currentPage.value + 1
    await fetchBooks({ page: nextPage })
  }

  return {
    // State
    books,
    totalBooks,
    currentPage,
    hasMore,
    loading: computed(() => loading.value),
    error: computed(() => error.value),

    // Methods
    fetchBooks,
    addBook,
    updateBook,
    deleteBook,
    searchBooks,
    loadMore
  }
}
```

### State Management with Pinia

#### Auth Store

```javascript
// stores/auth.js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const isAuthenticated = ref(false)
  const permissions = ref([])

  // Getters
  const userName = computed(() => user.value?.name || '')
  const userEmail = computed(() => user.value?.email || '')
  const hasPermission = computed(() => (permission) => {
    return permissions.value.includes(permission)
  })

  // Actions
  const setUser = (userData) => {
    user.value = userData
    permissions.value = userData.permissions || []
  }

  const setAuthenticated = (status) => {
    isAuthenticated.value = status
  }

  const logout = () => {
    user.value = null
    isAuthenticated.value = false
    permissions.value = []
  }

  const updateUserProfile = (updates) => {
    if (user.value) {
      user.value = { ...user.value, ...updates }
    }
  }

  return {
    // State
    user,
    isAuthenticated,
    permissions,

    // Getters
    userName,
    userEmail,
    hasPermission,

    // Actions
    setUser,
    setAuthenticated,
    logout,
    updateUserProfile
  }
})
```

#### Books Store

```javascript
// stores/books.js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useBooksStore = defineStore('books', () => {
  // State
  const books = ref([])
  const currentPage = ref(1)
  const totalPages = ref(1)
  const totalBooks = ref(0)
  const perPage = ref(20)
  const filters = ref({})
  const sortBy = ref('created_at')
  const sortOrder = ref('desc')

  // Getters
  const hasMore = computed(() => currentPage.value < totalPages.value)
  
  const booksByStatus = computed(() => {
    return books.value.reduce((acc, book) => {
      const status = book.status || 'unknown'
      if (!acc[status]) acc[status] = []
      acc[status].push(book)
      return acc
    }, {})
  })

  const readBooks = computed(() => booksByStatus.value.read || [])
  const readingBooks = computed(() => booksByStatus.value.reading || [])
  const toReadBooks = computed(() => booksByStatus.value['to-read'] || [])

  const statistics = computed(() => ({
    total: books.value.length,
    read: readBooks.value.length,
    reading: readingBooks.value.length,
    toRead: toReadBooks.value.length,
    averageRating: calculateAverageRating(),
    totalPages: books.value.reduce((sum, book) => sum + (book.pages || 0), 0)
  }))

  // Actions
  const setBooks = (newBooks) => {
    books.value = newBooks
  }

  const appendBooks = (newBooks) => {
    books.value.push(...newBooks)
  }

  const addBook = (book) => {
    books.value.unshift(book)
    totalBooks.value += 1
  }

  const updateBook = (updatedBook) => {
    const index = books.value.findIndex(book => book.id === updatedBook.id)
    if (index !== -1) {
      books.value[index] = { ...books.value[index], ...updatedBook }
    }
  }

  const removeBook = (bookId) => {
    const index = books.value.findIndex(book => book.id === bookId)
    if (index !== -1) {
      books.value.splice(index, 1)
      totalBooks.value -= 1
    }
  }

  const setPagination = (pagination) => {
    currentPage.value = pagination.current_page
    totalPages.value = pagination.total_pages
    totalBooks.value = pagination.total
    perPage.value = pagination.per_page
  }

  const setFilters = (newFilters) => {
    filters.value = { ...filters.value, ...newFilters }
  }

  const clearFilters = () => {
    filters.value = {}
  }

  const setSorting = (field, order = 'asc') => {
    sortBy.value = field
    sortOrder.value = order
  }

  // Helper functions
  const calculateAverageRating = () => {
    const ratedBooks = books.value.filter(book => book.rating)
    if (ratedBooks.length === 0) return 0
    
    const sum = ratedBooks.reduce((total, book) => total + book.rating, 0)
    return Math.round((sum / ratedBooks.length) * 10) / 10
  }

  const findBookById = (id) => {
    return books.value.find(book => book.id === id)
  }

  return {
    // State
    books,
    currentPage,
    totalPages,
    totalBooks,
    perPage,
    filters,
    sortBy,
    sortOrder,

    // Getters
    hasMore,
    booksByStatus,
    readBooks,
    readingBooks,
    toReadBooks,
    statistics,

    // Actions
    setBooks,
    appendBooks,
    addBook,
    updateBook,
    removeBook,
    setPagination,
    setFilters,
    clearFilters,
    setSorting,
    findBookById
  }
})
```

### Service Layer

#### API Service Base

```javascript
// services/api.js
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'

// Create axios instance
const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json'
  },
  withCredentials: true // For session cookies
})

// Request interceptor
api.interceptors.request.use(
  (config) => {
    // Add request timestamp for debugging
    config.metadata = { startTime: new Date() }
    
    // Log request in development
    if (import.meta.env.DEV) {
      console.log(`🚀 ${config.method?.toUpperCase()} ${config.url}`, config.data)
    }
    
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor
api.interceptors.response.use(
  (response) => {
    // Log response time in development
    if (import.meta.env.DEV) {
      const duration = new Date() - response.config.metadata.startTime
      console.log(`✅ ${response.config.method?.toUpperCase()} ${response.config.url} - ${duration}ms`)
    }
    
    return response
  },
  (error) => {
    const authStore = useAuthStore()
    const notificationStore = useNotificationStore()
    
    // Handle common error cases
    if (error.response) {
      const { status, data } = error.response
      
      switch (status) {
        case 401:
          // Unauthorized - redirect to login
          authStore.logout()
          window.location.href = '/login'
          break
          
        case 403:
          // Forbidden
          notificationStore.addError('You do not have permission to perform this action')
          break
          
        case 404:
          // Not found
          notificationStore.addError('The requested resource was not found')
          break
          
        case 429:
          // Rate limited
          notificationStore.addError('Too many requests. Please try again later.')
          break
          
        case 500:
          // Server error
          notificationStore.addError('Server error. Please try again later.')
          break
          
        default:
          // Other errors
          const message = data?.error || `Request failed with status ${status}`
          notificationStore.addError(message)
      }
    } else if (error.request) {
      // Network error
      notificationStore.addError('Network error. Please check your connection.')
    } else {
      // Other error
      notificationStore.addError('An unexpected error occurred')
    }
    
    // Log error in development
    if (import.meta.env.DEV) {
      console.error('❌ API Error:', error)
    }
    
    return Promise.reject(error)
  }
)

export default api
```

#### Book Service

```javascript
// services/bookService.js
import api from './api'

export const bookService = {
  // Get all books with pagination and filters
  async getBooks(params = {}) {
    const response = await api.get('/books', { params })
    return response.data
  },

  // Get single book by ID
  async getBook(id) {
    const response = await api.get(`/books/${id}`)
    return response.data
  },

  // Add new book
  async addBook(bookData) {
    const response = await api.post('/books', bookData)
    return response.data
  },

  // Update existing book
  async updateBook(id, updates) {
    const response = await api.put(`/books/${id}`, updates)
    return response.data
  },

  // Delete book
  async deleteBook(id) {
    const response = await api.delete(`/books/${id}`)
    return response.data
  },

  // Search books
  async searchBooks(params) {
    const response = await api.get('/books/search', { params })
    return response.data
  },

  // Get book statistics
  async getBookStats() {
    const response = await api.get('/books/stats')
    return response.data
  },

  // Import books from file
  async importBooks(file, options = {}) {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('type', 'books')
    formData.append('options', JSON.stringify(options))

    const response = await api.post('/library/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    return response.data
  },

  // Export books
  async exportBooks(format = 'csv', filters = {}) {
    const params = { format, type: 'books', ...filters }
    const response = await api.get('/library/export', { 
      params,
      responseType: 'blob' 
    })
    
    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `books_export_${new Date().toISOString().split('T')[0]}.${format}`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
    
    return response.data
  }
}
```

## Router Configuration

```javascript
// router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import routes from './routes'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    } else {
      return { top: 0 }
    }
  }
})

// Navigation guards
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  
  // Check if route requires authentication
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    // Redirect to login with return path
    next({
      name: 'login',
      query: { redirect: to.fullPath }
    })
    return
  }
  
  // Check permissions
  if (to.meta.permissions) {
    const hasPermission = to.meta.permissions.some(permission => 
      authStore.hasPermission(permission)
    )
    
    if (!hasPermission) {
      next({ name: 'unauthorized' })
      return
    }
  }
  
  next()
})

export default router
```

## 📖 Documentation Index

### Core Documentation
- **[Composables Guide](./composables.md)** - Detailed documentation for all Vue 3 composables
- **[Component Library](./components.md)** - Reusable components documentation  
- **[Router Configuration](./routing.md)** - Vue Router setup and navigation guards
- **[State Management](./state-management.md)** - Pinia stores and reactive state patterns

### Development Guides
- **[API Integration](./api.md)** - HTTP client setup and API communication
- **[Styling Guide](./styling.md)** - CSS/SCSS patterns and design system
- **[Testing Strategy](./testing.md)** - Unit tests, integration tests, and E2E testing
- **[Performance Optimization](./performance.md)** - Best practices for performance

### Deployment & Operations
- **[Build Process](./build.md)** - Production builds and optimization strategies
- **[Environment Configuration](./environment.md)** - Environment variables and configuration
- **[Docker Setup](./docker.md)** - Containerization for development and production

---

*Documentación actualizada: 18 de Agosto de 2025*
