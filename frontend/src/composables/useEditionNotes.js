import { useMediaNotes } from '@/composables/useMediaNotes'

/**
 * Reexporte de compatibilidad: la lógica vive en useMediaNotes, configurada
 * desde mediaRegistry. Se mantiene mientras haya código que importe este
 * nombre; los consumidores nuevos deben usar useMediaNotes('book').
 */
export function useEditionNotes () {
  return useMediaNotes('book')
}
