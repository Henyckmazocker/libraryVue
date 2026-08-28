<template>
  <header class="app-header">
    <!-- Logo/Título de la aplicación a la izquierda -->
    <div class="app-header__left">
      <router-link
        to="/"
        class="app-header__logo"
      >
        <i class="fas fa-book-open" />
        <span class="app-header__title">Biblioteca Personal</span>
      </router-link>
    </div>

    <!-- Sección derecha con autenticación -->
    <div class="app-header__right">
      <!-- Botón de cambio de tema -->
      <button
        class="app-header__theme-toggle"
        :title="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        :aria-label="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        @click="toggleTheme"
      >
        <i
          class="fas"
          :class="isDark ? 'fa-sun' : 'fa-moon'"
          aria-hidden="true"
        />
      </button>
      
      <template v-if="!isLoggedIn && !isLoading">
        <!-- Botón nativo Capacitor -->
        <button
          v-if="isNative"
          class="app-header__native-signin"
          @click="nativeSignIn"
        >
          <i class="fab fa-google" />
          Iniciar sesión con Google
        </button>
        <!-- Botón SDK web -->
        <div
          v-else
          id="g_id_signin"
        />
      </template>
      
      <template v-if="isLoading">
        <div class="app-header__loading">
          <i class="fas fa-spinner fa-spin" />
        </div>
      </template>
      
      <template v-if="isLoggedIn">
        <!-- La bandeja. El icono se pinta SIEMPRE y solo el contador aparece y
             desaparece: si se ocultara el icono entero, la cabecera daría un
             salto cada vez que llega o se resuelve una recomendación. -->
        <router-link
          to="/inbox"
          class="app-header__inbox"
        >
          <i
            class="fas fa-inbox"
            aria-hidden="true"
          />
          <span
            v-if="pendingCount > 0"
            class="app-header__inbox-badge"
            aria-hidden="true"
          >{{ pendingCount > 99 ? '99+' : pendingCount }}</span>
          <!-- El texto va en .u-sr-only y no en un aria-label: es la convención
               del proyecto para lo que solo existe como icono. -->
          <span class="u-sr-only">{{ inboxLabel }}</span>
        </router-link>

        <div class="app-header__user-menu">
          <img
            :src="user?.picture"
            alt="Usuario"
            class="app-header__user-avatar"
            loading="lazy"
            decoding="async"
          >
          <span class="app-header__user-name">{{ user?.name }}</span>
          <button
            class="app-header__logout-btn"
            title="Cerrar sesión"
            aria-label="Cerrar sesión"
            @click="handleLogout"
          >
            <i
              class="fas fa-sign-out-alt"
              aria-hidden="true"
            />
          </button>
        </div>
      </template>
      
      <!-- Mostrar errores si los hay -->
      <div
        v-if="error || googleError"
        class="app-header__error"
      >
        {{ error || googleError }}
      </div>
    </div>
  </header>
</template>

<script>
export default {
  name: 'AppHeader'
}
</script>

<script setup>
import { computed, watch, onMounted, defineEmits } from 'vue';
import { useAuth, useGoogleAuth } from '@/composables';
import { useUIStore } from '@/store/ui';
import { useInboxStore } from '@/store/inbox';
import { storeToRefs } from 'pinia';
import Logger from '@/utils/logger';

// Emits
const emit = defineEmits(['logout']);

// Composables
const {
  user,
  isAuthenticated,
  isLoggedIn,
  isLoading,
  error,
  initializeAuth,
  logout,
  clearError
} = useAuth();

const {
  isGoogleReady,
  isNative,
  renderGoogleButton,
  showGoogleOneTap,
  nativeSignIn,
  googleError,
  clearGoogleError
} = useGoogleAuth();

// La bandeja: el contador se pide al montar, y en cada navegación lo refresca la
// suscripción que `main.js` engancha sobre el store.
const inboxStore = useInboxStore();
const { pendingCount } = storeToRefs(inboxStore);

const inboxLabel = computed(() => {
  if (pendingCount.value === 0) return 'Recomendaciones';

  // Concuerda en singular: esto lo lee un lector de pantalla, y «1 pendientes»
  // es justo el tipo de detalle que solo se oye.
  return pendingCount.value === 1
    ? 'Recomendaciones: 1 pendiente'
    : `Recomendaciones: ${pendingCount.value} pendientes`;
});

// UI Store para tema
const uiStore = useUIStore();
const { isDark } = storeToRefs(uiStore);
const { toggleTheme } = uiStore;

// Watch auth state changes for debugging
watch(isAuthenticated, (newVal, oldVal) => {
  Logger.auth('Auth state changed:', oldVal, '->', newVal, 'User:', user.value?.name || 'null');
});

watch(isLoggedIn, (newVal, oldVal) => {
  Logger.auth('IsLoggedIn changed:', oldVal, '->', newVal);
});

// Lifecycle
onMounted(async () => {
  try {
    await initializeAuth();
  } catch (error) {
    Logger.error('[Header] Failed to initialize:', error);
  }

  // DESPUÉS de `initializeAuth`, no antes: `refreshCount` se rinde sin sesión, y
  // pedirlo mientras la auth se resuelve dejaría el contador a cero hasta la
  // primera navegación.
  inboxStore.refreshCount();
});

// Watch para renderizar el botón y mostrar One Tap cuando Google esté listo
// Y la autenticación haya terminado de verificarse
watch([isGoogleReady, isLoading], ([ready, loading]) => {
  if (ready && !loading) {
    const signInButton = document.getElementById('g_id_signin');
    if (signInButton) {
      renderGoogleButton('g_id_signin', {
        theme: 'outline',
        size: 'large',
        shape: 'circle',
        text: 'signin_with',
        logo_alignment: 'left'
      });
    }
    // Mostrar One Tap SOLO después de verificar completamente el estado de auth
    // Esto evita race conditions donde Google se inicializa antes que check_auth responda
    if (!isAuthenticated.value) {
      Logger.auth('[Header] Showing Google One Tap - user confirmed not authenticated');
      showGoogleOneTap();
    } else {
      Logger.auth('[Header] User already authenticated, skipping Google One Tap');
    }
  }
});

// Watch para errores y limpiarlos
watch([error, googleError], ([authError, gError]) => {
  if (authError || gError) {
    Logger.error('[Header] Authentication error:', authError || gError);
    setTimeout(() => {
      clearError();
      clearGoogleError();
    }, 5000);
  }
});

// Métodos
const handleLogout = async () => {
  try {
    await logout();
    emit('logout');
    
    // Redirigir a home después del logout
    if (window.location.pathname !== '/') {
      window.location.href = '/';
    }
  } catch (error) {
    Logger.error('[Header] Logout error:', error);
  }
};
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.app-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 70px;
  padding: 0 20px;
  background: var(--color-background-soft);
  border-bottom: 1px solid var(--color-border);
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  box-shadow: var(--shadow-medium);
}

.app-header__left {
  display: flex;
  align-items: center;
}

.app-header__logo {
  display: flex;
  align-items: center;
  color: var(--color-text);
  text-decoration: none;
  font-size: 18px;
  font-weight: 600;
  transition: var(--transition-fast);
}

.app-header__logo:hover {
  color: var(--color-text-light);
}

.app-header__logo i {
  font-size: 24px;
  margin-right: 12px;
  color: var(--color-highlight);
}

.app-header__title {
  font-size: 18px;
  font-weight: 600;
}

.app-header__right {
  display: flex;
  align-items: center;
  gap: 15px;
}

.app-header__theme-toggle {
  background: var(--color-background-card);
  border: 1px solid var(--color-border);
  color: var(--color-text-dark);
  width: 40px;
  height: 40px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: var(--transition-fast);
  font-size: 18px;
  box-shadow: var(--shadow-light);
}

.app-header__theme-toggle:hover {
  background: var(--color-secondary);
  border-color: var(--color-secondary);
  transform: rotate(20deg) scale(1.05);
  box-shadow: var(--shadow-medium);
}

.app-header__theme-toggle i {
  transition: var(--transition-fast);
}

/* La bandeja. Mismo tamaño y forma que el botón de tema, para que los dos
   controles de la cabecera se lean como una pareja. */
.app-header__inbox {
  position: relative;
  background: var(--color-background-card);
  border: 1px solid var(--color-border);
  color: var(--color-text-dark);
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: var(--transition-fast);
  font-size: 18px;
  box-shadow: var(--shadow-light);
  text-decoration: none;
}

.app-header__inbox:hover {
  background: var(--color-secondary);
  border-color: var(--color-secondary);
  box-shadow: var(--shadow-medium);
}

.app-header__inbox-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  background: var(--color-error);
  color: var(--color-on-status);
  font-size: 11px;
  font-weight: 700;
  line-height: 18px;
  text-align: center;
}

.app-header__loading {
  color: var(--color-text);
  font-size: 18px;
}

.app-header__user-menu {
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--color-background-card);
  padding: 8px 16px;
  border-radius: 20px;
  box-shadow: var(--shadow-medium);
  backdrop-filter: blur(10px);
}

.app-header__user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
}

.app-header__user-name {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-dark);
  max-width: 150px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.app-header__logout-btn {
  background: none;
  border: none;
  color: var(--color-error);
  cursor: pointer;
  padding: 6px;
  border-radius: 4px;
  transition: var(--transition-fast);
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.app-header__logout-btn:hover {
  background-color: var(--color-error-bg);
}

.app-header__error {
  color: var(--color-error);
  background-color: var(--color-error-bg);
  border: 1px solid var(--color-error);
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 12px;
  max-width: 200px;
  word-wrap: break-word;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.fa-spinner.fa-spin {
  animation: spin 1s linear infinite;
}

/* Responsive */
@include responsive-below(md) {
  .app-header {
    padding: 0 15px;
  }
  
  .app-header__title {
    display: none;
  }
  
  .app-header__user-name {
    display: none;
  }
}

.app-header__native-signin {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: var(--color-background-soft);
  border: 1px solid var(--color-border);
  border-radius: 24px;
  color: var(--color-text);
  font-size: 0.9rem;
  cursor: pointer;
  transition: background 0.2s;

  &:active {
    background: var(--color-background-mute);
  }

  i {
    /* stylelint-disable-next-line color-no-hex -- Google: color de marca, drift intencional (styles.md) */
    color: #4285F4;
  }
}
</style>
