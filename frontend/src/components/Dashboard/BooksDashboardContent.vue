<template>
  <div class="dashboard-content">
    <div class="content-header">
      <router-link to="/library?filter=books" class="btn btn--primary">
        <i class="fas fa-library"></i>
        Ver Mi Biblioteca de Libros
      </router-link>
    </div>

    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Cargando estadísticas de libros...</p>
    </div>

    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <p>{{ error }}</p>
      <button @click="loadBookStats" class="btn btn--primary">
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
const bookStats = ref(null);

const mockStats = computed(() => extractMockStats(bookStats.value, 'books'));
const statsCards = computed(() => createStatsCards(mockStats.value, 'books'));

const readingStatusData = computed(() => {
  if (!bookStats.value?.statusStats) return StatsService.transformStatusDataForChart({});
  return StatsService.transformStatusDataForChart(bookStats.value.statusStats);
});

const ratingsData = computed(() => {
  if (!bookStats.value?.ratingStats) return StatsService.transformRatingDataForChart({});
  return StatsService.transformRatingDataForChart(bookStats.value.ratingStats);
});

const genresData = computed(() => {
  if (!bookStats.value?.genreStats) return StatsService.transformGenreDataForChart({});
  return StatsService.transformGenreDataForChart(bookStats.value.genreStats);
});

const monthlyPagesData = computed(() => {
  if (!bookStats.value?.monthlyPagesStats) return StatsService.transformMonthlyDataForChart({}, 'pages');
  return StatsService.transformMonthlyDataForChart(bookStats.value.monthlyPagesStats, 'pages');
});

const chartConfigs = computed(() => {
  const chartData = {
    statusData: readingStatusData.value,
    ratingsData: ratingsData.value,
    genresData: genresData.value,
    monthlyData: monthlyPagesData.value
  };
  return createChartConfigs(chartData, 'books');
});

const loadBookStats = async () => {
  try {
    loading.value = true;
    error.value = null;
    Logger.info('[BooksDashboardContent] Loading book statistics...');
    const stats = await StatsService.getBookStats();
    bookStats.value = stats;
    Logger.info('[BooksDashboardContent] Book statistics loaded successfully', stats);
  } catch (err) {
    Logger.error('[BooksDashboardContent] Failed to load book statistics:', err);
    error.value = 'Error al cargar las estadísticas. Por favor, intenta de nuevo.';
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  await loadBookStats();
});
</script>

<style scoped>
.dashboard-content {
  width: 100%;
}

.content-header {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 1.5rem;
}

.loading-container,
.error-container {
  text-align: center;
  padding: 3rem;
}

.loading-container i {
  font-size: 3rem;
  color: var(--color-primary);
  margin-bottom: 1rem;
}

.error-container i {
  font-size: 3rem;
  color: var(--color-error);
  margin-bottom: 1rem;
}

.loading-container p,
.error-container p {
  font-size: 1.125rem;
  color: var(--color-text-muted);
  margin-bottom: 1.5rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  font-weight: 600;
  text-decoration: none;
  transition: var(--transition-fast);
  border: none;
  cursor: pointer;
}

.btn--primary {
  background: var(--btn-primary-bg);
  color: var(--btn-primary-text);
}

.btn--primary:hover {
  background: var(--btn-primary-bg-hover);
  transform: translateY(-2px);
  box-shadow: var(--shadow-medium);
}
</style>
