<template>
  <div class="dashboard-content">
    <div class="content-header">
      <router-link to="/library?filter=albums" class="btn btn--primary">
        <i class="fas fa-music"></i>
        Ver Mi Biblioteca de Música
      </router-link>
    </div>

    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Cargando estadísticas de álbumes...</p>
    </div>

    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <p>{{ error }}</p>
      <button @click="loadAlbumStats" class="btn btn--primary">
        Reintentar
      </button>
    </div>

    <template v-else>
      <DashboardStatsGrid :stats="statsCards" />
      <DashboardChartsGrid :charts="chartConfigs" />

      <!-- Last.fm Listening Stats -->
      <div class="lastfm-stats-section">
        <h3 class="lastfm-section-title">
          <i class="fas fa-headphones" style="color: #d51007;"></i>
          Estadísticas de Escucha (Last.fm)
        </h3>
        <ListeningStats />
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  CategoryScale,
  LinearScale,
  BarElement,
  PointElement,
  LineElement
} from 'chart.js';
import DashboardStatsGrid from './DashboardStatsGrid.vue';
import DashboardChartsGrid from './DashboardChartsGrid.vue';
import ListeningStats from '@/components/Albums/ListeningStats.vue';
import StatsService from '@/services/StatsService';
import Logger from '@/utils/logger';
import {
  createStatsCards,
  createChartConfigs,
  extractMockStats
} from '@/composables/useDashboardCharts';

ChartJS.register(
  Title, Tooltip, Legend, ArcElement,
  CategoryScale, LinearScale, BarElement,
  PointElement, LineElement
);

const loading = ref(true);
const error = ref(null);
const albumStats = ref(null);

const mockStats = computed(() => extractMockStats(albumStats.value, 'albums'));
const statsCards = computed(() => createStatsCards(mockStats.value, 'albums'));

const statusData = computed(() => {
  if (!albumStats.value?.statusStats) return StatsService.transformStatusDataForChart({});
  return StatsService.transformStatusDataForChart(albumStats.value.statusStats);
});

const ratingsData = computed(() => {
  if (!albumStats.value?.ratingStats) return StatsService.transformRatingDataForChart({});
  return StatsService.transformRatingDataForChart(albumStats.value.ratingStats);
});

const genresData = computed(() => {
  if (!albumStats.value?.genreStats) return StatsService.transformGenreDataForChart({});
  return StatsService.transformGenreDataForChart(albumStats.value.genreStats);
});

const albumTypeData = computed(() => {
  if (!albumStats.value?.albumTypeStats) return StatsService.transformGenreDataForChart({});
  return StatsService.transformGenreDataForChart(albumStats.value.albumTypeStats);
});

const monthlyData = computed(() => {
  if (!albumStats.value?.monthlyStats) return StatsService.transformMonthlyDataForChart({}, 'albums');
  return StatsService.transformMonthlyDataForChart(albumStats.value.monthlyStats, 'albums');
});

const chartConfigs = computed(() => {
  const chartData = {
    statusData: statusData.value,
    ratingsData: ratingsData.value,
    genresData: genresData.value,
    albumTypeData: albumTypeData.value,
    monthlyData: monthlyData.value
  };
  return createChartConfigs(chartData, 'albums');
});

const loadAlbumStats = async () => {
  try {
    loading.value = true;
    error.value = null;
    Logger.info('[AlbumsDashboardContent] Loading album statistics...');
    const stats = await StatsService.getAlbumStats();
    albumStats.value = stats;
    Logger.info('[AlbumsDashboardContent] Album statistics loaded successfully', stats);
  } catch (err) {
    Logger.error('[AlbumsDashboardContent] Failed to load album statistics:', err);
    error.value = 'Error al cargar las estadísticas. Por favor, intenta de nuevo.';
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  await loadAlbumStats();
});
</script>


<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/dashboard' as *;

.dashboard-content {
  @include dashboard-content-page;
}

// Sección Last.fm exclusiva de Albums (drift intencional: brand color rojo)
.lastfm-stats-section {
  margin-top: spacing(xl);
  background: var(--color-background-card);
  border: 1px solid var(--color-border-light);
  border-radius: radius(lg);
  padding: spacing(lg);
}

.lastfm-section-title {
  display: flex;
  align-items: center;
  gap: spacing(xs);
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-text);
  margin: 0 0 spacing(md);
  padding-bottom: spacing(sm);
  border-bottom: 1px solid var(--color-border);
}
</style>
