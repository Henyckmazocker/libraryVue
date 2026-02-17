import { useUIStore } from '@/store/ui'

/**
 * Composable para manejo centralizado de notificaciones en la librería
 * 
 * @deprecated Este composable está DEPRECADO. Usar directamente useUIStore
 * 
 * RAZÓN: useUIStore ya tiene un sistema de notificaciones más completo con:
 * - addNotification(options)
 * - showSuccess(message, title)
 * - showError(message, title)
 * - showWarning(message, title)
 * - showInfo(message, title)
 * - removeNotification(id)
 * - clearAllNotifications()
 * 
 * Este wrapper se mantiene SOLO para compatibilidad temporal.
 * Se recomienda migrar a useUIStore directamente.
 * 
 * MIGRACIÓN:
 * Antes:
 *   const { showSuccess, showError } = useLibraryNotifications()
 * 
 * Después:
 *   const uiStore = useUIStore()
 *   uiStore.showSuccess(message)
 *   uiStore.showError(message)
 */
export function useLibraryNotifications() {
  const uiStore = useUIStore()
  
  // Wrapper para compatibilidad con API anterior (statusMessage/statusType)
  // Nota: Esta API es limitada comparada con useUIStore
  
  /**
   * @deprecated Usar useUIStore.addNotification() o showSuccess/showError/etc
   */
  const showMessage = (message, type = 'info', duration = 3000) => {
    const typeMapping = {
      'info': 'info',
      'success': 'success',
      'error': 'error',
      'warning': 'warning'
    }
    
    uiStore.addNotification({
      type: typeMapping[type] || 'info',
      message,
      duration
    })
  }
  
  /**
   * @deprecated Usar useUIStore.clearAllNotifications()
   */
  const clearMessage = () => {
    uiStore.clearAllNotifications()
  }
  
  /**
   * @deprecated Usar useUIStore.showSuccess()
   */
  const showSuccess = (message) => {
    uiStore.showSuccess(message)
  }
  
  /**
   * @deprecated Usar useUIStore.showError()
   */
  const showError = (message) => {
    uiStore.showError(message)
  }
  
  // Exposición limitada para compatibilidad
  // No incluye statusMessage/statusType porque no son parte del nuevo sistema
  return { 
    showMessage, 
    clearMessage,
    showSuccess,
    showError,
    
    // Helper para facilitar migración
    _uiStore: uiStore // Acceso directo al store para facilitar transición
  }
}

