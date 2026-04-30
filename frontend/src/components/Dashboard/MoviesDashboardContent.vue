<template>
  <div class="dashboard-content">
    <div class="content-header">
      <router-link to="/library?filter=movies" class="btn btn--primary">
        <i class="fas fa-library"></i>
        Ver Mi Biblioteca de Películas
      </router-link>
    </div>

    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Cargando estadísticas de películas...</p>
    </div>

    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <p>{{ error }}</p>
      <button @click="loadMovieStats" class="btn btn--primary">
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
const movieStats = ref(null);

const mockStats = computed(() => extractMockStats(movieStats.value, 'movies'));
const statsCards = computed(() => createStatsCards(mockStats.value, 'movies'));

const watchStatusData = computed(() => {
  if (!movieStats.value?.statusStats) return StatsService.transformStatusDataForChart({});
  return StatsService.transformStatusDataForChart(movieStats.value.statusStats);
});

const ratingsData = computed(() => {
  if (!movieStats.value?.ratingStats) return StatsService.transformRatingDataForChart({});
  return StatsService.transformRatingDataForChart(movieStats.value.ratingStats);
});

const genresData = computed(() => {
  if (!movieStats.value?.genreStats) return StatsService.transformGenreDataForChart({});
  return StatsService.transformGenreDataForChart(movieStats.value.genreStats);
});

const monthlyWatchedData = computed(() => {
  if (!movieStats.value?.monthlyWatchedStats) return StatsService.transformMonthlyDataForChart({}, 'movies');
  return StatsService.transformMonthlyDataForChart(movieStats.value.monthlyWatchedStats, 'movies');
});

const chartConfigs = computed(() => {
  const chartData = {
    statusData: watchStatusData.value,
    ratingsData: ratingsData.value,
    genresData: genresData.value,
    monthlyData: monthlyWatchedData.value
  };
  return createChartConfigs(chartData, 'movies');
});

const loadMovieStats = async () => {
  try {
    loading.value = true;
    error.value = null;
    Logger.info('[MoviesDashboardContent] Loading movie statistics...');
    const stats = await StatsService.getMovieStats();
    movieStats.value = stats;
    Logger.info('[MoviesDashboardContent] Movie statistics loaded successfully', stats);
  } catch (err) {
    Logger.error('[MoviesDashboardContent] Failed to load movie statistics:', err);
    error.value = 'Error al cargar las estadísticas. Por favor, intenta de nuevo.';
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  await loadMovieStats();
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
