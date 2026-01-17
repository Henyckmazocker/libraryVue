import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import Logger from '@/utils/logger'

export const useMoviesStore = defineStore('movies', {
  state: () => ({
    movies: [],
    allowedStatuses: [],
    userTags: [],
    isLoading: false,
    error: null,
    lastSearchQuery: '',
    searchResults: [],
    isSearching: false
  }),

  getters: {
    totalMovies: (state) => state.movies.length,
    
    hasMovies: (state) => state.movies.length > 0,
    
    hasSearchResults: (state) => state.searchResults.length > 0,
    
    moviesWithRating: (state) => 
      state.movies.filter(movie => movie.user_rating && movie.user_rating > 0),
    
    averageRating: (state) => {
      const rated = state.movies.filter(m => m.user_rating && m.user_rating > 0)
      if (rated.length === 0) return 0
      const sum = rated.reduce((acc, movie) => acc + movie.user_rating, 0)
      return (sum / rated.length).toFixed(2)
    },
    
    moviesByStatus: (state) => {
      const statusGroups = {}
      state.movies.forEach(movie => {
        if (movie.userStatuses && Array.isArray(movie.userStatuses)) {
          movie.userStatuses.forEach(status => {
            if (!statusGroups[status]) statusGroups[status] = []
            statusGroups[status].push(movie)
          })
        }
      })
      return statusGroups
    },
    
    getMovieById: (state) => (id) => 
      state.movies.find(movie => movie.id === id || movie.imdbID === id || movie.isbn === id),
    
    movieCountByStatus: (state) => {
      const counts = {}
      state.movies.forEach(movie => {
        if (movie.userStatuses && Array.isArray(movie.userStatuses)) {
          movie.userStatuses.forEach(status => {
            counts[status] = (counts[status] || 0) + 1
          })
        }
      })
      return counts
    }
  },

  actions: {
    /**
     * Obtiene todas las películas del usuario
     */
    async fetchMovies() {
      this.isLoading = true
      this.error = null
      
      try {
        Logger.debug('[MoviesStore] Fetching user movies...')
        const authStore = useAuthStore()
        const response = await authStore.authenticatedApiCall('get_library_items')
        
        if (response.data.status === 'success') {
          const data = response.data.data || {}
          const moviesArray = Array.isArray(data.movies) ? data.movies : []
          
          this.movies = moviesArray.map(movie => ({
            ...movie,
            itemType: 'movie'
          }))
          
          Logger.debug(`[MoviesStore] Fetched ${this.movies.length} movies`)
        } else {
          throw new Error(response.data.message || 'Failed to fetch movies')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch movies')
        Logger.error('[MoviesStore] Error fetching movies:', err)
        this.movies = []
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Busca películas por título
     */
    async searchMovies(query) {
      if (!query || query.trim().length === 0) {
        this.searchResults = []
        return []
      }

      this.isSearching = true
      this.error = null
      this.lastSearchQuery = query

      try {
        Logger.debug(`[MoviesStore] Searching movies with query: ${query}`)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('search_movie_name', {
          name: query
        })

        if (response.data.status === 'success') {
          this.searchResults = response.data.data || []
          Logger.debug(`[MoviesStore] Found ${this.searchResults.length} movie results`)
          return this.searchResults
        } else {
          throw new Error(response.data.message || 'Search failed')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Search failed')
        Logger.error('[MoviesStore] Error searching movies:', err)
        this.searchResults = []
        return []
      } finally {
        this.isSearching = false
      }
    },

    /**
     * Agrega una película a la biblioteca del usuario
     */
    async addMovie(movie, statuses = []) {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[MoviesStore] Adding movie to library:', movie.imdbID)
        const authStore = useAuthStore()
        
        const movieData = {
          id: movie.isbn || movie.imdbID,
          isbn: movie.isbn || movie.imdbID,
          imdbID: movie.imdbID,
          title: movie.title,
          originalTitle: movie.originalTitle || movie.title,
          director: movie.director || '',
          author: movie.author || movie.director || '',
          year: movie.year || '',
          genre: movie.genre || '',
          plot: movie.plot || '',
          coverUrl: movie.coverUrl || '',
          userStatuses: statuses,
          user_rating: movie.user_rating || 0,
          itemType: 'movie',
          genres: movie.genres || []
        }
        
        const response = await authStore.authenticatedApiCall('add_movie', { movie: movieData })

        if (response.data.status === 'success') {
          const newMovie = {
            ...movieData,
            userStatuses: statuses,
            user_rating: movie.user_rating || 0
          }
          this.movies.push(newMovie)
          
          Logger.debug('[MoviesStore] Movie added successfully')
          return { success: true, movie: newMovie }
        } else {
          throw new Error(response.data.message || 'Failed to add movie')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to add movie')
        Logger.error('[MoviesStore] Error adding movie:', err)
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Elimina una película de la biblioteca
     */
    async deleteMovie(movieId) {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[MoviesStore] Deleting movie:', movieId)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('delete_movie', {
          isbn: movieId,
          itemType: 'movie'
        })

        if (response.data.status === 'success') {
          this.movies = this.movies.filter(m => 
            m.id !== movieId && m.imdbID !== movieId && m.isbn !== movieId
          )
          Logger.debug('[MoviesStore] Movie deleted successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to delete movie')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to delete movie')
        Logger.error('[MoviesStore] Error deleting movie:', err)
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Actualiza la calificación de una película
     */
    async updateMovieRating(movieId, rating) {
      try {
        Logger.debug(`[MoviesStore] Updating movie rating: ${movieId} -> ${rating}`)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('update_movie_rating', {
          isbn: movieId,
          rating: rating
        })

        if (response.data.status === 'success') {
          const movie = this.movies.find(m => 
            m.id === movieId || m.imdbID === movieId || m.isbn === movieId
          )
          if (movie) {
            movie.user_rating = rating
          }
          Logger.debug('[MoviesStore] Movie rating updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update rating')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update rating')
        Logger.error('[MoviesStore] Error updating movie rating:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Actualiza los estados de una película
     */
    async updateMovieStatuses(movieId, statuses) {
      try {
        Logger.debug(`[MoviesStore] Updating movie statuses: ${movieId}`, statuses)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('update_movie_user_statuses', {
          isbn: movieId,
          statuses: statuses
        })

        if (response.data.status === 'success') {
          const movie = this.movies.find(m => 
            m.id === movieId || m.imdbID === movieId || m.isbn === movieId
          )
          if (movie) {
            movie.userStatuses = statuses
          }
          Logger.debug('[MoviesStore] Movie statuses updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update statuses')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update statuses')
        Logger.error('[MoviesStore] Error updating movie statuses:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Edita una película completa
     */
    async editMovie(movieId, updatedData) {
      try {
        Logger.debug(`[MoviesStore] Editing movie: ${movieId}`, updatedData)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('edit_user_movie', {
          isbn: movieId,
          ...updatedData
        })

        if (response.data.status === 'success') {
          const movieIndex = this.movies.findIndex(m => 
            m.id === movieId || m.imdbID === movieId || m.isbn === movieId
          )
          if (movieIndex !== -1) {
            this.movies[movieIndex] = {
              ...this.movies[movieIndex],
              ...updatedData
            }
          }
          Logger.debug('[MoviesStore] Movie edited successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to edit movie')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to edit movie')
        Logger.error('[MoviesStore] Error editing movie:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Obtiene los estados permitidos para películas
     */
    async fetchAllowedStatuses() {
      try {
        Logger.debug('[MoviesStore] Fetching allowed statuses...')
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('get_movie_allowed_statuses')
        
        if (response.data.status === 'success') {
          this.allowedStatuses = response.data.data || []
          Logger.debug(`[MoviesStore] Fetched ${this.allowedStatuses.length} allowed statuses`)
          return this.allowedStatuses
        } else {
          throw new Error(response.data.message || 'Failed to fetch allowed statuses')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch allowed statuses')
        Logger.error('[MoviesStore] Error fetching allowed statuses:', err)
        return []
      }
    },

    /**
     * Obtiene los tags del usuario
     */
    async fetchUserTags() {
      try {
        Logger.debug('[MoviesStore] Fetching user tags...')
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('get_user_movie_tags')
        
        if (response.data.status === 'success') {
          this.userTags = response.data.data || []
          Logger.debug(`[MoviesStore] Fetched ${this.userTags.length} user tags`)
          return this.userTags
        } else {
          throw new Error(response.data.message || 'Failed to fetch user tags')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch user tags')
        Logger.error('[MoviesStore] Error fetching user tags:', err)
        return []
      }
    },

    /**
     * Crea un nuevo tag
     */
    async createTag(name, color = '#007bff') {
      try {
        Logger.debug(`[MoviesStore] Creating tag: ${name}`)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('create_user_movie_tag', {
          name: name,
          color: color
        })

        if (response.data.status === 'success') {
          const newTag = response.data.data
          this.userTags.push(newTag)
          Logger.debug('[MoviesStore] Tag created successfully')
          return { success: true, tag: newTag }
        } else {
          throw new Error(response.data.message || 'Failed to create tag')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to create tag')
        Logger.error('[MoviesStore] Error creating tag:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Actualiza los tags de una película
     */
    async updateMovieTags(movieId, tagIds) {
      try {
        Logger.debug(`[MoviesStore] Updating movie tags: ${movieId}`, tagIds)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('update_movie_tags', {
          isbn: movieId,
          tag_ids: tagIds
        })

        if (response.data.status === 'success') {
          Logger.debug('[MoviesStore] Movie tags updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update tags')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update tags')
        Logger.error('[MoviesStore] Error updating movie tags:', err)
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
