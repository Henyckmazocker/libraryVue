import { createMediaStore } from './createMediaStore'

/**
 * Books Store using Pinia
 *
 * La implementación vive en createMediaStore, configurada desde mediaRegistry.
 * Los nombres que usaban los consumidores (fetchBooks, addBook, totalBooks,
 * getBookByIsbn…) se generan como alias, así que nada de fuera cambia.
 *
 * fetchBooks sigue leyendo de _libraryCache: comparte get_library_items con
 * las películas, y la deduplicación en vuelo se hace ahora dentro de la factoría.
 */
export const useBooksStore = createMediaStore('book')
