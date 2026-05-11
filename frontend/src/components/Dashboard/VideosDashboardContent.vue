<template>
  <div class="dashboard-content">
    <div class="content-header">
      <router-link to="/library?filter=videos" class="btn btn--primary">
        <i class="fab fa-youtube"></i>
        Ver Mi Biblioteca de Vídeos
      </router-link>
    </div>

    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Cargando estadísticas de vídeos...</p>
    </div>

    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <p>{{ error }}</p>
      <button @click="loadVideoStats" class="btn btn--primary">
        Reintentar
      </button>
    </div>

    <template v-else>
      <DashboardStatsGrid :stats="statsCards" />
      <DashboardChartsGrid :charts="chartConfigs" />
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
const videoStats = ref(null);

const mockStats = computed(() => extractMockStats(videoStats.value, 'videos'));
const statsCards = computed(() => createStatsCards(mockStats.value, 'videos'));

const statusData = computed(() => {
  if (!videoStats.value?.statusStats) return StatsService.transformStatusDataForChart({});
  return StatsService.transformStatusDataForChart(videoStats.value.statusStats);
});

const ratingsData = computed(() => {
  if (!videoStats.value?.ratingStats) return StatsService.transformRatingDataForChart({});
  return StatsService.transformRatingDataForChart(videoStats.value.ratingStats);
});

const genresData = computed(() => {
  if (!videoStats.value?.categoryStats) return StatsService.transformGenreDataForChart({});
  return StatsService.transformGenreDataForChart(videoStats.value.categoryStats);
});

const monthlyData = computed(() => {
  if (!videoStats.value?.monthlyStats) return StatsService.transformMonthlyDataForChart({}, 'videos');
  return StatsService.transformMonthlyDataForChart(videoStats.value.monthlyStats, 'videos');
});

const chartConfigs = computed(() => {
  const chartData = {
    statusData: statusData.value,
    ratingsData: ratingsData.value,
    genresData: genresData.value,
    monthlyData: monthlyData.value
  };
  return createChartConfigs(chartData, 'videos');
});

const loadVideoStats = async () => {
  try {
    loading.value = true;
    error.value = null;
    Logger.info('[VideosDashboardContent] Loading video statistics...');
    const stats = await StatsService.getVideoStats();
    videoStats.value = stats;
    Logger.info('[VideosDashboardContent] Video statistics loaded successfully', stats);
  } catch (err) {
    Logger.error('[VideosDashboardContent] Failed to load video statistics:', err);
    error.value = 'Error al cargar las estadísticas. Por favor, intenta de nuevo.';
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  await loadVideoStats();
});
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/dashboard' as *;

.dashboard-content {
  @include dashboard-content-page;
}
</style>
