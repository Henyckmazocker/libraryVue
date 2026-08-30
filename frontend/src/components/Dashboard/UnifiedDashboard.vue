<template>
  <div class="unified-dashboard">
    <div class="dashboard-header">
      <h1 class="dashboard-title">
        <i class="fas fa-chart-bar" />
        Estadísticas
      </h1>
      <p class="dashboard-subtitle">
        Resumen y estadísticas de tu biblioteca personal
      </p>
    </div>

    <!-- `scrollable` es lo que da a la fila de tabs sus flechas de navegación: sin
         ella, `p-tablist` recorta con `overflow-x: hidden` y a 360px tres de los
         cinco destinos quedan inalcanzables (los cinco suman 717px sobre 254). -->
    <Tabs
      :value="activeTab"
      class="dashboard-tabs"
      scrollable
      @update:value="activeTab = $event"
    >
      <TabList>
        <Tab value="books">
          <i class="fas fa-book" />
          <span>Libros</span>
        </Tab>
        <Tab value="movies">
          <i class="fas fa-film" />
          <span>Películas</span>
        </Tab>
        <Tab value="games">
          <i class="fas fa-gamepad" />
          <span>Videojuegos</span>
        </Tab>
        <Tab value="albums">
          <i class="fas fa-music" />
          <span>Música</span>
        </Tab>
        <Tab value="videos">
          <i class="fab fa-youtube" />
          <span>Vídeos</span>
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
        <TabPanel value="videos">
          <VideosDashboardContent />
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
import VideosDashboardContent from './VideosDashboardContent.vue';

const route = useRoute();
const router = useRouter();

// Determine initial tab from route query or default to 'books'
const getInitialTab = () => {
  const tab = route.query.tab;
  if (['books', 'movies', 'games', 'albums', 'videos'].includes(tab)) return tab;
  return 'books';
};

const activeTab = ref(getInitialTab());

// Sync tab with URL query param
watch(activeTab, (newTab) => {
  router.replace({ query: { ...route.query, tab: newTab } });
});
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.unified-dashboard {
  padding: spacing(xl);
  max-width: 1400px;
  margin: 0 auto;
  background: var(--color-background);
  min-height: 100vh;
}

.dashboard-header {
  text-align: center;
  margin-bottom: spacing(xl);
}

.dashboard-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 spacing(xs) 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: spacing(sm);

  i {
    color: var(--color-primary);
    font-size: 1.75rem;
  }
}

.dashboard-subtitle {
  font-size: 1rem;
  color: var(--color-text-secondary);
  margin: 0;
}

.dashboard-tabs :deep(.p-tablist) {
  justify-content: center;
}

.dashboard-tabs :deep(.p-tab) {
  display: flex;
  align-items: center;
  gap: spacing(xs);
  padding: spacing(sm) spacing(lg);
  font-weight: 600;
  font-size: 1rem;

  i { font-size: 1.1rem; }
}

.dashboard-tabs :deep(.p-tabpanel) {
  padding: spacing(lg) 0 0 0;
}

// Por debajo de `sm` los cinco tabs con rótulo suman 717px sobre los 254 disponibles,
// y `scrollable` no basta: su flecha de navegación existe en el DOM pero no llega a
// verse, así que los tres últimos destinos quedaban inalcanzables. Aquí el rótulo pasa
// a lectores de pantalla y quedan los cinco iconos, que caben de una vez y no piden
// scroll. Es la misma convención que `MyLibrary` usa en sus filtros por medio y que
// documenta `Header.vue`: lo que solo existe como icono lleva su texto en `u-sr-only`.
@include responsive-below(sm) {
  .dashboard-tabs :deep(.p-tab) {
    padding: spacing(sm);

    span {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }
  }
}
</style>
