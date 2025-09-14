import { ref } from 'vue'

/**
 * Composable para manejo centralizado de notificaciones en la librería
 */
export function useLibraryNotifications() {
  const statusMessage = ref('')
  const statusType = ref('')
  
  const showMessage = (message, type = 'info', duration = 3000) => {
    statusMessage.value = message
    statusType.value = type
    
    if (duration > 0) {
      setTimeout(() => clearMessage(), duration)
    }
  }
  
  const clearMessage = () => {
    statusMessage.value = ''
    statusType.value = ''
  }
  
  const showSuccess = (message, duration = 3000) => {
    showMessage(message, 'success', duration)
  }
  
  const showError = (message, duration = 5000) => {
    showMessage(message, 'error', duration)
  }
  
  return { 
    statusMessage, 
    statusType, 
    showMessage, 
    clearMessage,
    showSuccess,
    showError
  }
}
