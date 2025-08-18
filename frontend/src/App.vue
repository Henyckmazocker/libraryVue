<template>
  <div id="nav">
    <div class="nav-center">
      <router-link to="/"><i class="fas fa-home"></i></router-link> | 
      <router-link to="/library" v-if="isLoggedIn"><i class="fas fa-bookmark"></i></router-link>
    </div>
    <div class="nav-right">
      <template v-if="!isLoggedIn && !isLoading">
        <div id="g_id_signin"></div>
      </template>
      <template v-if="isLoading">
        <div class="loading-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
      </template>
      <template v-if="isLoggedIn">
        <div class="user-menu">
          <img :src="user?.picture" alt="Usuario" class="user-avatar" />
          <span class="user-name">{{ user?.name }}</span>
          <button @click="handleLogout" class="logout-btn" title="Cerrar sesión">
            <i class="fas fa-sign-out-alt"></i>
          </button>
        </div>
      </template>
      
      <!-- Mostrar errores si los hay -->
      <div v-if="error || googleError" class="error-message">
        {{ error || googleError }}
      </div>
    </div>
  </div>
  <router-view/> <!-- Router will render components here -->
</template>

<script>
import { onMounted, watch } from 'vue';
import { useAuth, useGoogleAuth } from '@/composables';
import Logger from '@/utils/logger';

export default {
  name: 'App',
  setup() {
    // Usar composables en lugar del store directamente
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

    // Watch auth state changes for debugging
    watch(isAuthenticated, (newVal, oldVal) => {
      Logger.auth('Auth state changed:', oldVal, '->', newVal, 'User:', user.value?.name || 'null');
    });

    watch(isLoggedIn, (newVal, oldVal) => {
      Logger.auth('IsLoggedIn changed:', oldVal, '->', newVal);
    });

    onMounted(async () => {
      try {
        // Inicializar autenticación usando el composable
        await initializeAuth();
        
        // Una vez que la autenticación esté inicializada, configurar Google
        // El useGoogleAuth se encarga automáticamente de la inicialización
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
        Logger.error('[App] Failed to initialize:', error);
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
        Logger.error('[App] Authentication error:', authError || gError);
        // Limpiar errores después de un tiempo
        setTimeout(() => {
          clearError();
          clearGoogleError();
        }, 5000);
      }
    });

    const handleLogout = async () => {
      try {
        await logout();
        // Redirigir a home después del logout
        if (window.location.pathname !== '/') {
          window.location.href = '/';
        }
      } catch (error) {
        Logger.error('[App] Logout error:', error);
      }
    };

    return { 
      // Estados de autenticación
      user,
      isAuthenticated,
      isLoggedIn,
      isLoading,
      error,
      
      // Estados de Google
      isGoogleReady,
      googleError,
      
      // Métodos
      handleLogout
    };
  }
}
</script>

<style>
#app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  color: #2c3e50;
}

#nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 30px;
  background-color: #252525 !important;
  margin-bottom: 20px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  border-bottom: 1px solid #1a252f;
}

#nav a {
  font-weight: bold;
  color: white;
  text-decoration: none;
  margin: 0 10px;
  transition: color 0.3s ease;
}

#nav a:hover {
  color: #f0f0f0;
}

#nav a.router-link-exact-active {
  color: #42b983;
  text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

.nav-center {
  flex: 1;
  text-align: center;
}

.nav-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-menu {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(255, 255, 255, 0.95);
  padding: 8px 12px;
  border-radius: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  backdrop-filter: blur(10px);
}

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
}

.user-name {
  font-size: 14px;
  font-weight: 500;
  color: #18212b;
  max-width: 150px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.logout-btn {
  background: none;
  border: none;
  color: #dc3545;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  transition: background-color 0.2s;
  font-size: 14px;
}

.logout-btn:hover {
  background-color: #f8f9fa;
}

.loading-spinner {
  color: white;
  font-size: 18px;
}

.error-message {
  color: #dc3545;
  background-color: rgba(220, 53, 69, 0.1);
  border: 1px solid rgba(220, 53, 69, 0.3);
  border-radius: 4px;
  padding: 8px 12px;
  margin-left: 10px;
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
</style>
