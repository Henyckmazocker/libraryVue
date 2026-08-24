import { useMediaNotes } from '@/composables/useMediaNotes'

/**
 * Reexporte de compatibilidad: la lógica vive en useMediaNotes, configurada
 * desde mediaRegistry. Se mantiene mientras haya código que importe este
 * nombre; los consumidores nuevos deben usar useMediaNotes('video').
 */
export function useVideoNotes () {
  return useMediaNotes('video')
}
