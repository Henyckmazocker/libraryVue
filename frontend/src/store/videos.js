import { createMediaStore } from './createMediaStore'

/**
 * Videos Store using Pinia
 *
 * La implementación vive en createMediaStore, configurada desde mediaRegistry.
 * Los nombres que usaban los consumidores (fetchVideos, addVideo, totalVideos,
 * getVideoByYouTubeId…) se generan como alias, así que nada de fuera cambia.
 *
 * Las acciones de notas (fetchVideoNotes, addVideoNote, updateVideoNote,
 * deleteVideoNote) se han eliminado: su lógica vive en useMediaNotes('video')
 * desde el plan de componentes genéricos, y no tenían ya ningún consumidor.
 */
export const useVideosStore = createMediaStore('video')
