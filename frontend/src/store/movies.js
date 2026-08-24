import { createMediaStore } from './createMediaStore'

/**
 * Movies Store using Pinia
 *
 * La implementación vive en createMediaStore, configurada desde mediaRegistry.
 * Los nombres que usaban los consumidores (fetchMovies, addMovie, totalMovies,
 * getMovieById…) se generan como alias, así que nada de fuera cambia.
 *
 * fetchMovies sigue leyendo de _libraryCache: comparte get_library_items con
 * los libros, y la deduplicación en vuelo se hace ahora dentro de la factoría.
 */
export const useMoviesStore = createMediaStore('movie')
