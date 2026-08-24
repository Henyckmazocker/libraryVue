<template>
  <AppLayout>
    <div
      v-if="showLoading"
      class="app-loading"
    >
      <i class="pi pi-spin pi-spinner app-loading__spinner" />
    </div>
    <router-view
      v-else
      v-slot="{ Component }"
    >
      <Transition
        name="page-fade"
        mode="out-in"
      >
        <component
          :is="Component"
          :key="$route.path"
        />
      </Transition>
    </router-view>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import AppLayout from '@/components/common/Layout.vue';
import { useUIStore } from '@/store/ui';
import { useAuthStore } from '@/store/auth';
import { useRoute } from 'vue-router';

const uiStore = useUIStore();
const authStore = useAuthStore();
const route = useRoute();

// Mostrar loading solo mientras se verifica auth en rutas protegidas
const showLoading = computed(() => {
  return route.meta.requiresAuth && !authStore._authChecked;
});

onMounted(() => {
  uiStore.loadTheme();
  uiStore.initSystemThemeListener();
});
</script>

<style scoped lang="scss">
.app-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 70px);
}

.app-loading__spinner {
  font-size: 2.5rem;
  color: var(--color-secondary);
}
</style>
