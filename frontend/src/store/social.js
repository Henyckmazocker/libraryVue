/**
 * Social Store using Pinia
 * Manages friends, feed, user search and privacy settings
 */
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import Logger from '@/utils/logger'
import { t } from '@/config/i18n'
import { track } from '@/analytics'

/** Desde dónde se puede pedir una amistad (el enum de `friend_request_sent.source`). */
const FRIEND_REQUEST_SOURCES = ['user_search', 'public_profile']

/** El tamaño de página del feed: de él sale el `page` de `feed_loaded`. */
const FEED_PAGE_SIZE = 20

export const useSocialStore = defineStore('social', {
  state: () => ({
    friends: [],
    pendingRequests: [],
    feed: [],
    feedHasMore: true,
    feedOffset: 0,
    feedLoading: false,
    privacySettings: null,
    searchResults: [],
    isSearching: false,
    isLoading: false,
    error: null
  }),

  getters: {
    pendingRequestsCount: (state) => state.pendingRequests.length,
    hasFriends: (state) => state.friends.length > 0,
    hasFeed: (state) => state.feed.length > 0
  },

  actions: {
    // ─────────────────────────────────────────────
    // Friends
    // ─────────────────────────────────────────────

    async fetchFriends() {
      const authStore = useAuthStore()
      try {
        const response = await authStore.authenticatedApiCall('get_friends', {})
        if (response.data.status === 'success') {
          this.friends = response.data.data ?? []
        }
      } catch (err) {
        Logger.error('[SocialStore] fetchFriends error:', err)
      }
    },

    async fetchPendingRequests() {
      const authStore = useAuthStore()
      try {
        const response = await authStore.authenticatedApiCall('get_friend_requests', {})
        if (response.data.status === 'success') {
          this.pendingRequests = response.data.data ?? []
        }
      } catch (err) {
        Logger.error('[SocialStore] fetchPendingRequests error:', err)
      }
    },

    /**
     * `source` lo pone quien llama (`user_search` desde /friends, `public_profile` desde el
     * perfil): el store no sabe desde qué pantalla le piden. Sin él, `unknown`.
     */
    async sendFriendRequest(addresseeId, source = 'unknown') {
      const authStore = useAuthStore()
      const response = await authStore.authenticatedApiCall('send_friend_request', { addresseeId })
      if (response.data.status !== 'success') {
        throw new Error(t('friends.sendFailed'))
      }
      track('friend_request_sent', {
        source: FRIEND_REQUEST_SOURCES.includes(source) ? source : 'unknown'
      })
      return response.data.data
    },

    async acceptFriendRequest(friendshipId) {
      const authStore = useAuthStore()
      const response = await authStore.authenticatedApiCall('accept_friend_request', { friendshipId })
      if (response.data.status !== 'success') {
        throw new Error(t('friends.acceptFailed'))
      }
      track('friend_request_accepted')
      // Remove from pending, refresh friends
      this.pendingRequests = this.pendingRequests.filter(r => r.friendship_id !== friendshipId)
      await this.fetchFriends()
      return response.data.data
    },

    async rejectFriendRequest(friendshipId) {
      const authStore = useAuthStore()
      const response = await authStore.authenticatedApiCall('reject_friend_request', { friendshipId })
      if (response.data.status !== 'success') {
        throw new Error(t('friends.rejectFailed'))
      }
      track('friend_request_rejected')
      this.pendingRequests = this.pendingRequests.filter(r => r.friendship_id !== friendshipId)
    },

    async removeFriend(friendId) {
      const authStore = useAuthStore()
      const response = await authStore.authenticatedApiCall('remove_friend', { friendId })
      if (response.data.status !== 'success') {
        throw new Error(t('friends.removeFailed'))
      }
      track('friend_removed')
      this.friends = this.friends.filter(f => f.id !== friendId)
    },

    // ─────────────────────────────────────────────
    // User search
    // ─────────────────────────────────────────────

    async searchUsers(term) {
      const authStore = useAuthStore()
      this.isSearching = true
      try {
        const response = await authStore.authenticatedApiCall('search_users', { term })
        if (response.data.status === 'success') {
          this.searchResults = response.data.data ?? []
          // Del término, solo su longitud: es un nombre de usuario o parte de uno.
          track('user_searched', {
            query_length: String(term ?? '').length,
            results: Array.isArray(this.searchResults) ? this.searchResults.length : 0
          })
        }
      } catch (err) {
        Logger.error('[SocialStore] searchUsers error:', err)
        this.searchResults = []
      } finally {
        this.isSearching = false
      }
    },

    clearSearchResults() {
      this.searchResults = []
    },

    // ─────────────────────────────────────────────
    // Feed
    // ─────────────────────────────────────────────

    async loadFeed(reset = false) {
      if (this.feedLoading) return
      if (reset) {
        this.feed = []
        this.feedOffset = 0
        this.feedHasMore = true
      }
      if (!this.feedHasMore) return

      const authStore = useAuthStore()
      this.feedLoading = true
      // La página se fija ANTES de pedirla: el offset avanza al llegar la respuesta.
      const page = Math.floor(this.feedOffset / FEED_PAGE_SIZE) + 1
      try {
        const response = await authStore.authenticatedApiCall('get_feed', {
          limit: FEED_PAGE_SIZE,
          offset: this.feedOffset
        })
        if (response.data.status === 'success') {
          const { events, hasMore } = response.data.data
          this.feed = reset ? (events ?? []) : [...this.feed, ...(events ?? [])]
          this.feedHasMore = hasMore ?? false
          this.feedOffset += (events?.length ?? 0)
          track('feed_loaded', { page, n_events: Array.isArray(events) ? events.length : 0 })
        }
      } catch (err) {
        Logger.error('[SocialStore] loadFeed error:', err)
      } finally {
        this.feedLoading = false
      }
    },

    // ─────────────────────────────────────────────
    // Privacy settings
    // ─────────────────────────────────────────────

    async fetchPrivacySettings() {
      const authStore = useAuthStore()
      try {
        const response = await authStore.authenticatedApiCall('get_privacy_settings', {})
        if (response.data.status === 'success') {
          this.privacySettings = response.data.data
        }
      } catch (err) {
        Logger.error('[SocialStore] fetchPrivacySettings error:', err)
      }
    },

    async updatePrivacySettings(settings) {
      const authStore = useAuthStore()
      const before = this.privacySettings ?? {}
      const response = await authStore.authenticatedApiCall('update_privacy_settings', settings)
      if (response.data.status !== 'success') {
        throw new Error(t('friends.privacyFailed'))
      }
      // CUÁNTOS interruptores cambiaron, no cuáles: el panel manda siempre los siete. Contra lo
      // que había en el store antes de guardar (sin ajustes cargados, cuentan todos los enviados).
      const fields = Object.keys(settings ?? {}).filter((key) => settings[key] !== before[key]).length
      track('privacy_settings_updated', { fields })
      this.privacySettings = response.data.data
      return this.privacySettings
    }
  }
})
