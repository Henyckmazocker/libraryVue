import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import Logger from '@/utils/logger'
import { handleStoreError } from '@/utils/storeHelpers'
import { t } from '@/config/i18n'

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
     * Carga el historial detallado de progreso de páginas de un libro
     */
    async loadProgressHistory(bookId) {
      try {
        Logger.debug('[SessionsStore] Loading progress history for book:', bookId)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('get_progress_history', {
          isbn: bookId
        })

        if (response.data.status === 'success') {
          const progressHistory = response.data.data || []
          Logger.debug('[SessionsStore] Progress history loaded:', progressHistory.length, 'entries')
          return { success: true, history: progressHistory }
        }
      } catch (err) {
        Logger.error('[SessionsStore] Error loading progress history:', err)
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
          throw new Error(t('session.createFailed'))
        }
      } catch (err) {
        this.error = this._handleError(err)
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
          throw new Error(t('session.noneActive'))
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
          throw new Error(t('session.completeFailed'))
        }
      } catch (err) {
        this.error = this._handleError(err)
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
          throw new Error(t('session.noneActive'))
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
          throw new Error(t('session.pauseFailed'))
        }
      } catch (err) {
        this.error = this._handleError(err)
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
          throw new Error(t('session.noneActive'))
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
          throw new Error(t('session.resumeFailed'))
        }
      } catch (err) {
        this.error = this._handleError(err)
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
          throw new Error(t('session.noneActive'))
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
          throw new Error(t('session.abandonFailed'))
        }
      } catch (err) {
        this.error = this._handleError(err)
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
          throw new Error(t('session.deleteFailed'))
        }
      } catch (err) {
        this.error = this._handleError(err)
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
          return { success: false, message: t('session.noneActive') }
        }

        Logger.debug('[SessionsStore] Updating progress:', { bookId, currentPage })
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('update_reading_progress_with_session', {
          isbn: bookId,
          sessionId: activeSession.id,
          currentPage: currentPage
        })

        if (response.data.status === 'success') {
          const result = response.data.data || {}
          
          // ✅ Si vienen estados actualizados, actualizar el libro en el store
          if (result.updatedStatuses && Array.isArray(result.updatedStatuses)) {
            // Usar import dinámico para evitar dependencias circulares
            import('./books').then(({ useBooksStore }) => {
              const bookStore = useBooksStore()
              const book = bookStore.books.find(b => b.isbn === bookId)
              
              if (book) {
                book.userStatuses = result.updatedStatuses
                Logger.debug('[SessionsStore] Book statuses updated in store:', result.updatedStatuses)
              }
            }).catch(err => {
              Logger.error('[SessionsStore] Error updating book statuses:', err)
            })
          }
          
          Logger.debug('[SessionsStore] Progress updated successfully')
          return { success: true, data: result }
        } else {
          throw new Error(t('session.progressFailed'))
        }
      } catch (err) {
        this.error = this._handleError(err)
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
          throw new Error(t('session.fetchFailed'))
        }
      } catch (err) {
        this.error = this._handleError(err)
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
     * Manejo centralizado de errores. Era la cuarta copia de lo mismo; ahora
     * delega en `apiError`, que resuelve por código y no enseña el texto del
     * backend.
     * @private
     */
    _handleError (err) {
      return handleStoreError(err)
    }
  }
})
