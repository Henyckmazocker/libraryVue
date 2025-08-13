import { defineStore } from 'pinia'
import axios from 'axios'

// Configure axios defaults for sessions
axios.defaults.withCredentials = true

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isAuthenticated: false,
    csrfToken: null,
    isLoading: false
  }),

  getters: {
    userData: (state) => state.user,
    isLoggedIn: (state) => state.isAuthenticated && state.user !== null,
    getCSRFToken: (state) => state.csrfToken,
    userName: (state) => state.user?.name || '',
    userPicture: (state) => state.user?.picture || null,
    userEmail: (state) => state.user?.email || ''
  },

  actions: {
    async initializeAuth() {
      this.isLoading = true
      try {
        const response = await this.apiCall('check_auth')
        if (response.data.status === 'success') {
          this.user = response.data.data.user
          this.csrfToken = response.data.data.csrf_token
          this.isAuthenticated = true
          console.log('User authenticated:', this.user.name)
        }
      } catch (error) {
        console.log('User not authenticated:', error.response?.data?.message || error.message)
        this.logout()
      } finally {
        this.isLoading = false
      }
    },

    async login(googleToken) {
      this.isLoading = true
      try {
        const response = await this.apiCall('login', {
          google_token: googleToken
        })

        if (response.data.status === 'success') {
          this.user = response.data.data.user
          this.csrfToken = response.data.data.csrf_token
          this.isAuthenticated = true
          console.log('Login successful:', this.user.name)
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Login failed')
        }
      } catch (error) {
        console.error('Login error:', error)
        this.logout()
        return { 
          success: false, 
          message: error.response?.data?.message || error.message || 'Login failed' 
        }
      } finally {
        this.isLoading = false
      }
    },

    async logout() {
      try {
        await this.apiCall('logout')
        console.log('Logout successful')
      } catch (error) {
        console.error('Logout error:', error)
      } finally {
        this.user = null
        this.isAuthenticated = false
        this.csrfToken = null
        this.isLoading = false
      }
    },

    async apiCall(action, data = {}) {
      const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php'
      
      const requestData = {
        action,
        ...data
      }

      // Add CSRF token for protected operations
      const protectedActions = [
        'add_book', 'delete_book', 'update_book_rating', 'update_book_user_statuses',
        'add_movie', 'delete_movie', 'update_movie_rating', 'update_movie_user_statuses',
        'import_data'
      ]

      if (this.csrfToken && protectedActions.includes(action)) {
        requestData.csrf_token = this.csrfToken
      }

      const config = {
        withCredentials: true, // Important for sessions
        headers: {
          'Content-Type': 'application/json'
        }
      }

      return await axios.post(backendApiUrl, requestData, config)
    },

    updateCSRFToken(token) {
      this.csrfToken = token
    },

    // Helper method for components to make authenticated API calls
    async authenticatedApiCall(action, data = {}) {
      if (!this.isAuthenticated) {
        throw new Error('User not authenticated')
      }
      
      return await this.apiCall(action, data)
    },

    // Check if user needs to re-authenticate
    handleAuthError(error) {
      if (error.response?.status === 401 || error.response?.data?.code === 'AUTH_REQUIRED') {
        console.log('Authentication required, logging out...')
        this.logout()
        // Optionally redirect to login page
        if (window.location.pathname !== '/') {
          window.location.href = '/'
        }
        return true
      }
      return false
    }
  }
})
