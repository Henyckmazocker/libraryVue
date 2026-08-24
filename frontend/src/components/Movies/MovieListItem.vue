<template>
  <MediaListItem
    media="movie"
    :item="movie"
    :allowed-statuses="allowedStatuses"
    @click="$emit('click', $event)"
  />
</template>

<script setup>
// Wrapper de configuración sobre MediaListItem. Conserva el nombre del fichero,
// de la prop y del evento para que ningún consumidor cambie.
import MediaListItem from '@/components/shared/MediaListItem.vue'

defineProps({
  movie: {
    type: Object,
    required: true
  },
  allowedStatuses: {
    type: Array,
    default: () => []
  }
})

defineEmits(['click'])
</script>
