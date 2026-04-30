<template>
  <div class="games-dashboard">
    <DashboardHeader 
      title="Dashboard - Mis Videojuegos"
      subtitle="Estadísticas y resumen de tu biblioteca personal de videojuegos"
      icon="fas fa-gamepad"
      library-link="/library?filter=games"
      link-text="Ver Mi Biblioteca de Videojuegos"
    />

    <div v-if="loading" class="loading-container">
      <i class="fas fa-spinner fa-spin"></i>
      <p>Cargando estadísticas...</p>
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
const gameStats = ref(null);

// Stats generales computadas usando helper compartido
const mockStats = computed(() => extractMockStats(gameStats.value, 'games'));

// Configuración de tarjetas de estadísticas usando helper compartido
const statsCards = computed(() => createStatsCards(mockStats.value, 'games'));

// Datos para gráficas
const playStatusData = computed(() => {
  if (!gameStats.value?.statusStats) {
    return StatsService.transformStatusDataForChart({});
  }
  return StatsService.transformStatusDataForChart(gameStats.value.statusStats);
});

const ratingsData = computed(() => {
  if (!gameStats.value?.ratingStats) {
    return StatsService.transformRatingDataForChart({});
  }
  return StatsService.transformRatingDataForChart(gameStats.value.ratingStats);
});

const genresData = computed(() => {
  if (!gameStats.value?.genreStats) {
    return StatsService.transformGenreDataForChart({});
  }
  return StatsService.transformGenreDataForChart(gameStats.value.genreStats);
});

const platformsData = computed(() => {
  if (!gameStats.value?.platformStats) {
    return StatsService.transformGenreDataForChart({}); // Reusar la misma transformación
  }
  return StatsService.transformGenreDataForChart(gameStats.value.platformStats);
});

const completionData = computed(() => {
  if (!gameStats.value?.completionStats) {
    return { labels: [], datasets: [] };
  }
  
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
  if (!gameStats.value?.monthlyPlayedStats) {
    return StatsService.transformMonthlyDataForChart({}, 'games');
  }
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

// Cargar datos del servidor
const loadGameStats = async () => {
  try {
    loading.value = true;
    error.value = null;
    Logger.info('[GamesDashboard] Loading game statistics...');
    
    const stats = await StatsService.getGameStats();
    gameStats.value = stats;
    
    Logger.info('[GamesDashboard] Game statistics loaded successfully', stats);
  } catch (err) {
    Logger.error('[GamesDashboard] Failed to load game statistics:', err);
    error.value = 'Error al cargar las estadísticas. Por favor, intenta de nuevo.';
  } finally {
    loading.value = false;
  }
};

// Montar componente
onMounted(async () => {
  await loadGameStats();
});
</script>

<style scoped>
.games-dashboard {
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
}

@media (max-width: 768px) {
  .games-dashboard {
    padding: 1rem;
  }
}
</style>
