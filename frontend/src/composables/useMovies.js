import { createMediaComposable } from './createMediaComposable'
import { useAuthStore } from '@/store/auth'
import { track } from '@/analytics'

/**
 * Composable de películas y series.
 *
 * El núcleo lo genera `createMediaComposable` desde `mediaRegistry`. Lo propio
 * del medio es el seguimiento de temporadas, que solo tiene sentido en series:
 * no hay nada equivalente en los otros cuatro medios, así que se queda aquí en
 * vez de generalizarse.
 */
export function useMovies() {
  return createMediaComposable('movie', () => ({
    /** Marca una temporada como vista, con su valoración y sus notas. */
    trackSeriesSeason: async (seriesIsbn, seasonNumber, data = {}) => {
      try {
        const authStore = useAuthStore()
        const response = await authStore.apiCall('track_series_season', {
          seriesIsbn,
          seasonNumber,
          status: data.status || 'viewed',
          dateViewed: data.dateViewed || null,
          personalRating: data.personalRating || null,
          notes: data.notes || null
        })
        // Solo con el OK del backend: `apiCall` no lanza con un 2xx que trae `status: 'error'`.
        if (response?.data?.status === 'success') {
          const season = Number(seasonNumber)
          track('series_season_tracked', { season: Number.isInteger(season) ? season : 0 })
        }
        return { success: true, data: response.data }
      } catch (err) {
        return { success: false, message: err.message }
      }
    },

    /** Temporadas ya vistas de una serie. */
    getSeriesProgress: async (seriesIsbn) => {
      try {
        const authStore = useAuthStore()
        const response = await authStore.apiCall('get_series_progress', { seriesIsbn })
        return { success: true, data: response.data?.data || {} }
      } catch (err) {
        return { success: false, message: err.message, data: {} }
      }
    }
  }))
}
