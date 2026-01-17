import { storeToRefs } from 'pinia'
import { useMenuStore } from '@/store/menu'
import { computed } from 'vue'

/**
 * Composable para gestión del menú lateral (wrapper ligero de useMenuStore)
 * 
 * REFACTORIZADO: La lógica está en useMenuStore, este es solo un wrapper
 * para mantener compatibilidad con la API anterior
 */
export function useSidebarMenu() {
  const menuStore = useMenuStore()
  
  // ✅ Estado reactivo via storeToRefs
  const { menuData, isLoading, error, menuItems } = storeToRefs(menuStore)
  
  // ✅ Actions del store
  const { loadMenu, reloadMenu, clearError } = menuStore
  
  // Computed properties para compatibilidad
  const hasError = computed(() => error.value !== null)
  
  return {
    // Estado
    menuItems,
    isLoading,
    hasError,
    error,
    menuData,
    
    // Métodos
    loadMenu,
    reloadMenu,
    clearError
  }
}
