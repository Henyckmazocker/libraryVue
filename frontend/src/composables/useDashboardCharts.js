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
  const isGames = itemType === 'games';
  
  // Determinar icono principal
  let mainIcon = 'fas fa-book';
  if (isGames) mainIcon = 'fas fa-gamepad';
  else if (!isBooks) mainIcon = 'fas fa-film';
  
  // Determinar número total
  let totalNumber = stats.totalBooks;
  if (isGames) totalNumber = stats.totalGames;
  else if (!isBooks) totalNumber = stats.totalMovies;
  
  // Determinar label total
  let totalLabel = 'Total de Libros';
  if (isGames) totalLabel = 'Total de Videojuegos';
  else if (!isBooks) totalLabel = 'Total de Películas';
  
  // Determinar número completado
  let completedNumber = stats.readBooks;
  if (isGames) completedNumber = stats.completedGames;
  else if (!isBooks) completedNumber = stats.watchedMovies;
  
  // Determinar label completado
  let completedLabel = 'Libros Leídos';
  if (isGames) completedLabel = 'Juegos Completados';
  else if (!isBooks) completedLabel = 'Películas Vistas';
  
  // Determinar número pendiente
  let pendingNumber = stats.pendingBooks;
  if (isGames) pendingNumber = stats.pendingGames;
  else if (!isBooks) pendingNumber = stats.pendingMovies;
  
  // Determinar label pendiente
  let pendingLabel = 'Por Leer';
  if (isGames) pendingLabel = 'Por Jugar';
  else if (!isBooks) pendingLabel = 'Por Ver';
  
  return [
    {
      icon: mainIcon,
      number: totalNumber,
      label: totalLabel,
      color: 'primary'
    },
    {
      icon: 'fas fa-check-circle',
      number: completedNumber,
      label: completedLabel,
      color: 'success'
    },
    {
      icon: 'fas fa-clock',
      number: pendingNumber,
      label: pendingLabel,
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

// Opciones para gráficas de barras horizontales
export const getHorizontalBarChartOptions = () => ({
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
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
    x: {
      beginAtZero: true
    }
  }
});

// Helper para crear configuración de gráficas
export const createChartConfigs = (chartData, itemType = 'books') => {
  const isBooks = itemType === 'books';
  const isGames = itemType === 'games';
  
  // Determinar títulos según tipo
  let statusTitle = 'Estado de Lectura';
  let monthlyTitle = 'Progreso Mensual de Páginas Leídas';
  let genreIcon = 'fas fa-book-open';
  
  if (isGames) {
    statusTitle = 'Estado de Juego';
    monthlyTitle = 'Juegos Añadidos por Mes';
    genreIcon = 'fas fa-gamepad';
  } else if (!isBooks) {
    statusTitle = 'Estado de Visualización';
    monthlyTitle = 'Películas Vistas por Mes';
    genreIcon = 'fas fa-film';
  }
  
  const charts = [
    {
      title: statusTitle,
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
      icon: genreIcon
    }
  ];
  
  // Gráficas adicionales exclusivas de videojuegos
  if (isGames) {
    if (chartData.platformsData) {
      charts.push({
        title: 'Juegos por Plataforma',
        type: 'bar',
        data: chartData.platformsData,
        options: getHorizontalBarChartOptions(),
        icon: 'fas fa-desktop'
      });
    }
    if (chartData.completionData) {
      charts.push({
        title: 'Estado de Completitud',
        type: 'doughnut',
        data: chartData.completionData,
        options: getChartOptions(),
        icon: 'fas fa-trophy'
      });
    }
  }
  
  // Gráfica de línea al final
  charts.push({
    title: monthlyTitle,
    type: 'line',
    data: chartData.monthlyData,
    options: getLineChartOptions(),
    icon: 'fas fa-chart-line'
  });
  
  return charts;
};

// Helper para extraer estadísticas mock desde datos del servidor
export const extractMockStats = (rawStats, itemType = 'books') => {
  if (!rawStats) {
    if (itemType === 'books') {
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
    } else if (itemType === 'games') {
      return {
        totalGames: 0,
        completedGames: 0,
        pendingGames: 0,
        playingGames: 0,
        averageRating: 0,
        favoriteDeveloper: 'N/A',
        favoriteGenre: 'N/A',
        totalHoursPlayed: 0,
        averagePlayTime: 'N/A'
      };
    } else {
      return {
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
  }

  const statusStats = rawStats.statusStats || {};
  const isBooks = itemType === 'books';
  const isGames = itemType === 'games';
  
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
  } else if (isGames) {
    return {
      totalGames: rawStats.totalGames || 0,
      completedGames: statusStats.completed || statusStats.completado || 0,
      pendingGames: statusStats.to_play || statusStats['por jugar'] || statusStats.deseado || statusStats.owned || 0,
      playingGames: statusStats.playing || statusStats.jugando || 0,
      averageRating: rawStats.ratingStats?.averageRating || 0,
      favoriteDeveloper: 'Análisis en desarrollo',
      favoriteGenre: rawStats.genreStats?.topGenres ? Object.keys(rawStats.genreStats.topGenres)[0] : 'N/A',
      totalHoursPlayed: rawStats.hoursPlayedStats?.totalHours || 0,
      averagePlayTime: rawStats.hoursPlayedStats?.averageHours ? `${rawStats.hoursPlayedStats.averageHours}h` : 'N/A'
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
