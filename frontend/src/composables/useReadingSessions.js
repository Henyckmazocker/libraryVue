import { ref, computed } from 'vue';
import { useAuth } from './useAuth';
import { useConfirmationModal } from './useConfirmationModal';
import Logger from '@/utils/logger';

/**
 * Composable especializado para gestión de sesiones de lectura
 * Maneja el ciclo completo de vida de sesiones: crear, pausar, completar, abandonar
 */
export function useReadingSessions(bookId) {
  const { authenticatedApiCall } = useAuth();
  const {
    confirmNewReadingSession,
    confirmCompleteBook,
    confirmReset
  } = useConfirmationModal();

  // Estado local
  const activeSession = ref(null);
  const sessionHistory = ref([]);
  const isLoading = ref(false);
  const error = ref(null);

  // Computed properties
  const hasActiveSession = computed(() => !!activeSession.value);
  
  const currentSessionNumber = computed(() => 
    activeSession.value?.session_number || 
    (sessionHistory.value.length + 1)
  );

  const hasCompletedReading = computed(() =>
    sessionHistory.value.some(s => s.status === 'completed')
  );

  const totalCompleted = computed(() =>
    sessionHistory.value.filter(s => s.status === 'completed').length
  );

  const totalSessions = computed(() => sessionHistory.value.length);

  const isFirstReading = computed(() => 
    sessionHistory.value.length === 0 && !activeSession.value
  );

  /**
   * Carga la sesión activa del libro
   */
  const loadActiveSession = async () => {
    try {
      Logger.debug('[useReadingSessions] Loading active session for book:', bookId);
      
      const response = await authenticatedApiCall('get_active_reading_session', {
        isbn: bookId
      });

      if (response.data.status === 'success') {
        activeSession.value = response.data.data || null;
        Logger.debug('[useReadingSessions] Active session loaded:', activeSession.value);
        return { success: true, session: activeSession.value };
      }
    } catch (err) {
      Logger.error('[useReadingSessions] Error loading active session:', err);
      activeSession.value = null;
      return { success: false, message: err.message };
    }
  };

  /**
   * Carga el historial de sesiones del libro
   */
  const loadHistory = async () => {
    try {
      Logger.debug('[useReadingSessions] Loading session history for book:', bookId);
      
      const response = await authenticatedApiCall('get_reading_session_history', {
        isbn: bookId
      });

      if (response.data.status === 'success') {
        sessionHistory.value = response.data.data || [];
        Logger.debug('[useReadingSessions] History loaded:', sessionHistory.value.length, 'sessions');
        return { success: true, history: sessionHistory.value };
      }
    } catch (err) {
      Logger.error('[useReadingSessions] Error loading history:', err);
      sessionHistory.value = [];
      return { success: false, message: err.message };
    }
  };

  /**
   * Inicia una nueva sesión de lectura
   * @param {Object} bookInfo - Información del libro (title, current_page)
   * @param {number} startPage - Página inicial (opcional)
   */
  const start = async (bookInfo, startPage = null) => {
    try {
      isLoading.value = true;
      error.value = null;

      // Determinar página inicial
      const initialPage = startPage || bookInfo.current_page || 1;
      
      // Confirmar creación de sesión
      const confirmed = await confirmNewReadingSession(
        bookInfo.title, 
        initialPage,
        isFirstReading.value ? 'first' : 'rereading'
      );
      
      if (!confirmed) {
        return { success: false, cancelled: true };
      }

      Logger.debug('[useReadingSessions] Creating new session:', { isbn: bookId, startPage: initialPage });
      
      const response = await authenticatedApiCall('create_reading_session', {
        isbn: bookId,
        startPage: initialPage
      });

      Logger.debug('[useReadingSessions] API response:', response.data);

      if (response.data.status === 'success') {
        activeSession.value = response.data.data;
        sessionHistory.value.push(activeSession.value);
        
        Logger.debug('[useReadingSessions] Session created successfully:', activeSession.value);
        return { 
          success: true, 
          session: activeSession.value,
          sessionId: activeSession.value.id 
        };
      } else {
        Logger.error('[useReadingSessions] API returned error:', response.data.message);
        throw new Error(response.data.message || 'Failed to create session');
      }
    } catch (err) {
      error.value = err.message || 'Failed to create session';
      Logger.error('[useReadingSessions] Error creating session:', err);
      return { success: false, message: err.message };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Completa la sesión activa
   * @param {Object} bookInfo - Información del libro
   * @param {number} endPage - Página final
   * @param {string} notes - Notas opcionales
   */
  const complete = async (bookInfo, endPage, notes = '') => {
    if (!activeSession.value) {
      Logger.warn('[useReadingSessions] No active session to complete');
      return { success: false, message: 'No active session' };
    }

    try {
      isLoading.value = true;
      error.value = null;

      // Confirmar completado
      const confirmed = await confirmCompleteBook(bookInfo.title, endPage);
      if (!confirmed) {
        return { success: false, cancelled: true };
      }

      Logger.debug('[useReadingSessions] Completing session:', {
        sessionId: activeSession.value.id,
        endPage,
        notes
      });
      
      const response = await authenticatedApiCall('complete_reading_session', {
        sessionId: activeSession.value.id,
        endPage: endPage,
        notes: notes,
        reason: 'completed'
      });

      if (response.data.status === 'success') {
        // Actualizar sesión en historial
        const sessionIndex = sessionHistory.value.findIndex(
          s => s.id === activeSession.value.id
        );
        if (sessionIndex !== -1) {
          sessionHistory.value[sessionIndex] = {
            ...activeSession.value,
            status: 'completed',
            final_page: endPage,
            completed_at: new Date().toISOString(),
            session_notes: notes
          };
        }
        
        activeSession.value = null;
        
        Logger.debug('[useReadingSessions] Session completed successfully');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to complete session');
      }
    } catch (err) {
      error.value = err.message || 'Failed to complete session';
      Logger.error('[useReadingSessions] Error completing session:', err);
      return { success: false, message: err.message };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Pausa la sesión activa
   */
  const pause = async () => {
    if (!activeSession.value) {
      Logger.warn('[useReadingSessions] No active session to pause');
      return { success: false, message: 'No active session' };
    }

    try {
      isLoading.value = true;
      error.value = null;

      Logger.debug('[useReadingSessions] Pausing session:', activeSession.value.id);
      
      const response = await authenticatedApiCall('pause_reading_session', {
        sessionId: activeSession.value.id
      });

      if (response.data.status === 'success') {
        activeSession.value.status = 'paused';
        
        // Actualizar en historial
        const sessionIndex = sessionHistory.value.findIndex(
          s => s.id === activeSession.value.id
        );
        if (sessionIndex !== -1) {
          sessionHistory.value[sessionIndex].status = 'paused';
        }
        
        Logger.debug('[useReadingSessions] Session paused successfully');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to pause session');
      }
    } catch (err) {
      error.value = err.message || 'Failed to pause session';
      Logger.error('[useReadingSessions] Error pausing session:', err);
      return { success: false, message: err.message };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Abandona la sesión activa
   * @param {string} reason - Razón del abandono (opcional)
   */
  const abandon = async (reason = '') => {
    if (!activeSession.value) {
      Logger.warn('[useReadingSessions] No active session to abandon');
      return { success: false, message: 'No active session' };
    }

    try {
      isLoading.value = true;
      error.value = null;

      Logger.debug('[useReadingSessions] Abandoning session:', activeSession.value.id);
      
      const response = await authenticatedApiCall('abandon_reading_session', {
        sessionId: activeSession.value.id,
        reason: reason
      });

      if (response.data.status === 'success') {
        activeSession.value.status = 'abandoned';
        
        // Actualizar en historial
        const sessionIndex = sessionHistory.value.findIndex(
          s => s.id === activeSession.value.id
        );
        if (sessionIndex !== -1) {
          sessionHistory.value[sessionIndex].status = 'abandoned';
        }
        
        activeSession.value = null;
        
        Logger.debug('[useReadingSessions] Session abandoned successfully');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to abandon session');
      }
    } catch (err) {
      error.value = err.message || 'Failed to abandon session';
      Logger.error('[useReadingSessions] Error abandoning session:', err);
      return { success: false, message: err.message };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Actualiza el progreso de lectura en la sesión activa
   * @param {number} currentPage - Página actual
   */
  const updateProgress = async (currentPage) => {
    if (!activeSession.value) {
      Logger.warn('[useReadingSessions] No active session to update progress');
      return { success: false, message: 'No active session' };
    }

    try {
      Logger.debug('[useReadingSessions] Updating progress:', {
        sessionId: activeSession.value.id,
        currentPage
      });
      
      const response = await authenticatedApiCall('update_reading_progress_with_session', {
        bookId: bookId,
        currentPage: currentPage,
        sessionId: activeSession.value.id
      });

      if (response.data.status === 'success') {
        Logger.debug('[useReadingSessions] Progress updated successfully');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to update progress');
      }
    } catch (err) {
      error.value = err.message || 'Failed to update progress';
      Logger.error('[useReadingSessions] Error updating progress:', err);
      return { success: false, message: err.message };
    }
  };

  /**
   * Reinicia todo el progreso del libro (requiere confirmación)
   * @param {Object} bookInfo - Información del libro
   */
  const resetAll = async (bookInfo) => {
    try {
      isLoading.value = true;
      error.value = null;

      // Confirmar reset
      const changes = [
        `La página actual (${bookInfo.current_page || 0}) volverá a 1`,
        'Se mantendrá el historial de sesiones de lectura',
        hasActiveSession.value ? 'La sesión activa se pausará' : ''
      ].filter(Boolean);

      const confirmed = await confirmReset(bookInfo.title, changes);
      if (!confirmed) {
        return { success: false, cancelled: true };
      }

      Logger.debug('[useReadingSessions] Resetting all progress for book:', bookId);
      
      const response = await authenticatedApiCall('reset_book_progress', {
        bookId: bookId
      });

      if (response.data.status === 'success') {
        // Pausar sesión activa si existe
        if (activeSession.value) {
          activeSession.value.status = 'paused';
          activeSession.value = null;
        }
        
        Logger.debug('[useReadingSessions] Progress reset successfully');
        return { success: true };
      } else {
        throw new Error(response.data.message || 'Failed to reset progress');
      }
    } catch (err) {
      error.value = err.message || 'Failed to reset progress';
      Logger.error('[useReadingSessions] Error resetting progress:', err);
      return { success: false, message: err.message };
    } finally {
      isLoading.value = false;
    }
  };

  // Retornar API pública del composable
  return {
    // Estado
    activeSession,
    sessionHistory,
    isLoading,
    error,
    
    // Computed
    hasActiveSession,
    currentSessionNumber,
    hasCompletedReading,
    totalCompleted,
    totalSessions,
    isFirstReading,
    
    // Métodos
    loadActiveSession,
    loadHistory,
    start,
    complete,
    pause,
    abandon,
    updateProgress,
    resetAll
  };
}
