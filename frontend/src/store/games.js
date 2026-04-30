/**
 * Games Store using Pinia
 * Manages game library state and operations
 */
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import { handleStoreError, matchesGameId } from '@/utils/storeHelpers'
import Logger from '@/utils/logger'

export const useGamesStore = defineStore('games', {
  state: () => ({
    games: [],
    allowedStatuses: [],
    userTags: [],
    gameNotes: {}, // Notas por juego: { gameId: [notes] }
    isLoading: false,
    error: null,
    lastSearchQuery: '',
    searchResults: [],
    isSearching: false
  }),

  getters: {
    /**
     * Total de juegos en la biblioteca
     */
    totalGames: (state) => state.games.length,

    /**
     * Verifica si hay juegos en la biblioteca
     */
    hasGames: (state) => state.games.length > 0,

    /**
     * Verifica si hay resultados de búsqueda
     */
    hasSearchResults: (state) => state.searchResults.length > 0,

    /**
     * Juegos con calificación
     */
    gamesWithRating: (state) => {
      return state.games.filter(game => game.user_rating && game.user_rating > 0)
    },

    /**
     * Calificación promedio
     */
    averageRating: (state) => {
      const gamesWithRating = state.games.filter(g => g.user_rating && g.user_rating > 0)
      if (gamesWithRating.length === 0) return 0
      const sum = gamesWithRating.reduce((acc, g) => acc + parseFloat(g.user_rating), 0)
      return (sum / gamesWithRating.length).toFixed(1)
    },

    /**
     * Juegos por estado
     */
    gamesByStatus: (state) => {
      return (statusId) => {
        return state.games.filter(game => 
          game.userStatuses && game.userStatuses.some(s => s.status_id === statusId)
        )
      }
    },

    /**
     * Obtiene un juego por ID
     */
    getGameById: (state) => {
      return (gameId) => {
        return state.games.find(g => matchesGameId(g, gameId))
      }
    },

    /**
     * Verifica si un juego está en la biblioteca
     */
    isGameInLibrary: (state) => {
      return (gameId) => {
        return state.games.some(g => matchesGameId(g, gameId))
      }
    },

    /**
     * Cuenta de juegos por estado
     */
    gameCountByStatus: (state) => {
      const counts = {}
      state.allowedStatuses.forEach(status => {
        counts[status.id] = state.games.filter(game => 
          game.userStatuses && game.userStatuses.some(s => s.status_id === status.id)
        ).length
      })
      return counts
    }
  },

  actions: {
    /**
     * Obtiene todos los juegos de la biblioteca del usuario
     */
    async fetchGames() {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[GamesStore] Fetching games from library...')
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('get_games', {
          filters: {}
        })

        if (response.data.status === 'success') {
          // Ensure response.data.data is an array
          this.games = Array.isArray(response.data.data) ? response.data.data : []
          Logger.debug(`[GamesStore] Fetched ${this.games.length} games`)
          return this.games
        } else {
          throw new Error(response.data.message || 'Failed to fetch games')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch games')
        Logger.error('[GamesStore] Error fetching games:', err)
        return []
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Busca juegos por nombre en el backend
     */
    async searchGames(query) {
      if (!query || query.trim() === '') {
        this.searchResults = []
        return []
      }

      this.isSearching = true
      this.error = null
      this.lastSearchQuery = query

      try {
        Logger.debug(`[GamesStore] Searching games: "${query}"`)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('search_game_name', {
          name: query
        })

        if (response.data.status === 'success') {
          this.searchResults = response.data.data || []
          Logger.debug(`[GamesStore] Found ${this.searchResults.length} games`)
          return this.searchResults
        } else {
          throw new Error(response.data.message || 'Search failed')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Search failed')
        Logger.error('[GamesStore] Error searching games:', err)
        return []
      } finally {
        this.isSearching = false
      }
    },

    /**
     * Añade un juego a la biblioteca
     */
    async addGame(game, statuses = []) {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[GamesStore] Adding game to library:', game)
        const authStore = useAuthStore()
        
        // Construir el objeto del juego con todos los campos necesarios
        const gameData = {
          id: game.id || game.gameId || game.rawgId,
          gameId: game.gameId || game.rawgId || game.id,
          rawgId: game.rawgId || game.id,
          title: game.title || game.name,
          originalTitle: game.originalTitle || game.original_name || game.title || game.name,
          developer: game.developer || game.developers?.[0]?.name || '',
          publisher: game.publisher || game.publishers?.[0]?.name || '',
          releaseDate: game.releaseDate || game.released || game.release_date || '',
          genres: Array.isArray(game.genres) 
            ? game.genres.map(g => typeof g === 'string' ? g : g.name).join(', ')
            : (game.genres || ''),
          platforms: Array.isArray(game.platforms)
            ? game.platforms.map(p => typeof p === 'string' ? p : p.platform?.name || p.name).join(', ')
            : (game.platforms || ''),
          description: game.description || game.plot || '',
          coverUrl: game.coverUrl || game.background_image || game.cover || '',
          metacriticScore: game.metacriticScore || game.metacritic || null,
          esrbRating: game.esrbRating || game.esrb_rating?.name || '',
          playtime: game.playtime || 0,
          userStatuses: statuses,
          user_rating: game.user_rating || null,
          hoursPlayed: game.hoursPlayed || game.hours_played || 0,
          notes: game.notes || '',
          dateStarted: game.dateStarted || game.date_started || null,
          dateFinished: game.dateFinished || game.date_finished || null,
          ownership_format_id: game.ownership_format_id || null
        }

        const response = await authStore.authenticatedApiCall('add_game', {
          game: gameData
        })

        if (response.data.status === 'success') {
          // Añadir el juego a la lista local
          const addedGame = response.data.data || gameData
          this.games.push(addedGame)
          Logger.debug('[GamesStore] Game added successfully')
          return { success: true, game: addedGame }
        } else {
          throw new Error(response.data.message || 'Failed to add game')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to add game')
        Logger.error('[GamesStore] Error adding game:', err)
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Elimina un juego de la biblioteca
     */
    async deleteGame(gameId) {
      this.isLoading = true
      this.error = null

      try {
        Logger.debug('[GamesStore] Deleting game:', gameId)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('delete_game', {
          gameId: gameId,
          itemType: 'game'
        })

        if (response.data.status === 'success') {
          this.games = this.games.filter(g => 
            g.id !== gameId && g.rawgId !== gameId && g.gameId !== gameId
          )
          Logger.debug('[GamesStore] Game deleted successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to delete game')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to delete game')
        Logger.error('[GamesStore] Error deleting game:', err)
        return { success: false, message: this.error }
      } finally {
        this.isLoading = false
      }
    },

    /**
     * Actualiza la calificación de un juego
     */
    async updateGameRating(gameId, rating) {
      try {
        Logger.debug(`[GamesStore] Updating game rating: ${gameId} -> ${rating}`)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('update_game_rating', {
          gameId: gameId,
          rating: rating
        })

        if (response.data.status === 'success') {
          const game = this.games.find(g => matchesGameId(g, gameId))
          if (game) {
            game.user_rating = rating
          }
          Logger.debug('[GamesStore] Game rating updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update rating')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update rating')
        Logger.error('[GamesStore] Error updating game rating:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Actualiza los estados de un juego
     */
    async updateGameStatuses(gameId, statuses) {
      try {
        Logger.debug(`[GamesStore] Updating game statuses: ${gameId}`, statuses)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('update_game_user_statuses', {
          gameId: gameId,
          statuses: statuses
        })

        if (response.data.status === 'success') {
          const game = this.games.find(g => matchesGameId(g, gameId))
          if (game) {
            game.userStatuses = statuses
          }
          Logger.debug('[GamesStore] Game statuses updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update statuses')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update statuses')
        Logger.error('[GamesStore] Error updating game statuses:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Edita un juego completo
     */
    async editGame(gameId, updatedData) {
      try {
        Logger.debug(`[GamesStore] Editing game: ${gameId}`, updatedData)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('edit_user_game', {
          gameId: gameId,
          ...updatedData
        })

        if (response.data.status === 'success') {
          const gameIndex = this.games.findIndex(g => matchesGameId(g, gameId))
          if (gameIndex !== -1) {
            this.games[gameIndex] = {
              ...this.games[gameIndex],
              ...updatedData
            }
          }
          Logger.debug('[GamesStore] Game edited successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to edit game')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to edit game')
        Logger.error('[GamesStore] Error editing game:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Obtiene los estados permitidos para juegos
     */
    async fetchAllowedStatuses() {
      try {
        Logger.debug('[GamesStore] Fetching allowed statuses...')
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('get_game_allowed_statuses')
        
        if (response.data.status === 'success') {
          this.allowedStatuses = response.data.data || []
          Logger.debug(`[GamesStore] Fetched ${this.allowedStatuses.length} allowed statuses`)
          return this.allowedStatuses
        } else {
          throw new Error(response.data.message || 'Failed to fetch allowed statuses')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch allowed statuses')
        Logger.error('[GamesStore] Error fetching allowed statuses:', err)
        return []
      }
    },

    /**
     * Obtiene los tags del usuario
     */
    async fetchUserTags() {
      try {
        Logger.debug('[GamesStore] Fetching user tags...')
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('get_user_game_tags')
        
        if (response.data.status === 'success') {
          this.userTags = response.data.data || []
          Logger.debug(`[GamesStore] Fetched ${this.userTags.length} user tags`)
          return this.userTags
        } else {
          throw new Error(response.data.message || 'Failed to fetch user tags')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch user tags')
        Logger.error('[GamesStore] Error fetching user tags:', err)
        return []
      }
    },

    /**
     * Crea un nuevo tag
     */
    async createTag(name, color = '#007bff') {
      try {
        Logger.debug(`[GamesStore] Creating tag: ${name}`)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('create_user_game_tag', {
          name: name,
          color: color
        })

        if (response.data.status === 'success') {
          const newTag = response.data.data
          this.userTags.push(newTag)
          Logger.debug('[GamesStore] Tag created successfully')
          return { success: true, tag: newTag }
        } else {
          throw new Error(response.data.message || 'Failed to create tag')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to create tag')
        Logger.error('[GamesStore] Error creating tag:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Actualiza los tags de un juego
     */
    async updateGameTags(gameId, tagIds) {
      try {
        Logger.debug(`[GamesStore] Updating game tags: ${gameId}`, tagIds)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('update_game_tags', {
          gameId: gameId,
          tag_ids: tagIds
        })

        if (response.data.status === 'success') {
          Logger.debug('[GamesStore] Game tags updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update tags')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update tags')
        Logger.error('[GamesStore] Error updating game tags:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Obtiene las notas de un juego
     */
    async fetchGameNotes(gameId) {
      try {
        Logger.debug(`[GamesStore] Fetching notes for game: ${gameId}`)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('get_game_notes', {
          gameId: gameId
        })

        if (response.data.status === 'success') {
          this.gameNotes[gameId] = response.data.data || []
          Logger.debug(`[GamesStore] Fetched ${this.gameNotes[gameId].length} notes`)
          return { success: true, notes: this.gameNotes[gameId] }
        } else {
          throw new Error(response.data.message || 'Failed to fetch notes')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to fetch notes')
        Logger.error('[GamesStore] Error fetching game notes:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Añade una nota a un juego
     */
    async addGameNote(gameId, noteText, noteType = 'note', isPrivate = true) {
      try {
        Logger.debug(`[GamesStore] Adding note to game: ${gameId}`)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('add_game_note', {
          gameId: gameId,
          noteText: noteText,
          noteType: noteType,
          isPrivate: isPrivate ? 1 : 0
        })

        if (response.data.status === 'success') {
          const newNote = response.data.data.note || response.data.data
          if (!this.gameNotes[gameId]) {
            this.gameNotes[gameId] = []
          }
          this.gameNotes[gameId].push(newNote)
          Logger.debug('[GamesStore] Note added successfully')
          return { success: true, note: newNote }
        } else {
          throw new Error(response.data.message || 'Failed to add note')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to add note')
        Logger.error('[GamesStore] Error adding note:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Actualiza una nota de un juego
     */
    async updateGameNote(noteId, noteText, noteType = 'note', isPrivate = true) {
      try {
        Logger.debug(`[GamesStore] Updating note: ${noteId}`)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('update_game_note', {
          noteId: noteId,
          noteText: noteText,
          noteType: noteType,
          isPrivate: isPrivate ? 1 : 0
        })

        if (response.data.status === 'success') {
          // Actualizar la nota en el estado local
          for (const gameId in this.gameNotes) {
            const noteIndex = this.gameNotes[gameId].findIndex(n => n.id === noteId)
            if (noteIndex !== -1) {
              this.gameNotes[gameId][noteIndex] = {
                ...this.gameNotes[gameId][noteIndex],
                note_text: noteText,
                note_type: noteType,
                is_private: isPrivate ? 1 : 0
              }
              break
            }
          }
          Logger.debug('[GamesStore] Note updated successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to update note')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to update note')
        Logger.error('[GamesStore] Error updating note:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Elimina una nota de un juego
     */
    async deleteGameNote(noteId) {
      try {
        Logger.debug(`[GamesStore] Deleting note: ${noteId}`)
        const authStore = useAuthStore()
        
        const response = await authStore.authenticatedApiCall('delete_game_note', {
          noteId: noteId
        })

        if (response.data.status === 'success') {
          // Eliminar la nota del estado local
          for (const gameId in this.gameNotes) {
            const noteIndex = this.gameNotes[gameId].findIndex(n => n.id === noteId)
            if (noteIndex !== -1) {
              this.gameNotes[gameId].splice(noteIndex, 1)
              break
            }
          }
          Logger.debug('[GamesStore] Note deleted successfully')
          return { success: true }
        } else {
          throw new Error(response.data.message || 'Failed to delete note')
        }
      } catch (err) {
        this.error = this._handleError(err, 'Failed to delete note')
        Logger.error('[GamesStore] Error deleting note:', err)
        return { success: false, message: this.error }
      }
    },

    /**
     * Limpia los resultados de búsqueda
     */
    clearSearchResults() {
      this.searchResults = []
      this.lastSearchQuery = ''
    },

    /**
     * Limpia el error actual
     */
    clearError() {
      this.error = null
    },

    /**
     * Manejo centralizado de errores
     * @private
     */
    _handleError(err, defaultMessage = 'Operation failed') {
      return handleStoreError(err, defaultMessage)
    }
  }
})
