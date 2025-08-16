<template>
  <div id="nav">
    <div class="nav-center">
      <router-link to="/"><i class="fas fa-home"></i></router-link> | 
      <router-link to="/library" v-if="authStore.isLoggedIn"><i class="fas fa-bookmark"></i></router-link>
    </div>
    <div class="nav-right">
      <template v-if="!authStore.isLoggedIn && !authStore.isLoading">
        <div id="g_id_signin"></div>
      </template>
      <template v-if="authStore.isLoading">
        <div class="loading-spinner">
          <i class="fas fa-spinner fa-spin"></i>
        </div>
      </template>
      <template v-if="authStore.isLoggedIn">
        <div class="user-menu">
          <img :src="authStore.userPicture" alt="Usuario" class="user-avatar" />
          <span class="user-name">{{ authStore.userName }}</span>
          <button @click="handleLogout" class="logout-btn" title="Cerrar sesión">
            <i class="fas fa-sign-out-alt"></i>
          </button>
        </div>
      </template>
    </div>
  </div>
  <router-view/> <!-- Router will render components here -->
</template>

<script>
import { onMounted, watch } from 'vue';
import { useAuthStore } from './store/auth.js';
import Logger from '@/utils/logger';

export default {
  name: 'App',
  setup() {
    const authStore = useAuthStore();
    const clientId = process.env.VUE_APP_GOOGLE_CLIENT_ID || "YOUR_GOOGLE_CLIENT_ID";

    // Watch auth state changes for debugging
    watch(() => authStore.isAuthenticated, (newVal, oldVal) => {
      Logger.auth('Auth state changed:', oldVal, '->', newVal, 'User:', authStore.user?.name || 'null');
    });

    watch(() => authStore.isLoggedIn, (newVal, oldVal) => {
      Logger.auth('IsLoggedIn changed:', oldVal, '->', newVal);
    });

    onMounted(async () => {
      // Initialize authentication state only if user is not already logged in
      if (!authStore.isAuthenticated) {
        await authStore.initializeAuth();
      }
      
      // Función para manejar la respuesta del token de Google
      async function handleCredentialResponse(response) {
        Logger.auth('Google token received, attempting login...');
        try {
          const result = await authStore.login(response.credential);
          if (result.success) {
            Logger.auth('Login successful, user authenticated');
            // Force UI update
            await authStore.$patch({ isAuthenticated: true });
          } else {
            Logger.error('Login failed:', result.message);
          }
        } catch (error) {
          Logger.error('Login error:', error);
        }
      }

      // Hacer disponible globalmente para el callback de Google
      window.handleCredentialResponse = handleCredentialResponse;

      // Función para inicializar Google Sign-In
      const initializeGoogleSignIn = () => {
        if (window.google && window.google.accounts && window.google.accounts.id) {
          try {
            window.google.accounts.id.initialize({
              client_id: clientId,
              callback: handleCredentialResponse
            });
            
            const signInButton = document.getElementById('g_id_signin');
            if (signInButton) {
              window.google.accounts.id.renderButton(signInButton, {
                theme: 'outline',
                size: 'large',
                shape: 'circle',
                text: 'signin_with',
                logo_alignment: 'left'
              });
            }
          } catch (error) {
            Logger.error('Error inicializando Google Sign-In:', error);
          }
        }
      };
      
      // Si Google ya está cargado, inicializar inmediatamente
      if (window.googleSignInReady) {
        initializeGoogleSignIn();
      } else {
        // Esperar al evento de carga de Google
        window.addEventListener('googleSignInLoaded', initializeGoogleSignIn);
        
        // Fallback: intentar cada 200ms por si el evento no funciona
        const fallbackInterval = setInterval(() => {
          if (window.googleSignInReady) {
            clearInterval(fallbackInterval);
            initializeGoogleSignIn();
          }
        }, 200);
        
        // Limpiar el intervalo después de 10 segundos para evitar bucles infinitos
        setTimeout(() => clearInterval(fallbackInterval), 10000);
      }
    });

    const handleLogout = async () => {
      await authStore.logout(true); // manual logout
      // Optionally redirect to home page
      if (window.location.pathname !== '/') {
        window.location.href = '/';
      }
    };

    return { 
      authStore,
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

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.fa-spinner.fa-spin {
  animation: spin 1s linear infinite;
}
</style>
