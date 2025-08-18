// Re-export de todos los composables de autenticación
// Este archivo facilita las importaciones desde un solo punto

export { useAuth } from './useAuth';
export { useGoogleAuth } from './useGoogleAuth';
export { usePermissions } from './usePermissions';

// Importar composables para el composable combinado
import { useAuth } from './useAuth';
import { useGoogleAuth } from './useGoogleAuth';
import { usePermissions } from './usePermissions';

/**
 * Composable combinado que incluye todas las funcionalidades de autenticación
 * Útil cuando se necesitan múltiples aspectos de autenticación en un solo componente
 */
export function useAuthSystem() {
  const auth = useAuth();
  const googleAuth = useGoogleAuth();
  const permissions = usePermissions();

  return {
    // useAuth
    ...auth,
    
    // useGoogleAuth (con prefijo para evitar conflictos)
    googleAuth: {
      isGoogleSDKLoaded: googleAuth.isGoogleSDKLoaded,
      isGoogleInitialized: googleAuth.isGoogleInitialized,
      isGoogleReady: googleAuth.isGoogleReady,
      googleError: googleAuth.googleError,
      googleCredential: googleAuth.googleCredential,
      hasGoogleError: googleAuth.hasGoogleError,
      googleErrorMessage: googleAuth.googleErrorMessage,
      initializeGoogleAuth: googleAuth.initializeGoogleAuth,
      renderGoogleButton: googleAuth.renderGoogleButton,
      showGoogleOneTap: googleAuth.showGoogleOneTap,
      cancelGoogleOneTap: googleAuth.cancelGoogleOneTap,
      disableGoogleAutoSelect: googleAuth.disableGoogleAutoSelect,
      clearGoogleError: googleAuth.clearGoogleError,
      GOOGLE_CLIENT_ID: googleAuth.GOOGLE_CLIENT_ID
    },
    
    // usePermissions (con prefijo para evitar conflictos)
    permissions: {
      PERMISSIONS: permissions.PERMISSIONS,
      ROLES: permissions.ROLES,
      getCurrentRole: permissions.getCurrentRole,
      redirectAfterLogin: permissions.redirectAfterLogin,
      accessDeniedReason: permissions.accessDeniedReason,
      isGuest: permissions.isGuest,
      isUser: permissions.isUser,
      isAdmin: permissions.isAdmin,
      canReadBooks: permissions.canReadBooks,
      canWriteBooks: permissions.canWriteBooks,
      canDeleteBooks: permissions.canDeleteBooks,
      canReadMovies: permissions.canReadMovies,
      canWriteMovies: permissions.canWriteMovies,
      canDeleteMovies: permissions.canDeleteMovies,
      canImportData: permissions.canImportData,
      hasRole: permissions.hasRole,
      canAccessRoute: permissions.canAccessRoute,
      canPerformAction: permissions.canPerformAction,
      requirePermissions: permissions.requirePermissions,
      routeGuard: permissions.routeGuard,
      redirectAfterAuthentication: permissions.redirectAfterAuthentication,
      handleAccessDenied: permissions.handleAccessDenied,
      clearAccessDenied: permissions.clearAccessDenied
    }
  };
}
