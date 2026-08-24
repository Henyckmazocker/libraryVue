<template>
  <LibraryMediaItem
    ref="inner"
    media="movie"
    :item="movie"
    :allowed-statuses="allowedUserStatuses"
    :is-new="!editable"
    :can-delete="editable"
    @save="(payload) => emit('save-movie', payload)"
    @edit="(item, type) => emit('edit-item', item, type)"
    @delete="(payload) => emit('delete-movie', payload)"
  />
</template>

<script setup>
import { ref } from 'vue'
import LibraryMediaItem from '@/components/shared/LibraryMediaItem.vue'

/**
 * Wrapper de compatibilidad: la ficha vive en LibraryMediaItem, configurada
 * desde mediaRegistry. Se conservan el nombre de la prop, los nombres de los
 * eventos y los métodos expuestos para no tocar a MovieDetailView ni a
 * SeriesDetailView.
 */
defineProps({
  movie: {
    type: Object,
    required: true,
    default: () => ({ imdbID: '', title: '', author: '', coverUrl: '', rating: null })
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

const emit = defineEmits(['delete-movie', 'save-movie', 'edit-item'])
const inner = ref(null)

defineExpose({
  setSaveSuccess: () => inner.value?.setSaveSuccess(),
  setSaveError: () => inner.value?.setSaveError(),
  setEditSuccess: () => inner.value?.setEditSuccess(),
  setEditError: () => inner.value?.setEditError()
})
</script>
