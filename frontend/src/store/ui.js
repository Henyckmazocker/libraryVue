import { defineStore } from 'pinia'
import Logger from '@/utils/logger'

export const useUIStore = defineStore('ui', {
  state: () => ({
    // Modales
    modals: {
      confirmation: {
        isOpen: false,
        title: '',
        message: '',
        confirmText: 'Confirmar',
        cancelText: 'Cancelar',
        type: 'default', // 'default' | 'danger' | 'warning' | 'success'
        resolve: null
      },
      itemEdit: {
        isOpen: false,
        item: null,
        itemType: null // 'book' | 'movie'
      },
      sessionHistory: {
        isOpen: false,
        bookId: null
      }
    },
    
    // Notificaciones
    notifications: [],
    nextNotificationId: 1,
    
    // Tema
    theme: localStorage.getItem('theme') || 'light',
    systemThemeListener: null,
    
    // Sidebar
    sidebarOpen: false,
    sidebarCollapsed: false,
    
    // Loading global
    globalLoading: false,
    loadingMessage: ''
  }),

  getters: {
    /**
     * Verifica si algún modal está abierto
     */
    isAnyModalOpen: (state) => 
      Object.values(state.modals).some(modal => modal.isOpen),
    
    /**
     * Obtiene el tema actual
     */
    currentTheme: (state) => state.theme,
    
    /**
     * Verifica si el tema es oscuro
     */
    isDarkTheme: (state) => state.theme === 'dark',
    
    /**
     * Alias para compatibilidad
     */
    isDark: (state) => state.theme === 'dark',
    
    /**
     * Obtiene las notificaciones activas
     */
    activeNotifications: (state) => state.notifications,
    
    /**
     * Verifica si hay notificaciones
     */
    hasNotifications: (state) => state.notifications.length > 0
  },

  actions: {
    /**
     * Muestra un modal de confirmación
     */
    showConfirmationModal(options) {
      return new Promise((resolve) => {
        this.modals.confirmation = {
          isOpen: true,
          title: options.title || 'Confirmar acción',
          message: options.message || '¿Estás seguro?',
          confirmText: options.confirmText || 'Confirmar',
          cancelText: options.cancelText || 'Cancelar',
          type: options.type || 'default',
          resolve
        }
      })
    },

    /**
     * Cierra el modal de confirmación con resultado
     */
    closeConfirmationModal(confirmed = false) {
      if (this.modals.confirmation.resolve) {
        this.modals.confirmation.resolve(confirmed)
      }
      this.modals.confirmation.isOpen = false
      this.modals.confirmation.resolve = null
    },

    /**
     * Abre el modal de edición de item
     */
    openItemEditModal(item, itemType) {
      this.modals.itemEdit = {
        isOpen: true,
        item: { ...item }, // Clonar para evitar mutación directa
        itemType
      }
    },

    /**
     * Cierra el modal de edición de item
     */
    closeItemEditModal() {
      this.modals.itemEdit = {
        isOpen: false,
        item: null,
        itemType: null
      }
    },

    /**
     * Abre el modal de historial de sesiones
     */
    openSessionHistoryModal(bookId) {
      this.modals.sessionHistory = {
        isOpen: true,
        bookId
      }
    },

    /**
     * Cierra el modal de historial de sesiones
     */
    closeSessionHistoryModal() {
      this.modals.sessionHistory = {
        isOpen: false,
        bookId: null
      }
    },

    /**
     * Añade una notificación
     */
    addNotification(options) {
      const notification = {
        id: this.nextNotificationId++,
        type: options.type || 'info', // 'info' | 'success' | 'warning' | 'error'
        title: options.title || '',
        message: options.message || '',
        duration: options.duration || 5000,
        dismissible: options.dismissible !== false
      }

      this.notifications.push(notification)
      Logger.debug('[UIStore] Notification added:', notification)

      // Auto-dismiss después de la duración especificada
      if (notification.duration > 0) {
        setTimeout(() => {
          this.removeNotification(notification.id)
        }, notification.duration)
      }

      return notification.id
    },

    /**
     * Muestra una notificación de éxito
     */
    showSuccess(message, title = 'Éxito') {
      return this.addNotification({
        type: 'success',
        title,
        message,
        duration: 3000
      })
    },

    /**
     * Muestra una notificación de error
     */
    showError(message, title = 'Error') {
      return this.addNotification({
        type: 'error',
        title,
        message,
        duration: 5000
      })
    },

    /**
     * Muestra una notificación de advertencia
     */
    showWarning(message, title = 'Advertencia') {
      return this.addNotification({
        type: 'warning',
        title,
        message,
        duration: 4000
      })
    },

    /**
     * Muestra una notificación informativa
     */
    showInfo(message, title = 'Información') {
      return this.addNotification({
        type: 'info',
        title,
        message,
        duration: 3000
      })
    },

    /**
     * Remueve una notificación
     */
    removeNotification(id) {
      const index = this.notifications.findIndex(n => n.id === id)
      if (index !== -1) {
        this.notifications.splice(index, 1)
        Logger.debug('[UIStore] Notification removed:', id)
      }
    },

    /**
     * Limpia todas las notificaciones
     */
    clearNotifications() {
      this.notifications = []
      Logger.debug('[UIStore] All notifications cleared')
    },

    /**
     * Aplica el tema al documento
     */
    applyTheme() {
      if (this.theme === 'dark') {
        document.documentElement.classList.add('app-dark')
      } else {
        document.documentElement.classList.remove('app-dark')
      }
      document.documentElement.setAttribute('data-theme', this.theme)
      Logger.debug('[UIStore] Theme applied:', this.theme)
    },
    
    /**
     * Carga el tema desde localStorage o detecta preferencia del sistema
     */
    loadTheme() {
      const savedTheme = localStorage.getItem('theme')
      
      if (savedTheme) {
        this.theme = savedTheme
      } else {
        // Detectar preferencia del sistema
        if (typeof window !== 'undefined' && window.matchMedia) {
          const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
          this.theme = prefersDark ? 'dark' : 'light'
        }
      }
      
      this.applyTheme()
      Logger.debug('[UIStore] Theme loaded:', this.theme)
    },
    
    /**
     * Inicializa el listener de cambios del sistema
     */
    initSystemThemeListener() {
      if (typeof window !== 'undefined' && window.matchMedia) {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
        
        const handler = (e) => {
          // Solo actualizar si no hay preferencia guardada
          if (!localStorage.getItem('theme')) {
            this.theme = e.matches ? 'dark' : 'light'
            this.applyTheme()
            Logger.debug('[UIStore] System theme changed:', this.theme)
          }
        }
        
        mediaQuery.addEventListener('change', handler)
        this.systemThemeListener = { mediaQuery, handler }
      }
    },
    
    /**
     * Remueve el listener de cambios del sistema
     */
    removeSystemThemeListener() {
      if (this.systemThemeListener) {
        this.systemThemeListener.mediaQuery.removeEventListener(
          'change',
          this.systemThemeListener.handler
        )
        this.systemThemeListener = null
      }
    },

    /**
     * Cambia el tema
     */
    toggleTheme() {
      this.theme = this.theme === 'light' ? 'dark' : 'light'
      localStorage.setItem('theme', this.theme)
      this.applyTheme()
      Logger.debug('[UIStore] Theme toggled to:', this.theme)
    },

    /**
     * Establece un tema específico
     */
    setTheme(theme) {
      if (theme !== 'light' && theme !== 'dark') {
        Logger.warn('[UIStore] Invalid theme:', theme)
        return
      }
      
      this.theme = theme
      localStorage.setItem('theme', theme)
      this.applyTheme()
      Logger.debug('[UIStore] Theme set to:', theme)
    },

    /**
     * Inicializa el tema desde localStorage
     */
    initTheme() {
      this.loadTheme()
      this.initSystemThemeListener()
    },

    /**
     * Abre el sidebar
     */
    openSidebar() {
      this.sidebarOpen = true
      Logger.debug('[UIStore] Sidebar opened')
    },

    /**
     * Cierra el sidebar
     */
    closeSidebar() {
      this.sidebarOpen = false
      Logger.debug('[UIStore] Sidebar closed')
    },

    /**
     * Toggle del sidebar
     */
    toggleSidebar() {
      this.sidebarOpen = !this.sidebarOpen
      Logger.debug('[UIStore] Sidebar toggled:', this.sidebarOpen)
    },

    /**
     * Colapsa/expande el sidebar
     */
    toggleSidebarCollapse() {
      this.sidebarCollapsed = !this.sidebarCollapsed
      Logger.debug('[UIStore] Sidebar collapse toggled:', this.sidebarCollapsed)
    },

    /**
     * Muestra el loading global
     */
    showGlobalLoading(message = 'Cargando...') {
      this.globalLoading = true
      this.loadingMessage = message
      Logger.debug('[UIStore] Global loading shown:', message)
    },

    /**
     * Oculta el loading global
     */
    hideGlobalLoading() {
      this.globalLoading = false
      this.loadingMessage = ''
      Logger.debug('[UIStore] Global loading hidden')
    }
  }
})
