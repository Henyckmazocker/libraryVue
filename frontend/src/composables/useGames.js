import { createMediaComposable } from './createMediaComposable'

/**
 * Composable de videojuegos.
 *
 * Sin extras: todo su cuerpo lo genera `createMediaComposable` desde
 * `mediaRegistry`.
 */
export function useGames() {
  return createMediaComposable('game')
}
