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
      return {
        menu: [
          {
            title: "Principal",
            items: [
              {
                name: "Biblioteca",
                path: "/library",
                icon: "fas fa-bookmark",
                description: "Tu biblioteca personal"
              }
            ]
          },
          {
            title: "Libros",
            items: [
              {
                name: "Buscar Libros",
                path: "/books",
                icon: "fas fa-search",
                description: "Buscar nuevos libros"
              },
              {
                name: "Mis Libros",
                path: "/dashboard/books",
                icon: "fas fa-book",
                description: "Dashboard de tus libros"
              }
            ]
          },
          {
            title: "Películas",
            items: [
              {
                name: "Buscar Películas",
                path: "/movies",
                icon: "fas fa-search",
                description: "Buscar nuevas películas"
              },
              {
                name: "Mis Películas",
                path: "/dashboard/movies",
                icon: "fas fa-film",
                description: "Dashboard de tus películas"
              }
            ]
          },
          {
            title: "Videojuegos",
            items: [
              {
                name: "Buscar Videojuegos",
                path: "/games",
                icon: "fas fa-gamepad",
                description: "Buscar nuevos videojuegos"
              },
              {
                name: "Mis Videojuegos",
                path: "/dashboard/games",
                icon: "fas fa-trophy",
                description: "Dashboard de tus videojuegos"
              }
            ]
          },
          {
            title: "Próximamente",
            items: [
              {
                name: "Música",
                path: "#",
                icon: "fas fa-music",
                description: "Próximamente disponible",
                disabled: true
              }
            ]
          }
        ]
      }
    }
  }
})
