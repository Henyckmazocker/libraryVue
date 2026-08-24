<template>
  <LibraryMediaItem
    ref="inner"
    media="game"
    :item="game"
    :allowed-statuses="allowedUserStatuses"
    :is-new="!editable"
    :can-delete="editable"
    @save="(payload) => emit('save-game', payload)"
    @edit="(item, type) => emit('edit-item', item, type)"
    @delete="(payload) => emit('delete-game', payload)"
  />
</template>

<script setup>
import { ref } from 'vue'
import LibraryMediaItem from '@/components/shared/LibraryMediaItem.vue'

/**
 * Wrapper de compatibilidad: la ficha vive en LibraryMediaItem, configurada
 * desde mediaRegistry. GameDetailView no cambia.
 */
defineProps({
  game: {
    type: Object,
    required: true,
    default: () => ({ id: '', title: '', coverUrl: '', hoursPlayed: 0 })
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

const emit = defineEmits(['delete-game', 'save-game', 'edit-item'])
const inner = ref(null)

defineExpose({
  setSaveSuccess: () => inner.value?.setSaveSuccess(),
  setSaveError: () => inner.value?.setSaveError(),
  setEditSuccess: () => inner.value?.setEditSuccess(),
  setEditError: () => inner.value?.setEditError()
})
</script>
