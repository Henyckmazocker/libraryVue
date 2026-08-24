/**
 * Composable para configuración compartida de gráficas de dashboard
 * Elimina duplicación entre BooksDashboard y MoviesDashboard
 *
 * El color no vive aquí: sale de `config/chartTheme.js`, que lo lee del sistema de
 * tokens. Por eso estas funciones son funciones y no constantes — hay que volver a
 * llamarlas al cambiar de tema para que la gráfica repinte.
 */
import { chartInk, chartTooltip } from '@/config/chartTheme';

// Ejes, rejilla y texto, recesivos y según el tema activo.
const scaleTheme = () => {
  const ink = chartInk();
  return {
    ticks: { color: ink.muted },
    grid: { color: ink.grid },
    border: { color: ink.axis }
  };
};

// Opciones base para gráficas de tipo doughnut/pie
export const getChartOptions = () => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        padding: 15,
        color: chartInk().text,
        font: {
          size: 12
        }
      }
    },
    tooltip: {
      ...chartTooltip(),
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
      ...chartTooltip(),
      padding: 12
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ...scaleTheme(),
      ticks: { ...scaleTheme().ticks, stepSize: 1 }
    },
    x: scaleTheme()
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
        padding: 15,
        color: chartInk().text
      }
    },
    tooltip: {
      ...chartTooltip(),
      padding: 12
    }
  },
  scales: {
    y: { beginAtZero: true, ...scaleTheme() },
    x: scaleTheme()
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
  const isAlbums = itemType === 'albums';

  let mainIcon = 'fas fa-book';
  if (isGames) mainIcon = 'fas fa-gamepad';
  else if (isAlbums) mainIcon = 'fas fa-music';
  else if (!isBooks) mainIcon = 'fas fa-film';

  let totalNumber = stats.totalBooks;
  if (isGames) totalNumber = stats.totalGames;
  else if (isAlbums) totalNumber = stats.totalAlbums;
  else if (!isBooks) totalNumber = stats.totalMovies;

  let totalLabel = 'Total de Libros';
  if (isGames) totalLabel = 'Total de Videojuegos';
  else if (isAlbums) totalLabel = 'Total de Álbumes';
  else if (!isBooks) totalLabel = 'Total de Películas';

  let completedNumber = stats.readBooks;
  if (isGames) completedNumber = stats.completedGames;
  else if (isAlbums) completedNumber = stats.listenedAlbums;
  else if (!isBooks) completedNumber = stats.watchedMovies;

  let completedLabel = 'Libros Leídos';
  if (isGames) completedLabel = 'Juegos Completados';
  else if (isAlbums) completedLabel = 'Álbumes Escuchados';
  else if (!isBooks) completedLabel = 'Películas Vistas';

  let pendingNumber = stats.pendingBooks;
  if (isGames) pendingNumber = stats.pendingGames;
  else if (isAlbums) pendingNumber = stats.wishlistAlbums;
  else if (!isBooks) pendingNumber = stats.pendingMovies;

  let pendingLabel = 'Por Leer';
  if (isGames) pendingLabel = 'Por Jugar';
  else if (isAlbums) pendingLabel = 'En Lista de Deseos';
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
      ...chartTooltip(),
      padding: 12
    }
  },
  scales: {
    x: { beginAtZero: true, ...scaleTheme() },
    y: scaleTheme()
  }
});

// Helper para crear configuración de gráficas
export const createChartConfigs = (chartData, itemType = 'books') => {
  const isBooks = itemType === 'books';
  const isGames = itemType === 'games';
  const isAlbums = itemType === 'albums';

  let statusTitle = 'Estado de Lectura';
  let monthlyTitle = 'Progreso Mensual de Páginas Leídas';
  let genreIcon = 'fas fa-book-open';

  if (isGames) {
    statusTitle = 'Estado de Juego';
    monthlyTitle = 'Juegos Añadidos por Mes';
    genreIcon = 'fas fa-gamepad';
  } else if (isAlbums) {
    statusTitle = 'Estado de Escucha';
    monthlyTitle = 'Álbumes Añadidos por Mes';
    genreIcon = 'fas fa-music';
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

  // Gráficas adicionales exclusivas de álbumes
  if (isAlbums) {
    if (chartData.albumTypeData) {
      charts.push({
        title: 'Tipos de Álbum',
        type: 'doughnut',
        data: chartData.albumTypeData,
        options: getChartOptions(),
        icon: 'fas fa-record-vinyl'
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
    } else if (itemType === 'albums') {
      return {
        totalAlbums: 0,
        listenedAlbums: 0,
        wishlistAlbums: 0,
        listeningAlbums: 0,
        averageRating: 0,
        favoriteArtist: 'N/A',
        favoriteGenre: 'N/A',
        totalListens: 0,
        averageListens: 'N/A'
      };
    } else if (itemType === 'videos') {
      return {
        totalVideos: 0,
        watchedVideos: 0,
        pendingVideos: 0,
        watchingVideos: 0,
        averageRating: 0,
        favoriteChannel: 'N/A',
        favoriteCategory: 'N/A',
        totalWatches: 0,
        averageWatches: 'N/A'
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
  const isAlbums = itemType === 'albums';
  const isVideos = itemType === 'videos';

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
  } else if (isAlbums) {
    return {
      totalAlbums: rawStats.totalAlbums || 0,
      listenedAlbums: statusStats.listened || statusStats.escuchado || 0,
      wishlistAlbums: statusStats.wishlist || statusStats.deseado || 0,
      listeningAlbums: statusStats.listening || statusStats.escuchando || 0,
      averageRating: rawStats.ratingStats?.averageRating || 0,
      favoriteArtist: 'Análisis en desarrollo',
      favoriteGenre: rawStats.genreStats?.topGenres ? Object.keys(rawStats.genreStats.topGenres)[0] : 'N/A',
      totalListens: rawStats.listenStats?.totalListens || 0,
      averageListens: rawStats.listenStats?.averageListens ? `${rawStats.listenStats.averageListens}` : 'N/A'
    };
  } else if (isVideos) {
    return {
      totalVideos: rawStats.totalVideos || 0,
      watchedVideos: statusStats.watched || statusStats.visto || statusStats.vista || 0,
      pendingVideos: statusStats.to_watch || statusStats['por ver'] || statusStats.deseado || 0,
      watchingVideos: statusStats.watching || statusStats.viendo || 0,
      averageRating: rawStats.ratingStats?.averageRating || 0,
      favoriteChannel: rawStats.channelStats ? Object.keys(rawStats.channelStats)[0] : 'N/A',
      favoriteCategory: rawStats.categoryStats?.topGenres ? Object.keys(rawStats.categoryStats.topGenres)[0] : 'N/A',
      totalWatches: rawStats.watchStats?.totalWatches || 0,
      averageWatches: rawStats.watchStats?.averageWatches ? `${rawStats.watchStats.averageWatches}` : 'N/A'
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
