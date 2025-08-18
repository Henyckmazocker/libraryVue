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
      <template v-if="!isLoggedIn && !isLoading">
        <div id="g_id_signin"></div>
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
  renderGoogleButton,
  googleError,
  clearGoogleError
} = useGoogleAuth();

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
    
    if (isGoogleReady.value) {
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
    }
  } catch (error) {
    Logger.error('[Header] Failed to initialize:', error);
  }
});

// Watch para renderizar el botón cuando Google esté listo
watch(isGoogleReady, (ready) => {
  if (ready) {
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

<style scoped>
.app-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 70px;
  padding: 0 20px;
  background: #1a1a1b;
  border-bottom: 1px solid #343536;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.app-header__left {
  display: flex;
  align-items: center;
}

.app-header__logo {
  display: flex;
  align-items: center;
  color: #d7dadc;
  text-decoration: none;
  font-size: 18px;
  font-weight: 600;
  transition: color 0.2s ease;
}

.app-header__logo:hover {
  color: #ffffff;
}

.app-header__logo i {
  font-size: 24px;
  margin-right: 12px;
  color: #ff6314; /* Color naranja estilo Reddit */
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

.app-header__loading {
  color: #d7dadc;
  font-size: 18px;
}

.app-header__user-menu {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(255, 255, 255, 0.95);
  padding: 8px 16px;
  border-radius: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
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
  color: #18212b;
  max-width: 150px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.app-header__logout-btn {
  background: none;
  border: none;
  color: #dc3545;
  cursor: pointer;
  padding: 6px;
  border-radius: 4px;
  transition: background-color 0.2s;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.app-header__logout-btn:hover {
  background-color: #f8f9fa;
}

.app-header__error {
  color: #dc3545;
  background-color: rgba(220, 53, 69, 0.1);
  border: 1px solid rgba(220, 53, 69, 0.3);
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
</style>
