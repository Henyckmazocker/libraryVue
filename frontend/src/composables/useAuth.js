import { ref, computed, watch } from 'vue';
import { useAuthStore } from '@/store/auth';
import Logger from '@/utils/logger';

/**
 * Composable principal para autenticación
 * Proporciona una interfaz reactiva y reutilizable para la gestión de autenticación
 */
export function useAuth() {
  const authStore = useAuthStore();
  
  // Estados reactivos
  const isLoading = ref(false);
  const error = ref(null);
  const lastLoginAttempt = ref(null);

  // Computed properties para facilitar el acceso a datos del store
  const user = computed(() => authStore.user);
  const isAuthenticated = computed(() => authStore.isAuthenticated);
  const isLoggedIn = computed(() => authStore.isLoggedIn);
  const userName = computed(() => authStore.userName);
  const userEmail = computed(() => authStore.userEmail);
  const userPicture = computed(() => authStore.userPicture);
  const csrfToken = computed(() => authStore.csrfToken);
  const jwtToken = computed(() => authStore.jwtToken);

  /**
   * Inicializa la autenticación verificando el estado actual
   */
  const initializeAuth = async () => {
    isLoading.value = true;
    error.value = null;
    
    try {
      Logger.auth('[useAuth] Initializing authentication...');
      await authStore.initializeAuth();
      Logger.auth('[useAuth] Authentication initialized successfully');
    } catch (err) {
      error.value = err.message || 'Failed to initialize authentication';
      Logger.error('[useAuth] Failed to initialize authentication:', err);
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Realiza login con token de Google
   * @param {string} googleToken - Token de Google OAuth
   * @returns {Promise<{success: boolean, message?: string}>}
   */
  const login = async (googleToken) => {
    if (!googleToken) {
      const message = 'Google token is required';
      error.value = message;
      return { success: false, message };
    }

    isLoading.value = true;
    error.value = null;
    lastLoginAttempt.value = new Date();

    try {
      Logger.auth('[useAuth] Attempting login...');
      const result = await authStore.login(googleToken);
      
      if (result.success) {
        Logger.auth('[useAuth] Login successful');
        return { success: true };
      } else {
        error.value = result.message || 'Login failed';
        Logger.auth('[useAuth] Login failed:', result.message);
        return { success: false, message: result.message };
      }
    } catch (err) {
      const message = err.message || 'An unexpected error occurred during login';
      error.value = message;
      Logger.error('[useAuth] Login error:', err);
      return { success: false, message };
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Realiza logout del usuario
   */
  const logout = async () => {
    isLoading.value = true;
    error.value = null;

    try {
      Logger.auth('[useAuth] Logging out...');
      await authStore.logout();
      Logger.auth('[useAuth] Logout successful');
    } catch (err) {
      error.value = err.message || 'Failed to logout';
      Logger.error('[useAuth] Logout error:', err);
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Realiza una llamada API autenticada
   * @param {string} action - Acción a realizar
   * @param {Object} data - Datos para enviar
   * @returns {Promise<any>} - Respuesta de la API
   */
  const authenticatedApiCall = async (action, data = {}) => {
    try {
      return await authStore.authenticatedApiCall(action, data);
    } catch (err) {
      // Maneja errores de autenticación
      if (authStore.handleAuthError(err)) {
        error.value = 'Session expired. Please login again.';
      } else {
        error.value = err.message || 'API call failed';
      }
      throw err;
    }
  };

  /**
   * Actualiza el token CSRF
   * @param {string} token - Nuevo token CSRF
   */
  const updateCSRFToken = (token) => {
    authStore.updateCSRFToken(token);
  };

  /**
   * Limpia los errores
   */
  const clearError = () => {
    error.value = null;
  };

  /**
   * Verifica si el usuario tiene permisos para una acción específica
   * @param {string} permission - Permiso a verificar
   * @returns {boolean}
   */
  const hasPermission = (permission) => {
    if (!isAuthenticated.value) return false;
    
    // Por ahora todos los usuarios autenticados tienen los mismos permisos
    // En el futuro esto se puede expandir basado en roles/permisos del usuario
    const basicPermissions = [
      'read_books',
      'write_books', 
      'delete_books',
      'read_movies',
      'write_movies',
      'delete_movies',
      'import_data'
    ];
    
    return basicPermissions.includes(permission);
  };

  /**
   * Verifica si el usuario es dueño de un recurso
   * @param {Object} resource - Recurso a verificar
   * @returns {boolean}
   */
  const isOwner = (resource) => {
    if (!isAuthenticated.value || !resource) return false;
    return resource.user_id === user.value?.id;
  };

  // Watch para loggear cambios de estado de autenticación
  watch(isAuthenticated, (newValue, oldValue) => {
    if (newValue !== oldValue) {
      Logger.auth(`[useAuth] Authentication state changed: ${oldValue} -> ${newValue}`);
    }
  });

  // Watch para loggear errores
  watch(error, (newError) => {
    if (newError) {
      Logger.error(`[useAuth] Error state updated: ${newError}`);
    }
  });

  return {
    // Estados
    user,
    isAuthenticated,
    isLoggedIn,
    isLoading,
    error,
    userName,
    userEmail,
    userPicture,
    csrfToken,
    jwtToken,
    lastLoginAttempt,

    // Métodos
    initializeAuth,
    login,
    logout,
    authenticatedApiCall,
    updateCSRFToken,
    clearError,
    hasPermission,
    isOwner
  };
}
