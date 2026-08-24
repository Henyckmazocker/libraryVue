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
      :aria-label="tab.label"
    >
      <i :class="tab.icon" />
      <span>{{ tab.label }}</span>
    </RouterLink>
  </nav>
</template>

<script setup>
import { useRoute } from 'vue-router';
import { useBreakpoint } from '@/composables/useBreakpoint';

const route = useRoute();

// Visible en plataforma nativa O en pantallas pequeñas. Mismo umbral y mismo
// listener que `Layout.vue`: ambos salen de `useBreakpoint`.
const { isNativeOrMobile: isVisible } = useBreakpoint();

const tabs = [
  { path: '/library',   icon: 'fas fa-bookmark',   label: 'Biblioteca' },
  { path: '/books',     icon: 'fas fa-book',        label: 'Libros'     },
  { path: '/movies',    icon: 'fas fa-film',        label: 'Películas'  },
  { path: '/games',     icon: 'fas fa-gamepad',     label: 'Juegos'     },
  { path: '/friends',   icon: 'fas fa-users',       label: 'Social'     },
];

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

  &__tab {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    color: var(--color-text-secondary);
    text-decoration: none;
    font-size: 0.65rem;
    transition: transition(fast);
    padding: spacing(2xs) spacing(3xs);

    i {
      font-size: 1.1rem;
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
