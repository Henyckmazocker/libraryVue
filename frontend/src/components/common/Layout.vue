<template>
  <div class="app-layout">
    <!-- Header -->
    <AppHeader @logout="handleLogout" />
    
    <!-- Sidebar (oculto en móvil/nativo) -->
    <AppSidebar 
      v-if="!isMobileOrNative"
      :collapsed="sidebarCollapsed" 
      @toggle="handleSidebarToggle" 
    />
    
    <!-- Main content area -->
    <main
      class="app-layout__main"
      :class="{
        'app-layout__main--sidebar-collapsed': sidebarCollapsed && !isMobileOrNative,
        'app-layout__main--mobile': isMobileOrNative
      }"
    >
      <div class="app-layout__content">
        <slot />
      </div>
    </main>

    <!-- Bottom navigation (solo en móvil/nativo) -->
    <MobileNavBar v-if="isMobileOrNative" />
    
    <!-- Superficie global de avisos. Antes de esto el único <Toast /> vivía en
         FriendsView, así que todo lo que empujaba uiStore.showError/showSuccess
         se acumulaba en el store sin que nadie lo pintara. -->
    <Toast position="bottom-right" />

    <!-- Modal de confirmación global -->
    <ConfirmationModal
      :is-visible="modalState.isVisible"
      :is-processing="modalState.isProcessing"
      v-bind="modalState.config"
      @confirm="handleConfirm"
      @cancel="handleCancel"
    />
  </div>
</template>

<script>
export default {
  name: 'AppLayout'
}
</script>

<script setup>
import { ref, watch } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import AppHeader from './Header.vue';
import AppSidebar from './Sidebar.vue';
import MobileNavBar from './MobileNavBar.vue';
import ConfirmationModal from './ConfirmationModal.vue';
import { useUIStore } from '@/store/ui';
import { useConfirmationModal } from '@/composables/useConfirmationModal';
import { useBreakpoint } from '@/composables/useBreakpoint';

// Móvil/nativo: el umbral y el listener viven en el composable, uno solo para toda la app.
const { isNativeOrMobile: isMobileOrNative } = useBreakpoint();

// Estado del sidebar
const sidebarCollapsed = ref(false);

// Modal de confirmación
const { modalState, handleConfirm, handleCancel } = useConfirmationModal();

// Puente store → Toast de PrimeVue.
// useToast() necesita contexto de componente, así que no se puede llamar desde un
// store: el store apila en uiStore.notifications y este watch las saca por pantalla.
const uiStore = useUIStore();
const toast = useToast();

const SEVERITY_BY_TYPE = { success: 'success', error: 'error', warning: 'warn', info: 'info' };

watch(
  () => uiStore.notifications.length,
  (length, previousLength) => {
    if (length <= (previousLength ?? 0)) return;
    const notification = uiStore.notifications[length - 1];
    toast.add({
      severity: SEVERITY_BY_TYPE[notification.type] || 'info',
      summary: notification.title,
      detail: notification.message,
      life: notification.duration
    });
  }
);

// Métodos
const handleSidebarToggle = (collapsed) => {
  sidebarCollapsed.value = collapsed;
};

const handleLogout = () => {
  // Additional logout logic can be added here if needed
};
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.app-layout {
  min-height: 100dvh; /* dvh evita que el teclado virtual tape contenido */
  background: var(--color-background);
  color: var(--color-text);
  display: flex;
  flex-direction: column;
}

.app-layout__main {
  margin-left: 280px; /* Ancho del sidebar */
  margin-top: 70px; /* Altura del header */
  transition: margin-left 0.3s ease;
  min-height: calc(100dvh - 70px);
  position: relative;
  z-index: 10;
  flex: 1;

  &--sidebar-collapsed {
    margin-left: 60px;
  }

  /* En móvil/nativo: sin sidebar, con espacio para la bottom nav bar */
  &--mobile {
    margin-left: 0;
    padding-bottom: 60px; /* Altura de MobileNavBar */
  }
}

.app-layout__content {
  padding: 20px;
  max-width: 1600px;
  margin: 0 auto;
  width: 100%;
}

@include responsive-below(md) {
  .app-layout__content {
    padding: 15px;
  }
}
</style>
