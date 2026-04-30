import { storeToRefs } from 'pinia';
import { useSessionsStore } from '@/store/sessions';
import { useConfirmationModal } from './useConfirmationModal';
import Logger from '@/utils/logger';

/**
 * Composable para gestión de sesiones de lectura
 * Wrapper ligero del store Pinia useSessionsStore
 * Maneja el ciclo completo de vida de sesiones: crear, pausar, completar, abandonar
 * 
 * REFACTORIZADO: La lógica de negocio está en el store, aquí solo helpers de UI específicos por libro
 */
export function useReadingSessions(bookId) {
  const sessionsStore = useSessionsStore();
  const {
    confirmNewReadingSession,
    confirmCompleteBook,
    confirmReset
  } = useConfirmationModal();

  // ✅ Getters específicos para este libro
  const activeSession = storeToRefs(sessionsStore).activeSessions.value[bookId] || null;
  const sessionHistory = storeToRefs(sessionsStore).sessionHistories.value[bookId] || [];

  // Computed properties locales para este libro específico
  const hasActiveSession = !!activeSession;
  
  const currentSessionNumber = activeSession?.session_number || 
    (sessionHistory.length + 1);

  const hasCompletedReading = sessionHistory.some(s => s.status === 'completed');

  const totalCompleted = sessionHistory.filter(s => s.status === 'completed').length;

  const totalSessions = sessionHistory.length;

  const isFirstReading = sessionHistory.length === 0 && !activeSession;

  /**
   * Carga la sesión activa del libro
   */
  const loadActiveSession = async () => {
    const result = await sessionsStore.loadActiveSession(bookId);
    return result;
  };

  /**
   * Carga el historial de sesiones del libro
   */
  const loadHistory = async () => {
    const result = await sessionsStore.loadHistory(bookId);
    return result;
  };

  /**
   * Carga el historial detallado de progreso de páginas del libro
   */
  const loadProgressHistory = async () => {
    const result = await sessionsStore.loadProgressHistory(bookId);
    return result;
  };

  /**
   * Inicia una nueva sesión de lectura CON confirmación
   * Wrapper que añade lógica de confirmación modal
   */
  const start = async (bookInfo, startPage = null) => {
    try {
      // Determinar página inicial
      const initialPage = startPage || bookInfo.current_page || 1;
      
      // Confirmar creación de sesión
      const confirmed = await confirmNewReadingSession(
        bookInfo.title, 
        initialPage,
        isFirstReading ? 'first' : 'rereading'
      );
      
      if (!confirmed) {
        return { success: false, cancelled: true };
      }

      Logger.debug('[useReadingSessions] Creating session via store');
      return await sessionsStore.createSession(bookId, initialPage);
    } catch (err) {
      Logger.error('[useReadingSessions] Error in start wrapper:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Completa la sesión activa CON confirmación
   */
  const complete = async (bookInfo, endPage, reason = 'completed') => {
    try {
      if (!hasActiveSession) {
        throw new Error('No active session found');
      }

      // Confirmar completación
      const confirmed = await confirmCompleteBook(
        bookInfo.title,
        endPage,
        bookInfo.pages || 0
      );
      
      if (!confirmed) {
        return { success: false, cancelled: true };
      }

      Logger.debug('[useReadingSessions] Completing session via store');
      return await sessionsStore.completeSession(bookId, endPage, reason);
    } catch (err) {
      Logger.error('[useReadingSessions] Error in complete wrapper:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Pausa la sesión activa - Delegación directa
   */
  const pause = async () => {
    return await sessionsStore.pauseSession(bookId);
  };

  /**
   * Reanuda una sesión pausada - Delegación directa
   */
  const resume = async () => {
    return await sessionsStore.resumeSession(bookId);
  };

  /**
   * Abandona la sesión activa CON confirmación
   */
  const abandon = async (bookInfo) => {
    try {
      if (!hasActiveSession) {
        throw new Error('No active session found');
      }

      const confirmed = await confirmReset(
        bookInfo.title,
        'La sesión se marcará como abandonada'
      );
      
      if (!confirmed) {
        return { success: false, cancelled: true };
      }

      Logger.debug('[useReadingSessions] Abandoning session via store');
      return await sessionsStore.abandonSession(bookId);
    } catch (err) {
      Logger.error('[useReadingSessions] Error in abandon wrapper:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Elimina una sesión del historial CON confirmación
   */
  const deleteSession = async (sessionId, sessionInfo) => {
    const { confirmDelete } = useConfirmationModal();
    
    try {
      const sessionLabel = `Sesión #${sessionInfo.session_number || sessionId}`;
      
      const confirmed = await confirmDelete(
        sessionLabel,
        'Esta acción no se puede deshacer'
      );
      
      if (!confirmed) {
        return { success: false, cancelled: true };
      }

      return await sessionsStore.deleteSession(bookId, sessionId);
    } catch (err) {
      Logger.error('[useReadingSessions] Error in deleteSession wrapper:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Actualiza el progreso de lectura con la sesión activa
   */
  const updateProgress = async (currentPage) => {
    return await sessionsStore.updateProgress(bookId, currentPage);
  };

  /**
   * Obtiene información de la sesión activa con datos adicionales
   */
  const getActiveSessionInfo = () => {
    if (!activeSession) return null;
    
    return {
      ...activeSession,
      hasActiveSession: true,
      sessionNumber: activeSession.session_number || 1,
      startPage: activeSession.start_page || 1,
      startedAt: activeSession.started_at,
      status: activeSession.status || 'active'
    };
  };

  /**
   * Obtiene estadísticas de sesiones del libro
   */
  const getSessionStats = () => {
    return {
      total: totalSessions,
      completed: totalCompleted,
      hasCompleted: hasCompletedReading,
      hasActive: hasActiveSession,
      isFirstReading
    };
  };

  return {
    // ===== ESTADO ESPECÍFICO DEL LIBRO =====
    activeSession,
    sessionHistory,
    
    // ===== COMPUTED PROPERTIES =====
    hasActiveSession,
    currentSessionNumber,
    hasCompletedReading,
    totalCompleted,
    totalSessions,
    isFirstReading,

    // ===== MÉTODOS DE SESIÓN (con confirmaciones) =====
    loadActiveSession,                    // Carga sesión activa
    loadHistory,                          // Carga historial
    loadProgressHistory,                  // Carga historial de progreso detallado
    start,                                // Wrapper con confirmación
    complete,                             // Wrapper con confirmación
    pause,                                // Delegación directa
    resume,                               // Delegación directa
    abandon,                              // Wrapper con confirmación
    deleteSession,                        // Wrapper con confirmación
    updateProgress,                       // Delegación directa

    // ===== UTILIDADES =====
    getActiveSessionInfo,                 // Helper de info
    getSessionStats                       // Helper de estadísticas
  };
}
