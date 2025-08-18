import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuth } from './useAuth';
import Logger from '@/utils/logger';

/**
 * Composable para manejar Google OAuth
 * Proporciona funcionalidades específicas para autenticación con Google
 */
export function useGoogleAuth() {
  const { login, isLoading, error } = useAuth();
  
  // Estados específicos de Google OAuth
  const isGoogleSDKLoaded = ref(false);
  const isGoogleInitialized = ref(false);
  const googleError = ref(null);
  const googleCredential = ref(null);

  // Google OAuth configuration
  const GOOGLE_CLIENT_ID = process.env.VUE_APP_GOOGLE_CLIENT_ID;
  
  if (!GOOGLE_CLIENT_ID) {
    console.warn('Google Client ID not found. Google authentication will be disabled.');
  }

  /**
   * Verifica si Google SDK está disponible
   */
  const checkGoogleSDK = () => {
    return typeof window !== 'undefined' && window.google && window.google.accounts;
  };

  /**
   * Carga el SDK de Google OAuth
   */
  const loadGoogleSDK = () => {
    return new Promise((resolve, reject) => {
      if (checkGoogleSDK()) {
        isGoogleSDKLoaded.value = true;
        resolve(true);
        return;
      }

      // Verificar si ya existe el script
      const existingScript = document.querySelector('script[src*="accounts.google.com"]');
      if (existingScript) {
        existingScript.onload = () => {
          isGoogleSDKLoaded.value = true;
          resolve(true);
        };
        existingScript.onerror = () => reject(new Error('Failed to load Google SDK'));
        return;
      }

      // Crear y cargar el script
      const script = document.createElement('script');
      script.src = 'https://accounts.google.com/gsi/client';
      script.async = true;
      script.defer = true;
      
      script.onload = () => {
        Logger.auth('[useGoogleAuth] Google SDK loaded successfully');
        isGoogleSDKLoaded.value = true;
        resolve(true);
      };
      
      script.onerror = () => {
        const error = new Error('Failed to load Google SDK');
        Logger.error('[useGoogleAuth] Failed to load Google SDK:', error);
        reject(error);
      };

      document.head.appendChild(script);
    });
  };

  /**
   * Inicializa Google OAuth
   */
  const initializeGoogleAuth = async () => {
    try {
      if (!GOOGLE_CLIENT_ID) {
        throw new Error('Google Client ID not configured');
      }

      Logger.auth('[useGoogleAuth] Initializing Google OAuth...');
      
      // Cargar SDK si no está cargado
      if (!isGoogleSDKLoaded.value) {
        await loadGoogleSDK();
      }

      // Esperar a que Google SDK esté completamente cargado
      await new Promise((resolve, reject) => {
        const checkInterval = setInterval(() => {
          if (checkGoogleSDK()) {
            clearInterval(checkInterval);
            resolve();
          }
        }, 100);

        // Timeout después de 10 segundos
        setTimeout(() => {
          clearInterval(checkInterval);
          reject(new Error('Google SDK initialization timeout'));
        }, 10000);
      });

      // Inicializar Google Identity Services
      window.google.accounts.id.initialize({
        client_id: GOOGLE_CLIENT_ID,
        callback: handleGoogleResponse,
        auto_select: false,
        cancel_on_tap_outside: true,
        use_fedcm_for_prompt: false
      });

      isGoogleInitialized.value = true;
      Logger.auth('[useGoogleAuth] Google OAuth initialized successfully');

    } catch (err) {
      googleError.value = err.message;
      Logger.error('[useGoogleAuth] Failed to initialize Google OAuth:', err);
      throw err;
    }
  };

  /**
   * Maneja la respuesta de Google OAuth
   * @param {Object} response - Respuesta de Google
   */
  const handleGoogleResponse = async (response) => {
    try {
      Logger.auth('[useGoogleAuth] Google response received');
      
      if (!response.credential) {
        throw new Error('No credential received from Google');
      }

      googleCredential.value = response.credential;
      
      // Usar el composable useAuth para el login
      const result = await login(response.credential);
      
      if (!result.success) {
        throw new Error(result.message || 'Login failed');
      }

      Logger.auth('[useGoogleAuth] Login successful via Google OAuth');
      
    } catch (err) {
      googleError.value = err.message;
      Logger.error('[useGoogleAuth] Google login error:', err);
    }
  };

  /**
   * Renderiza el botón de Google Sign-In
   * @param {string} elementId - ID del elemento donde renderizar el botón
   * @param {Object} options - Opciones de configuración del botón
   */
  const renderGoogleButton = (elementId, options = {}) => {
    if (!isGoogleInitialized.value) {
      Logger.warn('[useGoogleAuth] Google OAuth not initialized');
      return;
    }

    const defaultOptions = {
      theme: 'outline',
      size: 'large',
      text: 'signin_with',
      shape: 'rectangular',
      logo_alignment: 'left',
      width: 250
    };

    const buttonOptions = { ...defaultOptions, ...options };

    try {
      window.google.accounts.id.renderButton(
        document.getElementById(elementId),
        buttonOptions
      );
      Logger.auth(`[useGoogleAuth] Google button rendered in element: ${elementId}`);
    } catch (err) {
      googleError.value = err.message;
      Logger.error('[useGoogleAuth] Failed to render Google button:', err);
    }
  };

  /**
   * Muestra el prompt de Google One Tap
   */
  const showGoogleOneTap = () => {
    if (!isGoogleInitialized.value) {
      Logger.warn('[useGoogleAuth] Google OAuth not initialized');
      return;
    }

    try {
      window.google.accounts.id.prompt((notification) => {
        Logger.auth('[useGoogleAuth] Google One Tap notification:', notification);
        
        if (notification.isNotDisplayed()) {
          Logger.auth('[useGoogleAuth] Google One Tap not displayed:', notification.getNotDisplayedReason());
        } else if (notification.isSkippedMoment()) {
          Logger.auth('[useGoogleAuth] Google One Tap skipped:', notification.getSkippedReason());
        }
      });
    } catch (err) {
      googleError.value = err.message;
      Logger.error('[useGoogleAuth] Failed to show Google One Tap:', err);
    }
  };

  /**
   * Cancela el prompt de Google One Tap
   */
  const cancelGoogleOneTap = () => {
    if (checkGoogleSDK()) {
      window.google.accounts.id.cancel();
      Logger.auth('[useGoogleAuth] Google One Tap cancelled');
    }
  };

  /**
   * Desactiva el auto-select de Google
   */
  const disableGoogleAutoSelect = () => {
    if (checkGoogleSDK()) {
      window.google.accounts.id.disableAutoSelect();
      Logger.auth('[useGoogleAuth] Google auto-select disabled');
    }
  };

  /**
   * Limpia errores específicos de Google
   */
  const clearGoogleError = () => {
    googleError.value = null;
  };

  // Estados computados
  const isGoogleReady = computed(() => 
    isGoogleSDKLoaded.value && isGoogleInitialized.value
  );

  const hasGoogleError = computed(() => 
    googleError.value !== null || error.value !== null
  );

  const googleErrorMessage = computed(() => 
    googleError.value || error.value
  );

  // Lifecycle hooks
  onMounted(async () => {
    try {
      await initializeGoogleAuth();
    } catch (err) {
      Logger.error('[useGoogleAuth] Failed to initialize on mount:', err);
    }
  });

  onUnmounted(() => {
    // Limpiar recursos si es necesario
    cancelGoogleOneTap();
  });

  return {
    // Estados
    isGoogleSDKLoaded,
    isGoogleInitialized,
    isGoogleReady,
    googleError,
    googleCredential,
    hasGoogleError,
    googleErrorMessage,
    isLoading,

    // Métodos
    initializeGoogleAuth,
    renderGoogleButton,
    showGoogleOneTap,
    cancelGoogleOneTap,
    disableGoogleAutoSelect,
    clearGoogleError,
    
    // Configuración
    GOOGLE_CLIENT_ID: computed(() => GOOGLE_CLIENT_ID)
  };
}
