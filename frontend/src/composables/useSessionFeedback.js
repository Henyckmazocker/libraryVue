import { useUIStore } from '@/store/ui';
import Logger from '@/utils/logger';
import { t } from '@/config/i18n';

/**
 * Composable para gestionar feedback visual de sesiones de lectura
 * Proporciona notificaciones automáticas para transiciones de estado
 */
export function useSessionFeedback() {
  const uiStore = useUIStore();
  
  // Helper para mostrar notificaciones con el nuevo formato
  const showNotification = ({ type, title, message, duration }) => {
    uiStore.addNotification({ type, title, message, duration });
  };

  /**
   * Notifica el inicio de una sesión de lectura
   */
  const notifySessionStart = (bookTitle, sessionNumber = 1) => {
    const isFirstReading = sessionNumber === 1;
    
    Logger.info('[useSessionFeedback] Session started notification', { bookTitle, sessionNumber });
    
    showNotification({
      type: 'info',
      title: isFirstReading ? t('sessionFeedback.startTitle') : t('sessionFeedback.reReadTitle'),
      // Una clave por frase entera y no un montaje de trozos: en inglés la
      // primera lectura y la re-lectura no se parten por el mismo sitio.
      message: isFirstReading
        ? t('sessionFeedback.startFirst', { title: bookTitle })
        : t('sessionFeedback.startAgain', { title: bookTitle, n: sessionNumber }),
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
      title: t('sessionFeedback.completeTitle'),
      message: t('sessionFeedback.complete', {
        title: bookTitle, percent: completionPercentage, page: finalPage, total: totalPages
      }),
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
      title: t('sessionFeedback.pauseTitle'),
      message: t('sessionFeedback.pause', { title: bookTitle, page: currentPage }),
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
      title: t('sessionFeedback.abandonTitle'),
      message: t('sessionFeedback.abandon', { title: bookTitle, percent: readPercentage }),
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
        title: t('sessionFeedback.milestoneTitle', { percent: progressPercentage }),
        message: [
          t('sessionFeedback.milestone', { page: currentPage, total: totalPages, title: bookTitle }),
          pagesAdvanced > 0 ? t('sessionFeedback.milestoneAdvanced', { n: pagesAdvanced }) : ''
        ].filter(Boolean).join(' '),
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
      title: t('sessionFeedback.autoStartTitle'),
      message: t('sessionFeedback.autoStart', { title: bookTitle }),
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
      title: t('sessionFeedback.autoCompleteTitle'),
      message: t('sessionFeedback.autoComplete', { title: bookTitle }),
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
      title: t('sessionFeedback.autoPauseTitle'),
      message: t('sessionFeedback.autoPause', { title: bookTitle }),
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
      title: t('sessionFeedback.autoAbandonTitle'),
      message: t('sessionFeedback.autoAbandon', { title: bookTitle }),
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
