import { ref, computed } from 'vue'
import { useAuthStore } from '@/store/auth'
import { storeToRefs } from 'pinia'

/**
 * Composable for fetching Last.fm listening statistics.
 *
 * Usage:
 *   const { stats, isLoading, error, fetchStats } = useListeningStats()
 *   await fetchStats({ statsType: 'top_albums', period: '1month', limit: 10 })
 */
export function useListeningStats() {
  const authStore = useAuthStore()
  const { userLastFmUsername } = storeToRefs(authStore)

  const stats = ref(null)
  const isLoading = ref(false)
  const error = ref(null)

  const hasLastFmUsername = computed(() => !!userLastFmUsername.value)

  /**
   * @param {Object} opts
   * @param {string} opts.statsType  top_albums | top_artists | top_tracks | recent_tracks |
   *                                 loved_tracks | user_info | weekly_album_chart | weekly_artist_chart |
   *                                 album_info
   * @param {string} opts.period     overall | 7day | 1month | 3month | 6month | 12month
   * @param {number} opts.limit
   * @param {string} opts.artist     Required for album_info
   * @param {string} opts.album      Required for album_info
   */
  async function fetchStats({ statsType = 'user_info', period = 'overall', limit = 20, artist = '', album = '' } = {}) {
    // album_info works without a lastfm_username (shows global stats without personal playcount)
    if (statsType !== 'album_info' && !hasLastFmUsername.value) {
      error.value = 'No tienes un usuario de Last.fm configurado. Ve a tu perfil para añadirlo.'
      return
    }

    isLoading.value = true
    error.value = null
    stats.value = null

    try {
      const payload = { stats_type: statsType, period, limit }
      if (artist) payload.artist = artist
      if (album) payload.album = album

      const response = await authStore.authenticatedApiCall('get_listening_stats', payload)

      if (response.data.status === 'success') {
        stats.value = response.data.data
      } else {
        error.value = response.data.message || 'Error al obtener estadísticas de Last.fm'
      }
    } catch (err) {
      error.value = err?.response?.data?.message || err.message || 'Error de conexión'
    } finally {
      isLoading.value = false
    }
  }

  return {
    stats,
    isLoading,
    error,
    hasLastFmUsername,
    fetchStats
  }
}
