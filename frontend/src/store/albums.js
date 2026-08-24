import { createMediaStore } from './createMediaStore'

/**
 * Albums Store using Pinia
 *
 * La implementación vive en createMediaStore, configurada desde mediaRegistry.
 * Los nombres que usaban los consumidores (fetchAlbums, addAlbum, totalAlbums,
 * getAlbumBySpotifyId…) se generan como alias, así que nada de fuera cambia.
 *
 * Las acciones de notas (fetchAlbumNotes, addAlbumNote, updateAlbumNote,
 * deleteAlbumNote) se han eliminado: su lógica vive en useMediaNotes('album')
 * desde el plan de componentes genéricos.
 */
export const useAlbumsStore = createMediaStore('album')
