<template>
  <div class="books-dashboard">
    <!-- Header del Dashboard -->
    <div class="dashboard-header">
      <h1 class="dashboard-title">
        <i class="fas fa-book"></i>
        Dashboard - Mis Libros
      </h1>
      <p class="dashboard-subtitle">
        Estadísticas y resumen de tu biblioteca personal de libros
      </p>
    </div>

    <!-- Botón para ir a la biblioteca -->
    <div class="quick-actions">
      <router-link 
        to="/library?filter=books" 
        class="btn btn--primary btn--large"
      >
        <i class="fas fa-library"></i>
        Ver Mi Biblioteca de Libros
      </router-link>
    </div>

    <!-- Grid de estadísticas -->
    <div class="stats-grid">
      <!-- Total de libros -->
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-book"></i>
        </div>
        <div class="stat-content">
          <h3 class="stat-number">{{ mockStats.totalBooks }}</h3>
          <p class="stat-label">Total de Libros</p>
        </div>
      </div>

      <!-- Libros leídos -->
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
          <h3 class="stat-number">{{ mockStats.readBooks }}</h3>
          <p class="stat-label">Libros Leídos</p>
        </div>
      </div>

      <!-- Libros pendientes -->
      <div class="stat-card">
        <div class="stat-icon">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stat-content">
          <h3 class="stat-number">{{ mockStats.pendingBooks }}</h3>
          <p class="stat-label">Por Leer</p>
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
        <!-- Gráfica de estado de lectura -->
        <div class="chart-card">
          <h3 class="chart-title">Estado de Lectura</h3>
          <div class="chart-container">
            <DoughnutChart 
              :data="readingStatusData" 
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

        <!-- Gráfica de progreso mensual -->
        <div class="chart-card">
          <h3 class="chart-title">Páginas Leídas por Mes</h3>
          <div class="chart-container">
            <LineChart 
              :data="monthlyPagesData" 
              :options="lineChartOptions"
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
          <li><strong>Autor más leído:</strong> {{ mockStats.favoriteAuthor }}</li>
          <li><strong>Género preferido:</strong> {{ mockStats.favoriteGenre }}</li>
          <li><strong>Páginas totales leídas:</strong> {{ mockStats.totalPages.toLocaleString() }}</li>
          <li><strong>Tiempo promedio de lectura:</strong> {{ mockStats.averageReadingTime }}</li>
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
const bookStats = ref(null);

// Stats generales computadas
const mockStats = computed(() => {
  if (!bookStats.value) {
    return {
      totalBooks: 0,
      readBooks: 0,
      pendingBooks: 0,
      currentlyReading: 0,
      averageRating: 0,
      favoriteAuthor: 'N/A',
      favoriteGenre: 'N/A',
      totalPages: 0,
      averageReadingTime: 'N/A'
    };
  }

  const stats = bookStats.value;
  const statusStats = stats.statusStats || {};
  
  return {
    totalBooks: stats.totalBooks || 0,
    readBooks: statusStats.read || statusStats.leido || 0,
    pendingBooks: statusStats.to_read || statusStats['por leer'] || statusStats.deseado || 0,
    currentlyReading: statusStats.reading || statusStats.leyendo || 0,
    averageRating: stats.ratingStats?.averageRating || 0,
    favoriteAuthor: 'Análisis en desarrollo',
    favoriteGenre: stats.genreStats?.topGenres ? Object.keys(stats.genreStats.topGenres)[0] : 'N/A',
    totalPages: 'Calculando...',
    averageReadingTime: 'Análisis en desarrollo'
  };
});

// Datos para gráfica de estado de lectura
const readingStatusData = computed(() => {
  if (!bookStats.value?.statusStats) {
    return StatsService.transformStatusDataForChart({});
  }
  return StatsService.transformStatusDataForChart(bookStats.value.statusStats);
});

// Datos para gráfica de calificaciones
const ratingsData = computed(() => {
  if (!bookStats.value?.ratingStats) {
    return StatsService.transformRatingDataForChart({});
  }
  return StatsService.transformRatingDataForChart(bookStats.value.ratingStats);
});

// Datos para gráfica de géneros
const genresData = computed(() => {
  if (!bookStats.value?.genreStats) {
    return StatsService.transformGenreDataForChart({});
  }
  return StatsService.transformGenreDataForChart(bookStats.value.genreStats);
});

// Datos para progreso mensual de páginas leídas
const monthlyPagesData = computed(() => {
  if (!bookStats.value?.monthlyPagesStats) {
    return StatsService.transformMonthlyDataForChart({}, 'pages');
  }
  return StatsService.transformMonthlyDataForChart(bookStats.value.monthlyPagesStats, 'pages');
});

// Cargar datos del servidor
const loadBookStats = async () => {
  try {
    loading.value = true;
    error.value = null;
    Logger.info('[BooksDashboard] Loading book statistics...');
    
    const stats = await StatsService.getBookStats();
    bookStats.value = stats;
    
    Logger.info('[BooksDashboard] Book statistics loaded successfully', stats);
  } catch (err) {
    Logger.error('[BooksDashboard] Failed to load book statistics:', err);
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
  loadBookStats();
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
.books-dashboard {
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
  color: #4CAF50;
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
  color: #4CAF50;
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
}
</style>