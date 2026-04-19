<template>
  <div class="app-layout">
    <!-- Header -->
    <AppHeader @logout="handleLogout" />
    
    <!-- Sidebar -->
    <AppSidebar 
      :collapsed="sidebarCollapsed" 
      @toggle="handleSidebarToggle" 
    />
    
    <!-- Main content area -->
    <main class="app-layout__main" :class="{ 'app-layout__main--sidebar-collapsed': sidebarCollapsed }">
      <div class="app-layout__content">
        <slot></slot>
      </div>
    </main>
    
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
import { ref } from 'vue';
import AppHeader from './Header.vue';
import AppSidebar from './Sidebar.vue';
import ConfirmationModal from './ConfirmationModal.vue';
import { useConfirmationModal } from '@/composables/useConfirmationModal';

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

<style scoped>
.app-layout {
  min-height: 100vh;
  background: var(--color-background);
  color: var(--color-text);
  display: flex;
  flex-direction: column;
}

.app-layout__main {
  margin-left: 280px; /* Ancho del sidebar */
  margin-top: 70px; /* Altura del header */
  transition: margin-left 0.3s ease;
  min-height: calc(100vh - 70px);
  position: relative;
  z-index: 10; /* Menor z-index que el sidebar */
  flex: 1;
}

.app-layout__main--sidebar-collapsed {
  margin-left: 60px; /* Ancho del sidebar colapsado */
}

.app-layout__content {
  padding: 20px;
  max-width: 1600px; /* Aumentado de 1200px a 1600px para consistencia */
  margin: 0 auto;
  width: 100%;
}

/* Responsive */
@media (max-width: 768px) {
  .app-layout__main {
    margin-left: 60px; /* En móvil siempre sidebar colapsado */
  }
  
  .app-layout__main--sidebar-collapsed {
    margin-left: 60px;
  }
  
  .app-layout__content {
    padding: 15px;
  }
}
</style>
