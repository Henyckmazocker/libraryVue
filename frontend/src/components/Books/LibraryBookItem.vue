<template>
  <LibraryMediaItem
    ref="inner"
    media="book"
    :item="book"
    :allowed-statuses="allowedUserStatuses"
    :is-new="!editable"
    :can-delete="editable"
    @save="(payload) => emit('save-book', payload)"
    @edit="(item, type) => emit('edit-item', item, type)"
    @delete="(payload) => emit('delete-book', payload)"
    @show-history="() => emit('show-session-history', { book: book })"
  >
    <!-- Lo irreductible del libro: progreso y estado de lectura. -->
    <template #after-rating>
      <ReadingProgressBar
        :current-page="book.currentPage || 0"
        :total-pages="book.pages || 0"
        :editable="false"
        theme="blue"
      />
    </template>

    <template #after-status>
      <ReadingStatusWidget
        v-if="editable"
        :book="book"
      />
    </template>
  </LibraryMediaItem>
</template>

<script setup>
import { ref } from 'vue'
import LibraryMediaItem from '@/components/shared/LibraryMediaItem.vue'
import ReadingProgressBar from '@/components/common/ReadingProgressBar.vue'
import ReadingStatusWidget from '@/components/Books/ReadingStatusWidget.vue'

/**
 * Wrapper de compatibilidad: la ficha vive en LibraryMediaItem, configurada
 * desde mediaRegistry. Los libros son el único medio con contenido propio
 * dentro de la ficha —barra de progreso y widget de estado de lectura—, y por
 * eso son los únicos que usan los slots del genérico. BookDetailView no cambia.
 */
defineProps({
  book: {
    type: Object,
    required: true,
    default: () => ({ isbn: '', title: '', author: '', coverUrl: '', rating: null })
  },
  allowedUserStatuses: {
    type: Array,
    required: true
  },
  editable: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['delete-book', 'edit-item', 'save-book', 'show-session-history'])
const inner = ref(null)

defineExpose({
  setSaveSuccess: () => inner.value?.setSaveSuccess(),
  setSaveError: () => inner.value?.setSaveError(),
  setEditSuccess: () => inner.value?.setEditSuccess(),
  setEditError: () => inner.value?.setEditError()
})
</script>
