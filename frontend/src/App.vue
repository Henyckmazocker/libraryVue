<template>
  <AppLayout>
    <router-view />
  </AppLayout>
</template>

<script setup>
import { onMounted } from 'vue';
import AppLayout from '@/components/common/Layout.vue';
import { useUIStore } from '@/store/ui';

// Inicializar el tema al montar la aplicación
const uiStore = useUIStore();

onMounted(() => {
  // Cargar y aplicar el tema guardado o detectar preferencia del sistema
  uiStore.loadTheme();
  // Inicializar listener de cambios del sistema
  uiStore.initSystemThemeListener();
});
</script>

<style>
@import '@/assets/styles/variables.css';

#app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  color: var(--color-text);
  background: var(--color-background);
  min-height: 100vh;
}

/* Reset de estilos base */
* {
  box-sizing: border-box;
}

body {
  margin: 0;
  padding: 0;
  background: var(--color-background);
  color: var(--color-text);
  font-family: Avenir, Helvetica, Arial, sans-serif;
}

/* Estilos globales para enlaces */
a {
  color: var(--color-primary-light);
  text-decoration: none;
}

a:hover {
  text-decoration: underline;
  color: var(--color-highlight);
}

/* Estilos para botones */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: var(--transition-fast);
  text-decoration: none;
  gap: 8px;
}

.btn--primary {
  background: var(--btn-primary-bg);
  color: var(--btn-primary-text);
}

.btn--primary:hover {
  background: var(--btn-primary-bg-hover);
}

.btn--secondary {
  background: var(--btn-secondary-bg);
  color: var(--btn-secondary-text);
  border: 1px solid var(--color-border);
}

.btn--secondary:hover {
  background: var(--btn-secondary-bg-hover);
}

/* Utilidades */
.text-center { text-align: center; }
.text-left { text-align: left; }
.text-right { text-align: right; }

.mb-1 { margin-bottom: 0.5rem; }
.mb-2 { margin-bottom: 1rem; }
.mb-3 { margin-bottom: 1.5rem; }
.mb-4 { margin-bottom: 2rem; }

.mt-1 { margin-top: 0.5rem; }
.mt-2 { margin-top: 1rem; }
.mt-3 { margin-top: 1.5rem; }
.mt-4 { margin-top: 2rem; }

/* Global z-index fix for PrimeVue dropdowns in modals */
.p-multiselect-panel,
.p-dropdown-panel,
.p-overlay-mask .p-multiselect-panel,
.p-overlay-mask .p-dropdown-panel {
  z-index: 3000 !important;
}

/* Ensure modals have appropriate z-index base */
.modal-overlay {
  z-index: 2000;
}

/* Additional fix for PrimeVue overlays */
.p-component-overlay,
.p-multiselect-overlay,
.p-dropdown-overlay {
  z-index: 3000 !important;
}
</style>
