<template>
  <div class="dashboard-content">
    <div class="content-header">
      <router-link to="/library?filter=games" class="btn btn--primary">
        <i class="fas fa-library"></i>
        Ver Mi Biblioteca de Videojuegos
      </router-link>
    </div>

    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Cargando estadísticas de videojuegos...</p>
    </div>

    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <p>{{ error }}</p>
      <button @click="loadGameStats" class="btn btn--primary">
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
const gameStats = ref(null);

const mockStats = computed(() => extractMockStats(gameStats.value, 'games'));
const statsCards = computed(() => createStatsCards(mockStats.value, 'games'));

const playStatusData = computed(() => {
  if (!gameStats.value?.statusStats) return StatsService.transformStatusDataForChart({});
  return StatsService.transformStatusDataForChart(gameStats.value.statusStats);
});

const ratingsData = computed(() => {
  if (!gameStats.value?.ratingStats) return StatsService.transformRatingDataForChart({});
  return StatsService.transformRatingDataForChart(gameStats.value.ratingStats);
});

const genresData = computed(() => {
  if (!gameStats.value?.genreStats) return StatsService.transformGenreDataForChart({});
  return StatsService.transformGenreDataForChart(gameStats.value.genreStats);
});

const platformsData = computed(() => {
  if (!gameStats.value?.platformStats) return StatsService.transformGenreDataForChart({});
  return StatsService.transformGenreDataForChart(gameStats.value.platformStats);
});

const completionData = computed(() => {
  if (!gameStats.value?.completionStats) return { labels: [], datasets: [] };
  const stats = gameStats.value.completionStats;
  const labels = Object.keys(stats);
  const values = Object.values(stats);
  return {
    labels,
    datasets: [{
      label: 'Estado de Completitud',
      data: values,
      backgroundColor: StatsService.generateColors(labels.length)
    }]
  };
});

const monthlyPlayedData = computed(() => {
  if (!gameStats.value?.monthlyPlayedStats) return StatsService.transformMonthlyDataForChart({}, 'games');
  return StatsService.transformMonthlyDataForChart(gameStats.value.monthlyPlayedStats, 'games');
});

// Configuración de gráficas usando helper compartido
const chartConfigs = computed(() => {
  const chartData = {
    statusData: playStatusData.value,
    ratingsData: ratingsData.value,
    genresData: genresData.value,
    platformsData: platformsData.value,
    completionData: completionData.value,
    monthlyData: monthlyPlayedData.value
  };
  return createChartConfigs(chartData, 'games');
});

const loadGameStats = async () => {
  try {
    loading.value = true;
    error.value = null;
    Logger.info('[GamesDashboardContent] Loading game statistics...');
    const stats = await StatsService.getGameStats();
    gameStats.value = stats;
    Logger.info('[GamesDashboardContent] Game statistics loaded successfully', stats);
  } catch (err) {
    Logger.error('[GamesDashboardContent] Failed to load game statistics:', err);
    error.value = 'Error al cargar las estadísticas. Por favor, intenta de nuevo.';
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  await loadGameStats();
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
}
</style>
