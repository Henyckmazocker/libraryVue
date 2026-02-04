import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import Logger from '@/utils/logger'

export const useBooksStore = defineStore('books', {
  state: () => ({
    books: [],
    allowedStatuses: [],
    userTags: [],
    isLoading: false,
    error: null,
    lastSearchQuery: '',
    searchResults: [],
    isSearching: false
  }),

  getters: {
    totalBooks: (state) => state.books.length,
    
    hasBooks: (state) => state.books.length > 0,
    
    hasSearchResults: (state) => state.searchResults.length > 0,
    
    booksWithRating: (state) => 
      state.books.filter(book => book.user_rating && book.user_rating > 0),
    
    averageRating: (state) => {
      const rated = state.books.filter(b => b.user_rating && b.user_rating > 0)
      if (rated.length === 0) return 0
      const sum = rated.reduce((acc, book) => acc + book.user_rating, 0)
      return (sum / rated.length).toFixed(2)
    },
    
    booksByStatus: (state) => {
      const statusGroups = {}
      state.books.forEach(book => {
        if (book.userStatuses && Array.isArray(book.userStatuses)) {
          book.userStatuses.forEach(status => {
            if (!statusGroups[status]) statusGroups[status] = []
            statusGroups[status].push(book)
          })
        }
      })
      return statusGroups
    },
    
    getBookByIsbn: (state) => (isbn) => 
      state.books.find(book => book.isbn === isbn),
    
    isBookInLibrary: (state) => (isbn) => 
      state.books.some(book => book.isbn === isbn),
    
    bookCountByStatus: (state) => {
      const counts = {}
      state.books.forEach(book => {
        if (book.userStatuses && Array.isArray(book.userStatuses)) {
          book.userStatuses.forEach(status => {
            counts[status] = (counts[status] || 0) + 1
          })
        }
      })
      return counts
    }
  },

  actions: {
    /**
     * Obtiene todos los libros del usuario
     */
    async fetchBooks() {
      this.isLoading = true
      this.error = null
      
      try {
        Logger.debug('[BooksStore] Fetching user books...')
        const authStore = useAuthStore()
        const response = await authStore.authenticatedApiCall('get_library_items')
        
        if (response.data.status === 'success') {
          Logger.debug('[BooksStore] Response data:', response.data)
          
          const data = response.data.data || {}
          const booksArray = Array.isArray(data.books) ? data.books : []
          
          this.books = booksArray.map(book => ({
            ...book,
            itemType: 'book'
          }))
          
          Logger.debug(`[BooksStore] Fetched ${this.books.length} books`)
        } else {
          throw new Error(response.data.message || 'Failed to fetch books')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch books')
        Logger.error('[BooksStore] Error fetching books:', err)
        this.books = []
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Busca libros por ISBN o título
     */
    async searchBooks(query) {
      if (!query || query.trim().length === 0) {
        this.searchResults = []
        return []
      }

      this.isSearching = true
      this.error = null
      this.lastSearchQuery = query

      try {
        Logger.debug(`[BooksStore] Searching books with query: ${query}`)
        const authStore = useAuthStore()
        
        // Determinar si es búsqueda por ISBN o por nombre
        const isISBN = /^\d{10}(\d{3})?$/.test(query.replace(/[-\s]/g, ''))
        const action = isISBN ? 'search_book_isbn' : 'search_book_name'
        const param = isISBN ? 'isbn' : 'name'
        
        const response = await authStore.authenticatedApiCall(action, {
          [param]: query
        })

        if (response.data.status === 'success') {
          this.searchResults = response.data.data || []
          Logger.debug(`[BooksStore] Found ${this.searchResults.length} book results`)
          return this.searchResults
        } else {
          throw new Error(response.data.message || 'Search failed')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Search failed')
        Logger.error('[BooksStore] Error searching books:', err)
        this.searchResults = []
        return []
      } finally {
        this.isSearching = false
      }
    },

    /**
     * Agrega un libro a la biblioteca del usuario
     */
    async addBook(book, statuses = []) {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[BooksStore] Adding book to library:', book.isbn)
        const authStore = useAuthStore()
        
        // Ensure we have allowed statuses available
        if (this.allowedStatuses.length === 0) {
          await this.fetchAllowedStatuses()
        }
        
        // Normalizar publisher
        let publisherValue = ''
        if (book.publishers && Array.isArray(book.publishers) && book.publishers.length > 0) {
          publisherValue = book.publishers.join(', ')
        } else if (book.publisher) {
          publisherValue = book.publisher
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
          allowedStatuses: this.allowedStatuses,
          rating: book.user_rating || null,
          genres: book.genres || []
        }

        const payload = { book: bookData }
        const response = await authStore.authenticatedApiCall('add_book', payload)

        Logger.debug('[BooksStore] Backend response:', response)

        if (response.data.status === 'success') {
          const newBook = {
            ...book,
            userStatuses: statuses,
            user_rating: book.user_rating || null,
            itemType: 'book'
          }
          this.books.push(newBook)
          
          Logger.debug('[BooksStore] Book added successfully')
          return { success: true, book: newBook }
        } else {
          Logger.error('[BooksStore] Backend returned error:', response.data)
          throw new Error(response.data.message || 'Failed to add book')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to add book')
        Logger.error('[BooksStore] Error adding book:', err)
        Logger.error('[BooksStore] Full error details:', {
          message: err.message,
          response: err.response?.data,
          status: err.response?.status
        })
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Elimina un libro de la biblioteca
     */
    async deleteBook(isbn) {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[BooksStore] Deleting book:', isbn)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('delete_book', {
          isbn: isbn,
          itemType: 'book'
        })

        if (response.data.status === 'success') {
          this.books = this.books.filter(book => book.isbn !== isbn)
          Logger.debug('[BooksStore] Book deleted successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to delete book')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to delete book')
        Logger.error('[BooksStore] Error deleting book:', err)
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Actualiza la calificación de un libro
     */
    async updateBookRating(isbn, rating) {
      try {
        Logger.debug(`[BooksStore] Updating book rating: ${isbn} -> ${rating}`)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('update_book_rating', {
          isbn: isbn,
          rating: rating
        })

        if (response.data.status === 'success') {
          const book = this.books.find(b => b.isbn === isbn)
          if (book) {
            book.user_rating = rating
          }
          Logger.debug('[BooksStore] Book rating updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update rating')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update rating')
        Logger.error('[BooksStore] Error updating book rating:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Actualiza los estados de un libro
     */
    async updateBookStatuses(isbn, statuses) {
      try {
        Logger.debug(`[BooksStore] Updating book statuses: ${isbn}`, statuses)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('update_book_user_statuses', {
          isbn: isbn,
          statuses: statuses
        })

        if (response.data.status === 'success') {
          const book = this.books.find(b => b.isbn === isbn)
          if (book) {
            book.userStatuses = statuses
          }
          Logger.debug('[BooksStore] Book statuses updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update statuses')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update statuses')
        Logger.error('[BooksStore] Error updating book statuses:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Edita un libro completo
     */
    async editBook(isbn, updatedData) {
      try {
        Logger.debug(`[BooksStore] Editing book: ${isbn}`, updatedData)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('edit_user_book', {
          isbn: isbn,
          ...updatedData
        })

        if (response.data.status === 'success') {
          const bookIndex = this.books.findIndex(b => b.isbn === isbn)
          if (bookIndex !== -1) {
            this.books[bookIndex] = {
              ...this.books[bookIndex],
              ...updatedData
            }
          }
          Logger.debug('[BooksStore] Book edited successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to edit book')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to edit book')
        Logger.error('[BooksStore] Error editing book:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Obtiene los estados permitidos para libros
     */
    async fetchAllowedStatuses() {
      try {
        Logger.debug('[BooksStore] Fetching allowed statuses...')
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('get_book_allowed_statuses')
        
        if (response.data.status === 'success') {
          this.allowedStatuses = response.data.data || []
          Logger.debug(`[BooksStore] Fetched ${this.allowedStatuses.length} allowed statuses`)
          return this.allowedStatuses
        } else {
          throw new Error(response.data.message || 'Failed to fetch allowed statuses')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch allowed statuses')
        Logger.error('[BooksStore] Error fetching allowed statuses:', err)
        return []
      }
    },

    /**
     * Obtiene los tags del usuario
     */
    async fetchUserTags() {
      try {
        Logger.debug('[BooksStore] Fetching user tags...')
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('get_user_book_tags')
        
        if (response.data.status === 'success') {
          this.userTags = response.data.data || []
          Logger.debug(`[BooksStore] Fetched ${this.userTags.length} user tags`)
          return this.userTags
        } else {
          throw new Error(response.data.message || 'Failed to fetch user tags')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch user tags')
        Logger.error('[BooksStore] Error fetching user tags:', err)
        return []
      }
    },

    /**
     * Crea un nuevo tag
     */
    async createTag(name, color = '#007bff') {
      try {
        Logger.debug(`[BooksStore] Creating tag: ${name}`)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('create_user_book_tag', {
          name: name,
          color: color
        })

        if (response.data.status === 'success') {
          const newTag = response.data.data
          this.userTags.push(newTag)
          Logger.debug('[BooksStore] Tag created successfully')
          return { success: true, tag: newTag }
        } else {
          throw new Error(response.data.message || 'Failed to create tag')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to create tag')
        Logger.error('[BooksStore] Error creating tag:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Actualiza los tags de un libro
     */
    async updateBookTags(isbn, tagIds) {
      try {
        Logger.debug(`[BooksStore] Updating book tags: ${isbn}`, tagIds)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('update_book_tags', {
          isbn: isbn,
          tag_ids: tagIds
        })

        if (response.data.status === 'success') {
          Logger.debug('[BooksStore] Book tags updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update tags')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update tags')
        Logger.error('[BooksStore] Error updating book tags:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Limpia los resultados de búsqueda
     */
    clearSearchResults() {
      this.searchResults = []
      this.lastSearchQuery = ''
    },

    /**
     * Limpia el error actual
     */
    clearError() {
      this.error = null
    },

    /**
     * Manejo centralizado de errores
     * @private
     */
    _handleError(err, defaultMessage = 'Operation failed') {
      if (err.response) {
        const status = err.response.status
        const data = err.response.data
        
        if (status === 401) {
          return 'Authentication required. Please login again.'
        } else if (status === 403) {
          return 'Invalid CSRF token. Please refresh the page and try again.'
        } else if (data && data.message) {
          return data.message
        } else {
          return `Server error (${status})`
        }
      } else if (err.request) {
        return 'Network error. Please check your connection.'
      } else if (err.message) {
        return err.message
      }
      
      return defaultMessage
    }
  }
})
