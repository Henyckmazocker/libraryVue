import { reactive } from 'vue'
import { t } from '@/config/i18n'

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
        title: t('confirm.title'),
        message: t('confirm.message'),
        type: 'warning',
        confirmText: t('confirm.confirm'),
        cancelText: t('common.cancel'),
        processingText: t('confirm.processing'),
        closeOnOverlay: true,
        size: 'medium',
        requiresTextConfirmation: false,
        textConfirmationValue: '',
        textConfirmationLabel: t('confirm.textLabel'),
        textConfirmationPlaceholder: t('confirm.textPlaceholder'),
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
      title: t('confirm.delete.title'),
      message: t('confirm.delete.message', { name: itemName, extra: additionalMessage }),
      type: 'danger',
      confirmText: t('confirm.delete.confirm'),
      details: [t('confirm.irreversible')],
      requiresTextConfirmation: true,
      // La palabra que hay que teclear se traduce con el resto: en inglés se
      // escribe DELETE, y el `placeholder` la nombra a partir de la misma clave.
      textConfirmationValue: t('confirm.delete.word'),
      textConfirmationPlaceholder: t('confirm.delete.placeholder', { word: t('confirm.delete.word') }),
      textConfirmationHint: t('confirm.delete.hint')
    })
  }

  /**
   * Modal de confirmación de reinicio/reset
   */
  const confirmReset = (itemName, additionalDetails = []) => {
    return showConfirmation({
      title: t('confirm.reset.title'),
      message: t('confirm.reset.message', { name: itemName }),
      type: 'warning',
      confirmText: t('confirm.reset.confirm'),
      details: [
        t('confirm.reset.lose'),
        t('confirm.reset.keepHistory'),
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
      title: isReReading ? t('confirm.session.reReadTitle') : t('confirm.session.newTitle'),
      // Una clave por frase entera, y no una plantilla con el trozo variable
      // dentro: en inglés la preposición cambia de sitio y el hueco no cuadra.
      message: isReReading
        ? t('confirm.session.reReadMessage', { title: bookTitle })
        : t('confirm.session.newMessage', { title: bookTitle }),
      type: 'info',
      confirmText: t('confirm.session.confirm'),
      details: isReReading ? [
        t('confirm.session.fromPage', { n: currentPage }),
        t('confirm.session.willCreate'),
        t('confirm.session.keepPrevious')
      ] : [
        t('confirm.session.currentPage', { n: currentPage }),
        t('confirm.session.willCreate'),
        t('confirm.session.canGoBack')
      ]
    })
  }

  /**
   * Modal de confirmación de completar libro
   */
  const confirmCompleteBook = (bookTitle, finalPage) => {
    return showConfirmation({
      title: t('confirm.complete.title'),
      message: t('confirm.complete.message', { title: bookTitle }),
      type: 'success',
      confirmText: t('confirm.complete.confirm'),
      details: [
        t('confirm.complete.finalPage', { n: finalPage }),
        t('confirm.complete.willClose'),
        t('confirm.complete.willMark')
      ]
    })
  }

  /**
   * Modal de confirmación de re-lectura
   */
  const confirmReReading = (bookTitle) => {
    return showConfirmation({
      title: t('confirm.reRead.title'),
      message: t('confirm.reRead.message', { title: bookTitle }),
      type: 'info',
      confirmText: t('confirm.reRead.confirm'),
      cancelText: t('common.cancel'),
      details: [
        t('confirm.reRead.willCreate'),
        t('confirm.reRead.keepPrevious'),
        t('confirm.reRead.fromFirst')
      ]
    })
  }

  /**
   * Modal de confirmación de cambio de estado con impacto en sesión
   */
  const confirmStatusChangeWithSession = (bookTitle, newStatus, sessionData) => {
    // Las claves del mapa son los slugs que manda el backend, y se quedan como
    // están: lo que se traduce es el valor. `'to read'` lleva espacio y en el
    // catálogo es `toRead`, que es la única diferencia entre ambos.
    const statusConfigs = {
      'read': {
        type: 'success',
        title: t('confirm.status.read.title'),
        message: t('confirm.status.read.message', { title: bookTitle }),
        confirmText: t('confirm.status.read.confirm'),
        sessionAction: t('confirm.status.read.action'),
        icon: '✓'
      },
      'paused': {
        type: 'warning',
        title: t('confirm.status.paused.title'),
        message: t('confirm.status.paused.message', { title: bookTitle }),
        confirmText: t('confirm.status.paused.confirm'),
        sessionAction: t('confirm.status.paused.action'),
        icon: '⏸'
      },
      'abandoned': {
        type: 'danger',
        title: t('confirm.status.abandoned.title'),
        message: t('confirm.status.abandoned.message', { title: bookTitle }),
        confirmText: t('confirm.status.abandoned.confirm'),
        sessionAction: t('confirm.status.abandoned.action'),
        icon: '✗'
      },
      'to read': {
        type: 'info',
        title: t('confirm.status.toRead.title'),
        message: t('confirm.status.toRead.message', { title: bookTitle }),
        confirmText: t('confirm.status.toRead.confirm'),
        sessionAction: t('confirm.status.toRead.action'),
        icon: '✗'
      }
    }

    const config = statusConfigs[newStatus] || {
      type: 'warning',
      title: t('confirm.status.other.title'),
      message: t('confirm.status.other.message', { title: bookTitle }),
      confirmText: t('confirm.status.other.confirm'),
      sessionAction: t('confirm.status.other.action'),
      icon: '◉'
    }

    const details = []

    // Información de la sesión actual
    if (sessionData.hasActiveSession) {
      details.push(t('confirm.status.sessionMark', {
        icon: config.icon,
        n: sessionData.sessionNumber,
        action: config.sessionAction.toUpperCase()
      }))
      
      if (sessionData.currentPage && sessionData.totalPages) {
        details.push(t('confirm.status.finalPage', {
          current: sessionData.currentPage,
          total: sessionData.totalPages
        }))
      }

      if (sessionData.startedAt) {
        const daysReading = Math.ceil((new Date() - new Date(sessionData.startedAt)) / (1000 * 60 * 60 * 24))
        // El plural lo resuelve el motor; antes se concatenaba una «s».
        details.push(t('confirm.status.duration', { n: daysReading }))
      }
    }

    // Información adicional según el nuevo estado
    if (newStatus === 'read') {
      details.push(t('confirm.status.markedComplete'))
      if (sessionData.totalCompleted > 0) {
        details.push(t('confirm.status.nthReading', { n: sessionData.totalCompleted + 1 }))
      }
    } else if (newStatus === 'paused') {
      details.push(t('confirm.status.savedProgress'))
      details.push(t('confirm.status.canResume'))
    } else if (newStatus === 'abandoned') {
      details.push(t('confirm.status.lostProgress'))
      details.push(t('confirm.status.keepHistory'))
    } else if (newStatus === 'to read') {
      details.push(t('confirm.status.backToPending'))
      details.push(t('confirm.status.keepHistory'))
    }

    return showConfirmation({
      title: config.title,
      message: config.message,
      type: config.type,
      confirmText: config.confirmText,
      cancelText: t('common.cancel'),
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
      textConfirmationPlaceholder: t('confirm.delete.placeholder', { word: expectedText })
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