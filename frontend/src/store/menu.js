import { defineStore } from 'pinia'
import Logger from '@/utils/logger'

export const useMenuStore = defineStore('menu', {
  state: () => ({
    menuData: null,
    isLoading: false,
    error: null
  }),

  getters: {
    /**
     * Obtiene los items del menú
     */
    menuItems: (state) => state.menuData?.menu || [],
    
    /**
     * Verifica si hay error
     */
    hasError: (state) => state.error !== null,
    
    /**
     * Obtiene el número de secciones del menú
     */
    menuSectionsCount: (state) => state.menuData?.menu?.length || 0,
    
    /**
     * Obtiene todos los items de menú aplanados
     */
    flatMenuItems: (state) => {
      if (!state.menuData?.menu) return []
      
      return state.menuData.menu.reduce((acc, section) => {
        return acc.concat(section.items || [])
      }, [])
    },
    
    /**
     * Obtiene items de menú habilitados
     */
    enabledMenuItems: (state, getters) => {
      return getters.flatMenuItems.filter(item => !item.disabled)
    }
  },

  actions: {
    /**
     * Carga el menú desde el archivo de configuración
     */
    async loadMenu() {
      // Si ya está cargado, retornar directamente
      if (this.menuData) {
        Logger.debug('[MenuStore] Menu already loaded')
        return { success: true, data: this.menuData }
      }

      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[MenuStore] Loading menu from config...')
        
        // Cargar el archivo JSON desde la carpeta public
        const response = await fetch('/config/sidebar-menu.json')
        
        if (!response.ok) {
          throw new Error(`Error loading menu: ${response.status}`)
        }
        
        const menuConfig = await response.json()
        this.menuData = menuConfig
        
        Logger.debug('[MenuStore] Menu loaded successfully:', menuConfig)
        return { success: true, data: menuConfig }
      } catch (err) {
        this.error = err.message
        Logger.error('[MenuStore] Error loading sidebar menu:', err)
        
        // Fallback al menú hardcodeado si falla la carga
        const fallbackConfig = this._getFallbackMenu()
        this.menuData = fallbackConfig
        
        Logger.warn('[MenuStore] Using fallback menu configuration')
        return { success: false, data: fallbackConfig, error: err.message }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Recarga el menú forzosamente
     */
    async reloadMenu() {
      this.menuData = null
      return await this.loadMenu()
    },

    /**
     * Limpia el error actual
     */
    clearError() {
      this.error = null
    },

    /**
     * Obtiene el menú fallback hardcodeado
     * @private
     */
    _getFallbackMenu() {
      // Por clave y no por texto, igual que el JSON servido: si el `fetch` falla
      // con la app en inglés, el menú de reserva no puede salir en español.
      return {
        menu: [
          {
            titleKey: "menu.sections.main",
            items: [
              {
                nameKey: "menu.library.name",
                path: "/library",
                icon: "fas fa-bookmark",
                descriptionKey: "menu.library.hint"
              }
            ]
          },
          {
            titleKey: "menu.sections.books",
            items: [
              {
                nameKey: "menu.searchBooks.name",
                path: "/books",
                icon: "fas fa-search",
                descriptionKey: "menu.searchBooks.hint"
              },
              {
                nameKey: "menu.myBooks.name",
                path: "/dashboard/books",
                icon: "fas fa-book",
                descriptionKey: "menu.myBooks.hint"
              }
            ]
          },
          {
            titleKey: "menu.sections.movies",
            items: [
              {
                nameKey: "menu.searchMovies.name",
                path: "/movies",
                icon: "fas fa-search",
                descriptionKey: "menu.searchMovies.hint"
              },
              {
                nameKey: "menu.myMovies.name",
                path: "/dashboard/movies",
                icon: "fas fa-film",
                descriptionKey: "menu.myMovies.hint"
              }
            ]
          },
          {
            titleKey: "menu.sections.games",
            items: [
              {
                nameKey: "menu.searchGames.name",
                path: "/games",
                icon: "fas fa-gamepad",
                descriptionKey: "menu.searchGames.hint"
              },
              {
                nameKey: "menu.myGames.name",
                path: "/dashboard/games",
                icon: "fas fa-trophy",
                descriptionKey: "menu.myGames.hint"
              }
            ]
          },
          {
            titleKey: "menu.sections.videos",
            items: [
              {
                nameKey: "menu.searchVideos.name",
                path: "/videos",
                icon: "fab fa-youtube",
                descriptionKey: "menu.searchVideos.hint"
              },
              {
                nameKey: "menu.myVideos.name",
                path: "/dashboard/videos",
                icon: "fas fa-film",
                descriptionKey: "menu.myVideos.hint"
              }
            ]
          },
          {
            titleKey: "menu.sections.soon",
            items: [
              {
                nameKey: "menu.music.name",
                path: "#",
                icon: "fas fa-music",
                descriptionKey: "menu.music.hint",
                disabled: true
              }
            ]
          }
        ]
      }
    }
  }
})
