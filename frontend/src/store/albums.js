/**
 * Albums Store using Pinia
 * Manages music album library state and operations
 */
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import { handleStoreError } from '@/utils/storeHelpers'
import Logger from '@/utils/logger'

export const useAlbumsStore = defineStore('albums', {
  state: () => ({
    albums: [],
    allowedStatuses: [],
    userTags: [],
    albumNotes: {}, // Notes per album: { albumId: [notes] }
    isLoading: false,
    error: null,
    lastSearchQuery: '',
    searchResults: [],
    isSearching: false
  }),

  getters: {
    totalAlbums: (state) => state.albums.length,

    hasAlbums: (state) => state.albums.length > 0,

    hasSearchResults: (state) => state.searchResults.length > 0,

    albumsWithRating: (state) => {
      return state.albums.filter(album => album.user_rating && album.user_rating > 0)
    },

    averageRating: (state) => {
      const rated = state.albums.filter(a => a.user_rating && a.user_rating > 0)
      if (rated.length === 0) return 0
      const sum = rated.reduce((acc, a) => acc + parseFloat(a.user_rating), 0)
      return (sum / rated.length).toFixed(1)
    },

    albumsByStatus: (state) => {
      return (statusId) => {
        return state.albums.filter(album =>
          album.userStatuses && album.userStatuses.some(s => s.status_id === statusId)
        )
      }
    },

    getAlbumById: (state) => {
      return (albumId) => state.albums.find(a => a.id === albumId)
    },

    getAlbumBySpotifyId: (state) => {
      return (spotifyId) => state.albums.find(a => a.spotify_id === spotifyId)
    },

    isAlbumInLibrary: (state) => {
      return (albumId) => state.albums.some(a => a.id === albumId)
    },

    albumCountByStatus: (state) => {
      const counts = {}
      state.allowedStatuses.forEach(status => {
        counts[status.id] = state.albums.filter(album =>
          album.userStatuses && album.userStatuses.some(s => s.status_id === status.id)
        ).length
      })
      return counts
    }
  },

  actions: {
    /**
     * Fetch all albums from the user's library
     */
    async fetchAlbums() {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[AlbumsStore] Fetching albums from library...')
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('get_albums', {
          filters: {}
        })

        if (response.data.status === 'success') {
          this.albums = Array.isArray(response.data.data) ? response.data.data : []
          Logger.debug(`[AlbumsStore] Fetched ${this.albums.length} albums`)
          return this.albums
        } else {
          throw new Error(response.data.message || 'Failed to fetch albums')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch albums')
        Logger.error('[AlbumsStore] Error fetching albums:', err)
        return []
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Search albums by name via Spotify
     */
    async searchAlbums(query) {
      if (!query || query.trim() === '') {
        this.searchResults = []
        return []
      }

      this.isSearching = true
      this.error = null
      this.lastSearchQuery = query

      try {
        Logger.debug(`[AlbumsStore] Searching albums: "${query}"`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('search_spotify_albums', {
          name: query
        })

        if (response.data.status === 'success') {
          this.searchResults = response.data.data || []
          Logger.debug(`[AlbumsStore] Found ${this.searchResults.length} albums`)
          return this.searchResults
        } else {
          throw new Error(response.data.message || 'Search failed')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Search failed')
        Logger.error('[AlbumsStore] Error searching albums:', err)
        return []
      } finally {
        this.isSearching = false
      }
    },

    /**
     * Add an album to the library
     */
    async addAlbum(album, statuses = []) {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[AlbumsStore] Adding album to library:', album)
        const authStore = useAuthStore()

        const albumData = {
          spotify_id: album.spotify_id || album.spotifyId || album.id,
          title: album.title || album.name,
          artist: album.artist || album.artists?.[0]?.name || '',
          artist_id: album.artist_id || album.artists?.[0]?.id || '',
          release_date: album.release_date || album.releaseDate || '',
          release_date_precision: album.release_date_precision || album.releaseDatePrecision || 'year',
          cover_url: album.cover_url || album.coverUrl || album.images?.[0]?.url || '',
          genres: Array.isArray(album.genres)
            ? album.genres.map(g => typeof g === 'string' ? g : g.name)
            : [],
          label: album.label || '',
          total_tracks: album.total_tracks || album.totalTracks || 0,
          album_type: album.album_type || album.albumType || 'album',
          duration_ms: album.duration_ms || album.durationMs || 0,
          popularity: album.popularity || 0,
          external_url: album.external_url || album.externalUrl || album.external_urls?.spotify || '',
          upc: album.upc || '',
          userStatuses: statuses,
          ownership_format_id: album.ownership_format_id || null
        }

        const response = await authStore.authenticatedApiCall('add_album', {
          album: albumData
        })

        if (response.data.status === 'success') {
          const addedAlbum = response.data.data || albumData
          this.albums.push(addedAlbum)
          Logger.debug('[AlbumsStore] Album added successfully')
          return { success: true, album: addedAlbum }
        } else {
          throw new Error(response.data.message || 'Failed to add album')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to add album')
        Logger.error('[AlbumsStore] Error adding album:', err)
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Delete an album from the library
     */
    async deleteAlbum(albumId) {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[AlbumsStore] Deleting album:', albumId)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('delete_album', {
          albumId: albumId
        })

        if (response.data.status === 'success') {
          this.albums = this.albums.filter(a => a.id !== albumId)
          Logger.debug('[AlbumsStore] Album deleted successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to delete album')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to delete album')
        Logger.error('[AlbumsStore] Error deleting album:', err)
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Update album rating
     */
    async updateAlbumRating(albumId, rating) {
      try {
        Logger.debug(`[AlbumsStore] Updating album rating: ${albumId} -> ${rating}`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('update_album_rating', {
          albumId: albumId,
          rating: rating
        })

        if (response.data.status === 'success') {
          const album = this.albums.find(a => a.id === albumId)
          if (album) {
            album.user_rating = rating
          }
          Logger.debug('[AlbumsStore] Album rating updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update rating')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update rating')
        Logger.error('[AlbumsStore] Error updating album rating:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Update album user statuses
     */
    async updateAlbumStatuses(albumId, statuses) {
      try {
        Logger.debug(`[AlbumsStore] Updating album statuses: ${albumId}`, statuses)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('update_album_user_statuses', {
          albumId: albumId,
          statuses: statuses
        })

        if (response.data.status === 'success') {
          const album = this.albums.find(a => a.id === albumId)
          if (album) {
            album.userStatuses = statuses
          }
          Logger.debug('[AlbumsStore] Album statuses updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update statuses')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update statuses')
        Logger.error('[AlbumsStore] Error updating album statuses:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Edit user album (full update)
     */
    async editAlbum(albumId, updatedData) {
      try {
        Logger.debug(`[AlbumsStore] Editing album: ${albumId}`, updatedData)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('edit_user_album', {
          albumId: albumId,
          ...updatedData
        })

        if (response.data.status === 'success') {
          const albumIndex = this.albums.findIndex(a => a.id === albumId)
          if (albumIndex !== -1) {
            this.albums[albumIndex] = {
              ...this.albums[albumIndex],
              ...updatedData
            }
          }
          Logger.debug('[AlbumsStore] Album edited successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to edit album')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to edit album')
        Logger.error('[AlbumsStore] Error editing album:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Fetch allowed statuses for albums
     */
    async fetchAllowedStatuses() {
      try {
        Logger.debug('[AlbumsStore] Fetching allowed statuses...')
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('get_album_allowed_statuses')

        if (response.data.status === 'success') {
          this.allowedStatuses = response.data.data || []
          Logger.debug(`[AlbumsStore] Fetched ${this.allowedStatuses.length} allowed statuses`)
          return this.allowedStatuses
        } else {
          throw new Error(response.data.message || 'Failed to fetch allowed statuses')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch allowed statuses')
        Logger.error('[AlbumsStore] Error fetching allowed statuses:', err)
        return []
      }
    },

    /**
     * Fetch user tags for albums
     */
    async fetchUserTags() {
      try {
        Logger.debug('[AlbumsStore] Fetching user tags...')
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('get_user_album_tags')

        if (response.data.status === 'success') {
          this.userTags = response.data.data || []
          Logger.debug(`[AlbumsStore] Fetched ${this.userTags.length} user tags`)
          return this.userTags
        } else {
          throw new Error(response.data.message || 'Failed to fetch user tags')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch user tags')
        Logger.error('[AlbumsStore] Error fetching user tags:', err)
        return []
      }
    },

    /**
     * Create a new album tag
     */
    async createTag(name, color = '#007bff') {
      try {
        Logger.debug(`[AlbumsStore] Creating tag: ${name}`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('create_user_album_tag', {
          name: name,
          color: color
        })

        if (response.data.status === 'success') {
          const newTag = response.data.data
          this.userTags.push(newTag)
          Logger.debug('[AlbumsStore] Tag created successfully')
          return { success: true, tag: newTag }
        } else {
          throw new Error(response.data.message || 'Failed to create tag')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to create tag')
        Logger.error('[AlbumsStore] Error creating tag:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Update album tags
     */
    async updateAlbumTags(albumId, tagIds) {
      try {
        Logger.debug(`[AlbumsStore] Updating album tags: ${albumId}`, tagIds)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('update_album_tags', {
          albumId: albumId,
          tag_ids: tagIds
        })

        if (response.data.status === 'success') {
          Logger.debug('[AlbumsStore] Album tags updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update tags')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update tags')
        Logger.error('[AlbumsStore] Error updating album tags:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Fetch notes for an album
     */
    async fetchAlbumNotes(albumId) {
      try {
        Logger.debug(`[AlbumsStore] Fetching notes for album: ${albumId}`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('get_album_notes', {
          albumId: albumId
        })

        if (response.data.status === 'success') {
          this.albumNotes[albumId] = response.data.data || []
          Logger.debug(`[AlbumsStore] Fetched ${this.albumNotes[albumId].length} notes`)
          return { success: true, notes: this.albumNotes[albumId] }
        } else {
          throw new Error(response.data.message || 'Failed to fetch notes')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch notes')
        Logger.error('[AlbumsStore] Error fetching album notes:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Add a note to an album
     */
    async addAlbumNote(albumId, noteText, noteType = 'note', isPrivate = true) {
      try {
        Logger.debug(`[AlbumsStore] Adding note to album: ${albumId}`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('add_album_note', {
          albumId: albumId,
          noteText: noteText,
          noteType: noteType,
          isPrivate: isPrivate ? 1 : 0
        })

        if (response.data.status === 'success') {
          const newNote = response.data.data.note || response.data.data
          if (!this.albumNotes[albumId]) {
            this.albumNotes[albumId] = []
          }
          this.albumNotes[albumId].push(newNote)
          Logger.debug('[AlbumsStore] Note added successfully')
          return { success: true, note: newNote }
        } else {
          throw new Error(response.data.message || 'Failed to add note')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to add note')
        Logger.error('[AlbumsStore] Error adding note:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Update a note on an album
     */
    async updateAlbumNote(noteId, noteText, noteType = 'note', isPrivate = true) {
      try {
        Logger.debug(`[AlbumsStore] Updating note: ${noteId}`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('update_album_note', {
          noteId: noteId,
          noteText: noteText,
          noteType: noteType,
          isPrivate: isPrivate ? 1 : 0
        })

        if (response.data.status === 'success') {
          for (const albumId in this.albumNotes) {
            const noteIndex = this.albumNotes[albumId].findIndex(n => n.id === noteId)
            if (noteIndex !== -1) {
              this.albumNotes[albumId][noteIndex] = {
                ...this.albumNotes[albumId][noteIndex],
                note_text: noteText,
                note_type: noteType,
                is_private: isPrivate ? 1 : 0
              }
              break
            }
          }
          Logger.debug('[AlbumsStore] Note updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update note')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update note')
        Logger.error('[AlbumsStore] Error updating note:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Delete a note from an album
     */
    async deleteAlbumNote(noteId) {
      try {
        Logger.debug(`[AlbumsStore] Deleting note: ${noteId}`)
        const authStore = useAuthStore()

        const response = await authStore.authenticatedApiCall('delete_album_note', {
          noteId: noteId
        })

        if (response.data.status === 'success') {
          for (const albumId in this.albumNotes) {
            const noteIndex = this.albumNotes[albumId].findIndex(n => n.id === noteId)
            if (noteIndex !== -1) {
              this.albumNotes[albumId].splice(noteIndex, 1)
              break
            }
          }
          Logger.debug('[AlbumsStore] Note deleted successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to delete note')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to delete note')
        Logger.error('[AlbumsStore] Error deleting note:', err)
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
