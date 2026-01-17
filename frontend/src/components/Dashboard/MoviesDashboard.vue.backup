<template>
  <div class="movies-dashboard">
    <!-- Header del Dashboard -->
    <div class="dashboard-header">
      <h1 class="dashboard-title">
        <i class="fas fa-film"></i>
        Dashboard - Mis Películas
      </h1>
      <p class="dashboard-subtitle">
        Estadísticas y resumen de tu biblioteca personal de películas
      </p>
    </div>

    <!-- Botón para ir a la biblioteca -->
    <div class="quick-actions">
      <router-link 
        to="/library?filter=movies" 
        class="btn btn--primary btn--large"
      >
        <i class="fas fa-library"></i>
        Ver Mi Biblioteca de Películas
      </router-link>
    </div>

    <!-- Grid de estadísticas -->
    <div class="stats-grid">
      <!-- Total de películas -->
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-film"></i>
        </div>
        <div class="stat-content">
          <h3 class="stat-number">{{ mockStats.totalMovies }}</h3>
          <p class="stat-label">Total de Películas</p>
        </div>
      </div>

      <!-- Películas vistas -->
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
          <h3 class="stat-number">{{ mockStats.watchedMovies }}</h3>
          <p class="stat-label">Películas Vistas</p>
        </div>
      </div>

      <!-- Películas pendientes -->
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
          <h3 class="stat-number">{{ mockStats.pendingMovies }}</h3>
          <p class="stat-label">Por Ver</p>
        </div>
      </div>

      <!-- Calificación promedio -->
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-star"></i>
        </div>
        <div class="stat-content">
          <h3 class="stat-number">{{ formatRating(mockStats.averageRating) }}</h3>
          <p class="stat-label">Calificación Promedio</p>
        </div>
      </div>
    </div>

    <!-- Gráficas -->
    <div class="charts-section">
      <div class="charts-grid">
        <!-- Gráfica de estado de visualización -->
        <div class="chart-card">
          <h3 class="chart-title">Estado de Visualización</h3>
          <div class="chart-container">
            <DoughnutChart 
              :data="watchingStatusData" 
              :options="chartOptions"
            />
          </div>
        </div>

        <!-- Gráfica de calificaciones -->
        <div class="chart-card">
          <h3 class="chart-title">Distribución de Calificaciones</h3>
          <div class="chart-container">
            <BarChart 
              :data="ratingsData" 
              :options="barChartOptions"
            />
          </div>
        </div>

        <!-- Gráfica de géneros -->
        <div class="chart-card">
          <h3 class="chart-title">Géneros Favoritos</h3>
          <div class="chart-container">
            <PieChart 
              :data="genresData" 
              :options="chartOptions"
            />
          </div>
        </div>

        <!-- Gráfica de duración promedio -->
        <div class="chart-card">
          <h3 class="chart-title">Duración de Películas</h3>
          <div class="chart-container">
            <BarChart 
              :data="durationData" 
              :options="durationChartOptions"
            />
          </div>
        </div>

        <!-- Gráfica de progreso mensual -->
        <div class="chart-card chart-card--wide">
          <h3 class="chart-title">Películas Vistas por Mes</h3>
          <div class="chart-container">
            <LineChart 
              :data="monthlyProgressData" 
              :options="lineChartOptions"
            />
          </div>
        </div>

        <!-- Gráfica de décadas -->
        <div class="chart-card">
          <h3 class="chart-title">Películas por Década</h3>
          <div class="chart-container">
            <BarChart 
              :data="decadeData" 
              :options="barChartOptions"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Información adicional -->
    <div class="info-section">
      <div class="info-card">
        <h3>Estadísticas Detalladas</h3>
        <ul class="stats-list">
          <li><strong>Director favorito:</strong> {{ mockStats.favoriteDirector }}</li>
          <li><strong>Género preferido:</strong> {{ mockStats.favoriteGenre }}</li>
          <li><strong>Actor más visto:</strong> {{ mockStats.favoriteActor }}</li>
          <li><strong>Tiempo total visto:</strong> {{ mockStats.totalWatchTime }}</li>
          <li><strong>Duración promedio:</strong> {{ mockStats.averageDuration }}</li>
          <li><strong>Película mejor calificada:</strong> {{ mockStats.topRatedMovie }}</li>
        </ul>
      </div>
    </div>
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
import { Doughnut as DoughnutChart, Bar as BarChart, Pie as PieChart, Line as LineChart } from 'vue-chartjs';
import StatsService from '@/services/StatsService';
import Logger from '@/utils/logger';

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

// Stats generales computadas
const mockStats = computed(() => {
  if (!movieStats.value) {
    return {
      totalMovies: 0,
      watchedMovies: 0,
      pendingMovies: 0,
      currentlyWatching: 0,
      abandonedMovies: 0,
      averageRating: 0,
      favoriteDirector: 'N/A',
      favoriteGenre: 'N/A',
      favoriteActor: 'N/A',
      totalWatchTime: 'N/A',
      averageDuration: 'N/A',
      topRatedMovie: 'N/A'
    };
  }

  const stats = movieStats.value;
  const statusStats = stats.statusStats || {};
  
  return {
    totalMovies: stats.totalMovies || 0,
    watchedMovies: statusStats.watched || statusStats.visto || 0,
    pendingMovies: statusStats.to_watch || statusStats['por ver'] || statusStats.deseado || 0,
    currentlyWatching: statusStats.watching || statusStats.viendo || 0,
    abandonedMovies: statusStats.dropped || statusStats.abandonado || 0,
    averageRating: stats.ratingStats?.averageRating || 0,
    favoriteDirector: 'Análisis en desarrollo',
    favoriteGenre: stats.genreStats?.topGenres ? Object.keys(stats.genreStats.topGenres)[0] : 'N/A',
    favoriteActor: 'Análisis en desarrollo',
    totalWatchTime: 'Calculando...',
    averageDuration: 'Análisis en desarrollo',
    topRatedMovie: 'Análisis en desarrollo'
  };
});

// Datos para gráfica de estado de visualización
const watchingStatusData = computed(() => {
  if (!movieStats.value?.statusStats) {
    return StatsService.transformStatusDataForChart({});
  }
  return StatsService.transformStatusDataForChart(movieStats.value.statusStats);
});

// Datos para gráfica de calificaciones
const ratingsData = computed(() => {
  if (!movieStats.value?.ratingStats) {
    return StatsService.transformRatingDataForChart({});
  }
  return StatsService.transformRatingDataForChart(movieStats.value.ratingStats);
});

// Datos para gráfica de géneros
const genresData = computed(() => {
  if (!movieStats.value?.genreStats) {
    return StatsService.transformGenreDataForChart({});
  }
  return StatsService.transformGenreDataForChart(movieStats.value.genreStats);
});

// Datos para gráfica de duración (usando datos mock por ahora, puede implementarse después)
const durationData = computed(() => ({
  labels: ['< 90 min', '90-120 min', '120-150 min', '150-180 min', '> 180 min'],
  datasets: [{
    label: 'Número de películas',
    data: [0, 0, 0, 0, 0], // Mock data - análisis de duración en desarrollo
    backgroundColor: '#9C27B0',
    borderColor: '#7B1FA2',
    borderWidth: 1
  }]
}));

// Datos para progreso mensual
const monthlyProgressData = computed(() => {
  if (!movieStats.value?.monthlyStats) {
    return StatsService.transformMonthlyDataForChart({});
  }
  return StatsService.transformMonthlyDataForChart(movieStats.value.monthlyStats);
});

// Datos para gráfica de décadas
const decadeData = computed(() => {
  if (!movieStats.value?.decadeStats) {
    return {
      labels: [],
      datasets: [{
        label: 'Número de películas',
        data: [],
        backgroundColor: '#00BCD4',
        borderColor: '#00ACC1',
        borderWidth: 1
      }]
    };
  }
  const decadeStats = movieStats.value.decadeStats;
  return {
    labels: Object.keys(decadeStats),
    datasets: [{
      label: 'Número de películas',
      data: Object.values(decadeStats),
      backgroundColor: '#00BCD4',
      borderColor: '#00ACC1',
      borderWidth: 1
    }]
  };
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

// Función para formatear rating con decimales
const formatRating = (rating) => {
  if (!rating || rating === 0) return '0.0';
  return Number(rating).toFixed(1);
};

// Cargar datos al montar el componente
onMounted(() => {
  loadMovieStats();
});

// Opciones para gráficas circulares
const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        color: '#d7dadc',
        font: {
          size: 12
        }
      }
    },
    tooltip: {
      titleColor: '#ffffff',
      bodyColor: '#ffffff',
      backgroundColor: 'rgba(0, 0, 0, 0.8)'
    }
  }
};

// Opciones para gráfica de barras
const barChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        color: '#d7dadc'
      },
      grid: {
        color: '#343536'
      }
    },
    x: {
      ticks: {
        color: '#d7dadc'
      },
      grid: {
        color: '#343536'
      }
    }
  }
};

// Opciones para gráfica de duración
const durationChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        color: '#d7dadc'
      },
      grid: {
        color: '#343536'
      }
    },
    x: {
      ticks: {
        color: '#d7dadc'
      },
      grid: {
        color: '#343536'
      }
    }
  }
};

// Opciones para gráfica de líneas
const lineChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        color: '#d7dadc'
      },
      grid: {
        color: '#343536'
      }
    },
    x: {
      ticks: {
        color: '#d7dadc'
      },
      grid: {
        color: '#343536'
      }
    }
  }
};
</script>

<style scoped>
.movies-dashboard {
  max-width: 1400px;
  margin: 0 auto;
  padding: 20px;
}

.dashboard-header {
  text-align: center;
  margin-bottom: 30px;
}

.dashboard-title {
  font-size: 2.5rem;
  color: #d7dadc;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 15px;
}

.dashboard-title i {
  color: #FF6B35;
}

.dashboard-subtitle {
  font-size: 1.1rem;
  color: #b3b3b3;
  margin: 0;
}

.quick-actions {
  text-align: center;
  margin-bottom: 40px;
}

.btn--large {
  padding: 15px 30px;
  font-size: 1.1rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 40px;
}

.stat-card {
  background: #272729;
  border-radius: 12px;
  padding: 25px;
  display: flex;
  align-items: center;
  gap: 20px;
  border: 1px solid #343536;
  transition: transform 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-2px);
}

.stat-icon {
  font-size: 2.5rem;
  color: #FF6B35;
  min-width: 60px;
  text-align: center;
}

.stat-content {
  flex: 1;
}

.stat-number {
  font-size: 2.2rem;
  font-weight: bold;
  margin: 0 0 5px 0;
  color: #d7dadc;
}

.stat-label {
  margin: 0;
  color: #b3b3b3;
  font-size: 0.9rem;
}

.charts-section {
  margin-bottom: 40px;
}

.charts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 25px;
}

.chart-card {
  background: #272729;
  border-radius: 12px;
  padding: 25px;
  border: 1px solid #343536;
}

.chart-card--wide {
  grid-column: span 2;
}

.chart-title {
  color: #d7dadc;
  margin-bottom: 20px;
  font-size: 1.2rem;
  text-align: center;
}

.chart-container {
  height: 300px;
  position: relative;
}

.info-section {
  margin-bottom: 20px;
}

.info-card {
  background: #272729;
  border-radius: 12px;
  padding: 25px;
  border: 1px solid #343536;
}

.info-card h3 {
  color: #d7dadc;
  margin-bottom: 20px;
  font-size: 1.3rem;
}

.stats-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.stats-list li {
  padding: 8px 0;
  color: #b3b3b3;
  border-bottom: 1px solid #343536;
}

.stats-list li:last-child {
  border-bottom: none;
}

.stats-list strong {
  color: #d7dadc;
}

/* Responsive */
@media (max-width: 1200px) {
  .chart-card--wide {
    grid-column: span 1;
  }
}

@media (max-width: 768px) {
  .charts-grid {
    grid-template-columns: 1fr;
  }
  
  .stat-card {
    flex-direction: column;
    text-align: center;
  }
  
  .dashboard-title {
    flex-direction: column;
    font-size: 2rem;
  }
  
  .chart-card--wide {
    grid-column: span 1;
  }
}
</style>