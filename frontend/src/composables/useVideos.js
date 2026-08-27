import { createMediaComposable } from './createMediaComposable'

/**
 * Composable de vídeos.
 *
 * El quinto y el último en llegar: hasta el plan «Composables Genéricos por
 * Medio» los vídeos eran la única asimetría de los cinco medios y `MyLibrary`
 * hablaba con `useVideosStore` directamente. No es que el medio necesitara un
 * trato distinto — es que nadie lo había escrito.
 *
 * Sin extras: todo su cuerpo lo genera `createMediaComposable` desde
 * `mediaRegistry`.
 */
export function useVideos() {
  return createMediaComposable('video')
}
