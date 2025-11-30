import { useLibraryNotifications } from './useLibraryNotifications';
import Logger from '@/utils/logger';

/**
 * Composable para gestionar feedback visual de sesiones de lectura
 * Proporciona notificaciones automáticas para transiciones de estado
 */
export function useSessionFeedback() {
  const { showNotification } = useLibraryNotifications();

  /**
   * Notifica el inicio de una sesión de lectura
   */
  const notifySessionStart = (bookTitle, sessionNumber = 1) => {
    const isFirstReading = sessionNumber === 1;
    
    Logger.info('[useSessionFeedback] Session started notification', { bookTitle, sessionNumber });
    
    showNotification({
      type: 'info',
      title: isFirstReading ? '📖 Sesión de lectura iniciada' : '🔄 Re-lectura iniciada',
      message: `Has comenzado ${isFirstReading ? 'a leer' : 'tu re-lectura de'} "${bookTitle}". ${isFirstReading ? 'Se ha creado tu primera sesión de lectura.' : `Esta es tu sesión #${sessionNumber}.`}`,
      duration: 4500
    });
  };

  /**
   * Notifica la finalización de una sesión de lectura
   */
  const notifySessionComplete = (bookTitle, finalPage, totalPages) => {
    const completionPercentage = totalPages > 0 ? Math.round((finalPage / totalPages) * 100) : 100;
    
    Logger.info('[useSessionFeedback] Session completed notification', { bookTitle, finalPage, totalPages });
    
    showNotification({
      type: 'success',
      title: '✅ ¡Libro completado!',
      message: `Has terminado "${bookTitle}" (${completionPercentage}% - página ${finalPage}/${totalPages}). La sesión de lectura se ha marcado como completada.`,
      duration: 6000
    });
  };

  /**
   * Notifica la pausa de una sesión de lectura
   */
  const notifySessionPause = (bookTitle, currentPage) => {
    Logger.info('[useSessionFeedback] Session paused notification', { bookTitle, currentPage });
    
    showNotification({
      type: 'warning',
      title: '⏸ Lectura pausada',
      message: `Has pausado "${bookTitle}" en la página ${currentPage}. Podrás retomar la sesión cuando desees cambiando el estado a "Leyendo".`,
      duration: 4000
    });
  };

  /**
   * Notifica el abandono de una sesión de lectura
   */
  const notifySessionAbandoned = (bookTitle, currentPage, totalPages) => {
    const readPercentage = totalPages > 0 ? Math.round((currentPage / totalPages) * 100) : 0;
    
    Logger.info('[useSessionFeedback] Session abandoned notification', { bookTitle, currentPage, totalPages });
    
    showNotification({
      type: 'warning',
      title: '✗ Lectura abandonada',
      message: `Has abandonado "${bookTitle}" (${readPercentage}% completado). La sesión se ha cerrado pero se mantiene en el historial.`,
      duration: 5000
    });
  };

  /**
   * Notifica actualización de progreso en hitos importantes
   */
  const notifyProgressUpdate = (bookTitle, currentPage, totalPages, pagesAdvanced) => {
    if (totalPages === 0) return;
    
    const progressPercentage = Math.round((currentPage / totalPages) * 100);
    
    // Solo notificar hitos importantes
    if ([25, 50, 75, 90, 100].includes(progressPercentage)) {
      Logger.info('[useSessionFeedback] Progress milestone reached', { bookTitle, progressPercentage });
      
      showNotification({
        type: 'info',
        title: `📊 ${progressPercentage}% completado`,
        message: `Llevas ${currentPage} de ${totalPages} páginas de "${bookTitle}". ${pagesAdvanced > 0 ? `¡Has avanzado ${pagesAdvanced} páginas!` : ''}`,
        duration: 3000
      });
    }
  };

  /**
   * Notifica inicio automático de sesión al cambiar estado
   */
  const notifyAutoSessionStart = (bookTitle) => {
    Logger.info('[useSessionFeedback] Auto session start notification', { bookTitle });
    
    showNotification({
      type: 'info',
      title: '📖 Sesión iniciada automáticamente',
      message: `Al cambiar el estado a "Leyendo", se ha creado automáticamente una sesión de lectura para "${bookTitle}".`,
      duration: 4000
    });
  };

  /**
   * Notifica finalización automática de sesión al cambiar estado
   */
  const notifyAutoSessionComplete = (bookTitle) => {
    Logger.info('[useSessionFeedback] Auto session complete notification', { bookTitle });
    
    showNotification({
      type: 'success',
      title: '✅ Sesión completada automáticamente',
      message: `Al cambiar el estado a "Leído", se ha completado automáticamente la sesión de lectura de "${bookTitle}".`,
      duration: 4000
    });
  };

  /**
   * Notifica pausa automática de sesión al cambiar estado
   */
  const notifyAutoSessionPause = (bookTitle) => {
    Logger.info('[useSessionFeedback] Auto session pause notification', { bookTitle });
    
    showNotification({
      type: 'warning',
      title: '⏸ Sesión pausada automáticamente',
      message: `Al cambiar el estado a "Pausado", se ha pausado automáticamente la sesión de lectura de "${bookTitle}".`,
      duration: 4000
    });
  };

  /**
   * Notifica abandono automático de sesión al cambiar estado
   */
  const notifyAutoSessionAbandoned = (bookTitle) => {
    Logger.info('[useSessionFeedback] Auto session abandoned notification', { bookTitle });
    
    showNotification({
      type: 'warning',
      title: '✗ Sesión abandonada automáticamente',
      message: `Al cambiar el estado a "Abandonado", se ha cerrado automáticamente la sesión de lectura de "${bookTitle}".`,
      duration: 4000
    });
  };

  return {
    notifySessionStart,
    notifySessionComplete,
    notifySessionPause,
    notifySessionAbandoned,
    notifyProgressUpdate,
    notifyAutoSessionStart,
    notifyAutoSessionComplete,
    notifyAutoSessionPause,
    notifyAutoSessionAbandoned
  };
}
