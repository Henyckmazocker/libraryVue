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
