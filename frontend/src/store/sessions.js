import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import Logger from '@/utils/logger'

export const useSessionsStore = defineStore('sessions', {
  state: () => ({
    // Map: bookId -> activeSession
    activeSessions: {},
    // Map: bookId -> sessionHistory[]
    sessionHistories: {},
    isLoading: false,
    error: null
  }),

  getters: {
    /**
     * Total de sesiones activas
     */
    activeSessionsCount: (state) => Object.keys(state.activeSessions).length,
    
    /**
     * Verifica si hay alguna sesión activa
     */
    hasActiveSessions: (state) => Object.keys(state.activeSessions).length > 0,
    
    /**
     * Obtiene la sesión activa de un libro específico
     */
    getActiveSessionByBook: (state) => (bookId) => state.activeSessions[bookId] || null,
    
    /**
     * Obtiene el historial de sesiones de un libro
     */
    getHistoryByBook: (state) => (bookId) => state.sessionHistories[bookId] || [],
    
    /**
     * Verifica si un libro tiene sesión activa
     */
    hasActiveSession: (state) => (bookId) => !!state.activeSessions[bookId],
    
    /**
     * Obtiene el número de sesión actual para un libro
     */
    getCurrentSessionNumber: (state) => (bookId) => {
      const activeSession = state.activeSessions[bookId]
      if (activeSession) return activeSession.session_number
      
      const history = state.sessionHistories[bookId] || []
      return history.length + 1
    },
    
    /**
     * Verifica si un libro ha sido completado alguna vez
     */
    hasCompletedReading: (state) => (bookId) => {
      const history = state.sessionHistories[bookId] || []
      return history.some(s => s.status === 'completed')
    },
    
    /**
     * Total de lecturas completadas de un libro
     */
    getTotalCompleted: (state) => (bookId) => {
      const history = state.sessionHistories[bookId] || []
      return history.filter(s => s.status === 'completed').length
    },
    
    /**
     * Total de sesiones de un libro
     */
    getTotalSessions: (state) => (bookId) => {
      const history = state.sessionHistories[bookId] || []
      return history.length
    },
    
    /**
     * Verifica si es la primera lectura de un libro
     */
    isFirstReading: (state) => (bookId) => {
      const history = state.sessionHistories[bookId] || []
      const activeSession = state.activeSessions[bookId]
      return history.length === 0 && !activeSession
    }
  },

  actions: {
    /**
     * Carga la sesión activa de un libro
     */
    async loadActiveSession(bookId) {
      try {
        Logger.debug('[SessionsStore] Loading active session for book:', bookId)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('get_active_reading_session', {
          isbn: bookId
        })

        if (response.data.status === 'success') {
          const session = response.data.data || null
          if (session) {
            this.activeSessions[bookId] = session
          } else {
            delete this.activeSessions[bookId]
          }
          Logger.debug('[SessionsStore] Active session loaded:', session)
          return { success: true, session }
        }
      } catch (err) {
        Logger.error('[SessionsStore] Error loading active session:', err)
        delete this.activeSessions[bookId]
        return { success: false, message: err.message }
      }
    },

    /**
     * Carga el historial de sesiones de un libro
     */
    async loadHistory(bookId) {
      try {
        Logger.debug('[SessionsStore] Loading session history for book:', bookId)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('get_reading_session_history', {
          isbn: bookId
        })

        if (response.data.status === 'success') {
          this.sessionHistories[bookId] = response.data.data || []
          Logger.debug('[SessionsStore] History loaded:', this.sessionHistories[bookId].length, 'sessions')
          return { success: true, history: this.sessionHistories[bookId] }
        }
      } catch (err) {
        Logger.error('[SessionsStore] Error loading history:', err)
        this.sessionHistories[bookId] = []
        return { success: false, message: err.message }
      }
    },

    /**
     * Inicia una nueva sesión de lectura
     */
    async createSession(bookId, startPage = 1) {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[SessionsStore] Creating new session:', { bookId, startPage })
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('create_reading_session', {
          isbn: bookId,
          startPage: startPage
        })

        Logger.debug('[SessionsStore] API response:', response.data)

        if (response.data.status === 'success') {
          const newSession = response.data.data
          this.activeSessions[bookId] = newSession
          
          // Añadir al historial
          if (!this.sessionHistories[bookId]) {
            this.sessionHistories[bookId] = []
          }
          this.sessionHistories[bookId].push(newSession)
          
          Logger.debug('[SessionsStore] Session created successfully:', newSession)
          return { 
            success: true, 
            session: newSession,
            sessionId: newSession.id 
          }
        } else {
          Logger.error('[SessionsStore] API returned error:', response.data.message)
          throw new Error(response.data.message || 'Failed to create session')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to create session')
        Logger.error('[SessionsStore] Error creating session:', err)
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Completa la sesión activa
     */
    async completeSession(bookId, endPage, reason = 'completed') {
      this.isLoading = true
      this.error = null

      try {
        const activeSession = this.activeSessions[bookId]
        if (!activeSession) {
          throw new Error('No active session found')
        }

        Logger.debug('[SessionsStore] Completing session:', activeSession.id)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('complete_reading_session', {
          sessionId: activeSession.id,
          endPage: endPage,
          reason: reason
        })

        if (response.data.status === 'success') {
          // Actualizar historial
          const history = this.sessionHistories[bookId] || []
          const index = history.findIndex(s => s.id === activeSession.id)
          if (index !== -1) {
            history[index] = {
              ...history[index],
              end_page: endPage,
              status: 'completed',
              completion_reason: reason,
              completed_at: new Date().toISOString()
            }
          }
          
          // Remover sesión activa
          delete this.activeSessions[bookId]
          
          Logger.debug('[SessionsStore] Session completed successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to complete session')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to complete session')
        Logger.error('[SessionsStore] Error completing session:', err)
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Pausa la sesión activa
     */
    async pauseSession(bookId) {
      try {
        const activeSession = this.activeSessions[bookId]
        if (!activeSession) {
          throw new Error('No active session found')
        }

        Logger.debug('[SessionsStore] Pausing session:', activeSession.id)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('pause_reading_session', {
          sessionId: activeSession.id
        })

        if (response.data.status === 'success') {
          this.activeSessions[bookId] = {
            ...activeSession,
            status: 'paused'
          }
          
          Logger.debug('[SessionsStore] Session paused successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to pause session')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to pause session')
        Logger.error('[SessionsStore] Error pausing session:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Reanuda una sesión pausada
     */
    async resumeSession(bookId) {
      try {
        const activeSession = this.activeSessions[bookId]
        if (!activeSession) {
          throw new Error('No active session found')
        }

        Logger.debug('[SessionsStore] Resuming session:', activeSession.id)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('resume_reading_session', {
          sessionId: activeSession.id
        })

        if (response.data.status === 'success') {
          this.activeSessions[bookId] = {
            ...activeSession,
            status: 'active'
          }
          
          Logger.debug('[SessionsStore] Session resumed successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to resume session')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to resume session')
        Logger.error('[SessionsStore] Error resuming session:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Abandona la sesión activa
     */
    async abandonSession(bookId) {
      try {
        const activeSession = this.activeSessions[bookId]
        if (!activeSession) {
          throw new Error('No active session found')
        }

        Logger.debug('[SessionsStore] Abandoning session:', activeSession.id)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('complete_reading_session', {
          sessionId: activeSession.id,
          endPage: activeSession.start_page || 1,
          reason: 'abandoned'
        })

        if (response.data.status === 'success') {
          // Actualizar historial
          const history = this.sessionHistories[bookId] || []
          const index = history.findIndex(s => s.id === activeSession.id)
          if (index !== -1) {
            history[index] = {
              ...history[index],
              status: 'abandoned',
              completion_reason: 'abandoned'
            }
          }
          
          // Remover sesión activa
          delete this.activeSessions[bookId]
          
          Logger.debug('[SessionsStore] Session abandoned successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to abandon session')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to abandon session')
        Logger.error('[SessionsStore] Error abandoning session:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Elimina una sesión del historial
     */
    async deleteSession(bookId, sessionId) {
      try {
        Logger.debug('[SessionsStore] Deleting session:', sessionId)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('delete_reading_session', {
          sessionId: sessionId
        })

        if (response.data.status === 'success') {
          // Remover del historial
          if (this.sessionHistories[bookId]) {
            this.sessionHistories[bookId] = this.sessionHistories[bookId].filter(
              s => s.id !== sessionId
            )
          }
          
          // Si es la sesión activa, removerla también
          if (this.activeSessions[bookId]?.id === sessionId) {
            delete this.activeSessions[bookId]
          }
          
          Logger.debug('[SessionsStore] Session deleted successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to delete session')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to delete session')
        Logger.error('[SessionsStore] Error deleting session:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Actualiza el progreso de lectura con sesión
     */
    async updateProgress(bookId, currentPage) {
      try {
        const activeSession = this.activeSessions[bookId]
        if (!activeSession) {
          Logger.warn('[SessionsStore] No active session, cannot update progress')
          return { success: false, message: 'No active session' }
        }

        Logger.debug('[SessionsStore] Updating progress:', { bookId, currentPage })
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('update_reading_progress_with_session', {
          isbn: bookId,
          sessionId: activeSession.id,
          currentPage: currentPage
        })

        if (response.data.status === 'success') {
          Logger.debug('[SessionsStore] Progress updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update progress')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update progress')
        Logger.error('[SessionsStore] Error updating progress:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Obtiene todas las sesiones activas del usuario
     */
    async fetchAllActiveSessions() {
      try {
        Logger.debug('[SessionsStore] Fetching all active sessions')
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('get_user_active_sessions')

        if (response.data.status === 'success') {
          const sessions = response.data.data || []
          
          // Actualizar el map de sesiones activas
          this.activeSessions = {}
          sessions.forEach(session => {
            this.activeSessions[session.book_isbn] = session
          })
          
          Logger.debug(`[SessionsStore] Fetched ${sessions.length} active sessions`)
          return { success: true, sessions }
        } else {
          throw new Error(response.data.message || 'Failed to fetch active sessions')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch active sessions')
        Logger.error('[SessionsStore] Error fetching active sessions:', err)
        return { success: false, message: this.error }
      }
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
