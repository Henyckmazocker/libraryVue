/**
 * Social Store using Pinia
 * Manages friends, feed, user search and privacy settings
 */
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import Logger from '@/utils/logger'

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

    async sendFriendRequest(addresseeId) {
      const authStore = useAuthStore()
      const response = await authStore.authenticatedApiCall('send_friend_request', { addresseeId })
      if (response.data.status !== 'success') {
        throw new Error(response.data.message || 'Error sending friend request')
      }
      return response.data.data
    },

    async acceptFriendRequest(friendshipId) {
      const authStore = useAuthStore()
      const response = await authStore.authenticatedApiCall('accept_friend_request', { friendshipId })
      if (response.data.status !== 'success') {
        throw new Error(response.data.message || 'Error accepting friend request')
      }
      // Remove from pending, refresh friends
      this.pendingRequests = this.pendingRequests.filter(r => r.friendship_id !== friendshipId)
      await this.fetchFriends()
      return response.data.data
    },

    async rejectFriendRequest(friendshipId) {
      const authStore = useAuthStore()
      const response = await authStore.authenticatedApiCall('reject_friend_request', { friendshipId })
      if (response.data.status !== 'success') {
        throw new Error(response.data.message || 'Error rejecting friend request')
      }
      this.pendingRequests = this.pendingRequests.filter(r => r.friendship_id !== friendshipId)
    },

    async removeFriend(friendId) {
      const authStore = useAuthStore()
      const response = await authStore.authenticatedApiCall('remove_friend', { friendId })
      if (response.data.status !== 'success') {
        throw new Error(response.data.message || 'Error removing friend')
      }
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
      try {
        const response = await authStore.authenticatedApiCall('get_feed', {
          limit: 20,
          offset: this.feedOffset
        })
        if (response.data.status === 'success') {
          const { events, hasMore } = response.data.data
          this.feed = reset ? (events ?? []) : [...this.feed, ...(events ?? [])]
          this.feedHasMore = hasMore ?? false
          this.feedOffset += (events?.length ?? 0)
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
      const response = await authStore.authenticatedApiCall('update_privacy_settings', settings)
      if (response.data.status !== 'success') {
        throw new Error(response.data.message || 'Error updating privacy settings')
      }
      this.privacySettings = response.data.data
      return this.privacySettings
    }
  }
})
