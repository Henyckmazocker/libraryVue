<template>
  <header class="app-header">
    <!-- Logo/Título de la aplicación a la izquierda -->
    <div class="app-header__left">
      <router-link to="/" class="app-header__logo">
        <i class="fas fa-book-open"></i>
        <span class="app-header__title">Biblioteca Personal</span>
      </router-link>
    </div>

    <!-- Sección derecha con autenticación -->
    <div class="app-header__right">
      <!-- Botón de cambio de tema -->
      <button @click="toggleTheme" class="app-header__theme-toggle" :title="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'">
        <i class="fas" :class="isDark ? 'fa-sun' : 'fa-moon'"></i>
      </button>
      
      <template v-if="!isLoggedIn && !isLoading">
        <!-- Botón nativo Capacitor -->
        <button v-if="isNative" @click="nativeSignIn" class="app-header__native-signin">
          <i class="fab fa-google"></i>
          Iniciar sesión con Google
        </button>
        <!-- Botón SDK web -->
        <div v-else id="g_id_signin"></div>
      </template>
      
      <template v-if="isLoading">
        <div class="app-header__loading">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
      </template>
      
      <template v-if="isLoggedIn">
        <div class="app-header__user-menu">
          <img :src="user?.picture" alt="Usuario" class="app-header__user-avatar" />
          <span class="app-header__user-name">{{ user?.name }}</span>
          <button @click="handleLogout" class="app-header__logout-btn" title="Cerrar sesión">
            <i class="fas fa-sign-out-alt"></i>
          </button>
        </div>
      </template>
      
      <!-- Mostrar errores si los hay -->
      <div v-if="error || googleError" class="app-header__error">
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
import { watch, onMounted, defineEmits } from 'vue';
import { useAuth, useGoogleAuth } from '@/composables';
import { useUIStore } from '@/store/ui';
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
@media (max-width: 768px) {
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
    color: #4285F4;
  }
}
</style>
