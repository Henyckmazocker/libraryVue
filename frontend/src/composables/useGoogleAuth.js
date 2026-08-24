import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Capacitor } from '@capacitor/core';
import { GoogleAuth } from '@codetrix-studio/capacitor-google-auth';
import { useAuth } from './useAuth';
import { useAuthStore } from '@/store/auth';
import Logger from '@/utils/logger';

/**
 * Composable para manejar Google OAuth
 * Proporciona funcionalidades específicas para autenticación con Google
 */
export function useGoogleAuth() {
  const { login, isLoading, error } = useAuth();
  const authStore = useAuthStore();
  
  // Estados específicos de Google OAuth
  const isGoogleSDKLoaded = ref(false);
  const isGoogleInitialized = ref(false);
  const googleError = ref(null);
  const googleCredential = ref(null);

  // Google OAuth configuration
  const GOOGLE_CLIENT_ID = process.env.VUE_APP_GOOGLE_CLIENT_ID;
  
  // Silently disable Google auth if client ID not configured

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

      // --- Plataforma nativa (Capacitor) ---
      if (Capacitor.isNativePlatform()) {
        GoogleAuth.initialize({
          clientId: GOOGLE_CLIENT_ID,
          scopes: ['profile', 'email'],
          grantOfflineAccess: false,
        });
        isGoogleSDKLoaded.value = true;
        isGoogleInitialized.value = true;
        Logger.auth('[useGoogleAuth] Google OAuth initialized (native)');
        return;
      }
      
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
        auto_select: true, // Auto-login con cuenta detectada en navegador
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
   * Sign-In nativo para Capacitor (Android/iOS).
   * Lanza el selector de cuentas de Google del SO.
   */
  const nativeSignIn = async () => {
    try {
      Logger.auth('[useGoogleAuth] Starting native Google Sign-In...');
      const googleUser = await GoogleAuth.signIn();
      const idToken = googleUser.authentication?.idToken;

      if (!idToken) {
        throw new Error('No idToken received from native Google Sign-In');
      }

      googleCredential.value = idToken;
      const result = await login(idToken);

      if (!result.success) {
        throw new Error(result.message || 'Login failed');
      }

      Logger.auth('[useGoogleAuth] Native Google Sign-In successful');
    } catch (err) {
      googleError.value = err.message;
      Logger.error('[useGoogleAuth] Native Google Sign-In error:', err);
    }
  };

  /**
   * Maneja la respuesta de Google OAuth (flujo web)
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
    // En plataforma nativa no se usa el botón del SDK web
    if (Capacitor.isNativePlatform()) return;

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
    // One Tap no está disponible en plataforma nativa
    if (Capacitor.isNativePlatform()) return;

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

  const isNative = computed(() => Capacitor.isNativePlatform());

  const hasGoogleError = computed(() => 
    googleError.value !== null || error.value !== null
  );

  const googleErrorMessage = computed(() => 
    googleError.value || error.value
  );

  /**
   * El SDK solo se pide cuando va a servir para algo: si el usuario ya tiene
   * sesión no hay botón que pintar, así que cargarlo era una petición a
   * accounts.google.com en cada visita a cambio de nada. En nativo se inicializa
   * de todas formas porque ahí no hay SDK web: `initializeGoogleAuth` corta en
   * su rama de Capacitor sin tocar la red.
   *
   * `_authChecked` es la señal de que `check_auth` ya respondió; sin esperarla,
   * `isAuthenticated` todavía es `false` y se cargaría el SDK a todo el mundo.
   */
  const shouldInitialize = () =>
    Capacitor.isNativePlatform() ||
    (authStore._authChecked && !authStore.isAuthenticated);

  const initializeIfNeeded = async () => {
    if (isGoogleInitialized.value || !shouldInitialize()) return;

    try {
      await initializeGoogleAuth();
    } catch (err) {
      Logger.error('[useGoogleAuth] Failed to initialize on mount:', err);
    }
  };

  // Lifecycle hooks
  onMounted(initializeIfNeeded);

  // La comprobación de sesión es asíncrona: cuando responda, se decide.
  watch(
    () => [authStore._authChecked, authStore.isAuthenticated],
    initializeIfNeeded
  );

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
    nativeSignIn,
    renderGoogleButton,
    showGoogleOneTap,
    cancelGoogleOneTap,
    disableGoogleAutoSelect,
    clearGoogleError,
    
    // Configuración
    isNative,
    GOOGLE_CLIENT_ID: computed(() => GOOGLE_CLIENT_ID)
  };
}
