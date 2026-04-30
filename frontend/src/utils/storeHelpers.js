/**
 * Shared utilities for Pinia stores
 * Extracted to avoid code duplication across books, movies, and games stores
 */

/**
 * Parses an API error and returns a user-friendly message.
 * Handles axios error shapes (response, request, message) and falls back to a default.
 *
 * @param {Error} err - The caught error (typically from axios)
 * @param {string} defaultMessage - Fallback message if no specific info is available
 * @returns {string} Human-readable error message
 */
export function handleStoreError(err, defaultMessage = 'Operation failed') {
  if (err.response) {
    const status = err.response.status
    const data = err.response.data

    if (status === 401) {
      return 'Authentication required. Please login again.'
    } else if (status === 403) {
      return 'Invalid CSRF token. Please refresh the page and try again.'
    } else if (data && data.message) {
      return data.message
    } else {
      return `Server error (${status})`
    }
  } else if (err.request) {
    return 'Network error. Please check your connection.'
  } else if (err.message) {
    return err.message
  }

  return defaultMessage
}

/**
 * Checks whether a game object matches a given ID.
 * Games may carry the IGDB id under different property names depending on the
 * source (backend response, IGDB search result, legacy RAWG data).
 *
 * @param {object} game - Game object from the store
 * @param {number|string} targetId - The ID to compare against
 * @returns {boolean}
 */
export function matchesGameId(game, targetId) {
  return game.id === targetId || game.rawgId === targetId || game.gameId === targetId
}
