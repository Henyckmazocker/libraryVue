<template>
  <div class="unified-dashboard">
    <div class="dashboard-header">
      <h1 class="dashboard-title">
        <i class="fas fa-chart-bar"></i>
        Estadísticas
      </h1>
      <p class="dashboard-subtitle">
        Resumen y estadísticas de tu biblioteca personal
      </p>
    </div>

    <Tabs :value="activeTab" @update:value="activeTab = $event" class="dashboard-tabs">
      <TabList>
        <Tab value="books">
          <i class="fas fa-book"></i>
          <span>Libros</span>
        </Tab>
        <Tab value="movies">
          <i class="fas fa-film"></i>
          <span>Películas</span>
        </Tab>
        <Tab value="games">
          <i class="fas fa-gamepad"></i>
          <span>Videojuegos</span>
        </Tab>
        <Tab value="albums">
          <i class="fas fa-music"></i>
          <span>Música</span>
        </Tab>
      </TabList>

      <TabPanels>
        <TabPanel value="books">
          <BooksDashboardContent />
        </TabPanel>
        <TabPanel value="movies">
          <MoviesDashboardContent />
        </TabPanel>
        <TabPanel value="games">
          <GamesDashboardContent />
        </TabPanel>
        <TabPanel value="albums">
          <AlbumsDashboardContent />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import BooksDashboardContent from './BooksDashboardContent.vue';
import MoviesDashboardContent from './MoviesDashboardContent.vue';
import GamesDashboardContent from './GamesDashboardContent.vue';
import AlbumsDashboardContent from './AlbumsDashboardContent.vue';

const route = useRoute();
const router = useRouter();

// Determine initial tab from route query or default to 'books'
const getInitialTab = () => {
  const tab = route.query.tab;
  if (['books', 'movies', 'games', 'albums'].includes(tab)) return tab;
  return 'books';
};

const activeTab = ref(getInitialTab());

// Sync tab with URL query param
watch(activeTab, (newTab) => {
  router.replace({ query: { ...route.query, tab: newTab } });
});
</script>

<style scoped>
.unified-dashboard {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
  background: var(--color-background);
  min-height: 100vh;
}

.dashboard-header {
  text-align: center;
  margin-bottom: 2rem;
}

.dashboard-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--color-text-dark);
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
}

.dashboard-title i {
  color: var(--color-primary);
  font-size: 1.75rem;
}

.dashboard-subtitle {
  font-size: 1rem;
  color: var(--color-text-muted);
  margin: 0;
}

.dashboard-tabs :deep(.p-tablist) {
  justify-content: center;
}

.dashboard-tabs :deep(.p-tab) {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  font-weight: 600;
  font-size: 1rem;
}

.dashboard-tabs :deep(.p-tab i) {
  font-size: 1.1rem;
}

.dashboard-tabs :deep(.p-tabpanel) {
  padding: 1.5rem 0 0 0;
}

/* Dark mode */
:global(.app-dark) .unified-dashboard {
  background: var(--color-background);
}

:global(.app-dark) .dashboard-subtitle {
  color: var(--color-text-secondary);
}
</style>
