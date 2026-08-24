<template>
  <LibraryMediaItem
    ref="inner"
    media="album"
    :item="album"
    :allowed-statuses="allowedStatuses"
    :is-new="isNewAlbum"
    :can-delete="canDelete"
    @save="(payload) => emit('save', payload)"
    @edit="(item) => emit('edit', item)"
    @delete="(id) => emit('delete', id)"
  />
</template>

<script setup>
import { ref } from 'vue'
import LibraryMediaItem from '@/components/shared/LibraryMediaItem.vue'

/**
 * Wrapper de compatibilidad: la ficha vive en LibraryMediaItem, configurada
 * desde mediaRegistry. AlbumDetailView no cambia de props ni de eventos, pero
 * **sí gana las llamadas de confirmación**: el guardado dejó de darse por bueno
 * en el acto y ahora lo confirma el padre, como en los otros cuatro medios.
 */
defineProps({
  album: {
    type: Object,
    required: true,
    default: () => ({ id: '', title: '', cover_url: '', personalNotes: '' })
  },
  allowedStatuses: {
    type: Array,
    default: () => []
  },
  isNewAlbum: {
    type: Boolean,
    default: false
  },
  canDelete: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['save', 'edit', 'delete'])
const inner = ref(null)

defineExpose({
  setSaveSuccess: () => inner.value?.setSaveSuccess(),
  setSaveError: () => inner.value?.setSaveError(),
  setEditSuccess: () => inner.value?.setEditSuccess(),
  setEditError: () => inner.value?.setEditError()
})
</script>
