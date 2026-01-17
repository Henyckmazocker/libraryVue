<template>
  <GenericSearch :config="searchConfig" />
</template>

<script setup>
import { computed } from 'vue';
import axios from 'axios';
import GenericSearch from '@/components/shared/GenericSearch.vue';
import MovieListItem from '@/components/Movies/MovieListItem.vue';
import { useMovies } from '@/composables/useMovies';
import { useUIStore } from '@/store/ui';
import Logger from '@/utils/logger';

// Composables
const moviesComposable = useMovies();
const uiStore = useUIStore();

// Handler de búsqueda para películas
const searchMovies = async (query, searchType) => {
  if (searchType === 'title') {
    try {
      Logger.debug('[MovieSearch] Searching movies:', query);
      const apiKey = 'f03583fd';
      const url = `https://www.omdbapi.com/?apikey=${apiKey}&s=${encodeURIComponent(query)}`;
      const response = await axios.get(url);
      
      if (response.data && response.data.Response === 'True') {
        Logger.debug(`[MovieSearch] Found ${response.data.Search.length} movies`);
        return response.data.Search;
      } else {
        throw new Error(response.data.Error || 'No se encontraron resultados.');
      }
    } catch (error) {
      Logger.error('[MovieSearch] Error searching movies:', error);
      throw error;
    }
  }
  
  return [];
};

// Transformar resultado de búsqueda
const transformResult = (result) => {
  return {
    isbn: result.imdbID,
    imdbID: result.imdbID,
    title: result.Title,
    Title: result.Title,
    year: result.Year,
    Year: result.Year,
    coverUrl: result.Poster !== 'N/A' ? result.Poster : null,
    Poster: result.Poster,
    user_rating: 0,
    userStatuses: []
  };
};

// Navegación a detalle
const navigateToDetail = (router, movie) => {
  if (!movie.imdbID) {
    Logger.warn('[MovieSearch] Movie has no IMDb ID, cannot navigate to detail');
    uiStore.showError('Esta película no tiene IMDb ID disponible');
    return;
  }
  
  Logger.debug('[MovieSearch] Navigating to movie detail:', movie.imdbID);
  
  const movieData = {
    isbn: movie.imdbID,
    imdbID: movie.imdbID,
    title: movie.Title || movie.title,
    originalTitle: movie.Title || movie.title,
    year: movie.Year || movie.year,
    coverUrl: movie.Poster !== 'N/A' ? movie.Poster : null,
    user_rating: 0,
    userStatuses: [],
    itemType: 'movie'
  };
  
  router.push({
    name: 'MovieDetail',
    params: { imdbId: movie.imdbID },
    state: { movie: movieData }
  });
};

// Obtener clave única del resultado
const getResultKey = (result) => {
  return result.imdbID || `movie-${Date.now()}-${Math.random()}`;
};

// Cargar estados permitidos
const fetchAllowedStatuses = async () => {
  await moviesComposable.fetchAllowedStatuses();
  return Array.isArray(moviesComposable.allowedStatuses.value) 
    ? moviesComposable.allowedStatuses.value 
    : [];
};

// Configuración del componente genérico
const searchConfig = computed(() => ({
  title: 'Buscador de Películas (OMDb)',
  inputs: [
    {
      type: 'title',
      placeholder: 'Introduce el título o palabra clave',
      buttonText: '',
      emptyMessage: 'Introduce un título o palabra clave para buscar.',
      errorMessage: 'Error al buscar las películas.'
    }
  ],
  itemComponent: MovieListItem,
  itemProp: 'movie',
  searchHandler: searchMovies,
  transformResult: transformResult,
  navigateToDetail: navigateToDetail,
  getResultKey: getResultKey,
  fetchAllowedStatuses: fetchAllowedStatuses
}));
</script>

