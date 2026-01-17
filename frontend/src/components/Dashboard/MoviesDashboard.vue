<template>
  <div class="movies-dashboard">
    <DashboardHeader 
      title="Dashboard - Mis Películas"
      subtitle="Estadísticas y resumen de tu biblioteca personal de películas"
      icon="fas fa-film"
      library-link="/library?filter=movies"
      link-text="Ver Mi Biblioteca de Películas"
    />

    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Cargando estadísticas...</p>
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
import DashboardHeader from './shared/DashboardHeader.vue';
import DashboardStatsGrid from './DashboardStatsGrid.vue';
import DashboardChartsGrid from './DashboardChartsGrid.vue';
import StatsService from '@/services/StatsService';
import Logger from '@/utils/logger';
import { 
  createStatsCards, 
  createChartConfigs,
  extractMockStats
} from '@/composables/useDashboardCharts';

// Registrar componentes de Chart.js
ChartJS.register(
  Title,
  Tooltip,
  Legend,
  ArcElement,
  CategoryScale,
  LinearScale,
  BarElement,
  PointElement,
  LineElement
);

// Estados reactivos
const loading = ref(true);
const error = ref(null);
const movieStats = ref(null);

// Stats generales computadas usando helper compartido
const mockStats = computed(() => extractMockStats(movieStats.value, 'movies'));

// Configuración de tarjetas de estadísticas usando helper compartido
const statsCards = computed(() => createStatsCards(mockStats.value, 'movies'));

// Datos para gráficas
const watchStatusData = computed(() => {
  if (!movieStats.value?.statusStats) {
    return StatsService.transformStatusDataForChart({});
  }
  return StatsService.transformStatusDataForChart(movieStats.value.statusStats);
});

const ratingsData = computed(() => {
  if (!movieStats.value?.ratingStats) {
    return StatsService.transformRatingDataForChart({});
  }
  return StatsService.transformRatingDataForChart(movieStats.value.ratingStats);
});

const genresData = computed(() => {
  if (!movieStats.value?.genreStats) {
    return StatsService.transformGenreDataForChart({});
  }
  return StatsService.transformGenreDataForChart(movieStats.value.genreStats);
});

const monthlyWatchedData = computed(() => {
  if (!movieStats.value?.monthlyWatchedStats) {
    return StatsService.transformMonthlyDataForChart({}, 'movies');
  }
  return StatsService.transformMonthlyDataForChart(movieStats.value.monthlyWatchedStats, 'movies');
});

// Configuración de gráficas usando helper compartido
const chartConfigs = computed(() => {
  const chartData = {
    statusData: watchStatusData.value,
    ratingsData: ratingsData.value,
    genresData: genresData.value,
    monthlyData: monthlyWatchedData.value
  };
  return createChartConfigs(chartData, 'movies');
});

// Cargar datos del servidor
const loadMovieStats = async () => {
  try {
    loading.value = true;
    error.value = null;
    Logger.info('[MoviesDashboard] Loading movie statistics...');
    
    const stats = await StatsService.getMovieStats();
    movieStats.value = stats;
    
    Logger.info('[MoviesDashboard] Movie statistics loaded successfully', stats);
  } catch (err) {
    Logger.error('[MoviesDashboard] Failed to load movie statistics:', err);
    error.value = 'Error al cargar las estadísticas. Por favor, intenta de nuevo.';
  } finally {
    loading.value = false;
  }
};

// Montar componente
onMounted(async () => {
  await loadMovieStats();
});
</script>

<style scoped>
.movies-dashboard {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
  background: var(--color-background);
  min-height: 100vh;
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

/* Dark mode */
:global(.app-dark) .movies-dashboard {
  background: var(--color-background);
}

:global(.app-dark) .loading-container p,
:global(.app-dark) .error-container p {
  color: var(--color-text-secondary);
}
</style>
