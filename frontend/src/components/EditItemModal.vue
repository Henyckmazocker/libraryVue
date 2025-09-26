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
            :total-pages="item?.pages || 0"
            :editable="true"
            theme="blue"
          />
          
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
import { useBooks } from '@/composables/useBooks'
import { useMovies } from '@/composables/useMovies'
import { useItemEdit } from '@/composables/useItemEdit'
import Logger from '@/utils/logger'

const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  itemType: {
    type: String,
    required: true,
    validator: (value) => ['book', 'movie'].includes(value)
  },
  allowedStatuses: {
    type: Array,
    default: () => []
  },
  isVisible: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['close', 'saved'])

// Composables
const itemEdit = useItemEdit()
const notifications = inject('notifications', null)

// Get appropriate composables based on item type
const booksComposable = useBooks()
const moviesComposable = useMovies()

// Local state
const localRating = ref(props.item?.user_rating ?? null)
const localStatuses = ref(props.item?.userStatuses ? [...props.item.userStatuses] : [])
const localTags = ref(props.item?.tags ? [...props.item.tags] : [])
const localCurrentPage = ref(props.item?.currentPage ?? 0)
const isSaving = ref(false)
const progressBarRef = ref(null)

// Get user tags based on item type
const userTags = computed(() => {
  return props.itemType === 'book' 
    ? booksComposable.userTags.value 
    : moviesComposable.userTags?.value || []
})

// Load data on mount
onMounted(async () => {
  try {
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
    const itemId = props.itemType === 'book' ? props.item.isbn : props.item.isbn
    
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
    
    // Add currentPage for books
    if (props.itemType === 'book') {
      data.currentPage = currentPageToSave
      localCurrentPage.value = currentPageToSave
    }
    
    Logger.debug('Saving item with data:', { itemType: props.itemType, itemId, data })
    
    const result = await itemEdit.editItem(props.itemType, itemId, data, [...localTags.value], [])
    
    Logger.debug('Save result:', result)
    
    if (result.success) {
      // Show success message
      if (notifications) {
        notifications.showSuccess(`${props.itemType === 'book' ? 'Libro' : 'Película'} actualizado correctamente`)
      }
      
      // Emit updated item
      const updatedItem = {
        ...props.item,
        user_rating: localRating.value,
        userStatuses: [...localStatuses.value],
        itemType: props.itemType
      }
      
      // Add currentPage for books
      if (props.itemType === 'book') {
        updatedItem.currentPage = localCurrentPage.value
        updatedItem.isbn = props.item.isbn // Asegurar que el ISBN esté presente
      } else {
        // Para películas, asegurar que el ISBN esté presente
        updatedItem.isbn = props.item.isbn
      }
      
      Logger.debug('Emitting saved event with updatedItem:', updatedItem)
      emit('saved', updatedItem)
      emit('close')
    } else {
      // Show error message
      if (notifications) {
        notifications.showError(result.message || `Error al guardar ${props.itemType === 'book' ? 'el libro' : 'la película'}`)
      }
    }
  } catch (error) {
    Logger.error('Error saving item:', error)
    if (notifications) {
      notifications.showError(`Error al guardar ${props.itemType === 'book' ? 'el libro' : 'la película'}`)
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
</style>
