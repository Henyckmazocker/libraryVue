<template>
  <aside
    class="app-sidebar"
    :class="{ 'app-sidebar--collapsed': isCollapsed }"
  >
    <!-- Toggle button -->
    <button 
      class="app-sidebar__toggle" 
      :class="{ 'app-sidebar__toggle--collapsed': isCollapsed }"
      @click="toggleSidebar"
    >
      <i
        class="fas"
        :class="isCollapsed ? 'fa-chevron-right' : 'fa-chevron-left'"
      />
    </button>

    <!-- Sidebar content -->
    <div class="app-sidebar__content">
      <div
        v-if="isLoading"
        class="app-sidebar__loading"
      >
        <i class="fas fa-spinner fa-spin" />
        <span v-if="!isCollapsed">Cargando menú...</span>
      </div>

      <div
        v-else-if="hasError"
        class="app-sidebar__error"
      >
        <i class="fas fa-exclamation-triangle" />
        <span v-if="!isCollapsed">Error al cargar menú</span>
      </div>

      <nav
        v-else
        class="app-sidebar__nav"
      >
        <div 
          v-for="section in menuItems" 
          :key="section.title" 
          class="app-sidebar__section"
        >
          <h3
            v-if="!isCollapsed"
            class="app-sidebar__section-title"
          >
            {{ section.title }}
          </h3>
          
          <ul class="app-sidebar__menu">
            <li 
              v-for="item in section.items" 
              :key="item.name"
              class="app-sidebar__menu-item"
            >
              <router-link 
                :to="item.path"
                :class="[
                  'app-sidebar__link',
                  { 'app-sidebar__link--disabled': item.disabled }
                ]"
                :title="isCollapsed ? item.description : ''"
                @click="item.disabled && $event.preventDefault()"
              >
                <i
                  :class="item.icon"
                  class="app-sidebar__icon"
                />
                <span
                  v-if="!isCollapsed"
                  class="app-sidebar__text"
                >{{ item.name }}</span>
              </router-link>
            </li>
          </ul>
        </div>
      </nav>
    </div>
  </aside>
</template>

<script>
export default {
  name: 'AppSidebar'
}
</script>

<script setup>
import { ref, onMounted, defineProps, defineEmits } from 'vue';
import { useSidebarMenu } from '@/composables/useSidebarMenu';

// Props
const props = defineProps({
  collapsed: {
    type: Boolean,
    default: false
  }
});

// Emits
const emit = defineEmits(['toggle']);

// Estado local del sidebar
const isCollapsed = ref(props.collapsed);

// Composable del menú
const { menuItems, isLoading, hasError, loadMenu } = useSidebarMenu();

// Métodos
const toggleSidebar = () => {
  isCollapsed.value = !isCollapsed.value;
  emit('toggle', isCollapsed.value);
};

// Lifecycle
onMounted(async () => {
  await loadMenu();
});
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.app-sidebar {
  position: fixed;
  top: 70px; /* Altura del header */
  left: 0;
  width: 280px;
  height: calc(100vh - 70px);
  background: var(--color-background-soft);
  border-right: 1px solid var(--color-border);
  transition: width var(--transition-medium);
  z-index: 200;
  overflow: hidden;
}

.app-sidebar--collapsed {
  width: 60px;
}

.app-sidebar__toggle {
  position: fixed;
  top: 85px;
  left: 265px;
  width: 40px;
  height: 40px;
  border: 1px solid var(--color-border);
  border-radius: 50%;
  background: var(--color-background-soft);
  color: var(--color-text);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1500;
  transition: all var(--transition-medium);
  box-shadow: var(--shadow-medium);
}

/* Posición del toggle cuando el sidebar está colapsado */
.app-sidebar__toggle--collapsed {
  left: 45px !important; /* Posición cuando está colapsado */
}

.app-sidebar__toggle:hover {
  background: var(--color-background-mute);
  color: var(--color-text-light);
  border-color: var(--color-primary-light);
  box-shadow: var(--shadow-heavy);
}

.app-sidebar__content {
  height: 100%;
  padding: 20px 0;
  overflow-y: auto;
  overflow-x: hidden;
}

.app-sidebar__loading,
.app-sidebar__error {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  padding: 20px;
  color: var(--color-text-secondary);
  gap: 10px;
}

.app-sidebar__nav {
  padding: 0;
}

.app-sidebar__section {
  margin-bottom: 25px;
}

.app-sidebar__section-title {
  color: var(--color-text-secondary);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 0 0 10px 20px;
  opacity: 0.8;
}

.app-sidebar__menu {
  list-style: none;
  padding: 0;
  margin: 0;
}

.app-sidebar__menu-item {
  margin: 2px 0;
}

.app-sidebar__link {
  display: flex;
  align-items: center;
  padding: 12px 20px;
  color: var(--color-text);
  text-decoration: none;
  transition: all var(--transition-fast);
  font-size: 14px;
  font-weight: 500;
  border-radius: 0;
  position: relative;
}

.app-sidebar__link:hover:not(.app-sidebar__link--disabled) {
  background: var(--color-background-mute);
  color: var(--color-text-light);
}

.app-sidebar__link.router-link-active {
  background: var(--color-primary);
  color: var(--color-text-light);
}

.app-sidebar__link.router-link-active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
  background: var(--color-highlight);
}

.app-sidebar__link--disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.app-sidebar__icon {
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

.app-sidebar__text {
  margin-left: 15px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Scrollbar personalizado */
.app-sidebar__content::-webkit-scrollbar {
  width: 6px;
}

.app-sidebar__content::-webkit-scrollbar-track {
  background: transparent;
}

.app-sidebar__content::-webkit-scrollbar-thumb {
  background: var(--color-border);
  border-radius: 3px;
}

.app-sidebar__content::-webkit-scrollbar-thumb:hover {
  background: var(--color-border-hover);
}

/* Responsive */
@include responsive-below(md) {
  .app-sidebar {
    width: 60px;
    z-index: 250;
  }
  
  .app-sidebar--collapsed {
    width: 60px;
  }
  
  .app-sidebar__toggle {
    left: 45px !important; /* En móvil siempre en posición colapsada */
    width: 45px;
    height: 45px;
    z-index: 1503;
  }
  
  .app-sidebar__toggle--collapsed {
    left: 45px !important;
  }
  
  .app-sidebar__section-title {
    display: none;
  }
  
  .app-sidebar__text {
    display: none;
  }
}
</style>
