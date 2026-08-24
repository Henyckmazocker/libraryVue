<template>
  <MediaNotes
    media="movie"
    :item-id="imdbId"
  />
</template>

<script setup>
// Wrapper de configuración sobre MediaNotes, igual que los cinco *Search.vue lo
// son de GenericSearch. Conserva el nombre del fichero y de la prop para que
// ningún consumidor cambie; lo propio de las películas vive en mediaRegistry.
import MediaNotes from '@/components/shared/MediaNotes.vue'

defineProps({
  imdbId: {
    type: String,
    required: true
  }
})
</script>
