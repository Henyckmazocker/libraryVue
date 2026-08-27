import { createMediaComposable } from './createMediaComposable'

/**
 * Composable de álbumes de música.
 *
 * Sin extras: todo su cuerpo lo genera `createMediaComposable` desde
 * `mediaRegistry`. Buscar por el id de Spotify vive en el store, como
 * `getAlbumBySpotifyId` (`mediaRegistry.js`, `store.altGetter`).
 */
export function useAlbums() {
  return createMediaComposable('album')
}
