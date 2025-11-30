import { reactive } from 'vue'

// Estado global del modal de confirmación
const modalState = reactive({
  isVisible: false,
  isProcessing: false,
  config: {},
  resolvePromise: null,
  rejectPromise: null
})

/**
 * Composable para manejar modales de confirmación de forma reactiva
 * Permite mostrar modales de confirmación desde cualquier componente
 */
export function useConfirmationModal() {
  
  /**
   * Muestra un modal de confirmación
   * @param {Object} config - Configuración del modal
   * @returns {Promise<boolean>} - Promesa que se resuelve con true si confirma, false si cancela
   */
  const showConfirmation = (config) => {
    return new Promise((resolve, reject) => {
      modalState.config = {
        // Valores por defecto
        title: 'Confirmar acción',
        message: '¿Estás seguro de que deseas continuar?',
        type: 'warning',
        confirmText: 'Confirmar',
        cancelText: 'Cancelar',
        processingText: 'Procesando...',
        closeOnOverlay: true,
        size: 'medium',
        requiresTextConfirmation: false,
        textConfirmationValue: '',
        textConfirmationLabel: 'Para confirmar, escribe el texto exacto:',
        textConfirmationPlaceholder: 'Escribe aquí...',
        textConfirmationHint: '',
        details: [],
        // Sobrescribir con la configuración proporcionada
        ...config
      }
      
      modalState.isVisible = true
      modalState.isProcessing = false
      modalState.resolvePromise = resolve
      modalState.rejectPromise = reject
    })
  }

  /**
   * Confirma la acción del modal
   */
  const handleConfirm = () => {
    if (modalState.resolvePromise) {
      modalState.resolvePromise(true)
      closeModal()
    }
  }

  /**
   * Cancela la acción del modal
   */
  const handleCancel = () => {
    if (modalState.resolvePromise) {
      modalState.resolvePromise(false)
      closeModal()
    }
  }

  /**
   * Cierra el modal y limpia el estado
   */
  const closeModal = () => {
    modalState.isVisible = false
    modalState.isProcessing = false
    modalState.config = {}
    modalState.resolvePromise = null
    modalState.rejectPromise = null
  }

  /**
   * Marca el modal como procesando
   */
  const setProcessing = (processing = true) => {
    modalState.isProcessing = processing
  }

  // Métodos de conveniencia para diferentes tipos de confirmación

  /**
   * Modal de confirmación de eliminación
   */
  const confirmDelete = (itemName, additionalMessage = '') => {
    return showConfirmation({
      title: 'Eliminar elemento',
      message: `¿Estás seguro de que deseas eliminar <strong>"${itemName}"</strong>?<br>${additionalMessage}`,
      type: 'danger',
      confirmText: 'Eliminar',
      details: ['Esta acción no se puede deshacer'],
      requiresTextConfirmation: true,
      textConfirmationValue: 'ELIMINAR',
      textConfirmationPlaceholder: 'Escribe "ELIMINAR" para confirmar',
      textConfirmationHint: 'Esta acción es irreversible'
    })
  }

  /**
   * Modal de confirmación de reinicio/reset
   */
  const confirmReset = (itemName, additionalDetails = []) => {
    return showConfirmation({
      title: 'Reiniciar progreso',
      message: `¿Deseas reiniciar el progreso de <strong>"${itemName}"</strong>?`,
      type: 'warning',
      confirmText: 'Reiniciar',
      details: [
        'Se perderá todo el progreso actual',
        'Se mantendrá el historial de sesiones',
        ...additionalDetails
      ]
    })
  }

  /**
   * Modal de confirmación de nueva sesión de lectura
   */
  const confirmNewReadingSession = (bookTitle, currentPage, readingType = 'first') => {
    const isReReading = readingType === 'rereading'
    
    return showConfirmation({
      title: isReReading ? 'Nueva re-lectura' : 'Nueva sesión de lectura',
      message: `¿Deseas iniciar ${isReReading ? 'una re-lectura' : 'una nueva sesión de lectura'} para <strong>"${bookTitle}"</strong>?`,
      type: 'info',
      confirmText: 'Iniciar sesión',
      details: isReReading ? [
        `Comenzarás desde la página ${currentPage}`,
        'Se creará un nuevo registro de sesión',
        'Tu historial de lecturas anteriores se mantendrá'
      ] : [
        `Página actual: ${currentPage}`,
        'Se creará un nuevo registro de sesión',
        'Podrás retroceder en páginas sin perder el historial'
      ]
    })
  }

  /**
   * Modal de confirmación de completar libro
   */
  const confirmCompleteBook = (bookTitle, finalPage) => {
    return showConfirmation({
      title: 'Completar libro',
      message: `¿Has terminado de leer <strong>"${bookTitle}"</strong>?`,
      type: 'success',
      confirmText: 'Marcar como completado',
      details: [
        `Página final: ${finalPage}`,
        'Se cerrará la sesión actual de lectura',
        'El libro se marcará como completado'
      ]
    })
  }

  /**
   * Modal de confirmación de re-lectura
   */
  const confirmReReading = (bookTitle) => {
    return showConfirmation({
      title: 'Releer libro',
      message: `¿Deseas volver a leer <strong>"${bookTitle}"</strong>?`,
      type: 'info',
      confirmText: 'Iniciar re-lectura',
      cancelText: 'Cancelar',
      details: [
        'Se creará una nueva sesión de lectura',
        'Se mantendrá el historial anterior',
        'Comenzarás desde la página 1'
      ]
    })
  }

  /**
   * Modal de confirmación de cambio de estado con impacto en sesión
   */
  const confirmStatusChangeWithSession = (bookTitle, newStatus, sessionData) => {
    const statusConfigs = {
      'read': {
        type: 'success',
        title: 'Marcar como leído',
        message: `¿Deseas marcar <strong>"${bookTitle}"</strong> como leído?`,
        confirmText: 'Marcar como leído',
        sessionAction: 'completada',
        icon: '✓'
      },
      'paused': {
        type: 'warning',
        title: 'Pausar lectura',
        message: `¿Deseas pausar la lectura de <strong>"${bookTitle}"</strong>?`,
        confirmText: 'Pausar lectura',
        sessionAction: 'pausada',
        icon: '⏸'
      },
      'abandoned': {
        type: 'danger',
        title: 'Abandonar libro',
        message: `¿Deseas abandonar <strong>"${bookTitle}"</strong>?`,
        confirmText: 'Abandonar',
        sessionAction: 'abandonada',
        icon: '✗'
      },
      'to read': {
        type: 'info',
        title: 'Cambiar a "Para leer"',
        message: `¿Deseas cambiar <strong>"${bookTitle}"</strong> a "Para leer"?`,
        confirmText: 'Confirmar cambio',
        sessionAction: 'abandonada',
        icon: '✗'
      }
    }

    const config = statusConfigs[newStatus] || {
      type: 'warning',
      title: 'Cambiar estado',
      message: `¿Deseas cambiar el estado de <strong>"${bookTitle}"</strong>?`,
      confirmText: 'Confirmar',
      sessionAction: 'modificada',
      icon: '◉'
    }

    const details = []

    // Información de la sesión actual
    if (sessionData.hasActiveSession) {
      details.push(
        `${config.icon} Sesión actual (#${sessionData.sessionNumber}) se marcará como ${config.sessionAction.toUpperCase()}`
      )
      
      if (sessionData.currentPage && sessionData.totalPages) {
        details.push(`📖 Página final: ${sessionData.currentPage} de ${sessionData.totalPages}`)
      }

      if (sessionData.startedAt) {
        const daysReading = Math.ceil((new Date() - new Date(sessionData.startedAt)) / (1000 * 60 * 60 * 24))
        details.push(`📅 Duración de la sesión: ${daysReading} día${daysReading !== 1 ? 's' : ''}`)
      }
    }

    // Información adicional según el nuevo estado
    if (newStatus === 'read') {
      details.push('📚 El libro se marcará como completado en tu biblioteca')
      if (sessionData.totalCompleted > 0) {
        details.push(`🔄 Esta será tu lectura #${sessionData.totalCompleted + 1} de este libro`)
      }
    } else if (newStatus === 'paused') {
      details.push('💾 Se guardará tu progreso actual')
      details.push('▶️ Podrás reanudar la lectura cuando quieras')
    } else if (newStatus === 'abandoned') {
      details.push('⚠️ El progreso actual se perderá')
      details.push('📋 El historial de sesiones se mantendrá')
    } else if (newStatus === 'to read') {
      details.push('🔄 El libro volverá a tu lista de pendientes')
      details.push('📋 El historial de sesiones se mantendrá')
    }

    return showConfirmation({
      title: config.title,
      message: config.message,
      type: config.type,
      confirmText: config.confirmText,
      cancelText: 'Cancelar',
      details: details,
      size: 'medium'
    })
  }

  /**
   * Modal de confirmación genérica con procesamiento asíncrono
   */
  const confirmAsync = async (config, asyncAction) => {
    try {
      const confirmed = await showConfirmation(config)
      if (confirmed) {
        setProcessing(true)
        const result = await asyncAction()
        setProcessing(false)
        closeModal()
        return result
      }
      return false
    } catch (error) {
      setProcessing(false)
      closeModal()
      throw error
    }
  }

  /**
   * Modal de confirmación con entrada de texto personalizada
   */
  const confirmWithText = (config, expectedText) => {
    return showConfirmation({
      ...config,
      requiresTextConfirmation: true,
      textConfirmationValue: expectedText,
      textConfirmationPlaceholder: `Escribe "${expectedText}" para confirmar`
    })
  }

  return {
    // Estado reactivo
    modalState,
    
    // Métodos principales
    showConfirmation,
    handleConfirm,
    handleCancel,
    closeModal,
    setProcessing,
    
    // Métodos de conveniencia
    confirmDelete,
    confirmReset,
    confirmNewReadingSession,
    confirmCompleteBook,
    confirmReReading,
    confirmStatusChangeWithSession,
    confirmAsync,
    confirmWithText
  }
}

// Instancia singleton para uso global
export const confirmationModal = useConfirmationModal()

// Función helper para uso directo sin composable
export const $confirm = confirmationModal.showConfirmation
export const $confirmDelete = confirmationModal.confirmDelete
export const $confirmReset = confirmationModal.confirmReset
export const $confirmNewSession = confirmationModal.confirmNewReadingSession
export const $confirmComplete = confirmationModal.confirmCompleteBook
export const $confirmReRead = confirmationModal.confirmReReading
export const $confirmStatusChange = confirmationModal.confirmStatusChangeWithSession