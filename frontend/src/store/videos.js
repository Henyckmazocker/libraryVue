/**
 * Videos Store using Pinia
 * Manages YouTube video library state and operations
 */
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import { handleStoreError } from '@/utils/storeHelpers'
import Logger from '@/utils/logger'

export const useVideosStore = defineStore('videos', {
  state: () => ({
    videos: [],
    allowedStatuses: [],
    userTags: [],
    videoNotes: {}, // Notes per video: { youtubeId: [notes] }
    isLoading: false,
    error: null,
    lastSearchQuery: '',
    searchResults: [],
    isSearching: false
  }),

  getters: {
    totalVideos: (state) => state.videos.length,

    hasVideos: (state) => state.videos.length > 0,

    hasSearchResults: (state) => state.searchResults.length > 0,

    videosWithRating: (state) => {
      return state.videos.filter(video => video.user_rating && video.user_rating > 0)
    },

    averageRating: (state) => {
      const rated = state.videos.filter(v => v.user_rating && v.user_rating > 0)
      if (rated.length === 0) return 0
      const sum = rated.reduce((acc, v) => acc + parseFloat(v.user_rating), 0)
      return (sum / rated.length).toFixed(1)
    },

    videosByStatus: (state) => {
      return (statusId) => {
        return state.videos.filter(video =>
          video.userStatuses && video.userStatuses.some(s => s.status_id === statusId)
        )
      }
    },

    getVideoById: (state) => {
      return (videoId) => state.videos.find(v => v.id === videoId)
    },

    getVideoByYouTubeId: (state) => {
      return (youtubeId) => state.videos.find(v => v.youtube_id === youtubeId)
    },

    isVideoInLibrary: (state) => {
      return (youtubeId) => state.videos.some(v => v.youtube_id === youtubeId)
    },

    videoCountByStatus: (state) => {
      const counts = {}
      state.allowedStatuses.forEach(status => {
        counts[status.id] = state.videos.filter(video =>
          video.userStatuses && video.userStatuses.some(s => s.status_id === status.id)
        ).length
      })
      return counts
    }
  },

  actions: {
    /**
     * Fetch all videos from the user's library
     */
    async fetchVideos() {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[VideosStore] Fetching videos from library...')
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('get_videos', {
          filters: {}
        })

        if (response.data.status === 'success') {
          this.videos = Array.isArray(response.data.data) ? response.data.data : []
          Logger.debug(`[VideosStore] Fetched ${this.videos.length} videos`)
          return this.videos
        } else {
          throw new Error(response.data.message || 'Failed to fetch videos')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch videos')
        Logger.error('[VideosStore] Error fetching videos:', err)
        return []
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Search videos via YouTube Data API
     */
    async searchVideos(query) {
      if (!query || query.trim() === '') {
        this.searchResults = []
        return []
      }

      this.isSearching = true
      this.error = null
      this.lastSearchQuery = query

      try {
        Logger.debug(`[VideosStore] Searching videos: "${query}"`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('search_youtube_videos', {
          q: query
        })

        if (response.data.status === 'success') {
          this.searchResults = response.data.data || []
          Logger.debug(`[VideosStore] Found ${this.searchResults.length} videos`)
          return this.searchResults
        } else {
          throw new Error(response.data.message || 'Search failed')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Search failed')
        Logger.error('[VideosStore] Error searching videos:', err)
        return []
      } finally {
        this.isSearching = false
      }
    },

    /**
     * Add a video to the library
     */
    async addVideo(video, statuses = []) {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[VideosStore] Adding video to library:', video)
        const authStore = useAuthStore()

        const videoData = {
          youtube_id: video.youtube_id || video.youtubeId || video.id,
          title: video.title || video.name || '',
          channel_name: video.channel_name || video.channelName || '',
          channel_id: video.channel_id || video.channelId || '',
          cover_url: video.cover_url || video.coverUrl || video.thumbnail || '',
          duration: video.duration || '',
          duration_seconds: video.duration_seconds || video.durationSeconds || 0,
          view_count: video.view_count || video.viewCount || 0,
          like_count: video.like_count || video.likeCount || 0,
          published_at: video.published_at || video.publishedAt || '',
          description: video.description || '',
          categories: Array.isArray(video.categories) ? video.categories : [],
          userStatuses: statuses
        }

        const response = await authStore.authenticatedApiCall('add_video', videoData)

        if (response.data.status === 'success') {
          const addedVideo = response.data.data || videoData
          this.videos.push(addedVideo)
          Logger.debug('[VideosStore] Video added successfully')
          return { success: true, video: addedVideo }
        } else {
          throw new Error(response.data.message || 'Failed to add video')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to add video')
        Logger.error('[VideosStore] Error adding video:', err)
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Delete a video from the library
     */
    async deleteVideo(youtubeId) {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[VideosStore] Deleting video:', youtubeId)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('delete_video', {
          youtubeId: youtubeId
        })

        if (response.data.status === 'success') {
          this.videos = this.videos.filter(v => v.youtube_id !== youtubeId)
          Logger.debug('[VideosStore] Video deleted successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to delete video')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to delete video')
        Logger.error('[VideosStore] Error deleting video:', err)
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Update video rating
     */
    async updateVideoRating(youtubeId, rating) {
      try {
        Logger.debug(`[VideosStore] Updating video rating: ${youtubeId} -> ${rating}`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('update_video_rating', {
          youtubeId: youtubeId,
          rating: rating
        })

        if (response.data.status === 'success') {
          const video = this.videos.find(v => v.youtube_id === youtubeId)
          if (video) {
            video.user_rating = rating
          }
          Logger.debug('[VideosStore] Video rating updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update rating')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update rating')
        Logger.error('[VideosStore] Error updating video rating:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Update video user statuses
     */
    async updateVideoStatuses(youtubeId, statuses) {
      try {
        Logger.debug(`[VideosStore] Updating video statuses: ${youtubeId}`, statuses)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('update_video_user_statuses', {
          youtubeId: youtubeId,
          statuses: statuses
        })

        if (response.data.status === 'success') {
          const video = this.videos.find(v => v.youtube_id === youtubeId)
          if (video) {
            video.userStatuses = statuses
          }
          Logger.debug('[VideosStore] Video statuses updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update statuses')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update statuses')
        Logger.error('[VideosStore] Error updating video statuses:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Edit user video (full update)
     */
    async editVideo(youtubeId, updatedData) {
      try {
        Logger.debug(`[VideosStore] Editing video: ${youtubeId}`, updatedData)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('edit_user_video', {
          youtubeId: youtubeId,
          ...updatedData
        })

        if (response.data.status === 'success') {
          const videoIndex = this.videos.findIndex(v => v.youtube_id === youtubeId)
          if (videoIndex !== -1) {
            this.videos[videoIndex] = {
              ...this.videos[videoIndex],
              ...updatedData
            }
          }
          Logger.debug('[VideosStore] Video edited successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to edit video')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to edit video')
        Logger.error('[VideosStore] Error editing video:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Fetch allowed statuses for videos
     */
    async fetchAllowedStatuses() {
      try {
        Logger.debug('[VideosStore] Fetching allowed statuses...')
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('get_video_allowed_statuses')

        if (response.data.status === 'success') {
          this.allowedStatuses = response.data.data || []
          Logger.debug(`[VideosStore] Fetched ${this.allowedStatuses.length} allowed statuses`)
          return this.allowedStatuses
        } else {
          throw new Error(response.data.message || 'Failed to fetch allowed statuses')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch allowed statuses')
        Logger.error('[VideosStore] Error fetching allowed statuses:', err)
        return []
      }
    },

    /**
     * Fetch user tags for videos
     */
    async fetchUserTags() {
      try {
        Logger.debug('[VideosStore] Fetching user tags...')
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('get_user_video_tags')

        if (response.data.status === 'success') {
          this.userTags = response.data.data || []
          Logger.debug(`[VideosStore] Fetched ${this.userTags.length} user tags`)
          return this.userTags
        } else {
          throw new Error(response.data.message || 'Failed to fetch user tags')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch user tags')
        Logger.error('[VideosStore] Error fetching user tags:', err)
        return []
      }
    },

    /**
     * Create a new video tag
     */
    async createTag(name, color = '#c0392b') {
      try {
        Logger.debug(`[VideosStore] Creating tag: ${name}`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('create_user_video_tag', {
          name: name,
          color: color
        })

        if (response.data.status === 'success') {
          const newTag = response.data.data
          this.userTags.push(newTag)
          Logger.debug('[VideosStore] Tag created successfully')
          return { success: true, tag: newTag }
        } else {
          throw new Error(response.data.message || 'Failed to create tag')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to create tag')
        Logger.error('[VideosStore] Error creating tag:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Update video tags
     */
    async updateVideoTags(videoId, tagIds) {
      try {
        Logger.debug(`[VideosStore] Updating video tags: ${videoId}`, tagIds)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('update_video_tags', {
          videoId: videoId,
          tag_ids: tagIds
        })

        if (response.data.status === 'success') {
          Logger.debug('[VideosStore] Video tags updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update tags')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update tags')
        Logger.error('[VideosStore] Error updating video tags:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Fetch notes for a video
     */
    async fetchVideoNotes(youtubeId) {
      try {
        Logger.debug(`[VideosStore] Fetching notes for video: ${youtubeId}`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('get_video_notes', {
          youtubeId: youtubeId
        })

        if (response.data.status === 'success') {
          this.videoNotes[youtubeId] = response.data.data || []
          Logger.debug(`[VideosStore] Fetched ${this.videoNotes[youtubeId].length} notes`)
          return { success: true, notes: this.videoNotes[youtubeId] }
        } else {
          throw new Error(response.data.message || 'Failed to fetch notes')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch notes')
        Logger.error('[VideosStore] Error fetching video notes:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Add a note to a video
     */
    async addVideoNote(youtubeId, noteText, noteType = 'note', isPrivate = true) {
      try {
        Logger.debug(`[VideosStore] Adding note to video: ${youtubeId}`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('add_video_note', {
          youtubeId: youtubeId,
          noteText: noteText,
          noteType: noteType,
          isPrivate: isPrivate ? 1 : 0
        })

        if (response.data.status === 'success') {
          const newNote = response.data.data?.note || response.data.data
          if (!this.videoNotes[youtubeId]) {
            this.videoNotes[youtubeId] = []
          }
          this.videoNotes[youtubeId].push(newNote)
          Logger.debug('[VideosStore] Note added successfully')
          return { success: true, note: newNote }
        } else {
          throw new Error(response.data.message || 'Failed to add note')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to add note')
        Logger.error('[VideosStore] Error adding note:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Update a note on a video
     */
    async updateVideoNote(noteId, noteText, noteType = 'note', isPrivate = true) {
      try {
        Logger.debug(`[VideosStore] Updating note: ${noteId}`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('update_video_note', {
          noteId: noteId,
          noteText: noteText,
          noteType: noteType,
          isPrivate: isPrivate ? 1 : 0
        })

        if (response.data.status === 'success') {
          for (const youtubeId in this.videoNotes) {
            const noteIndex = this.videoNotes[youtubeId].findIndex(n => n.id === noteId)
            if (noteIndex !== -1) {
              this.videoNotes[youtubeId][noteIndex] = {
                ...this.videoNotes[youtubeId][noteIndex],
                note_text: noteText,
                note_type: noteType,
                is_private: isPrivate ? 1 : 0
              }
              break
            }
          }
          Logger.debug('[VideosStore] Note updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update note')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update note')
        Logger.error('[VideosStore] Error updating note:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Delete a note from a video
     */
    async deleteVideoNote(noteId) {
      try {
        Logger.debug(`[VideosStore] Deleting note: ${noteId}`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('delete_video_note', {
          noteId: noteId
        })

        if (response.data.status === 'success') {
          for (const youtubeId in this.videoNotes) {
            const noteIndex = this.videoNotes[youtubeId].findIndex(n => n.id === noteId)
            if (noteIndex !== -1) {
              this.videoNotes[youtubeId].splice(noteIndex, 1)
              break
            }
          }
          Logger.debug('[VideosStore] Note deleted successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to delete note')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to delete note')
        Logger.error('[VideosStore] Error deleting note:', err)
        return { success: false, message: this.error }
      }
    },

    clearSearchResults() {
      this.searchResults = []
      this.lastSearchQuery = ''
    },

    clearError() {
      this.error = null
    },

    _handleError(err, defaultMessage = 'Operation failed') {
      return handleStoreError(err, defaultMessage)
    }
  }
})
