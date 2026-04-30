<template>
  <Teleport to="body">
    <div v-if="isVisible" class="modal-overlay" @click="onBackgroundClick">
      <div class="modal-content">
        <button class="close-btn" @click="$emit('close')" aria-label="Cerrar">&times;</button>
        
        <h2>{{ item?.title || 'Sin título' }}</h2>
        
        <div class="edit-fields">
          <!-- Rating Component -->
          <RatingComponent
            v-model:rating="localRating"
            :editable="true"
          />
          
          <!-- Reading Progress Bar (solo para libros) -->
          <ReadingProgressBar
            v-if="itemType === 'book'"
            ref="progressBarRef"
            :current-page="item?.currentPage || 0"
            :total-pages="localTotalPages"
            :editable="true"
            theme="blue"
          />

          <!-- Total pages input (books only, shown when pages is unknown) -->
          <div v-if="itemType === 'book'" class="form-group">
            <label for="total-pages-input">Total de páginas</label>
            <input
              id="total-pages-input"
              v-model.number="localTotalPages"
              type="number"
              min="1"
              placeholder="Nº total de páginas del libro"
              class="game-input"
            />
          </div>
          
          <!-- Status Selector -->
          <StatusSelector
            v-model="localStatuses"
            :allowed-statuses="allowedStatuses"
            :multiple="true"
            label="Estado"
            subtitle="(selecciona uno o más)"
          />
          
          <!-- Tag Selector -->
          <TagSelector
            v-model="localTags"
            :tags="userTags"
            :readonly="false"
            @add-tag="handleAddTag"
          />

          <!-- Ownership Format Selector (all entity types) -->
          <div v-if="ownershipFormats.length > 0" class="form-group">
            <label for="ownership-format">Formato de Propiedad</label>
            <select
              id="ownership-format"
              v-model="localOwnershipFormatId"
              class="game-input"
            >
              <option :value="null">— Sin especificar —</option>
              <option
                v-for="fmt in ownershipFormats"
                :key="fmt.id"
                :value="fmt.id"
              >
                {{ fmt.label }}
              </option>
            </select>
          </div>
          
          <!-- Game-specific fields -->
          <div v-if="itemType === 'game'" class="game-fields">
            <div class="form-group">
              <label for="hours-played">Horas Jugadas</label>
              <input
                id="hours-played"
                v-model.number="localHoursPlayed"
                type="number"
                min="0"
                step="0.5"
                placeholder="Horas jugadas"
                class="game-input"
              />
            </div>
            
            <div class="form-group">
              <label for="platform-played">Plataforma</label>
              <input
                id="platform-played"
                v-model="localPlatformPlayed"
                type="text"
                placeholder="PC, PS5, Xbox, etc."
                class="game-input"
              />
            </div>
            
            <div class="form-group">
              <label for="date-started">Fecha de Inicio</label>
              <input
                id="date-started"
                v-model="localDateStarted"
                type="date"
                class="game-input"
              />
            </div>
            
            <div class="form-group">
              <label for="date-finished">Fecha de Finalización</label>
              <input
                id="date-finished"
                v-model="localDateFinished"
                type="date"
                class="game-input"
              />
            </div>
            
            <div class="form-group">
              <label for="personal-notes">Notas Personales</label>
              <textarea
                id="personal-notes"
                v-model="localPersonalNotes"
                rows="3"
                placeholder="Tus notas sobre este juego..."
                class="game-textarea"
              ></textarea>
            </div>
          </div>
          
          <!-- Reading Status Widget (solo para libros existentes) -->
          <ReadingStatusWidget
            v-if="itemType === 'book' && item?.isbn && !isNewItem"
            :book="item"
          />

          <!-- Edition Notes (solo para libros) -->
          <EditionNotes
            v-if="itemType === 'book' && userEditionId"
            :user-edition-id="userEditionId"
          />
          
          <!-- Movie Notes (solo para películas) -->
          <MovieNotes
            v-if="itemType === 'movie' && item?.isbn"
            :movie-isbn="item.isbn"
          />
          
          <!-- Game Notes (solo para juegos) -->
          <GameNotes
            v-if="itemType === 'game' && item?.id"
            :game-id="item.id"
          />

          <!-- Album-specific fields -->
          <div v-if="itemType === 'album'" class="game-fields">
            <div class="form-group">
              <label for="favorite-track">Canción Favorita</label>
              <select
                v-if="albumTracks && albumTracks.length > 0"
                id="favorite-track"
                v-model="localFavoriteTrack"
                class="game-input"
              >
                <option value="">— Ninguna —</option>
                <option
                  v-for="track in albumTracks"
                  :key="track.id || track.track_number"
                  :value="track.name"
                >
                  {{ track.track_number }}. {{ track.name }}
                </option>
              </select>
              <input
                v-else
                id="favorite-track"
                v-model="localFavoriteTrack"
                type="text"
                placeholder="Tu canción favorita del álbum"
                class="game-input"
              />
            </div>

            <div class="form-group">
              <label for="album-date-started">Primera Escucha</label>
              <input
                id="album-date-started"
                v-model="localDateStarted"
                type="date"
                class="game-input"
              />
            </div>

            <div class="form-group">
              <label for="album-personal-notes">Notas Personales</label>
              <textarea
                id="album-personal-notes"
                v-model="localPersonalNotes"
                rows="3"
                placeholder="Tus notas sobre este álbum..."
                class="game-textarea"
              ></textarea>
            </div>
          </div>

          <!-- Album Notes -->
          <AlbumNotes
            v-if="itemType === 'album' && item?.id"
            :album-id="item.id"
          />
        </div>
        
        <div class="save-btn-container">
          <button class="save-btn" @click="handleSave" :disabled="isSaving">
            {{ isSaving ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, inject, defineProps, defineEmits } from 'vue'
import RatingComponent from '@/components/common/RatingComponent.vue'
import ReadingProgressBar from '@/components/common/ReadingProgressBar.vue'
import StatusSelector from '@/components/common/StatusSelector.vue'
import TagSelector from '@/components/common/TagSelector.vue'
import EditionNotes from '@/components/Books/EditionNotes.vue'
import ReadingStatusWidget from '@/components/Books/ReadingStatusWidget.vue'
import MovieNotes from '@/components/Movies/MovieNotes.vue'
import GameNotes from '@/components/Games/GameNotes.vue'
import AlbumNotes from '@/components/Albums/AlbumNotes.vue'
import { useBooks } from '@/composables/useBooks'
import { useMovies } from '@/composables/useMovies'
import { useGames } from '@/composables/useGames'
import { useAlbums } from '@/composables/useAlbums'
import { useItemEdit } from '@/composables/useItemEdit'
import { useUIStore } from '@/store/ui'
import Logger from '@/utils/logger'

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  itemType: {
    type: String,
    required: true,
    validator: (value) => ['book', 'movie', 'game', 'album'].includes(value)
  },
  allowedStatuses: {
    type: Array,
    default: () => []
  },
  isVisible: {
    type: Boolean,
    default: true
  },
  albumTracks: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'saved'])

// Composables
const itemEdit = useItemEdit()
const notifications = inject('notifications', null)
const uiStore = useUIStore()

// Get appropriate composables based on item type
const booksComposable = useBooks()
const moviesComposable = useMovies()
const gamesComposable = useGames()
const albumsComposable = useAlbums()

// Local state
const localRating = ref(props.item?.user_rating ?? null)
const localStatuses = ref(props.item?.userStatuses ? [...props.item.userStatuses] : [])
const localTags = ref(props.item?.tags ? [...props.item.tags] : [])
const localCurrentPage = ref(props.item?.currentPage ?? 0)
const localTotalPages = ref(props.item?.pages || props.item?.totalPages || 0)

// Game-specific local state
const localHoursPlayed = ref(props.item?.hoursPlayed ?? props.item?.hours_played ?? null)
const localPlatformPlayed = ref(props.item?.platformPlayed ?? props.item?.platform_played ?? '')
const localDateStarted = ref(props.item?.dateStarted ?? props.item?.date_started ?? '')
const localDateFinished = ref(props.item?.dateFinished ?? props.item?.date_finished ?? '')
const localPersonalNotes = ref(props.item?.personalNotes ?? props.item?.personal_notes ?? '')

// Album-specific local state
const localFavoriteTrack = ref(props.item?.favoriteTrack ?? props.item?.favorite_track ?? '')

// Ownership format state
const ownershipFormats = ref([])
const localOwnershipFormatId = ref(
  props.item?.ownershipFormat?.id ?? props.item?.ownership_format?.id ?? props.item?.ownership_format_id ?? null
)

const isSaving = ref(false)
const progressBarRef = ref(null)

// Determine if this is a new item (not yet saved in library)
const isNewItem = computed(() => {
  if (props.itemType === 'book') {
    return !props.item?.user_edition_id && !props.item?.userEditionId && !props.item?.editable
  }
  return false
})

// User Edition ID for books (needed for edition notes)
const userEditionId = computed(() => {
  if (props.itemType !== 'book') return null
  return props.item?.user_edition_id || props.item?.userEditionId || props.item?.id || null
})

// Get user tags based on item type
const userTags = computed(() => {
  if (props.itemType === 'book') {
    return booksComposable.userTags.value
  } else if (props.itemType === 'movie') {
    return moviesComposable.userTags?.value || []
  } else if (props.itemType === 'game') {
    return gamesComposable.userTags?.value || []
  } else if (props.itemType === 'album') {
    return albumsComposable.userTags?.value || []
  }
  return []
})

// Load data on mount
onMounted(async () => {
  try {
    // Load ownership formats
    ownershipFormats.value = await uiStore.fetchOwnershipFormats(props.itemType)

    if (props.itemType === 'book') {
      // Load user tags for books
      await booksComposable.fetchUserTags()
      
      // Load book-specific tags
      const bookTagsResult = await booksComposable.getBookTags(props.item.isbn)
      if (bookTagsResult.success) {
        localTags.value = bookTagsResult.data.map(tag => tag.id)
      }
    } else if (props.itemType === 'movie') {
      // Load user tags for movies
      if (moviesComposable.fetchUserTags) {
        await moviesComposable.fetchUserTags()
      }
      
      // Load movie-specific tags if available
      if (moviesComposable.getMovieTags) {
        const movieTagsResult = await moviesComposable.getMovieTags(props.item.imdbID || props.item.tmdbId)
        if (movieTagsResult.success) {
          localTags.value = movieTagsResult.data.map(tag => tag.id)
        }
      }
    } else if (props.itemType === 'game') {
      // Load user tags for games
      if (gamesComposable.fetchUserTags) {
        await gamesComposable.fetchUserTags()
      }
      
      // Load game-specific tags if available
      if (gamesComposable.getGameTags) {
        const gameTagsResult = await gamesComposable.getGameTags(props.item.id || props.item.rawgId)
        if (gameTagsResult.success) {
          localTags.value = gameTagsResult.data.map(tag => tag.id)
        }
      }
    } else if (props.itemType === 'album') {
      if (albumsComposable.fetchUserTags) {
        await albumsComposable.fetchUserTags()
      }
      if (albumsComposable.getAlbumTags) {
        const albumTagsResult = await albumsComposable.getAlbumTags(props.item.id)
        if (albumTagsResult.success) {
          localTags.value = albumTagsResult.data.map(tag => tag.id)
        }
      }
    }
  } catch (error) {
    Logger.error('Error loading tags:', error)
  }
})

// Handle tag creation
const handleAddTag = async (tagName) => {
  try {
    let result
    if (props.itemType === 'book') {
      result = await booksComposable.createUserTag(tagName)
    } else if (props.itemType === 'movie' && moviesComposable.createUserTag) {
      result = await moviesComposable.createUserTag(tagName)
    } else if (props.itemType === 'game' && gamesComposable.createUserTag) {
      result = await gamesComposable.createUserTag(tagName)
    } else if (props.itemType === 'album' && albumsComposable.createUserTag) {
      result = await albumsComposable.createUserTag(tagName)
    }
    
    if (result?.success) {
      localTags.value.push(result.data.id)
    } else {
      if (notifications) {
        notifications.showError(result?.message || 'Error al crear el tag')
      }
    }
  } catch (error) {
    Logger.error('Error creating tag:', error)
    if (notifications) {
      notifications.showError('Error al crear el tag')
    }
  }
}

// Handle save
const handleSave = async () => {
  isSaving.value = true
  
  try {
    // Get the correct ID based on item type
    let itemId
    if (props.itemType === 'book') {
      itemId = props.item.isbn
    } else if (props.itemType === 'movie') {
      itemId = props.item.imdbID || props.item.tmdbId || props.item.isbn
    } else if (props.itemType === 'game') {
      itemId = props.item.id || props.item.rawgId
    } else if (props.itemType === 'album') {
      itemId = props.item.id
    }
    
    // Get current page from ReadingProgressBar component if it exists
    let currentPageToSave = localCurrentPage.value
    if (props.itemType === 'book' && progressBarRef.value) {
      currentPageToSave = progressBarRef.value.getCurrentPage()
    }
    
    const data = {
      personalRating: localRating.value,
      statuses: [...localStatuses.value],
      userId: props.item.userId || props.item.user_id
    }
    
    // Add currentPage and pages for books
    if (props.itemType === 'book') {
      data.currentPage = currentPageToSave
      localCurrentPage.value = currentPageToSave
      if (localTotalPages.value > 0) {
        data.pages = localTotalPages.value
      }
    }
    
    // Add game-specific fields
    if (props.itemType === 'game') {
      if (localHoursPlayed.value !== null) {
        data.hoursPlayed = localHoursPlayed.value
      }
      if (localPlatformPlayed.value) {
        data.platformPlayed = localPlatformPlayed.value
      }
      if (localDateStarted.value) {
        data.dateStarted = localDateStarted.value
      }
      if (localDateFinished.value) {
        data.dateFinished = localDateFinished.value
      }
      if (localPersonalNotes.value) {
        data.personalNotes = localPersonalNotes.value
      }
    }

    // Add album-specific fields
    if (props.itemType === 'album') {
      if (localFavoriteTrack.value) {
        data.favoriteTrack = localFavoriteTrack.value
      }
      if (localDateStarted.value) {
        data.dateStarted = localDateStarted.value
      }
      if (localDateFinished.value) {
        data.dateFinished = localDateFinished.value
      }
      if (localPersonalNotes.value) {
        data.personalNotes = localPersonalNotes.value
      }
    }
    
    // Add ownership format for all types
    if (localOwnershipFormatId.value !== null) {
      data.ownership_format_id = localOwnershipFormatId.value
    }

    Logger.debug('Saving item with data:', { itemType: props.itemType, itemId, data })
    
    const result = await itemEdit.editItem(props.itemType, itemId, data, [...localTags.value], [])
    
    Logger.debug('Save result:', result)
    
    if (result.success) {
      // For books, also call update_reading_progress to record session + history
      if (props.itemType === 'book' && props.item.isbn) {
        try {
          await booksComposable.updateReadingProgress(props.item.isbn, currentPageToSave)
        } catch (progressErr) {
          Logger.warn('[EditItemModal] Could not update reading progress history:', progressErr)
          // Non-fatal: main save succeeded
        }
      }

      // Show success message
      const itemTypeName = props.itemType === 'book' ? 'Libro' : props.itemType === 'movie' ? 'Película' : props.itemType === 'album' ? 'Álbum' : 'Juego'
      if (notifications) {
        notifications.showSuccess(`${itemTypeName} actualizado correctamente`)
      }
      
      // Emit updated item
      const updatedItem = {
        ...props.item,
        user_rating: localRating.value,
        userStatuses: [...localStatuses.value],
        itemType: props.itemType
      }
      
      // Add type-specific fields
      if (props.itemType === 'book') {
        updatedItem.currentPage = localCurrentPage.value
        updatedItem.isbn = props.item.isbn
        if (localTotalPages.value > 0) {
          updatedItem.pages = localTotalPages.value
          updatedItem.totalPages = localTotalPages.value
        }
      } else if (props.itemType === 'movie') {
        updatedItem.imdbID = props.item.imdbID
        updatedItem.tmdbId = props.item.tmdbId
      } else if (props.itemType === 'game') {
        updatedItem.id = props.item.id
        updatedItem.rawgId = props.item.rawgId
        updatedItem.hoursPlayed = localHoursPlayed.value
        updatedItem.platformPlayed = localPlatformPlayed.value
        updatedItem.dateStarted = localDateStarted.value
        updatedItem.dateFinished = localDateFinished.value
        updatedItem.personalNotes = localPersonalNotes.value
      } else if (props.itemType === 'album') {
        updatedItem.id = props.item.id
        updatedItem.favoriteTrack = localFavoriteTrack.value
        updatedItem.dateStarted = localDateStarted.value
        updatedItem.dateFinished = localDateFinished.value
        updatedItem.personalNotes = localPersonalNotes.value
      }

      // Update ownership format for all types
      if (localOwnershipFormatId.value !== null) {
        const fmt = ownershipFormats.value.find(f => f.id === localOwnershipFormatId.value)
        updatedItem.ownership_format_id = localOwnershipFormatId.value
        updatedItem.ownershipFormat = fmt || null
      }
      
      Logger.debug('Emitting saved event with updatedItem:', updatedItem)
      emit('saved', updatedItem)
      emit('close')
    } else {
      // Show error message
      const itemTypeName = props.itemType === 'book' ? 'el libro' : props.itemType === 'movie' ? 'la película' : props.itemType === 'album' ? 'el álbum' : 'el juego'
      if (notifications) {
        notifications.showError(result.message || `Error al guardar ${itemTypeName}`)
      }
    }
  } catch (error) {
    Logger.error('Error saving item:', error)
    const itemTypeName = props.itemType === 'book' ? 'el libro' : props.itemType === 'movie' ? 'la película' : props.itemType === 'album' ? 'el álbum' : 'el juego'
    if (notifications) {
      notifications.showError(`Error al guardar ${itemTypeName}`)
    }
  } finally {
    isSaving.value = false
  }
}

// Handle background click
const onBackgroundClick = (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    emit('close')
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}

.modal-content {
  background: var(--background, #23272f);
  color: var(--text, #f5f5f5);
  padding: 2rem 2.5rem 2rem 2rem;
  border-radius: 16px;
  min-width: 320px;
  width: 700px;
  min-height: 180px;
  max-width: 90vw;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 4px 24px rgba(0,0,0,0.35);
  position: relative;
  font-family: inherit;
}

.close-btn {
  position: absolute;
  top: 18px;
  right: 18px;
  background: transparent;
  border: none;
  font-size: 2rem;
  color: #f5f5f5;
  cursor: pointer;
  transition: color 0.2s;
  z-index: 10;
}

.close-btn:hover {
  color: #ff5252;
}

.edit-fields {
  margin-top: 1rem;
  margin-bottom: 1rem;
}

.save-btn-container {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-top: 2rem;
}

.save-btn {
  padding: 0.5rem 2rem;
  background: #1976d2;
  color: #fff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 1.1rem;
  box-shadow: 0 2px 8px rgba(25, 118, 210, 0.08);
  transition: background 0.2s;
}

.save-btn:hover:not(:disabled) {
  background: #1565c0;
}

.save-btn:disabled {
  background: #666;
  cursor: not-allowed;
}

/* Aggressive z-index fix for PrimeVue components inside modal */
.modal-content :deep(.p-multiselect-panel),
.modal-content :deep(.p-dropdown-panel),
.modal-content :deep(.p-component-overlay),
.modal-content :deep(.p-multiselect-overlay),
.modal-content :deep(.p-dropdown-overlay) {
  z-index: 3100 !important;
  position: fixed !important;
}

.modal-content :deep(.p-multiselect),
.modal-content :deep(.p-dropdown) {
  z-index: 1;
  position: relative;
}

/* Ensure overlay elements have higher z-index */
:deep(.p-multiselect-panel),
:deep(.p-dropdown-panel),
:deep(.p-component-overlay),
:deep(.p-multiselect-overlay),
:deep(.p-dropdown-overlay) {
  z-index: 3100 !important;
  position: fixed !important;
}

/* Force all PrimeVue overlays to be on top */
:deep(.p-connected-overlay),
:deep([data-pc-section="root"]) {
  z-index: 3100 !important;
}

/* Game-specific fields */
.game-fields {
  margin-top: 1rem;
  padding: 1rem;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.form-group {
  margin-bottom: 1rem;
}

.form-group:last-child {
  margin-bottom: 0;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #e0e0e0;
  font-size: 0.9rem;
}

.game-input,
.game-textarea {
  width: 100%;
  padding: 0.5rem;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 6px;
  color: #f5f5f5;
  font-size: 0.95rem;
  font-family: inherit;
  transition: border-color 0.2s, background 0.2s;
}

.game-input:focus,
.game-textarea:focus {
  outline: none;
  border-color: #1976d2;
  background: rgba(255, 255, 255, 0.1);
}

.game-textarea {
  resize: vertical;
  min-height: 60px;
}

.game-input::placeholder,
.game-textarea::placeholder {
  color: rgba(255, 255, 255, 0.4);
}
</style>
