<template>
  <header class="app-header">
    <!-- Logo/Título de la aplicación a la izquierda -->
    <div class="app-header__left">
      <router-link
        to="/"
        class="app-header__logo"
      >
        <i class="fas fa-book-open" />
        <span class="app-header__title">{{ t('header.appName') }}</span>
      </router-link>
    </div>

    <!-- Sección derecha con autenticación -->
    <div class="app-header__right">
      <!-- Botón de cambio de tema -->
      <button
        class="app-header__theme-toggle"
        :title="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        :aria-label="isDark ? t('header.toLight') : t('header.toDark')"
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
          {{ t('header.signInGoogle') }}
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
        <!-- El buscador general: una consulta, los seis catálogos. Va el primero
             de los cuatro porque es lo que más se usa, y sin contador como los
             otros tres: es un destino, no un aviso. `MobileNavBar` NO lo lleva,
             a propósito: sus cinco destinos se decidieron en el plan de nivelar
             clubs, listas y bandeja, y meter un sexto obligaría a sacar otro. -->
        <router-link
          to="/search"
          class="app-header__inbox"
        >
          <i
            class="fas fa-magnifying-glass"
            aria-hidden="true"
          />
          <!-- El texto va en .u-sr-only y no en un aria-label: es la convención
               del proyecto para lo que solo existe como icono. -->
          <span class="u-sr-only">{{ t('header.search') }}</span>
        </router-link>

        <!-- Las listas. Sin contador: no hay nada que avisar, solo un destino. -->
        <router-link
          to="/lists"
          class="app-header__inbox"
        >
          <i
            class="fas fa-list-ul"
            aria-hidden="true"
          />
          <!-- El texto va en .u-sr-only y no en un aria-label: es la convención
               del proyecto para lo que solo existe como icono. -->
          <span class="u-sr-only">{{ t('header.lists') }}</span>
        </router-link>

        <!-- Los clubs. Sin contador, como las listas: no hay nada que avisar. -->
        <router-link
          to="/clubs"
          class="app-header__inbox"
        >
          <i
            class="fas fa-users"
            aria-hidden="true"
          />
          <!-- El texto va en .u-sr-only y no en un aria-label: es la convención
               del proyecto para lo que solo existe como icono. -->
          <span class="u-sr-only">{{ t('header.clubs') }}</span>
        </router-link>

        <!-- La bandeja. El icono se pinta SIEMPRE y solo el contador aparece y
             desaparece: si se ocultara el icono entero, la cabecera daría un
             salto cada vez que llega o se resuelve una recomendación. -->
        <router-link
          to="/inbox"
          class="app-header__inbox app-header__inbox--tray"
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
            :alt="t('header.avatarAlt')"
            class="app-header__user-avatar"
            loading="lazy"
            decoding="async"
          >
          <span class="app-header__user-name">{{ user?.name }}</span>
          <button
            class="app-header__logout-btn"
            :title="t('header.signOut')"
            :aria-label="t('header.signOut')"
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
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

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
  if (pendingCount.value === 0) return t('inboxBadge.none');

  // Concuerda en singular: esto lo lee un lector de pantalla, y «1 pendientes»
  // es justo el tipo de detalle que solo se oye. El plural lo resuelve el motor.
  return t('inboxBadge.pending', { n: pendingCount.value });
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
  padding: 0 spacing(md);
  background: var(--color-background-soft);
  border-bottom: 1px solid var(--color-border);
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: z(sticky);
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
  font-size: var(--font-size-md);
  font-weight: 600;
  transition: var(--transition-fast);
}

.app-header__logo:hover {
  color: var(--color-text-light);
}

.app-header__logo i {
  font-size: var(--font-size-xl);
  margin-right: spacing(sm);
  color: var(--color-highlight);
}

.app-header__title {
  font-size: var(--font-size-md);
  font-weight: 600;
}

.app-header__right {
  display: flex;
  align-items: center;
  gap: spacing(md);
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
  font-size: var(--font-size-md);
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
  font-size: var(--font-size-md);
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
  min-width: min(18px, 100%);
  height: 18px;
  padding: 0 spacing(2xs);
  border-radius: radius(md);
  background: var(--color-error);
  color: var(--color-on-status);
  font-size: var(--font-size-xs);
  font-weight: 700;
  line-height: 18px;
  text-align: center;
}

.app-header__loading {
  color: var(--color-text);
  font-size: var(--font-size-md);
}

.app-header__user-menu {
  display: flex;
  align-items: center;
  gap: spacing(sm);
  background: var(--color-background-card);
  padding: spacing(xs) spacing(md);
  border-radius: radius(xl);
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
  font-size: var(--font-size-sm);
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
  padding: spacing(xs);
  border-radius: radius(sm);
  transition: var(--transition-fast);
  font-size: var(--font-size-sm);
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
  border-radius: radius(sm);
  padding: spacing(xs) spacing(sm);
  font-size: var(--font-size-xs);
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
    padding: 0 spacing(md);
  }
  
  .app-header__title {
    display: none;
  }
  
  .app-header__user-name {
    display: none;
  }
}

// Por debajo de `sm` la cabecera no cabe: a 360px se salía 23px del viewport y el
// botón de cerrar sesión quedaba fuera de la pantalla. Aquí no se quita ningún
// destino —se llega a todos—, solo se aprieta lo que no es contenido: el padding
// de la barra, los huecos entre iconos y el acolchado de la píldora de usuario.
//
// ⚠ Y no bastaba. Medido el 2026-09-03 a 360 px con sesión abierta: `.app-header__right`
// seguía llegando a `right: 376` con el viewport en 360, o sea **24 px fuera**, y se
// llevaba por delante la píldora de usuario y el botón de salir. La cuenta no daba:
// 8 de padding + 42 del logo + 5 círculos de 40 + 4 huecos de 8 + 82 de píldora = 376
// contra 352 de sitio. Faltaba apretar lo único que quedaba sin tocar —el tamaño de
// los cinco círculos— porque los cuatro destinos y el conmutador de tema **no se
// quitan**: son la única vía a búsqueda, listas, clubs y bandeja desde aquí.
//
// 36 px sigue por encima del mínimo de 24×24 de WCAG 2.5.8 y solo aplica por debajo
// de 480 px; a partir de ahí vuelven los 40 de siempre. Medido después: cero
// desbordados a 360, 390 y 479, con 8 px de margen, y 16 px a 480 con los círculos
// grandes ya puestos.
//
// A **320 px sigue saliéndose 15 px**, y se deja así a propósito: la escala no tiene
// un escalón por debajo de `sm` y apretar más los círculos empezaría a comerse el
// área de pulsación. Está apuntado en el Roadmap.
@include responsive-below(sm) {
  .app-header {
    padding: 0 spacing(xs);
  }

  .app-header__right {
    gap: spacing(2xs);
  }

  .app-header__theme-toggle,
  .app-header__inbox {
    width: 36px;
    height: 36px;
  }

  // La bandeja se retira **solo aquí**, y no es quitar un destino: `MobileNavBar`
  // ya lleva `/inbox` entre sus cinco pestañas y se pinta por debajo de `md`
  // (`useBreakpoint.isNativeOrMobile`), **con su propio contador de pendientes**
  // (`MobileNavBar.vue:19`). O sea que en esta franja el icono era una segunda copia
  // del mismo destino y del mismo aviso, ocupando 40 px de una barra que no llegaba.
  // Los otros tres —búsqueda, listas y clubs— NO están en la barra inferior, y por
  // eso se quedan.
  //
  // Esto es lo que cierra los 320 px: con los círculos a 36 seguían sobrando 15, y
  // recortarlos más se comía el área de pulsación. Ahora sobran 25.
  .app-header__inbox--tray {
    display: none;
  }

  .app-header__user-menu {
    gap: spacing(2xs);
    padding: spacing(2xs) spacing(xs);
  }
}

.app-header__native-signin {
  display: flex;
  align-items: center;
  gap: spacing(xs);
  padding: spacing(xs) spacing(md);
  background: var(--color-background-soft);
  border: 1px solid var(--color-border);
  border-radius: radius(2xl);
  color: var(--color-text);
  font-size: var(--font-size-sm);
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
