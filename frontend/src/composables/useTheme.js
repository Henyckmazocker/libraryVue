import { storeToRefs } from 'pinia'
import { useUIStore } from '@/store/ui'
import { onMounted, onUnmounted } from 'vue'

/**
 * Composable para gestión de tema (wrapper ligero de useUIStore)
 * 
 * REFACTORIZADO: La lógica está en useUIStore, este es solo un wrapper
 * para mantener compatibilidad con la API anterior
 * 
 * @deprecated Considerar migrar a useUIStore directamente
 */
export function useTheme() {
  const uiStore = useUIStore()
  
  // ✅ Estado reactivo via storeToRefs
  const { theme, isDark, isDarkTheme } = storeToRefs(uiStore)
  
  // ✅ Actions del store
  const { toggleTheme, setTheme, loadTheme, initSystemThemeListener, removeSystemThemeListener, applyTheme } = uiStore
  
  // Inicializar tema y listeners al montar
  onMounted(() => {
    // Si el tema no está inicializado, cargarlo
    if (!theme.value) {
      loadTheme()
    }
    initSystemThemeListener()
  })
  
  // Limpiar listeners al desmontar
  onUnmounted(() => {
    removeSystemThemeListener()
  })
  
  return {
    // Estado
    isDark,
    isDarkTheme,
    theme,
    
    // Métodos
    toggleTheme,
    setTheme,
    loadTheme,
    applyTheme
  }
}
