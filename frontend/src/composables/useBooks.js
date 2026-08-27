import { useSessionsStore } from '@/store/sessions'
import { useAuthStore } from '@/store/auth'
import { createMediaComposable } from './createMediaComposable'
import { useConfirmationModal } from './useConfirmationModal'
import Logger from '@/utils/logger'

/**
 * Composable de libros.
 *
 * El núcleo lo genera `createMediaComposable` desde `mediaRegistry`. Lo que se
 * queda aquí es lo que **solo** tiene este medio y alguien consume: el progreso
 * de lectura y la máquina de transiciones de estado que lo coordina con las
 * sesiones. No se generaliza porque ningún otro medio tiene nada equivalente.
 *
 * Las sesiones **no** se reexportan desde aquí: viven en `useReadingSessions` y
 * `useReadingProgress`, que es lo que consumen `SessionHistoryModal` y
 * `ReadingProgressHistory`.
 *
 * `updateBookStatuses` **sustituye** al del núcleo (que es una delegación de
 * tres líneas): los extras se mezclan después, así que ganan.
 */
export function useBooks() {
  return createMediaComposable('book', ({ books }, booksStore) => {
    const sessionsStore = useSessionsStore()
    const authStore = useAuthStore()

    /**
     * Actualiza el progreso de lectura. El backend puede tocar los estados de
     * rebote (añadir 'reading' al empezar, 'read' al llegar a la última
     * página), así que la respuesta puede traer estados nuevos.
     */
    const updateReadingProgress = async (isbn, currentPage) => {
      try {
        const response = await authStore.authenticatedApiCall('update_reading_progress', {
          isbn,
          currentPage
        })

        if (response.data.status === 'success') {
          const data = response.data.data || {}
          const book = books.value.find(b => b.isbn === isbn)
          if (book) {
            book.current_page = currentPage
            book.currentPage = currentPage
            if (Array.isArray(data.updatedStatuses)) {
              book.userStatuses = data.updatedStatuses
              Logger.debug('[useBooks] Book statuses updated from progress:', data.updatedStatuses)
            }
          }
          return { success: true, data }
        }
        throw new Error(response.data.message || 'Error updating progress')
      } catch (err) {
        Logger.error('[useBooks] Error updating reading progress:', err)
        return { success: false, message: err.message }
      }
    }

    /**
     * Actualiza los estados CON lógica de sesiones.
     *
     * Detecta la transición, pide confirmación si hay una sesión abierta que se
     * va a cerrar, delega en el store y notifica. Y si el backend se queja de
     * que falta marcar la última página, ofrece completarla y reintenta.
     */
    const updateBookStatuses = async (isbn, statuses) => {
      try {
        Logger.debug(`[useBooks] Updating book statuses: ${isbn}`, statuses)

        const book = books.value.find(b => b.isbn === isbn)
        if (!book) {
          throw new Error('Book not found')
        }

        const previousStatuses = book.userStatuses || []

        // Detectar transiciones de estado
        const transitions = {
          startedReading: statuses.includes('reading') && !previousStatuses.includes('reading'),
          completedBook: statuses.includes('read') && !previousStatuses.includes('read'),
          pausedBook: statuses.includes('paused') && !previousStatuses.includes('paused'),
          abandonedBook: statuses.includes('abandoned') && !previousStatuses.includes('abandoned')
        }

        // Verificar si hay sesión activa
        const activeSession = sessionsStore.getActiveSessionByBook(isbn)
        let sessionInfo = null

        if (activeSession) {
          sessionInfo = {
            hasActiveSession: true,
            sessionNumber: activeSession.session_number || 1,
            currentPage: book.current_page || 0,
            totalPages: book.pages || 0,
            startedAt: activeSession.started_at
          }
        }

        // Confirmar cambios críticos si hay sesión activa
        if ((transitions.completedBook || transitions.abandonedBook) && sessionInfo) {
          const { confirmStatusChangeWithSession } = useConfirmationModal()
          const newStatus = transitions.completedBook ? 'read' : 'abandoned'

          const confirmed = await confirmStatusChangeWithSession(
            book.title,
            newStatus,
            sessionInfo
          )

          if (!confirmed) {
            Logger.debug('[useBooks] Status change cancelled by user')
            return { success: false, cancelled: true }
          }
        }

        // Delegar actualización al store
        const result = await booksStore.updateBookStatuses(isbn, statuses)

        // NOTIFICACIONES AUTOMÁTICAS (lógica de UI)
        if (result.success) {
          const { useSessionFeedback } = await import('./useSessionFeedback')
          const sessionFeedback = useSessionFeedback()

          if (transitions.startedReading) {
            sessionFeedback.notifyAutoSessionStart(book.title)
          }
          if (transitions.completedBook) {
            sessionFeedback.notifyAutoSessionComplete(book.title)
          }
          if (transitions.pausedBook) {
            sessionFeedback.notifyAutoSessionPause(book.title)
          }
          if (transitions.abandonedBook) {
            sessionFeedback.notifyAutoSessionAbandoned(book.title)
          }
        }

        return result
      } catch (err) {
        // Validación especial para error de página incompleta
        if (err.message && err.message.includes('Debes marcar la última página')) {
          const { confirm } = useConfirmationModal()
          const currentBook = books.value.find(b => b.isbn === isbn)

          const match = err.message.match(/página \((\d+)\)/)
          const lastPage = match ? parseInt(match[1]) : (currentBook?.pages || 0)

          const confirmed = await confirm(
            'Completar última página',
            `${err.message}\n\n¿Deseas actualizar automáticamente a la página ${lastPage} y marcar el libro como leído?`,
            {
              confirmText: 'Sí, actualizar y completar',
              cancelText: 'Cancelar',
              type: 'warning'
            }
          )

          if (confirmed) {
            await updateReadingProgress(isbn, lastPage)
            return await updateBookStatuses(isbn, statuses)
          } else {
            return { success: false, cancelled: true }
          }
        }

        Logger.error('[useBooks] Error updating book statuses:', err)
        return { success: false, message: err.message }
      }
    }

    return {
      // Sustituye la delegación de tres líneas del núcleo.
      updateBookStatuses,

      // La única de progreso con consumidor: `EditItemModal.vue:630`, al
      // guardar. Las ocho que la acompañaban aquí —el resto del progreso y los
      // reenvíos al `sessions` store— no las llamaba nadie y las sesiones se
      // usan por `useReadingSessions` y `useReadingProgress`, que es donde
      // viven de verdad.
      updateReadingProgress
    }
  })
}
