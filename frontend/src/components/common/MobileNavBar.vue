<template>
  <nav
    v-if="isVisible"
    class="mobile-nav-bar"
  >
    <RouterLink
      v-for="tab in tabs"
      :key="tab.path"
      :to="tab.path"
      class="mobile-nav-bar__tab"
      :class="{ 'mobile-nav-bar__tab--active': isActive(tab.path) }"
      :aria-label="tab.path === '/inbox' ? inboxLabel : tab.label"
    >
      <span class="mobile-nav-bar__icon">
        <i :class="tab.icon" />
        <!-- El contador va `aria-hidden`: la cuenta ya viaja en el `aria-label`
             del enlace, y leerla dos veces es peor que no leerla. -->
        <span
          v-if="tab.path === '/inbox' && pendingCount > 0"
          class="mobile-nav-bar__badge"
          aria-hidden="true"
        >{{ pendingCount > 99 ? '99+' : pendingCount }}</span>
      </span>
      <span>{{ tab.label }}</span>
    </RouterLink>
  </nav>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useBreakpoint } from '@/composables/useBreakpoint';
import { useInboxStore } from '@/store/inbox';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const route = useRoute();

// Visible en plataforma nativa O en pantallas pequeñas. Mismo umbral y mismo
// listener que `Layout.vue`: ambos salen de `useBreakpoint`.
const { isNativeOrMobile: isVisible } = useBreakpoint();

// Cinco es el máximo razonable en una barra inferior, así que el criterio es que
// lleve DESTINOS y no ACCIONES: buscar libros es una acción, y hasta el 2026-08-30
// las cinco búsquedas ocupaban tres de los cinco huecos mientras las tres
// funcionalidades sociales de agosto —listas, clubs y bandeja— no tenían entrada
// ninguna. Las búsquedas y `/lists` y `/clubs` viven donde ya vivían en escritorio:
// el menú lateral, que en móvil se abre desde la cabecera.
const tabs = [
  { path: '/library',   icon: 'fas fa-bookmark',  label: 'Biblioteca'   },
  { path: '/dashboard', icon: 'fas fa-chart-bar', get label () { return t('misc.stats') } },
  { path: '/inbox',     icon: 'fas fa-inbox',     label: 'Bandeja'      },
  { path: '/friends',   icon: 'fas fa-users',     label: 'Social'       },
  { path: '/profile',   icon: 'fas fa-user',      label: 'Perfil'       },
];

// El MISMO contador que pinta la campanita de `Header.vue:95`, no uno nuevo: es el
// store, y `main.js` ya lo refresca en cada navegación.
const inboxStore = useInboxStore();
const { pendingCount } = storeToRefs(inboxStore);

// Concuerda en singular, como el de la cabecera: esto lo lee un lector de pantalla.
const inboxLabel = computed(() => {
  if (pendingCount.value === 0) return t('inboxBadge.empty');

  // El plural lo resuelve el motor; antes se elegía la rama a mano.
  return t('inboxBadge.short', { n: pendingCount.value });
});

const isActive = (path) => route.path.startsWith(path);
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.mobile-nav-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 60px;
  background: var(--color-background-soft);
  border-top: 1px solid var(--color-border);
  display: flex;
  align-items: stretch;
  z-index: z(overlay);

  // Solo visible en pantallas pequeñas o nativo
  @include responsive-below(md) {
    display: flex;
  }

  &__icon {
    position: relative;
    display: inline-flex;
  }

  // Mismo tratamiento que `.app-header__inbox-badge`: relleno semántico con su
  // tinta pareja, que sí conmuta con el tema.
  &__badge {
    position: absolute;
    top: -5px;
    left: 11px;
    min-width: min(16px, 100%);
    height: 16px;
    padding: 0 spacing(2xs);
    border-radius: radius(full);
    background: var(--color-error);
    color: var(--color-on-status);
    font-size: var(--font-size-xs);
    font-weight: 700;
    line-height: 16px;
    text-align: center;
  }

  &__tab {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: spacing(2xs);
    color: var(--color-text-secondary);
    text-decoration: none;
    font-size: var(--font-size-xs);
    transition: transition(fast);
    padding: spacing(2xs) spacing(3xs);

    i {
      font-size: var(--font-size-md);
    }

    &--active {
      color: var(--color-primary);
    }

    &:active {
      background: var(--color-background-mute);
    }
  }
}
</style>
