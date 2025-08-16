import { defineStore } from 'pinia'
import axios from 'axios'
import Logger from '@/utils/logger'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isAuthenticated: false,
    csrfToken: null,
    isLoading: false,
    jwtToken: null
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
        // Try to get JWT token from localStorage first
        const storedToken = localStorage.getItem('jwt_token')
        if (storedToken) {
          this.jwtToken = storedToken
        }
        
        Logger.auth('Starting auth check...')
        const response = await this.apiCall('check_auth')
        Logger.auth('Response received:', response.data)
        
        if (response.data.status === 'success') {
          this.user = response.data.data.user
          this.csrfToken = response.data.data.csrf_token
          this.isAuthenticated = true
          Logger.auth('User authenticated successfully:', this.user.name)
        } else {
          Logger.auth('Auth check failed:', response.data.message)
        }
      } catch (error) {
        Logger.auth('User not authenticated:', error.response?.data?.message || error.message)
        // Call logout to properly clean up any stale session data
        await this.logout()
      } finally {
        this.isLoading = false
        Logger.auth('Final state - isAuthenticated:', this.isAuthenticated, 'user:', this.user?.name || 'null')
      }
    },

    async login(googleToken) {
      this.isLoading = true
      try {
        Logger.auth('Sending login request with token...')
        const response = await this.apiCall('login', {
          google_token: googleToken
        })

        Logger.auth('Login response received:', response.data)

        if (response.data.status === 'success') {
          this.user = response.data.data.user
          this.csrfToken = response.data.data.csrf_token
          this.isAuthenticated = true
          
          // Store JWT token if provided
          if (response.data.data.jwt_token) {
            this.jwtToken = response.data.data.jwt_token
            localStorage.setItem('jwt_token', this.jwtToken)
          }
          
          Logger.auth('Login successful:', this.user.name)
          
          // Small delay to ensure session is properly set before any other operations
          await new Promise(resolve => setTimeout(resolve, 100))
          
          return { success: true }
        } else {
          Logger.error('Login failed - backend error:', response.data.message)
          throw new Error(response.data.message || 'Login failed')
        }
      } catch (error) {
        Logger.error('Login error details:', {
          message: error.message,
          response: error.response?.data,
          status: error.response?.status
        })
        // Clean up any partial authentication state
        await this.logout()
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
        // Only call backend logout if we have an active session
        if (this.isAuthenticated) {
          await this.apiCall('logout')
          Logger.auth('Logout successful')
        }
      } catch (error) {
        Logger.error('Logout error:', error)
        // Continue with cleanup even if backend call fails
      } finally {
        // Always clean up local state
        this.user = null
        this.isAuthenticated = false
        this.csrfToken = null
        this.isLoading = false
        this.jwtToken = null
        localStorage.removeItem('jwt_token')
      }
    },

    async apiCall(action, data = {}) {
      const backendApiUrl = process.env.VUE_APP_API_URL || '/index.php'
      
      Logger.api('Making API call:', backendApiUrl, 'action:', action)
      
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
        },
        timeout: 10000 // 10 segundos timeout
      }

      // Add JWT token to headers if available
      if (this.jwtToken) {
        config.headers['Authorization'] = `Bearer ${this.jwtToken}`
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
        Logger.auth('Authentication required, logging out...')
        this.logout() // Clean logout for auth errors
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
