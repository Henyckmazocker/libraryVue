/**
 * Composable para configuración compartida de gráficas de dashboard
 * Elimina duplicación entre BooksDashboard y MoviesDashboard
 */

// Opciones base para gráficas de tipo doughnut/pie
export const getChartOptions = () => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        padding: 15,
        font: {
          size: 12
        }
      }
    },
    tooltip: {
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      padding: 12,
      titleFont: {
        size: 14
      },
      bodyFont: {
        size: 13
      }
    }
  }
});

// Opciones para gráficas de barras
export const getBarChartOptions = () => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      padding: 12
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        stepSize: 1
      }
    }
  }
});

// Opciones para gráficas de línea
export const getLineChartOptions = () => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'top',
      labels: {
        padding: 15
      }
    },
    tooltip: {
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      padding: 12
    }
  },
  scales: {
    y: {
      beginAtZero: true
    }
  }
});

// Helper para formatear rating
export const formatRating = (rating) => {
  if (!rating || rating === 0) return 'N/A';
  return Number(rating).toFixed(1);
};

// Helper para crear configuración de tarjetas de estadísticas
export const createStatsCards = (stats, itemType = 'books') => {
  const isBooks = itemType === 'books';
  
  return [
    {
      icon: isBooks ? 'fas fa-book' : 'fas fa-film',
      number: isBooks ? stats.totalBooks : stats.totalMovies,
      label: isBooks ? 'Total de Libros' : 'Total de Películas',
      color: 'primary'
    },
    {
      icon: 'fas fa-check-circle',
      number: isBooks ? stats.readBooks : stats.watchedMovies,
      label: isBooks ? 'Libros Leídos' : 'Películas Vistas',
      color: 'success'
    },
    {
      icon: 'fas fa-clock',
      number: isBooks ? stats.pendingBooks : stats.pendingMovies,
      label: 'Por ' + (isBooks ? 'Leer' : 'Ver'),
      color: 'warning'
    },
    {
      icon: 'fas fa-star',
      number: formatRating(stats.averageRating),
      label: 'Calificación Promedio',
      color: 'info'
    }
  ];
};

// Helper para crear configuración de gráficas
export const createChartConfigs = (chartData, itemType = 'books') => {
  const isBooks = itemType === 'books';
  
  return [
    {
      title: isBooks ? 'Estado de Lectura' : 'Estado de Visualización',
      type: 'doughnut',
      data: chartData.statusData,
      options: getChartOptions(),
      icon: 'fas fa-chart-pie'
    },
    {
      title: 'Distribución de Calificaciones',
      type: 'bar',
      data: chartData.ratingsData,
      options: getBarChartOptions(),
      icon: 'fas fa-chart-bar'
    },
    {
      title: 'Géneros Favoritos',
      type: 'pie',
      data: chartData.genresData,
      options: getChartOptions(),
      icon: isBooks ? 'fas fa-book-open' : 'fas fa-film'
    },
    {
      title: isBooks ? 'Progreso Mensual de Páginas Leídas' : 'Películas Vistas por Mes',
      type: 'line',
      data: chartData.monthlyData,
      options: getLineChartOptions(),
      icon: 'fas fa-chart-line'
    }
  ];
};

// Helper para extraer estadísticas mock desde datos del servidor
export const extractMockStats = (rawStats, itemType = 'books') => {
  if (!rawStats) {
    return itemType === 'books' ? {
      totalBooks: 0,
      readBooks: 0,
      pendingBooks: 0,
      currentlyReading: 0,
      averageRating: 0,
      favoriteAuthor: 'N/A',
      favoriteGenre: 'N/A',
      totalPages: 0,
      averageReadingTime: 'N/A'
    } : {
      totalMovies: 0,
      watchedMovies: 0,
      pendingMovies: 0,
      currentlyWatching: 0,
      averageRating: 0,
      favoriteDirector: 'N/A',
      favoriteGenre: 'N/A',
      totalWatchTime: 0,
      averageWatchTime: 'N/A'
    };
  }

  const statusStats = rawStats.statusStats || {};
  const isBooks = itemType === 'books';
  
  if (isBooks) {
    return {
      totalBooks: rawStats.totalBooks || 0,
      readBooks: statusStats.read || statusStats.leido || 0,
      pendingBooks: statusStats.to_read || statusStats['por leer'] || statusStats.deseado || 0,
      currentlyReading: statusStats.reading || statusStats.leyendo || 0,
      averageRating: rawStats.ratingStats?.averageRating || 0,
      favoriteAuthor: 'Análisis en desarrollo',
      favoriteGenre: rawStats.genreStats?.topGenres ? Object.keys(rawStats.genreStats.topGenres)[0] : 'N/A',
      totalPages: 'Calculando...',
      averageReadingTime: 'Análisis en desarrollo'
    };
  } else {
    return {
      totalMovies: rawStats.totalMovies || 0,
      watchedMovies: statusStats.watched || statusStats.vista || 0,
      pendingMovies: statusStats.to_watch || statusStats['por ver'] || statusStats.deseada || 0,
      currentlyWatching: statusStats.watching || statusStats.viendo || 0,
      averageRating: rawStats.ratingStats?.averageRating || 0,
      favoriteDirector: 'Análisis en desarrollo',
      favoriteGenre: rawStats.genreStats?.topGenres ? Object.keys(rawStats.genreStats.topGenres)[0] : 'N/A',
      totalWatchTime: 'Calculando...',
      averageWatchTime: 'Análisis en desarrollo'
    };
  }
};
