import { computed, ref, watch } from 'vue';
import { useAuth } from './useAuth';
import { useRouter, useRoute } from 'vue-router';
import Logger from '@/utils/logger';

/**
 * Composable para manejo de permisos y rutas protegidas
 * Proporciona funcionalidades para controlar el acceso basado en autenticación y permisos
 */
export function usePermissions() {
  const { isAuthenticated, user, hasPermission } = useAuth();
  const router = useRouter();
  const route = useRoute();
  
  // Estados para control de acceso
  const redirectAfterLogin = ref(null);
  const accessDeniedReason = ref(null);

  /**
   * Definición de permisos del sistema
   */
  const PERMISSIONS = {
    // Permisos de libros
    READ_BOOKS: 'read_books',
    WRITE_BOOKS: 'write_books',
    DELETE_BOOKS: 'delete_books',
    
    // Permisos de películas
    READ_MOVIES: 'read_movies',
    WRITE_MOVIES: 'write_movies',
    DELETE_MOVIES: 'delete_movies',
    
    // Permisos de datos
    IMPORT_DATA: 'import_data',
    EXPORT_DATA: 'export_data',
    
    // Permisos de administración
    ADMIN_USERS: 'admin_users',
    ADMIN_SYSTEM: 'admin_system'
  };

  /**
   * Definición de roles del sistema
   */
  const ROLES = {
    GUEST: 'guest',
    USER: 'user',
    ADMIN: 'admin'
  };

  /**
   * Rutas que requieren autenticación
   */
  const protectedRoutes = [
    '/library',
    '/books',
    '/movies',
    '/import',
    '/profile',
    '/settings'
  ];

  /**
   * Rutas que requieren permisos específicos
   */
  const permissionRoutes = {
    '/books': [PERMISSIONS.READ_BOOKS],
    '/movies': [PERMISSIONS.READ_MOVIES],
    '/import': [PERMISSIONS.IMPORT_DATA],
    '/admin': [PERMISSIONS.ADMIN_SYSTEM]
  };

  /**
   * Obtiene el rol actual del usuario
   */
  const getCurrentRole = computed(() => {
    if (!isAuthenticated.value) return ROLES.GUEST;
    
    // Por ahora todos los usuarios autenticados son 'user'
    // En el futuro esto se puede expandir basado en la información del usuario
    return user.value?.role || ROLES.USER;
  });

  /**
   * Verifica si el usuario tiene un rol específico
   * @param {string} role - Rol a verificar
   * @returns {boolean}
   */
  const hasRole = (role) => {
    const currentRole = getCurrentRole.value;
    
    // Los administradores tienen acceso a todo
    if (currentRole === ROLES.ADMIN) return true;
    
    return currentRole === role;
  };

  /**
   * Verifica si el usuario puede acceder a una ruta
   * @param {string} path - Ruta a verificar
   * @returns {boolean}
   */
  const canAccessRoute = (path) => {
    // Rutas públicas siempre accesibles
    if (!protectedRoutes.some(route => path.startsWith(route))) {
      return true;
    }

    // Verificar autenticación para rutas protegidas
    if (!isAuthenticated.value) {
      accessDeniedReason.value = 'Authentication required';
      return false;
    }

    // Verificar permisos específicos de la ruta
    const requiredPermissions = permissionRoutes[path];
    if (requiredPermissions) {
      const hasAllPermissions = requiredPermissions.every(permission => 
        hasPermission(permission)
      );
      
      if (!hasAllPermissions) {
        accessDeniedReason.value = `Missing required permissions: ${requiredPermissions.join(', ')}`;
        return false;
      }
    }

    accessDeniedReason.value = null;
    return true;
  };

  /**
   * Verifica si el usuario puede realizar una acción específica
   * @param {string} action - Acción a verificar
   * @param {Object} resource - Recurso opcional sobre el que se realiza la acción
   * @returns {boolean}
   */
  const canPerformAction = (action, resource = null) => {
    // Verificar autenticación
    if (!isAuthenticated.value) {
      return false;
    }

    // Mapeo de acciones a permisos
    const actionPermissions = {
      'create_book': [PERMISSIONS.WRITE_BOOKS],
      'edit_book': [PERMISSIONS.WRITE_BOOKS],
      'delete_book': [PERMISSIONS.DELETE_BOOKS],
      'view_books': [PERMISSIONS.READ_BOOKS],
      
      'create_movie': [PERMISSIONS.WRITE_MOVIES],
      'edit_movie': [PERMISSIONS.WRITE_MOVIES],
      'delete_movie': [PERMISSIONS.DELETE_MOVIES],
      'view_movies': [PERMISSIONS.READ_MOVIES],
      
      'import_data': [PERMISSIONS.IMPORT_DATA],
      'export_data': [PERMISSIONS.EXPORT_DATA]
    };

    const requiredPermissions = actionPermissions[action];
    if (!requiredPermissions) {
      Logger.warn(`[usePermissions] Unknown action: ${action}`);
      return false;
    }

    // Verificar permisos requeridos
    const hasRequiredPermissions = requiredPermissions.every(permission => 
      hasPermission(permission)
    );

    if (!hasRequiredPermissions) {
      return false;
    }

    // Verificar ownership si el recurso tiene owner
    if (resource && resource.user_id && resource.user_id !== user.value?.id) {
      // Solo el owner o admin puede modificar el recurso
      return hasRole(ROLES.ADMIN);
    }

    return true;
  };

  /**
   * Redirige al usuario después del login
   */
  const redirectAfterAuthentication = () => {
    const targetRoute = redirectAfterLogin.value || route.query.redirect || '/library';
    redirectAfterLogin.value = null;
    
    Logger.auth(`[usePermissions] Redirecting after authentication to: ${targetRoute}`);
    router.push(targetRoute);
  };

  /**
   * Maneja el acceso denegado
   * @param {string} reason - Razón del acceso denegado
   */
  const handleAccessDenied = (reason = null) => {
    accessDeniedReason.value = reason || 'Access denied';
    
    if (!isAuthenticated.value) {
      // Guardar la ruta actual para redirección después del login
      redirectAfterLogin.value = route.fullPath;
      Logger.auth(`[usePermissions] Access denied, redirecting to login. Will redirect back to: ${route.fullPath}`);
      router.push('/');
    } else {
      // Usuario autenticado pero sin permisos
      Logger.warn(`[usePermissions] Permission denied: ${reason}`);
      router.push('/unauthorized');
    }
  };

  /**
   * Guard de navegación para rutas protegidas
   * @param {Object} to - Ruta de destino
   * @returns {boolean|string} - true si permite acceso, string con ruta de redirección si no
   */
  const routeGuard = (to) => {
    const canAccess = canAccessRoute(to.path);
    
    if (!canAccess) {
      if (!isAuthenticated.value) {
        // Guardar ruta para redirección después del login
        redirectAfterLogin.value = to.fullPath;
        return '/';
      } else {
        return '/unauthorized';
      }
    }
    
    return true;
  };

  /**
   * Middleware para verificar permisos en componentes
   * @param {string|Array} requiredPermissions - Permisos requeridos
   * @returns {boolean}
   */
  const requirePermissions = (requiredPermissions) => {
    const permissions = Array.isArray(requiredPermissions) 
      ? requiredPermissions 
      : [requiredPermissions];
    
    const hasAllPermissions = permissions.every(permission => 
      hasPermission(permission)
    );

    if (!hasAllPermissions) {
      handleAccessDenied(`Missing permissions: ${permissions.join(', ')}`);
      return false;
    }

    return true;
  };

  /**
   * Limpia la razón de acceso denegado
   */
  const clearAccessDenied = () => {
    accessDeniedReason.value = null;
  };

  // Estados computados
  const isGuest = computed(() => getCurrentRole.value === ROLES.GUEST);
  const isUser = computed(() => getCurrentRole.value === ROLES.USER);
  const isAdmin = computed(() => getCurrentRole.value === ROLES.ADMIN);

  // Permisos específicos computados para facilitar uso en templates
  const canReadBooks = computed(() => hasPermission(PERMISSIONS.READ_BOOKS));
  const canWriteBooks = computed(() => hasPermission(PERMISSIONS.WRITE_BOOKS));
  const canDeleteBooks = computed(() => hasPermission(PERMISSIONS.DELETE_BOOKS));
  const canReadMovies = computed(() => hasPermission(PERMISSIONS.READ_MOVIES));
  const canWriteMovies = computed(() => hasPermission(PERMISSIONS.WRITE_MOVIES));
  const canDeleteMovies = computed(() => hasPermission(PERMISSIONS.DELETE_MOVIES));
  const canImportData = computed(() => hasPermission(PERMISSIONS.IMPORT_DATA));

  // Watch para redirección automática después del login
  watch(isAuthenticated, (newValue) => {
    if (newValue && redirectAfterLogin.value) {
      setTimeout(() => {
        redirectAfterAuthentication();
      }, 100); // Pequeño delay para asegurar que el estado esté completamente actualizado
    }
  });

  return {
    // Constantes
    PERMISSIONS,
    ROLES,

    // Estados
    getCurrentRole,
    redirectAfterLogin,
    accessDeniedReason,
    isGuest,
    isUser,
    isAdmin,

    // Permisos específicos
    canReadBooks,
    canWriteBooks,
    canDeleteBooks,
    canReadMovies,
    canWriteMovies,
    canDeleteMovies,
    canImportData,

    // Métodos de verificación
    hasRole,
    canAccessRoute,
    canPerformAction,
    requirePermissions,

    // Métodos de navegación
    routeGuard,
    redirectAfterAuthentication,
    handleAccessDenied,
    clearAccessDenied
  };
}
