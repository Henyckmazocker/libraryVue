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
        <slot></slot>
      </div>
    </main>

    <!-- Bottom navigation (solo en móvil/nativo) -->
    <MobileNavBar v-if="isMobileOrNative" />
    
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
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Capacitor } from '@capacitor/core';
import AppHeader from './Header.vue';
import AppSidebar from './Sidebar.vue';
import MobileNavBar from './MobileNavBar.vue';
import ConfirmationModal from './ConfirmationModal.vue';
import { useConfirmationModal } from '@/composables/useConfirmationModal';

// Detectar móvil/nativo (Capacitor nativo o pantalla pequeña, reactivo a resize)
const windowWidth = ref(window.innerWidth);
const onResize = () => { windowWidth.value = window.innerWidth; };
onMounted(() => window.addEventListener('resize', onResize));
onUnmounted(() => window.removeEventListener('resize', onResize));

const isMobileOrNative = computed(() =>
  Capacitor.isNativePlatform() || windowWidth.value <= 768
);

// Estado del sidebar
const sidebarCollapsed = ref(false);

// Modal de confirmación
const { modalState, handleConfirm, handleCancel } = useConfirmationModal();

// Métodos
const handleSidebarToggle = (collapsed) => {
  sidebarCollapsed.value = collapsed;
};

const handleLogout = () => {
  // Additional logout logic can be added here if needed
};
</script>

<style scoped lang="scss">
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

@media (max-width: 768px) {
  .app-layout__content {
    padding: 15px;
  }
}
</style>
