<template>
  <Teleport to="body">
    <div
      v-if="isVisible"
      class="edit-modal"
      :class="`edit-modal--${itemType}`"
      @click="onBackgroundClick"
    >
      <div class="edit-modal__dialog" role="dialog" aria-modal="true">
        <header class="edit-modal__header">
          <h2 class="edit-modal__title" :title="item?.title || 'Sin título'">
            {{ item?.title || 'Sin título' }}
          </h2>
          <button
            class="edit-modal__close"
            type="button"
            aria-label="Cerrar"
            @click="$emit('close')"
          >
            &times;
          </button>
        </header>

        <div class="edit-modal__body">
          <!-- Sección: valoración y progreso -->
          <section class="edit-modal__section">
            <RatingComponent v-model:rating="localRating" :editable="true" />

            <ReadingProgressBar
              v-if="itemType === 'book'"
              ref="progressBarRef"
              :current-page="item?.currentPage || 0"
              :total-pages="localTotalPages"
              :editable="true"
              theme="blue"
            />

            <div v-if="itemType === 'book'" class="edit-modal__field">
              <label for="total-pages-input">Total de páginas</label>
              <input
                id="total-pages-input"
                v-model.number="localTotalPages"
                type="number"
                min="1"
                placeholder="Nº total de páginas del libro"
                class="edit-modal__input"
              />
            </div>
          </section>

          <!-- Sección: estado, tags y propiedad -->
          <section class="edit-modal__section">
            <StatusSelector
              v-model="localStatuses"
              :allowed-statuses="allowedStatuses"
              :multiple="true"
              label="Estado"
              subtitle="(selecciona uno o más)"
            />

            <TagSelector
              v-model="localTags"
              :tags="userTags"
              :readonly="false"
              @add-tag="handleAddTag"
            />

            <div v-if="ownershipFormats.length > 0" class="edit-modal__field">
              <label for="ownership-format">Formato de Propiedad</label>
              <select
                id="ownership-format"
                v-model="localOwnershipFormatId"
                class="edit-modal__input"
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
          </section>

          <!-- Sección: detalles específicos del juego -->
          <section v-if="itemType === 'game'" class="edit-modal__section edit-modal__section--card">
            <h3 class="edit-modal__section-title">Detalles del juego</h3>

            <div class="edit-modal__grid">
              <div class="edit-modal__field">
                <label for="hours-played">Horas jugadas</label>
                <input
                  id="hours-played"
                  v-model.number="localHoursPlayed"
                  type="number"
                  min="0"
                  step="0.5"
                  placeholder="Horas jugadas"
                  class="edit-modal__input"
                />
              </div>

              <div class="edit-modal__field">
                <label for="platform-played">Plataforma</label>
                <input
                  id="platform-played"
                  v-model="localPlatformPlayed"
                  type="text"
                  placeholder="PC, PS5, Xbox, etc."
                  class="edit-modal__input"
                />
              </div>

              <div class="edit-modal__field">
                <label for="date-started">Fecha de inicio</label>
                <input
                  id="date-started"
                  v-model="localDateStarted"
                  type="date"
                  class="edit-modal__input"
                />
              </div>

              <div class="edit-modal__field">
                <label for="date-finished">Fecha de finalización</label>
                <input
                  id="date-finished"
                  v-model="localDateFinished"
                  type="date"
                  class="edit-modal__input"
                />
              </div>
            </div>

            <div class="edit-modal__field">
              <label for="personal-notes">Notas personales</label>
              <textarea
                id="personal-notes"
                v-model="localPersonalNotes"
                rows="3"
                placeholder="Tus notas sobre este juego..."
                class="edit-modal__textarea"
              ></textarea>
            </div>
          </section>

          <!-- Sección: detalles específicos del álbum -->
          <section v-if="itemType === 'album'" class="edit-modal__section edit-modal__section--card">
            <h3 class="edit-modal__section-title">Detalles del álbum</h3>

            <div class="edit-modal__field">
              <label for="favorite-track">Canción favorita</label>
              <select
                v-if="albumTracks && albumTracks.length > 0"
                id="favorite-track"
                v-model="localFavoriteTrack"
                class="edit-modal__input"
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
                class="edit-modal__input"
              />
            </div>

            <div class="edit-modal__field">
              <label for="album-date-started">Primera escucha</label>
              <input
                id="album-date-started"
                v-model="localDateStarted"
                type="date"
                class="edit-modal__input"
              />
            </div>

            <div class="edit-modal__field">
              <label for="album-personal-notes">Notas personales</label>
              <textarea
                id="album-personal-notes"
                v-model="localPersonalNotes"
                rows="3"
                placeholder="Tus notas sobre este álbum..."
                class="edit-modal__textarea"
              ></textarea>
            </div>
          </section>

          <!-- Sección: widgets/notas asociadas -->
          <section
            v-if="hasNotesSection"
            class="edit-modal__section"
          >
            <ReadingStatusWidget
              v-if="itemType === 'book' && item?.isbn && !isNewItem"
              :book="item"
            />

            <EditionNotes
              v-if="itemType === 'book' && userEditionId"
              :user-edition-id="userEditionId"
            />

            <MovieNotes
              v-if="itemType === 'movie' && item?.isbn"
              :movie-isbn="item.isbn"
            />

            <GameNotes
              v-if="itemType === 'game' && item?.id"
              :game-id="item.id"
            />

            <AlbumNotes
              v-if="itemType === 'album' && item?.id"
              :album-id="item.id"
            />
          </section>
        </div>

        <footer class="edit-modal__footer">
          <button
            type="button"
            class="btn btn--secondary"
            :disabled="isSaving"
            @click="$emit('close')"
          >
            Cancelar
          </button>
          <button
            type="button"
            class="btn btn--primary"
            :disabled="isSaving"
            @click="handleSave"
          >
            {{ isSaving ? 'Guardando...' : 'Guardar' }}
          </button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, inject, defineProps, defineEmits } from 'vue'
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
const localRating = ref(null)
const localStatuses = ref([])
const localTags = ref([])
const localCurrentPage = ref(0)
const localTotalPages = ref(0)

// Game-specific local state
const localHoursPlayed = ref(null)
const localPlatformPlayed = ref('')
const localDateStarted = ref('')
const localDateFinished = ref('')
const localPersonalNotes = ref('')

// Album-specific local state
const localFavoriteTrack = ref('')

// Ownership format state
const ownershipFormats = ref([])
const localOwnershipFormatId = ref(null)

const isSaving = ref(false)
const progressBarRef = ref(null)

// Reset all local state when the item prop changes (handles modal reuse)
const resetLocalState = (item) => {
  localRating.value = item?.user_rating ?? null
  localStatuses.value = item?.userStatuses ? [...item.userStatuses] : []
  localTags.value = item?.tags ? [...item.tags] : []
  localCurrentPage.value = item?.currentPage ?? 0
  localTotalPages.value = item?.pages || item?.totalPages || 0
  localHoursPlayed.value = item?.hoursPlayed ?? item?.hours_played ?? null
  localPlatformPlayed.value = item?.platformPlayed ?? item?.platform_played ?? ''
  localDateStarted.value = item?.dateStarted ?? item?.date_started ?? ''
  localDateFinished.value = item?.dateFinished ?? item?.date_finished ?? ''
  localPersonalNotes.value = item?.personalNotes ?? item?.personal_notes ?? ''
  localFavoriteTrack.value = item?.favoriteTrack ?? item?.favorite_track ?? ''
  localOwnershipFormatId.value =
    item?.ownershipFormat?.id ?? item?.ownership_format?.id ?? item?.ownership_format_id ?? null
}

// Keep local state in sync whenever the item prop changes
watch(() => props.item, resetLocalState, { immediate: true })

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
        tags: [...localTags.value],
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
  if (e.target.classList.contains('edit-modal')) {
    emit('close')
  }
}

// Whether the bottom "notes / widgets" section should render
const hasNotesSection = computed(() => {
  if (props.itemType === 'book') {
    return Boolean(props.item?.isbn) || Boolean(userEditionId.value)
  }
  if (props.itemType === 'movie') return Boolean(props.item?.isbn)
  if (props.itemType === 'game' || props.itemType === 'album') return Boolean(props.item?.id)
  return false
})
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/modal' as *;
@use '@/assets/styles/components/forms' as *;

// Overrides PrimeVue (z-index, overlays) → centralizados en
// assets/styles/components/_primevue-overrides.scss

.edit-modal {
  @include modal-overlay-base(modal);
  @include modal-overlay-blur;
  padding: spacing(md);

  &__dialog {
    @include modal-content-base(720px);
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0;
    animation: modalSlideIn transition(medium) ease-out;
    border-top: 3px solid var(--color-primary);
  }

  // Acento por entidad en el borde superior del modal
  &--book &__dialog   { border-top-color: var(--color-card-book-accent); }
  &--movie &__dialog  { border-top-color: var(--color-card-movie-accent); }
  &--game &__dialog   { border-top-color: var(--color-card-game-accent); }
  &--album &__dialog  { border-top-color: var(--color-card-album-accent); }

  &__header {
    position: sticky;
    top: 0;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: spacing(md);
    padding: spacing(lg) spacing(xl);
    background: var(--color-background-soft);
    border-bottom: 1px solid var(--color-border);
    border-top-left-radius: radius(xl);
    border-top-right-radius: radius(xl);
  }

  &__title {
    flex: 1 1 auto;
    margin: 0;
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    color: var(--color-text);
    @include truncate(2);
  }

  &__close {
    @include modal-close-button;
    position: static;
    flex: 0 0 auto;
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: radius(full);

    &:hover {
      background: var(--color-background-mute);
    }
  }

  &__body {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: spacing(lg) spacing(xl);
    display: flex;
    flex-direction: column;
    gap: spacing(lg);
  }

  &__section {
    display: flex;
    flex-direction: column;
    gap: spacing(md);

    & + & {
      padding-top: spacing(lg);
      border-top: 1px solid var(--color-border);
    }

    &--card {
      padding: spacing(md) spacing(lg);
      background: var(--color-background-mute);
      border: 1px solid var(--color-border);
      border-radius: radius(md);
    }

    // Cuando una sección es card, el separador visual ya lo da la propia card
    &--card + &,
    & + &--card {
      padding-top: 0;
      border-top: none;
    }
  }

  &__section-title {
    margin: 0;
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
    color: var(--color-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  &__field {
    @include form-group;
  }

  &__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: spacing(md);

    @include responsive-below(sm) {
      grid-template-columns: 1fr;
    }
  }

  &__input,
  &__textarea {
    @include form-control;
    width: 100%;
    font-family: inherit;
  }

  &__textarea {
    resize: vertical;
    min-height: 72px;
  }

  &__footer {
    position: sticky;
    bottom: 0;
    z-index: 2;
    display: flex;
    justify-content: flex-end;
    gap: spacing(sm);
    padding: spacing(md) spacing(xl);
    background: var(--color-background-soft);
    border-top: 1px solid var(--color-border);
    border-bottom-left-radius: radius(xl);
    border-bottom-right-radius: radius(xl);

    .btn {
      min-width: 110px;

      &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
      }
    }
  }

  // Mobile: ocupa toda la pantalla y elimina bordes redondeados
  @include responsive-below(md) {
    padding: 0;

    &__dialog {
      width: 100%;
      max-width: 100vw;
      max-height: 100vh;
      min-height: 100vh;
      border-radius: 0;
      border-top: none;
    }

    &__header,
    &__footer {
      border-radius: 0;
    }

    &__header,
    &__body,
    &__footer {
      padding-left: spacing(md);
      padding-right: spacing(md);
    }
  }
}
</style>
